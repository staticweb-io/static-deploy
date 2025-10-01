<?php

// exit uninstall if not called by WP
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit();
}

global $wpdb;

$tables_to_drop = [
    'crawled_files',
    'deploy_cache', // An older version of deployed_files
    'deployed_files',
    'detected_files',
    'jobs',
    'log',
    'options',
];

foreach ( $tables_to_drop as $table_to_drop ) {
    $table_name = StaticDeploy\Db::getTableName( $table_to_drop );

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name ) );
}
