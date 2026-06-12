<?php
/**
 * POD Theme Mod Migrator
 *
 * Handles one-shot migration of Site Variables into WordPress Customizer Theme Mods
 * for the GeneratePress child theme generator feature.
 *
 * Implements the POST /wp-json/sac/v1/theme/mods handler with the
 * `only_if_missing` flag (Req 11.2, 11.3) and tracks completion per stylesheet
 * via the `pod_customizer_migration_completed` option (Req 11.4).
 *
 * Idempotency contract:
 *   - When `only_if_missing = true`:
 *       * If a Theme Mod with the target key already has a non-empty value,
 *         the existing value is preserved (Req 11.3).
 *       * After a successful first migration for a stylesheet, subsequent
 *         calls return success immediately without writing any Theme Mods
 *         (a second call is a no-op).
 *   - When `only_if_missing = false`:
 *       * Every key in the request body is written, overwriting any existing
 *         Theme Mod value.
 *
 * The endpoints class (`class-pod-theme-endpoints.php`, task 11.3) is the
 * sole caller of this class. This class does NOT register REST routes itself;
 * route registration and HMAC verification happen in the endpoints class.
 *
 * @package POD_AI_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class POD_Theme_Mod_Migrator {

    /**
     * Singleton instance.
     *
     * @var POD_Theme_Mod_Migrator|null
     */
    private static $instance = null;

    /**
     * WordPress option name that stores the per-stylesheet migration
     * completion map. Shape: array<stylesheet_slug, int $completed_at_unix>.
     */
    const OPTION_KEY = 'pod_customizer_migration_completed';

    /**
     * Get singleton instance.
     *
     * @return POD_Theme_Mod_Migrator
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor — use get_instance().
     */
    private function __construct() {
        // Intentionally empty. Route registration is done by the endpoints class.
    }

    /**
     * Handle POST /theme/mods.
     *
     * Expected JSON body:
     *   {
     *     "theme_slug": "pod-shop-example-com-child",
     *     "mods": { "pod_brand_name": "Acme", "pod_phone": "+1 555-0100", ... },
     *     "only_if_missing": true
     *   }
     *
     * Response on success:
     *   {
     *     "success": true,
     *     "migrated": <int>,                // keys written to Theme Mods
     *     "skipped":  <int>,                // keys preserved or rejected
     *     "already_completed": <bool>       // true when the per-stylesheet
     *                                       //   completion flag short-circuited
     *                                       //   the call (only_if_missing=true)
     *   }
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_post( $request ) {
        $params = $request->get_json_params();
        if ( ! is_array( $params ) ) {
            $params = array();
        }

        $theme_slug      = isset( $params['theme_slug'] ) ? sanitize_key( $params['theme_slug'] ) : '';
        $mods            = ( isset( $params['mods'] ) && is_array( $params['mods'] ) ) ? $params['mods'] : array();
        $only_if_missing = ! empty( $params['only_if_missing'] );

        if ( '' === $theme_slug ) {
            return new WP_Error(
                'invalid_theme_slug',
                'theme_slug is required',
                array( 'status' => 400 )
            );
        }

        // Idempotent short-circuit: if migration is already recorded for this
        // stylesheet AND only_if_missing is true, this call is a no-op.
        // (Req 11.4 — migration runs at most once per theme.)
        if ( $only_if_missing && $this->is_migration_completed( $theme_slug ) ) {
            return rest_ensure_response( array(
                'success'           => true,
                'migrated'          => 0,
                'skipped'           => count( $mods ),
                'already_completed' => true,
            ) );
        }

        $existing_mods = $this->read_theme_mods( $theme_slug );

        $migrated = 0;
        $skipped  = 0;

        foreach ( $mods as $raw_key => $raw_value ) {
            $key = sanitize_key( (string) $raw_key );
            if ( '' === $key ) {
                $skipped++;
                continue;
            }

            if ( $only_if_missing && $this->has_non_empty_mod( $existing_mods, $key ) ) {
                // Preserve any pre-existing non-empty Theme Mod value (Req 11.3).
                $skipped++;
                continue;
            }

            $existing_mods[ $key ] = $this->sanitize_value( $raw_value );
            $migrated++;
        }

        $this->write_theme_mods( $theme_slug, $existing_mods );

        // Record per-stylesheet completion (Req 11.4). We mark completion even
        // when `migrated === 0` so that an empty Site Variables set still
        // counts as a successful first-push migration.
        $this->mark_migration_completed( $theme_slug );

        return rest_ensure_response( array(
            'success'           => true,
            'migrated'          => $migrated,
            'skipped'           => $skipped,
            'already_completed' => false,
        ) );
    }

    /**
     * Handle GET /theme/mods.
     *
     * Provided as a helper for the endpoints class (task 11.3) to wire the
     * GET route. Not part of the migration logic itself.
     *
     * Query/body params:
     *   { "theme_slug": "pod-shop-example-com-child" }
     *
     * Response:
     *   { "mods": { "pod_brand_name": "Acme", ... } }
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_get( $request ) {
        $theme_slug = $request->get_param( 'theme_slug' );
        $theme_slug = is_string( $theme_slug ) ? sanitize_key( $theme_slug ) : '';

        if ( '' === $theme_slug ) {
            return new WP_Error(
                'invalid_theme_slug',
                'theme_slug is required',
                array( 'status' => 400 )
            );
        }

        return rest_ensure_response( array(
            'mods' => $this->read_theme_mods( $theme_slug ),
        ) );
    }

    /**
     * Whether the one-shot migration has already been recorded for the given
     * stylesheet. Public so the orchestrator endpoint can use it for status.
     *
     * @param string $theme_slug
     * @return bool
     */
    public function is_migration_completed( $theme_slug ) {
        $completed = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $completed ) ) {
            return false;
        }
        return ! empty( $completed[ $theme_slug ] );
    }

    /**
     * Record completion of the migration for the given stylesheet.
     *
     * @param string $theme_slug
     * @return void
     */
    private function mark_migration_completed( $theme_slug ) {
        $completed = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $completed ) ) {
            $completed = array();
        }
        $completed[ $theme_slug ] = time();
        // Use autoload=false: this option is only read on push/migrate, not
        // on every page load.
        update_option( self::OPTION_KEY, $completed, false );
    }

    /**
     * Read the Theme Mods array for an arbitrary stylesheet.
     *
     * WordPress stores Theme Mods under the option `theme_mods_{stylesheet}`.
     * We read that option directly so we can target the freshly-pushed child
     * theme even when it is not yet the active theme (Req 11.2 — migration
     * runs as part of first-push, which may complete before activation).
     *
     * @param string $theme_slug
     * @return array
     */
    private function read_theme_mods( $theme_slug ) {
        $mods = get_option( 'theme_mods_' . $theme_slug, array() );
        return is_array( $mods ) ? $mods : array();
    }

    /**
     * Write the Theme Mods array for an arbitrary stylesheet.
     *
     * @param string $theme_slug
     * @param array  $mods
     * @return void
     */
    private function write_theme_mods( $theme_slug, $mods ) {
        update_option( 'theme_mods_' . $theme_slug, $mods );
    }

    /**
     * Whether the given key has a non-empty value in the existing Theme Mods.
     *
     * "Non-empty" matches Req 11.3 wording: an existing Theme Mod with a
     * non-empty value is preserved. We treat null, the empty string, false,
     * and an empty array as empty; every other value (including the integer
     * 0 or the string "0") is treated as a real value the user set on
     * purpose, and is therefore preserved.
     *
     * @param array  $existing_mods
     * @param string $key
     * @return bool
     */
    private function has_non_empty_mod( $existing_mods, $key ) {
        if ( ! array_key_exists( $key, $existing_mods ) ) {
            return false;
        }
        $value = $existing_mods[ $key ];
        if ( null === $value || false === $value ) {
            return false;
        }
        if ( '' === $value ) {
            return false;
        }
        if ( is_array( $value ) && empty( $value ) ) {
            return false;
        }
        return true;
    }

    /**
     * Apply minimal, type-aware sanitization to a Theme Mod value.
     *
     * Customizer per-setting `sanitize_callback`s run on input from the
     * Customizer UI — they do not run when values are written directly via
     * `set_theme_mod()` / option update. Rendering code (schemaEmitter,
     * htmlToPhpConverter) escapes Theme Mod values at output time using
     * `esc_html` / `esc_url` / `esc_attr` / `wp_json_encode`, so we do not
     * need to lock down content here. We do guarantee UTF-8 safety to
     * prevent corruption of the serialized option.
     *
     * @param mixed $value
     * @return mixed
     */
    private function sanitize_value( $value ) {
        if ( is_string( $value ) ) {
            return wp_check_invalid_utf8( $value );
        }
        if ( is_array( $value ) ) {
            $out = array();
            foreach ( $value as $k => $v ) {
                $out[ $k ] = $this->sanitize_value( $v );
            }
            return $out;
        }
        // Scalars (int, float, bool) and null pass through unchanged so
        // numeric and boolean Site Variables round-trip cleanly.
        return $value;
    }
}
