<?php
/**
 * POD Theme Endpoints
 *
 * Registers the four REST routes that drive the GeneratePress child-theme
 * generator from the desktop app, and wires them to the filesystem state
 * machine (POD_Theme_Filesystem) and the Customizer migrator
 * (POD_Theme_Mod_Migrator).
 *
 * Routes (all under `/wp-json/sac/v1/`, all HMAC-verified via
 * `sac_verify`):
 *
 *   POST /theme/upload     stage -> lint -> [dry_run short-circuit]
 *                          -> backup_live -> atomic_replace -> delete_previous
 *   POST /theme/activate   switch_theme($theme_slug)
 *   GET  /theme/status     parent / active / child presence / files / WC
 *   GET  /theme/mods       read theme_mods_{slug} option (delegated)
 *   POST /theme/mods       Customizer migration (delegated)
 *
 * Wire-format error_stage values (consumed by the desktop UI):
 *   - 'request'  malformed request body (missing theme_slug, no files, ...)
 *   - 'path'     one or more files[].path values failed validation (Req 8.6)
 *   - 'stage'    file write into the staging directory failed (Req 9.2)
 *   - 'lint'     `php -l` failed on at least one staged .php file (Req 8.7)
 *   - 'backup'   could not move the live theme aside before atomic replace
 *   - 'replace'  atomic_replace() failed but restore_from_backup() succeeded
 *                — the live theme is bit-for-bit identical to its pre-push
 *                state (Req 9.4)
 *   - 'restore'  atomic_replace() AND restore_from_backup() both failed —
 *                fatal, the live theme directory may be missing (Req 9.5)
 *
 * This class registers routes only; all filesystem mutations are performed
 * inside POD_Theme_Filesystem so the state machine has a single owner.
 *
 * Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 8.6, 8.7,
 *               9.1, 9.2, 9.3, 9.4, 9.5, 9.6,
 *               10.3, 11.2, 11.3, 11.4
 *
 * @package POD_AI_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class POD_Theme_Endpoints {

    /**
     * REST namespace + base for all theme endpoints.
     */
    const REST_NAMESPACE = 'sac/v1';

    /**
     * @var POD_Theme_Endpoints|null
     */
    private static $instance = null;

    /**
     * Singleton accessor.
     *
     * @return POD_Theme_Endpoints
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /* ------------------------------------------------------------------ *
     *  Route registration
     * ------------------------------------------------------------------ */

    /**
     * Register the four theme routes. All four use the plugin's existing
     * HMAC verification middleware (`sac_verify`) so the new
     * endpoints inherit identical auth (Req 8.5).
     */
    public function register_routes() {
        register_rest_route( self::REST_NAMESPACE, '/theme/upload', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_upload' ),
            'permission_callback' => 'sac_verify',
        ) );

        register_rest_route( self::REST_NAMESPACE, '/theme/activate', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_activate' ),
            'permission_callback' => 'sac_verify',
        ) );

        register_rest_route( self::REST_NAMESPACE, '/theme/status', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'handle_status' ),
            'permission_callback' => 'sac_verify',
        ) );

        // GET and POST /theme/mods are delegated to the migrator class.
        register_rest_route( self::REST_NAMESPACE, '/theme/mods', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'handle_mods_get' ),
            'permission_callback' => 'sac_verify',
        ) );

        register_rest_route( self::REST_NAMESPACE, '/theme/mods', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_mods_post' ),
            'permission_callback' => 'sac_verify',
        ) );

        // Create the WordPress Pages that surface the generated templates,
        // assign custom page templates, and optionally set the static
        // front page. Idempotent: existing pages (matched by slug) are
        // reused, never duplicated.
        register_rest_route( self::REST_NAMESPACE, '/theme/setup-pages', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_setup_pages' ),
            'permission_callback' => 'sac_verify',
        ) );

        // Read the raw bytes of one or more files inside a child theme so
        // the desktop app can run a prompt-driven edit and re-upload the
        // full theme (upload is an atomic whole-directory replace).
        register_rest_route( self::REST_NAMESPACE, '/theme/read-files', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_read_files' ),
            'permission_callback' => 'sac_verify',
        ) );
    }

    /* ------------------------------------------------------------------ *
     *  POST /theme/upload (Push State Machine)
     * ------------------------------------------------------------------ */

    /**
     * Handle the upload endpoint.
     *
     * Drives the push state machine:
     *   1. Stage every file into {slug}.staging/ via POD_Theme_Filesystem
     *      (path validation runs here — Req 8.6, 9.1, 9.2).
     *   2. Run `php -l` against every staged .php file (Req 8.7).
     *   3. If `dry_run = true`, delete the staging directory and return
     *      success — the live theme is never touched (Req 14.1, 14.2).
     *   4. Move the live theme aside as {slug}.previous (Req 9.6).
     *   5. Atomically rename {slug}.staging → {slug} (Req 9.3).
     *      On failure, attempt restore_from_backup() (Req 9.4, 9.5).
     *   6. Delete the previous-backup directory (Req 9.6).
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_upload( $request ) {
        $params = $request->get_json_params();
        if ( ! is_array( $params ) ) {
            $params = array();
        }

        $theme_slug = isset( $params['theme_slug'] ) ? sanitize_key( $params['theme_slug'] ) : '';
        $dry_run    = ! empty( $params['dry_run'] );
        $files      = ( isset( $params['files'] ) && is_array( $params['files'] ) ) ? $params['files'] : array();

        if ( '' === $theme_slug ) {
            return $this->error_response( 400, 'request', 'theme_slug is required' );
        }

        if ( empty( $files ) ) {
            return $this->error_response( 400, 'request', 'files array is required' );
        }

        $fs = POD_Theme_Filesystem::get_instance();

        // ---- 1. Stage files (Req 9.1, 9.2) ----------------------------
        $stage_result = $fs->stage_files( $theme_slug, $files );
        if ( ! $stage_result['success'] ) {
            // Path errors are caller-correctable input — return 422 so the
            // desktop client can surface the rejected paths to the user
            // (Req 8.6 wire format).
            if ( ! empty( $stage_result['path_errors'] ) ) {
                return new WP_REST_Response( array(
                    'success'      => false,
                    'error_stage'  => 'path',
                    'path_errors'  => $stage_result['path_errors'],
                    'write_errors' => $stage_result['write_errors'],
                ), 422 );
            }

            // Pure write errors are environmental (disk full, permissions);
            // surface as 500. The filesystem class already cleaned staging.
            return new WP_REST_Response( array(
                'success'      => false,
                'error_stage'  => 'stage',
                'write_errors' => $stage_result['write_errors'],
            ), 500 );
        }

        // ---- 2. Lint staged PHP files (Req 8.7, 14.1) -----------------
        $lint_report = $fs->lint_php_files( $theme_slug );
        if ( ! empty( $lint_report['errors'] ) ) {
            // A failed lint MUST NOT touch the live theme (Req 9.2). The
            // staging directory is the only place files have been written.
            $fs->delete_staging( $theme_slug );
            return new WP_REST_Response( array(
                'success'     => false,
                'error_stage' => 'lint',
                'lint_errors' => $lint_report['errors'],
            ), 422 );
        }

        // ---- 3. Dry-run short-circuit (Req 14.1, 14.2) ----------------
        if ( $dry_run ) {
            $fs->delete_staging( $theme_slug );
            return new WP_REST_Response( array(
                'success'         => true,
                'dry_run'         => true,
                'files_validated' => $stage_result['files_written'],
            ), 200 );
        }

        // ---- 4. Backup live (Req 9.6) ---------------------------------
        if ( ! $fs->backup_live( $theme_slug ) ) {
            $fs->delete_staging( $theme_slug );
            return $this->error_response(
                500,
                'backup',
                'failed to move the live theme directory aside; live theme unchanged'
            );
        }

        // ---- 5. Atomic replace (Req 9.3) ------------------------------
        if ( ! $fs->atomic_replace( $theme_slug ) ) {
            // Atomic move failed. Try to put the previous live directory
            // back so the site is bit-for-bit identical to pre-push state
            // (Req 9.4). If the restore itself fails, surface a fatal
            // error (Req 9.5) — at this point the live theme directory
            // may be missing and only out-of-band recovery (SSH/FTP) can
            // resolve it.
            $restored = $fs->restore_from_backup( $theme_slug );
            $fs->delete_staging( $theme_slug );

            if ( ! $restored ) {
                return $this->error_response(
                    500,
                    'restore',
                    'atomic replace failed and restore from backup failed; manual recovery required'
                );
            }

            return $this->error_response(
                500,
                'replace',
                'atomic replace failed; previous live theme restored'
            );
        }

        // ---- 6. Delete previous-live backup (Req 9.6) -----------------
        // Best-effort: even if the cleanup fails the push has succeeded
        // and the live theme is in place. We surface the cleanup failure
        // as a non-fatal warning so the next push starts with a clean
        // tree (the next backup_live() call also wipes any stale
        // .previous before renaming).
        $cleaned = $fs->delete_previous( $theme_slug );

        $response = array(
            'success'        => true,
            'files_written'  => $stage_result['files_written'],
            'theme_slug'     => $theme_slug,
        );
        if ( ! $cleaned ) {
            $response['cleanup_warning'] = 'failed to delete previous-live backup directory';
        }
        return new WP_REST_Response( $response, 200 );
    }

    /* ------------------------------------------------------------------ *
     *  POST /theme/activate
     * ------------------------------------------------------------------ */

    /**
     * Handle the activate endpoint.
     *
     * Switches the active theme to the named slug via switch_theme().
     * Refuses to activate a theme that does not exist on disk (Req 8.2).
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_activate( $request ) {
        $params = $request->get_json_params();
        if ( ! is_array( $params ) ) {
            $params = array();
        }

        $theme_slug = isset( $params['theme_slug'] ) ? sanitize_key( $params['theme_slug'] ) : '';
        if ( '' === $theme_slug ) {
            return $this->error_response( 400, 'request', 'theme_slug is required' );
        }

        $theme = wp_get_theme( $theme_slug );
        if ( ! $theme->exists() ) {
            return $this->error_response( 404, 'activate', 'theme not found: ' . $theme_slug );
        }

        switch_theme( $theme_slug );

        return new WP_REST_Response( array(
            'success'           => true,
            'active_stylesheet' => get_stylesheet(),
            'theme_slug'        => $theme_slug,
        ), 200 );
    }

    /* ------------------------------------------------------------------ *
     *  GET /theme/status
     * ------------------------------------------------------------------ */

    /**
     * Handle the status endpoint.
     *
     * Returns the documented JSON shape (Req 8.3): parent_theme,
     * active_stylesheet, child_theme_present, child_theme_version,
     * files[], woocommerce_active.
     *
     * The `theme_slug` query parameter selects which child theme to
     * inspect for presence / version / files. When absent, the active
     * stylesheet is inspected (useful for the very first call before
     * the desktop client has decided on a slug).
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_status( $request ) {
        $theme_slug = $request->get_param( 'theme_slug' );
        $theme_slug = is_string( $theme_slug ) ? sanitize_key( $theme_slug ) : '';

        // Identify the parent theme of whatever is currently active. When
        // no parent is declared (the active theme is itself a parent),
        // wp_get_theme()->parent() returns false — in that case the
        // "parent_theme" reported is the active stylesheet itself, which
        // is what the orchestrator's `parentTheme === 'generatepress'`
        // check needs to evaluate (Req 2.1, 2.2).
        $current      = wp_get_theme();
        $parent       = $current->parent();
        $parent_theme = ( $parent && ! is_wp_error( $parent ) )
            ? (string) $parent->get_stylesheet()
            : (string) $current->get_stylesheet();

        $active_stylesheet = (string) get_stylesheet();

        // Inspect the requested child theme for presence + file listing.
        $inspect_slug         = ( '' !== $theme_slug ) ? $theme_slug : $active_stylesheet;
        $inspect_theme        = wp_get_theme( $inspect_slug );
        $child_theme_present  = $inspect_theme->exists();
        $child_theme_version  = null;
        $files                = array();

        if ( $child_theme_present ) {
            $version = $inspect_theme->get( 'Version' );
            $child_theme_version = is_string( $version ) && '' !== $version ? $version : null;
            $files = $this->list_theme_files( $inspect_theme->get_stylesheet_directory() );
        }

        return new WP_REST_Response( array(
            'parent_theme'         => $parent_theme,
            'active_stylesheet'    => $active_stylesheet,
            'child_theme_present'  => $child_theme_present,
            'child_theme_version'  => $child_theme_version,
            'files'                => $files,
            'woocommerce_active'   => class_exists( 'WooCommerce' ),
        ), 200 );
    }

    /* ------------------------------------------------------------------ *
     *  GET|POST /theme/mods (delegated to migrator)
     * ------------------------------------------------------------------ */

    /**
     * Delegate to POD_Theme_Mod_Migrator::handle_get().
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_mods_get( $request ) {
        return POD_Theme_Mod_Migrator::get_instance()->handle_get( $request );
    }

    /**
     * Delegate to POD_Theme_Mod_Migrator::handle_post().
     *
     * The migrator implements the `only_if_missing` flag (Req 11.3) and
     * tracks per-stylesheet completion (Req 11.4).
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_mods_post( $request ) {
        return POD_Theme_Mod_Migrator::get_instance()->handle_post( $request );
    }

    /* ------------------------------------------------------------------ *
     *  POST /theme/setup-pages
     * ------------------------------------------------------------------ */

    /**
     * Create (or reuse) the WordPress Pages that surface the generated
     * templates, assign the matching custom page template, and optionally
     * set the static front page.
     *
     * Request body:
     *   {
     *     "theme_slug": "pod-...-child",      // required (for sanity only)
     *     "set_front_page": true,             // optional, default false
     *     "pages": [
     *       { "page_type": "homepage", "title": "Home",    "slug": "home",
     *         "template": "template-homepage.php", "front_page": true },
     *       { "page_type": "about",    "title": "About Us", "slug": "about" },
     *       ...
     *     ]
     *   }
     *
     * Behaviour:
     *   - A page is matched by its slug (post_name). If a published or
     *     draft page with that slug already exists it is REUSED — never
     *     duplicated (idempotent). Its template assignment is updated.
     *   - `template` (when provided) is written to the `_wp_page_template`
     *     post meta so WordPress renders the generated custom template.
     *     Hook-based page types (about/contact/shop/category) omit it.
     *   - When `set_front_page` is true and exactly one page is flagged
     *     `front_page: true`, the site is switched to a static front page
     *     (`show_on_front = page`, `page_on_front = <id>`). The previous
     *     front-page configuration is returned so the caller can report
     *     what changed.
     *
     * Response:
     *   {
     *     "success": true,
     *     "pages": [ { page_type, slug, page_id, url, created, template_assigned } ],
     *     "front_page_set": true|false,
     *     "previous_show_on_front": "posts"|"page",
     *     "previous_page_on_front": 0
     *   }
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_setup_pages( $request ) {
        $params = $request->get_json_params();
        if ( ! is_array( $params ) ) {
            $params = array();
        }

        $theme_slug = isset( $params['theme_slug'] ) ? sanitize_key( $params['theme_slug'] ) : '';
        if ( '' === $theme_slug ) {
            return $this->error_response( 400, 'request', 'theme_slug is required' );
        }

        $page_specs = ( isset( $params['pages'] ) && is_array( $params['pages'] ) ) ? $params['pages'] : array();
        if ( empty( $page_specs ) ) {
            return $this->error_response( 400, 'request', 'pages array is required' );
        }

        $set_front_page = ! empty( $params['set_front_page'] );

        $results        = array();
        $front_page_id  = 0;

        foreach ( $page_specs as $spec ) {
            if ( ! is_array( $spec ) ) {
                continue;
            }

            $page_type = isset( $spec['page_type'] ) ? sanitize_key( $spec['page_type'] ) : '';
            $title     = isset( $spec['title'] ) ? sanitize_text_field( $spec['title'] ) : '';
            $slug      = isset( $spec['slug'] ) ? sanitize_title( $spec['slug'] ) : '';
            $template  = isset( $spec['template'] ) ? sanitize_text_field( $spec['template'] ) : '';
            $is_front  = ! empty( $spec['front_page'] );

            if ( '' === $slug ) {
                // Derive a slug from the title as a fallback; skip if both empty.
                $slug = ( '' !== $title ) ? sanitize_title( $title ) : '';
            }
            if ( '' === $slug ) {
                continue;
            }
            if ( '' === $title ) {
                $title = ucwords( str_replace( '-', ' ', $slug ) );
            }

            // Look up an existing page by slug (post_name). Reuse it if
            // present so repeated runs never create duplicates.
            $existing = get_page_by_path( $slug, OBJECT, 'page' );
            $created  = false;

            if ( $existing instanceof WP_Post ) {
                $page_id = (int) $existing->ID;
            } else {
                $page_id = wp_insert_post( array(
                    'post_title'   => $title,
                    'post_name'    => $slug,
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                    'post_content' => '',
                ), true );

                if ( is_wp_error( $page_id ) || 0 === (int) $page_id ) {
                    $results[] = array(
                        'page_type'         => $page_type,
                        'slug'              => $slug,
                        'page_id'           => 0,
                        'url'               => '',
                        'created'           => false,
                        'template_assigned' => false,
                        'error'             => is_wp_error( $page_id ) ? $page_id->get_error_message() : 'wp_insert_post failed',
                    );
                    continue;
                }
                $page_id = (int) $page_id;
                $created = true;
            }

            // Assign the custom page template when one was supplied (full
            // template page types: homepage / landing). Hook-based pages
            // pass an empty template and rely on is_page() conditionals.
            $template_assigned = false;
            if ( '' !== $template ) {
                update_post_meta( $page_id, '_wp_page_template', $template );
                $template_assigned = true;
            }

            if ( $is_front ) {
                $front_page_id = $page_id;
            }

            $results[] = array(
                'page_type'         => $page_type,
                'slug'              => $slug,
                'page_id'           => $page_id,
                'url'               => (string) get_permalink( $page_id ),
                'created'           => $created,
                'template_assigned' => $template_assigned,
            );
        }

        // Capture the previous front-page configuration so the caller can
        // report (and a future feature could revert) the change.
        $previous_show_on_front = (string) get_option( 'show_on_front', 'posts' );
        $previous_page_on_front = (int) get_option( 'page_on_front', 0 );

        $front_page_set = false;
        if ( $set_front_page && $front_page_id > 0 ) {
            update_option( 'show_on_front', 'page' );
            update_option( 'page_on_front', $front_page_id );
            $front_page_set = true;
        }

        return new WP_REST_Response( array(
            'success'                => true,
            'pages'                  => $results,
            'front_page_set'         => $front_page_set,
            'front_page_id'          => $front_page_id,
            'previous_show_on_front' => $previous_show_on_front,
            'previous_page_on_front' => $previous_page_on_front,
        ), 200 );
    }

    /* ------------------------------------------------------------------ *
     *  POST /theme/read-files
     * ------------------------------------------------------------------ */

    /**
     * Read the raw bytes of files inside a child theme directory and
     * return them base64-encoded.
     *
     * Because `/theme/upload` performs an ATOMIC WHOLE-DIRECTORY replace,
     * editing a single file requires re-uploading the full theme with
     * that one file changed. The desktop app uses this endpoint to pull
     * the current theme contents, apply a prompt-driven edit to the
     * target file(s), and push the complete set back.
     *
     * Request body:
     *   {
     *     "theme_slug": "pod-...-child",   // required
     *     "paths": ["style.css", ...]      // optional; omit/empty = ALL files
     *   }
     *
     * Response:
     *   {
     *     "success": true,
     *     "theme_slug": "...",
     *     "theme_version": "1.0.0"|null,
     *     "files": [ { "path": "style.css", "content_base64": "..." }, ... ]
     *   }
     *
     * Every returned path is relative to the theme directory and passes
     * the same path-validation rule the upload endpoint enforces, so the
     * desktop client can feed the set straight back into `/theme/upload`.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_read_files( $request ) {
        $params = $request->get_json_params();
        if ( ! is_array( $params ) ) {
            $params = array();
        }

        $theme_slug = isset( $params['theme_slug'] ) ? sanitize_key( $params['theme_slug'] ) : '';
        if ( '' === $theme_slug ) {
            return $this->error_response( 400, 'request', 'theme_slug is required' );
        }

        $theme = wp_get_theme( $theme_slug );
        if ( ! $theme->exists() ) {
            return $this->error_response( 404, 'read', 'theme not found: ' . $theme_slug );
        }

        $theme_dir = wp_normalize_path( rtrim( $theme->get_stylesheet_directory(), '/\\' ) );
        if ( '' === $theme_dir || ! is_dir( $theme_dir ) ) {
            return $this->error_response( 404, 'read', 'theme directory missing on disk' );
        }

        // Determine the requested path set. When omitted/empty, return
        // every file in the theme (the full set needed for a safe
        // re-upload).
        $requested = ( isset( $params['paths'] ) && is_array( $params['paths'] ) )
            ? array_values( array_filter( array_map( 'strval', $params['paths'] ) ) )
            : array();

        if ( empty( $requested ) ) {
            $requested = $this->list_theme_files( $theme_dir );
        }

        $fs        = POD_Theme_Filesystem::get_instance();
        $base      = trailingslashit( $theme_dir );
        $out_files = array();

        foreach ( $requested as $rel ) {
            // Validate the relative path with the same allowlist the
            // upload endpoint uses so a malformed path can never escape
            // the theme directory.
            $check = $fs->validate_relative_path( $rel );
            if ( ! $check['valid'] ) {
                continue;
            }

            $abs = wp_normalize_path( $base . $check['normalized'] );

            // Confirm the resolved real path is still inside the theme
            // directory (defends against symlink escapes).
            $real = realpath( $abs );
            if ( false === $real ) {
                continue;
            }
            $real = wp_normalize_path( $real );
            if ( 0 !== strpos( $real, $base ) ) {
                continue;
            }
            if ( ! is_file( $real ) || is_link( $abs ) ) {
                continue;
            }

            $bytes = @file_get_contents( $real );
            if ( false === $bytes ) {
                continue;
            }

            $out_files[] = array(
                'path'           => $check['normalized'],
                'content_base64' => base64_encode( $bytes ),
            );
        }

        $version = $theme->get( 'Version' );

        return new WP_REST_Response( array(
            'success'       => true,
            'theme_slug'    => $theme_slug,
            'theme_version' => ( is_string( $version ) && '' !== $version ) ? $version : null,
            'files'         => $out_files,
        ), 200 );
    }

    /* ------------------------------------------------------------------ *
     *  Internal helpers
     * ------------------------------------------------------------------ */

    /**
     * Build a uniform error response carrying `success: false`,
     * `error_stage`, and `message`.
     *
     * @param int    $http_status
     * @param string $stage  See class doc-block for allowed values.
     * @param string $message
     * @return WP_REST_Response
     */
    private function error_response( $http_status, $stage, $message ) {
        return new WP_REST_Response( array(
            'success'     => false,
            'error_stage' => (string) $stage,
            'message'     => (string) $message,
        ), (int) $http_status );
    }

    /**
     * Recursively list every regular file inside a theme directory and
     * return their paths relative to the theme directory, sorted.
     *
     * Symbolic links are skipped to keep the listing within the theme
     * tree even on hosts where wp-content is itself behind a symlink.
     *
     * @param string $theme_dir Absolute path to the theme directory.
     * @return array<int, string>
     */
    private function list_theme_files( $theme_dir ) {
        $found = array();
        if ( empty( $theme_dir ) || ! is_dir( $theme_dir ) ) {
            return $found;
        }

        $base = wp_normalize_path( rtrim( $theme_dir, '/\\' ) );

        try {
            $iter = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $theme_dir,
                    FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO
                ),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
        } catch ( Exception $e ) {
            return $found;
        }

        foreach ( $iter as $file ) {
            /** @var SplFileInfo $file */
            if ( $file->isLink() ) {
                continue;
            }
            if ( ! $file->isFile() ) {
                continue;
            }
            $abs    = wp_normalize_path( $file->getPathname() );
            $prefix = $base . '/';
            if ( 0 === strpos( $abs, $prefix ) ) {
                $found[] = substr( $abs, strlen( $prefix ) );
            }
        }

        sort( $found, SORT_STRING );
        return $found;
    }
}

// Initialize singleton on plugin load. The constructor registers the
// REST routes against `rest_api_init`, so this line is the only wiring
// the bootstrap file needs.
POD_Theme_Endpoints::get_instance();
