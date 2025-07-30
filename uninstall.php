<?php

// exit uninstall if not called by WP
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit();
}

global $wpdb;

$tables_to_drop = [
    'crawled_files',
    'deploy_cache',
    'detected_files',
    'jobs',
    'log',
    'options',
];

foreach ( $tables_to_drop as $table ) {
    $table_name = StaticDeploy\Db::getTableName( $table );

    $wpdb->query( "DROP TABLE IF EXISTS $table_name" );
}
