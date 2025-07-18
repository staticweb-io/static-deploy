<?php
/**
 * Plugin Name: Static Deploy
 * Plugin URI:  https://github.com/staticweb-io/static-deploy
 * Description: Static site generator functionality for WordPress.
 * Version:     9.2.1
 * Author:      StaticWeb.io
 * Author URI:  https://github.com/staticweb-io/static-deploy
 * Text Domain: static-deploy
 */

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

define( 'STATIC_DEPLOY_VERSION', '9.2.1' );
define( 'STATIC_DEPLOY_PATH', plugin_dir_path( __FILE__ ) );

if ( file_exists( STATIC_DEPLOY_PATH . 'vendor/autoload.php' ) ) {
    require_once STATIC_DEPLOY_PATH . 'vendor/autoload.php';
}

if ( ! class_exists( 'StaticDeploy\Controller' ) ) {
    if ( file_exists( STATIC_DEPLOY_PATH . 'src/StaticDeployException.php' ) ) {
        require_once STATIC_DEPLOY_PATH . 'src/StaticDeployException.php';

        throw new StaticDeploy\StaticDeployException(
            'Looks like you\'re trying to activate Static Deploy from source code' .
            ', without compiling it first.'
        );
    }
}

StaticDeploy\Controller::init( __FILE__ );

/**
 * Define Settings link for plugin
 *
 * @param string[] $links array of links
 * @return string[] modified array of links
 */
function static_deploy_plugin_action_links( $links ) {
    $settings_link =
        '<a href="admin.php?page=static-deploy">' .
        __( 'Settings', 'static-html-output-plugin' ) .
        '</a>';
    array_unshift( $links, $settings_link );

    return $links;
}

add_filter(
    'plugin_action_links_' .
    plugin_basename( __FILE__ ),
    'static_deploy_plugin_action_links'
);

/**
 * Prevent WP scripts from loading which aren't useful
 * on a statically exported site
 */
function static_deploy_deregister_scripts(): void {
    wp_dequeue_script( 'wp-embed' );
    wp_deregister_script( 'wp-embed' );
    wp_dequeue_script( 'comment-reply' );
    wp_deregister_script( 'comment-reply' );
}

add_action( 'wp_footer', 'static_deploy_deregister_scripts' );

// TODO: move into own plugin for WP cleanup, don't belong in core
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );

if ( defined( 'WP_CLI' ) ) {
    WP_CLI::add_command( 'static-deploy', StaticDeploy\CLI::class );
}
