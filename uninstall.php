<?php

// exit uninstall if not called by WP
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit();
}

global $wpdb;

// Note that the plugin is deactivated at this point, so we
// can't call code in any of our other files.

$static_deploy_table_slugs = [
    'crawled_files',
    'deploy_cache', // An older version of deployed_files
    'deployed_files',
    'detected_files',
    'jobs',
    'log',
    'options',
];

foreach ( $static_deploy_table_slugs as $static_deploy_table_slug ) {
    $static_deploy_table_name = $wpdb->prefix . 'static_deploy_' . $static_deploy_table_slug;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $static_deploy_table_name ) );
}
