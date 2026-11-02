<?php
/**
 * Plugin Name: Store AI Connector
 * Plugin URI:  https://github.com/YOUR_GITHUB/store-ai-connector
 * Description: REST API connector for multi-store order management, GMC setup, and lightweight Virtual Option Forms.
 * Version:     2.5.3
 * Author:      POD AI Hub
 * License:     GPL v2 or later
 * Text Domain: store-ai-connector
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Automatic OPcache & LiteSpeed Cache invalidation on plugin upgrade (CyberPanel / OpenLiteSpeed safe).
 */
if ( get_option( 'sac_installed_version', '' ) !== '2.5.3' ) {
    update_option( 'sac_installed_version', '2.5.3', true );
    if ( function_exists( 'opcache_reset' ) ) {
        @opcache_reset();
    }
    if ( function_exists( 'wp_opcache_invalidate' ) ) {
        @wp_opcache_invalidate( __FILE__, true );
        @wp_opcache_invalidate( plugin_dir_path( __FILE__ ) . 'includes/sac-core.php', true );
        @wp_opcache_invalidate( plugin_dir_path( __FILE__ ) . 'includes/class-pod-option-forms.php', true );
        @wp_opcache_invalidate( plugin_dir_path( __FILE__ ) . 'includes/class-pod-option-forms-admin.php', true );
    }
    if ( function_exists( 'do_action' ) ) {
        do_action( 'litespeed_purge_all' );
    }
}

/**
 * Duplicate-folder crash guard:
 * Prevents PHP Fatal Error ("Cannot redeclare sac_*" / "Cannot declare class POD_*") when an older
 * folder such as `store-ai-connector-main`, `store-ai-connector-master`, or `pod-ai-connector`
 * is still active in WordPress alongside `store-ai-connector`. Automatically deactivates the
 * older duplicate folder so the site stays online without interruption.
 */
if (
    defined( 'SAC_VERSION' ) ||
    defined( 'POD_CONNECTOR_VERSION' ) ||
    function_exists( 'sac_register_routes' ) ||
    class_exists( 'POD_Custom_Fields', false )
) {
    $sac_self_basename = plugin_basename( __FILE__ );
    $sac_active        = get_option( 'active_plugins', array() );
    if ( is_array( $sac_active ) ) {
        $sac_cleaned = array();
        $sac_changed = false;
        foreach ( $sac_active as $sac_p ) {
            if ( $sac_p === $sac_self_basename ) {
                $sac_cleaned[] = $sac_p;
                continue;
            }
            if (
                strpos( $sac_p, 'store-ai-connector' ) !== false ||
                strpos( $sac_p, 'pod-ai-connector' ) !== false
            ) {
                $sac_changed = true;
                continue;
            }
            $sac_cleaned[] = $sac_p;
        }
        if ( ! in_array( $sac_self_basename, $sac_cleaned, true ) ) {
            $sac_cleaned[] = $sac_self_basename;
            $sac_changed   = true;
        }
        if ( $sac_changed ) {
            update_option( 'active_plugins', array_values( $sac_cleaned ) );
        }
    }

    if ( ! defined( 'SAC_VERSION' ) ) {
        define( 'SAC_VERSION', '2.5.3' );
    }
    if ( ! class_exists( 'POD_Option_Forms', false ) ) {
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-pod-option-forms.php';
    }
    if ( ! class_exists( 'POD_Option_Forms_Admin', false ) ) {
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-pod-option-forms-admin.php';
    }
    return;
}

if ( ! defined( 'SAC_PLUGIN_FILE' ) ) {
    define( 'SAC_PLUGIN_FILE', __FILE__ );
}

add_action(
    'plugins_loaded',
    function () {
        $self_basename = plugin_basename( SAC_PLUGIN_FILE );
        $active        = get_option( 'active_plugins', array() );
        if ( ! is_array( $active ) ) {
            return;
        }
        $cleaned = array();
        $changed = false;
        foreach ( $active as $p ) {
            if ( $p === $self_basename ) {
                $cleaned[] = $p;
                continue;
            }
            if (
                strpos( $p, 'store-ai-connector' ) !== false ||
                strpos( $p, 'pod-ai-connector' ) !== false
            ) {
                $changed = true;
                continue;
            }
            $cleaned[] = $p;
        }
        if ( $changed ) {
            update_option( 'active_plugins', array_values( $cleaned ) );
        }
    },
    1
);

require_once plugin_dir_path( __FILE__ ) . 'includes/sac-core.php';
