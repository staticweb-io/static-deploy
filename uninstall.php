<?php

// exit uninstall if not called by WP
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit();
}

global $wpdb;

WP2Static\Controller::init( __FILE__ );

$tables_to_drop = [
    'core_options',
    'crawl_cache',
    'deploy_cache',
    'jobs',
    'log',
    'urls',
];

foreach ( $tables_to_drop as $table ) {
    $table_name = WP2Static\Controller::getTableName( $table );

    $wpdb->query( "DROP TABLE IF EXISTS $table_name" );
}
