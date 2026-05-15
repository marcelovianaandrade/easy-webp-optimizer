<?php
/**
 * Easy WebP Optimizer — Uninstall Handler
 *
 * Runs when the plugin is deleted via the WordPress admin.
 * Cleans up all options, post meta, and .htaccess rules.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// 1. Remove plugin options.
delete_option( 'easy_webp_delivery_enabled' );

// 2. Remove all post meta created by the plugin.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_easy_webp_generated', '_easy_webp_original_size', '_easy_webp_size')" );

// 3. Remove .htaccess rules.
$htaccess = ABSPATH . '.htaccess';
if ( file_exists( $htaccess ) && is_writable( $htaccess ) ) {
    $content = file_get_contents( $htaccess );
    $new     = preg_replace( '/# BEGIN Easy WebP.*?# END Easy WebP\s*/s', '', $content );
    if ( $new !== $content ) {
        file_put_contents( $htaccess, $new );
    }
}

// Note: .webp files generated in /uploads/ are intentionally NOT deleted
// to preserve user data. Users can remove them manually if desired.
