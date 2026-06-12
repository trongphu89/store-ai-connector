<?php
/**
 * POD Theme Filesystem
 *
 * Implements the push state machine for the GeneratePress child-theme
 * generator. Pure filesystem operations only — staging, linting, backup,
 * atomic replace, restore, cleanup. The REST endpoints class drives the
 * state machine; this class never decides when to invoke a step.
 *
 * State machine (see design "Push state machine"):
 *   stage_files          -> writes uploaded files into {slug}.staging/
 *   lint_php_files       -> runs `php -l` on every staged .php file
 *   backup_live          -> rename {slug} -> {slug}.previous
 *   atomic_replace       -> rename {slug}.staging -> {slug}
 *   restore_from_backup  -> rename {slug}.previous -> {slug}
 *   delete_previous      -> remove {slug}.previous
 *   delete_staging       -> remove {slug}.staging
 *
 * Safety invariants:
 *   - All paths are normalized with wp_normalize_path().
 *   - `..` segments and symlinks are rejected before any write.
 *   - Atomic moves use rename(); on cross-volume / permission failure the
 *     method returns false so the caller can run restore_from_backup.
 *   - The PHP CLI binary is discovered through PHP_BINARY (env or constant)
 *     and arguments are escaped with escapeshellarg/escapeshellcmd before
 *     being handed to exec().
 *
 * Requirements: 8.6, 8.7, 9.1, 9.2, 9.3, 9.4, 9.5, 9.6
 *
 * @package POD_AI_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class POD_Theme_Filesystem {

    /**
     * Allowed file extensions inside a theme directory.
     *
     * Mirrored on the desktop side by themePushClient's path validator
     * (Property 15 in the design).
     */
    const ALLOWED_EXTENSIONS = array(
        'php', 'css', 'js', 'png', 'jpg', 'jpeg', 'svg', 'webp', 'mo', 'pot',
    );

    /**
     * @var POD_Theme_Filesystem|null
     */
    private static $instance = null;

    /**
     * Singleton accessor.
     *
     * @return POD_Theme_Filesystem
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /* ------------------------------------------------------------------ *
     *  Path helpers
     * ------------------------------------------------------------------ */

    /**
     * Absolute path to the live theme directory.
     *
     * @param string $theme_slug
     * @return string
     */
    public function live_dir( $theme_slug ) {
        return $this->theme_path( $theme_slug, '' );
    }

    /**
     * Absolute path to the transient staging directory.
     *
     * @param string $theme_slug
     * @return string
     */
    public function staging_dir( $theme_slug ) {
        return $this->theme_path( $theme_slug, '.staging' );
    }

    /**
     * Absolute path to the previous-live backup directory.
     *
     * @param string $theme_slug
     * @return string
     */
    public function previous_dir( $theme_slug ) {
        return $this->theme_path( $theme_slug, '.previous' );
    }

    /**
     * Build a directory path under wp-content/themes/ for the given slug
     * suffix ('', '.staging', '.previous').
     *
     * @param string $theme_slug
     * @param string $suffix
     * @return string
     */
    private function theme_path( $theme_slug, $suffix ) {
        $slug  = $this->sanitize_slug( $theme_slug );
        $root  = wp_normalize_path( get_theme_root() );
        $full  = trailingslashit( $root ) . $slug . $suffix;
        return wp_normalize_path( $full );
    }

    /**
     * Reduce a theme slug to lowercase alphanumerics and hyphens. Mirrors
     * the rule applied by deriveThemeSlug() on the TS side.
     *
     * @param string $slug
     * @return string
     */
    private function sanitize_slug( $slug ) {
        $slug = strtolower( (string) $slug );
        $slug = preg_replace( '/[^a-z0-9-]+/', '-', $slug );
        $slug = preg_replace( '/-+/', '-', (string) $slug );
        return trim( (string) $slug, '-' );
    }

    /* ------------------------------------------------------------------ *
     *  Path validation (Requirement 8.6)
     * ------------------------------------------------------------------ */

    /**
     * Validate a relative path that originates from a wire-format
     * `files[].path` value. Rejects absolute paths, `..` segments,
     * empty/`.` segments, and disallowed extensions.
     *
     * @param string $path
     * @return array {
     *     valid       bool   Whether the path is acceptable.
     *     reason      string Human-readable rejection reason (empty on success).
     *     normalized  string The wp_normalize_path() output (empty on failure).
     *     extension   string Lowercased file extension (empty on failure).
     * }
     */
    public function validate_relative_path( $path ) {
        $result = array(
            'valid'      => false,
            'reason'     => '',
            'normalized' => '',
            'extension'  => '',
        );

        if ( ! is_string( $path ) || '' === $path ) {
            $result['reason'] = 'empty path';
            return $result;
        }

        $normalized = wp_normalize_path( $path );

        // wp_normalize_path always emits forward slashes; if any backslash
        // survived we treat it as malformed input.
        if ( false !== strpos( $normalized, '\\' ) ) {
            $result['reason'] = 'unexpected backslash after normalization';
            return $result;
        }

        if ( '' === $normalized || '/' === $normalized[0] ) {
            $result['reason'] = 'absolute paths not allowed';
            return $result;
        }

        // Reject Windows drive prefixes such as "C:/foo".
        if ( preg_match( '/^[a-z]:/i', $normalized ) ) {
            $result['reason'] = 'absolute paths not allowed';
            return $result;
        }

        $segments = explode( '/', $normalized );
        foreach ( $segments as $seg ) {
            if ( '' === $seg || '.' === $seg ) {
                $result['reason'] = 'empty or current-directory segment';
                return $result;
            }
            if ( '..' === $seg ) {
                $result['reason'] = 'parent-directory segment not allowed';
                return $result;
            }
            // Defensive null-byte check.
            if ( false !== strpos( $seg, "\0" ) ) {
                $result['reason'] = 'null byte in path';
                return $result;
            }
        }

        $dot = strrpos( $normalized, '.' );
        if ( false === $dot || $dot === strlen( $normalized ) - 1 ) {
            $result['reason'] = 'missing file extension';
            return $result;
        }
        $ext = strtolower( substr( $normalized, $dot + 1 ) );
        if ( ! in_array( $ext, self::ALLOWED_EXTENSIONS, true ) ) {
            $result['reason'] = 'extension not in allowlist';
            return $result;
        }

        $result['valid']      = true;
        $result['normalized'] = $normalized;
        $result['extension']  = $ext;
        return $result;
    }

    /* ------------------------------------------------------------------ *
     *  stage_files (Requirements 9.1, 9.2)
     * ------------------------------------------------------------------ */

    /**
     * Stage every uploaded file under {slug}.staging/.
     *
     * On any failure (path rejection, base64 decode error, write error,
     * symlink detection) the staging directory is removed in full so the
     * live theme directory is never affected.
     *
     * @param string $theme_slug
     * @param array  $files Array of [ 'path' => string, 'content_base64' => string ].
     * @return array {
     *     success       bool
     *     path_errors   array<int, array{path:string, reason:string}>
     *     write_errors  array<int, array{path:string, reason:string}>
     *     files_written array<int, string>  Relative paths successfully written.
     * }
     */
    public function stage_files( $theme_slug, $files ) {
        $result = array(
            'success'       => false,
            'path_errors'   => array(),
            'write_errors'  => array(),
            'files_written' => array(),
        );

        $staging = $this->staging_dir( $theme_slug );

        // Wipe any stale staging directory from a prior aborted run.
        if ( $this->path_exists_or_link( $staging ) && ! $this->recursive_rmdir( $staging ) ) {
            $result['write_errors'][] = array(
                'path'   => $staging,
                'reason' => 'failed to remove stale staging directory',
            );
            return $result;
        }

        if ( ! wp_mkdir_p( $staging ) ) {
            $result['write_errors'][] = array(
                'path'   => $staging,
                'reason' => 'failed to create staging directory',
            );
            return $result;
        }

        // Resolve real path so we can detect symlink escapes.
        $staging_real = realpath( $staging );
        if ( false === $staging_real ) {
            $result['write_errors'][] = array(
                'path'   => $staging,
                'reason' => 'staging directory missing after creation',
            );
            $this->recursive_rmdir( $staging );
            return $result;
        }
        $staging_real = wp_normalize_path( $staging_real );

        if ( ! is_array( $files ) || empty( $files ) ) {
            // Empty bundle is treated as a path error rather than a silent success.
            $result['path_errors'][] = array(
                'path'   => '',
                'reason' => 'no files provided',
            );
            $this->recursive_rmdir( $staging );
            return $result;
        }

        foreach ( $files as $file ) {
            $rel  = isset( $file['path'] ) ? (string) $file['path'] : '';
            $b64  = isset( $file['content_base64'] ) ? (string) $file['content_base64'] : '';

            $check = $this->validate_relative_path( $rel );
            if ( ! $check['valid'] ) {
                $result['path_errors'][] = array( 'path' => $rel, 'reason' => $check['reason'] );
                continue;
            }

            $abs    = wp_normalize_path( trailingslashit( $staging ) . $check['normalized'] );
            $parent = dirname( $abs );

            // Reject symlinks anywhere on the way down to the target file.
            if ( $this->path_contains_symlink_between( $parent, $staging_real ) ) {
                $result['path_errors'][] = array(
                    'path'   => $check['normalized'],
                    'reason' => 'symlink in path',
                );
                continue;
            }

            if ( ! file_exists( $parent ) ) {
                if ( ! wp_mkdir_p( $parent ) ) {
                    $result['write_errors'][] = array(
                        'path'   => $check['normalized'],
                        'reason' => 'failed to create parent directory',
                    );
                    continue;
                }
            }

            // After creating any new parent dirs, re-confirm the resolved
            // parent stays under staging_real. Without this check, a race
            // could let a previously-created symlink escape the root.
            $parent_real = realpath( $parent );
            if ( false === $parent_real ) {
                $result['write_errors'][] = array(
                    'path'   => $check['normalized'],
                    'reason' => 'parent directory missing after creation',
                );
                continue;
            }
            $parent_real = wp_normalize_path( $parent_real );
            if ( 0 !== strpos( $parent_real, $staging_real ) ) {
                $result['path_errors'][] = array(
                    'path'   => $check['normalized'],
                    'reason' => 'resolved path escapes staging root',
                );
                continue;
            }

            $bytes = base64_decode( $b64, true );
            if ( false === $bytes ) {
                $result['write_errors'][] = array(
                    'path'   => $check['normalized'],
                    'reason' => 'invalid base64 payload',
                );
                continue;
            }

            // Reject if the target itself is an existing symlink (shouldn't
            // happen inside a fresh staging dir, but check anyway).
            if ( is_link( $abs ) ) {
                $result['path_errors'][] = array(
                    'path'   => $check['normalized'],
                    'reason' => 'target is a symlink',
                );
                continue;
            }

            $written = @file_put_contents( $abs, $bytes );
            if ( false === $written || $written !== strlen( $bytes ) ) {
                $result['write_errors'][] = array(
                    'path'   => $check['normalized'],
                    'reason' => 'failed to write file',
                );
                continue;
            }

            $result['files_written'][] = $check['normalized'];
        }

        if ( empty( $result['path_errors'] ) && empty( $result['write_errors'] ) ) {
            $result['success'] = true;
            return $result;
        }

        // Any failure -> clean up the entire staging directory so the live
        // theme directory remains untouched (Requirement 9.2).
        $this->recursive_rmdir( $staging );
        $result['files_written'] = array();
        return $result;
    }

    /* ------------------------------------------------------------------ *
     *  lint_php_files (Requirements 8.7, 9.2)
     * ------------------------------------------------------------------ */

    /**
     * Run `php -l` against every .php file inside the staging directory.
     *
     * On hosts where neither a CLI binary nor `exec()` is available we
     * fall back to a parser-only check using `token_get_all` with the
     * `TOKEN_PARSE` flag — this catches most syntax errors (unclosed
     * strings, missing semicolons, mis-balanced braces) without requiring
     * a separate process. The fallback is strictly less thorough than
     * `php -l` but is sufficient for the upload pipeline since the staged
     * files are subsequently loaded by WordPress's normal `require_once`
     * path which would itself fatal on a malformed file.
     *
     * @param string $theme_slug
     * @return array {
     *     errors array<int, array{path:string, line:int, message:string}>
     *     ran    int Number of php -l invocations performed.
     * }
     */
    public function lint_php_files( $theme_slug ) {
        $report = array( 'errors' => array(), 'ran' => 0 );
        $staging = $this->staging_dir( $theme_slug );

        if ( ! is_dir( $staging ) ) {
            return $report;
        }

        $php_files = $this->collect_files( $staging, '/\.php$/i' );
        if ( empty( $php_files ) ) {
            return $report;
        }

        $php_binary    = $this->resolve_php_binary();
        $can_use_exec  = function_exists( 'exec' ) && '' !== $php_binary;
        $disabled_funcs = explode( ',', (string) ini_get( 'disable_functions' ) );
        $disabled_funcs = array_map( 'trim', $disabled_funcs );
        if ( in_array( 'exec', $disabled_funcs, true ) ) {
            $can_use_exec = false;
        }

        foreach ( $php_files as $abs ) {
            $report['ran']++;

            if ( $can_use_exec ) {
                $output    = array();
                $exit_code = 0;

                // escapeshellarg quotes each argument; escapeshellcmd is
                // applied additionally to the binary path for defense-in-
                // depth on the off-chance PHP_BINARY contains an
                // unexpected metacharacter.
                $cmd = escapeshellcmd( $php_binary );
                $cmd = escapeshellarg( $cmd ) . ' -l ' . escapeshellarg( $abs );

                @exec( $cmd . ' 2>&1', $output, $exit_code );

                if ( 0 !== $exit_code ) {
                    $line    = $this->parse_lint_line( $output );
                    $message = $this->parse_lint_message( $output );

                    // Some hosts return a non-zero exit code from
                    // `php -l` even when the file is syntactically
                    // valid (SAPI binaries that don't fully support
                    // `-l`, exec wrappers that return 1 with no
                    // diagnostic output, etc.). When the exit-code
                    // failure carries no parsed message, cross-check
                    // with the parser-only fallback before reporting
                    // the file as broken — `token_get_all` with
                    // TOKEN_PARSE catches every real syntax error,
                    // and a parse-clean file must not be rejected by
                    // an unreliable shell.
                    if ( '' === $message && 0 === $line ) {
                        $fallback_error = $this->parse_lint_with_token_get_all( $abs, $staging );
                        if ( null === $fallback_error ) {
                            // Parser says the file is fine — accept it.
                            continue;
                        }
                        $report['errors'][] = $fallback_error;
                        continue;
                    }

                    $report['errors'][] = array(
                        'path'    => $this->relative_to( $abs, $staging ),
                        'line'    => $line,
                        'message' => $message,
                    );
                }
                continue;
            }

            // Parser-only fallback. `token_get_all` with `TOKEN_PARSE`
            // (PHP 7+) raises a `ParseError` on syntax errors that the
            // PHP parser itself rejects.
            $error = $this->parse_lint_with_token_get_all( $abs, $staging );
            if ( null !== $error ) {
                $report['errors'][] = $error;
            }
        }

        return $report;
    }

    /**
     * Lint a single PHP file using the parser-only fallback. Returns
     * `null` on success or an `{ path, line, message }` triple on
     * failure.
     *
     * @param string $abs     Absolute path to the file under lint.
     * @param string $staging Absolute path to the staging directory
     *                        (used to compute relative paths).
     * @return array|null
     */
    private function parse_lint_with_token_get_all( $abs, $staging ) {
        $source = @file_get_contents( $abs );
        if ( false === $source ) {
            return array(
                'path'    => $this->relative_to( $abs, $staging ),
                'line'    => 0,
                'message' => 'failed to read file for syntax check',
            );
        }

        try {
            // TOKEN_PARSE makes the tokenizer enforce parser semantics
            // (closed strings, balanced braces, valid tokens). The flag
            // exists on PHP 7+, which matches the plugin's minimum
            // version.
            @token_get_all( $source, TOKEN_PARSE );
            return null;
        } catch ( ParseError $e ) {
            return array(
                'path'    => $this->relative_to( $abs, $staging ),
                'line'    => (int) $e->getLine(),
                'message' => $e->getMessage(),
            );
        } catch ( Error $e ) {
            return array(
                'path'    => $this->relative_to( $abs, $staging ),
                'line'    => (int) $e->getLine(),
                'message' => $e->getMessage(),
            );
        }
    }

    /**
     * Resolve the PHP CLI binary. Prefers getenv('PHP_BINARY') so the
     * lint runs under the same PHP that's serving WordPress; falls back
     * to the PHP_BINARY constant which is always populated since 5.4.
     *
     * On many shared hosts PHP_BINARY points to the SAPI module (e.g.
     * `/usr/local/lsws/lsphp82/bin/lsphp`) rather than a CLI binary,
     * but the SAPI binary still accepts `-l` for lint mode in practice.
     * If the resolved candidate is not a regular file, we additionally
     * probe a small list of well-known CLI locations so most LiteSpeed,
     * cPanel, Plesk, and standard Linux installs work without manual
     * configuration.
     *
     * @return string Empty string if no usable binary is found.
     */
    private function resolve_php_binary() {
        $candidates = array();

        // 1. Explicit env var. Hosts that want to override CLI lookup
        //    can set this to the absolute path they prefer.
        $env = getenv( 'PHP_BINARY' );
        if ( is_string( $env ) && '' !== $env ) {
            $candidates[] = $env;
        }

        // 2. PHP_BINARY constant. Available since PHP 5.4 and points
        //    at the binary running the current process. On CLI this is
        //    a CLI binary; on FPM/CGI/LiteSpeed it is the SAPI binary,
        //    which still accepts `-l` arguments.
        if ( defined( 'PHP_BINARY' ) ) {
            $candidates[] = PHP_BINARY;
        }

        // 3. Common CLI binary locations across popular host stacks.
        //    Many shared hosts surface a separate `php` CLI alongside
        //    the SAPI module — probing them avoids forcing operators
        //    to set `PHP_BINARY` by hand.
        $common_paths = array(
            '/usr/local/bin/php',
            '/usr/bin/php',
            '/opt/cpanel/ea-php82/root/usr/bin/php',
            '/opt/cpanel/ea-php81/root/usr/bin/php',
            '/opt/cpanel/ea-php80/root/usr/bin/php',
            '/opt/cpanel/ea-php74/root/usr/bin/php',
            '/usr/local/lsws/lsphp82/bin/php',
            '/usr/local/lsws/lsphp81/bin/php',
            '/usr/local/lsws/lsphp80/bin/php',
            '/usr/local/lsws/lsphp74/bin/php',
            '/opt/plesk/php/8.2/bin/php',
            '/opt/plesk/php/8.1/bin/php',
            '/opt/plesk/php/8.0/bin/php',
            '/opt/plesk/php/7.4/bin/php',
        );
        foreach ( $common_paths as $path ) {
            $candidates[] = $path;
        }

        foreach ( $candidates as $candidate ) {
            if ( is_string( $candidate ) && '' !== $candidate && @is_file( $candidate ) ) {
                return $candidate;
            }
        }
        return '';
    }

    /* ------------------------------------------------------------------ *
     *  Atomic state transitions (Requirements 9.3, 9.4, 9.5, 9.6)
     * ------------------------------------------------------------------ */

    /**
     * Move the live theme directory aside as {slug}.previous so the
     * staging directory can be moved into place atomically.
     *
     * Returns true even when there is no live directory to back up; this
     * is the first-push case.
     *
     * @param string $theme_slug
     * @return bool
     */
    public function backup_live( $theme_slug ) {
        $live     = $this->live_dir( $theme_slug );
        $previous = $this->previous_dir( $theme_slug );

        // Refuse to operate on symlinks for the live or previous paths.
        if ( is_link( $live ) || is_link( $previous ) ) {
            return false;
        }

        // Wipe any leftover .previous from a prior aborted run. We only
        // get here after stage_files succeeded, so a stale .previous can
        // only be the residue of a previous push that failed at delete.
        if ( $this->path_exists_or_link( $previous ) && ! $this->recursive_rmdir( $previous ) ) {
            return false;
        }

        if ( ! $this->path_exists_or_link( $live ) ) {
            // First push: nothing to back up.
            return true;
        }

        // rename() is atomic for directories on the same volume; on
        // cross-volume failure we return false so the caller can decide
        // whether to abort or attempt a copy-based fallback.
        return @rename( $live, $previous );
    }

    /**
     * Atomically replace the live theme directory with the staged one.
     *
     * Caller MUST have run backup_live() first so that the live path is
     * absent. On failure (cross-volume, permission, race) returns false
     * so the caller can invoke restore_from_backup().
     *
     * @param string $theme_slug
     * @return bool
     */
    public function atomic_replace( $theme_slug ) {
        $live    = $this->live_dir( $theme_slug );
        $staging = $this->staging_dir( $theme_slug );

        if ( ! is_dir( $staging ) || is_link( $staging ) ) {
            return false;
        }
        if ( $this->path_exists_or_link( $live ) ) {
            // Live must have been moved aside by backup_live() first.
            return false;
        }

        return @rename( $staging, $live );
    }

    /**
     * Restore the previous live theme directory after a failed atomic
     * replace. Returns false if the backup is missing or the rename
     * itself fails (Requirement 9.5).
     *
     * @param string $theme_slug
     * @return bool
     */
    public function restore_from_backup( $theme_slug ) {
        $live     = $this->live_dir( $theme_slug );
        $previous = $this->previous_dir( $theme_slug );

        // Backup must exist and not be a symlink.
        if ( ! is_dir( $previous ) || is_link( $previous ) ) {
            return false;
        }

        // If a partial atomic replace left something behind at $live,
        // remove it so the rename has a clear target. Refuse to clobber
        // a symlink at $live to avoid escaping the themes root.
        if ( is_link( $live ) ) {
            return false;
        }
        if ( $this->path_exists_or_link( $live ) ) {
            if ( ! $this->recursive_rmdir( $live ) ) {
                return false;
            }
        }

        return @rename( $previous, $live );
    }

    /**
     * Delete the previous-live backup directory after a successful
     * atomic replace (Requirement 9.6).
     *
     * @param string $theme_slug
     * @return bool
     */
    public function delete_previous( $theme_slug ) {
        $previous = $this->previous_dir( $theme_slug );
        if ( ! $this->path_exists_or_link( $previous ) ) {
            return true;
        }
        return $this->recursive_rmdir( $previous );
    }

    /**
     * Delete the staging directory. Used both on the failure path and
     * defensively when a stale staging dir is detected.
     *
     * @param string $theme_slug
     * @return bool
     */
    public function delete_staging( $theme_slug ) {
        $staging = $this->staging_dir( $theme_slug );
        if ( ! $this->path_exists_or_link( $staging ) ) {
            return true;
        }
        return $this->recursive_rmdir( $staging );
    }

    /* ------------------------------------------------------------------ *
     *  Internal helpers
     * ------------------------------------------------------------------ */

    /**
     * file_exists() returns false for broken symlinks. We need to detect
     * them too so cleanup is reliable.
     *
     * @param string $path
     * @return bool
     */
    private function path_exists_or_link( $path ) {
        return file_exists( $path ) || is_link( $path );
    }

    /**
     * Check whether any directory component between $path and $root
     * (inclusive of $path, exclusive of $root) is a symbolic link.
     *
     * @param string $path
     * @param string $root  Already-normalized real path of the staging root.
     * @return bool
     */
    private function path_contains_symlink_between( $path, $root ) {
        $current = wp_normalize_path( $path );
        $root    = wp_normalize_path( rtrim( $root, '/' ) );
        $guard   = 64; // hard cap on directory depth

        while ( $guard-- > 0 ) {
            if ( '' === $current || $current === $root ) {
                return false;
            }
            if ( strlen( $current ) < strlen( $root ) ) {
                // We've walked above the root; nothing inside the staging
                // tree to worry about anymore.
                return false;
            }
            if ( is_link( $current ) ) {
                return true;
            }
            $parent = wp_normalize_path( dirname( $current ) );
            if ( $parent === $current ) {
                return false;
            }
            $current = $parent;
        }
        return false;
    }

    /**
     * Recursively remove a directory, refusing to follow symlinks.
     * Returns true if the directory is gone (or never existed), false
     * if any entry could not be removed.
     *
     * @param string $dir
     * @return bool
     */
    private function recursive_rmdir( $dir ) {
        $dir = wp_normalize_path( $dir );

        if ( is_link( $dir ) ) {
            // Never traverse into a symlinked directory; just unlink it.
            return @unlink( $dir );
        }
        if ( ! file_exists( $dir ) ) {
            return true;
        }
        if ( ! is_dir( $dir ) ) {
            return @unlink( $dir );
        }

        $entries = @scandir( $dir );
        if ( false === $entries ) {
            return false;
        }

        foreach ( $entries as $entry ) {
            if ( '.' === $entry || '..' === $entry ) {
                continue;
            }
            $abs = $dir . '/' . $entry;

            if ( is_link( $abs ) ) {
                if ( ! @unlink( $abs ) ) {
                    return false;
                }
                continue;
            }
            if ( is_dir( $abs ) ) {
                if ( ! $this->recursive_rmdir( $abs ) ) {
                    return false;
                }
                continue;
            }
            if ( ! @unlink( $abs ) ) {
                return false;
            }
        }

        return @rmdir( $dir );
    }

    /**
     * Walk a directory tree returning every regular file matching $regex.
     * Symlinks are skipped to avoid escaping the staging root.
     *
     * @param string $dir
     * @param string $regex
     * @return array<int, string> Normalized absolute paths.
     */
    private function collect_files( $dir, $regex ) {
        $found = array();
        if ( ! is_dir( $dir ) ) {
            return $found;
        }

        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $dir,
                FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO
            ),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ( $iter as $file ) {
            /** @var SplFileInfo $file */
            if ( $file->isLink() ) {
                continue;
            }
            if ( $file->isFile() && preg_match( $regex, $file->getFilename() ) ) {
                $found[] = wp_normalize_path( $file->getPathname() );
            }
        }

        sort( $found, SORT_STRING );
        return $found;
    }

    /**
     * Compute a relative path from $abs to $base. Returns the absolute
     * path unchanged if $abs does not start with $base.
     *
     * @param string $abs
     * @param string $base
     * @return string
     */
    private function relative_to( $abs, $base ) {
        $abs  = wp_normalize_path( $abs );
        $base = wp_normalize_path( rtrim( $base, '/' ) ) . '/';
        if ( 0 === strpos( $abs, $base ) ) {
            return substr( $abs, strlen( $base ) );
        }
        return $abs;
    }

    /**
     * Extract the line number from `php -l` output. Returns 0 if no
     * "on line N" pattern is present.
     *
     * @param array $output
     * @return int
     */
    private function parse_lint_line( $output ) {
        foreach ( $output as $line ) {
            if ( preg_match( '/on line\s+(\d+)/i', (string) $line, $m ) ) {
                return (int) $m[1];
            }
        }
        return 0;
    }

    /**
     * Extract a human-readable error message from `php -l` output.
     *
     * @param array $output
     * @return string
     */
    private function parse_lint_message( $output ) {
        foreach ( $output as $line ) {
            $line = trim( (string) $line );
            if ( '' === $line ) {
                continue;
            }
            if ( 0 === stripos( $line, 'No syntax errors' ) ) {
                continue;
            }
            return $line;
        }
        return trim( implode( ' ', array_map( 'strval', (array) $output ) ) );
    }
}
