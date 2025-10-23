<?php
/*
    WordPressAdmin

    Interface to WordPress Admin functions

    Used for registering hooks, Admin UI components, ...
*/

namespace StaticDeploy;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

class WordPressAdmin {

    /**
     * Build update checker
     *
     * @param string $bootstrap_file main plugin filepath
     */
    public static function buildUpdateChecker( string $bootstrap_file ): void {
        if ( defined( 'STATIC_DEPLOY_WP_ORG_MODE' ) && STATIC_DEPLOY_WP_ORG_MODE ) {
            return;
        }

        PucFactory::buildUpdateChecker(
            // phpcs:disable Generic.Files.LineLength
            'https://raw.githubusercontent.com/staticweb-io/static-deploy/refs/heads/develop/update.json',
            $bootstrap_file,
            'static-deploy'
        );
    }

    /**
     * Register hooks for WordPress and plugin actions
     *
     * @param string $bootstrap_file main plugin filepath
     */
    public static function registerHooks(): void {
        add_filter(
            // phpcs:ignore WordPress.WP.CronInterval -- namespaces not yet fully supported
            'cron_schedules',
            WPCron::customCronSchedules( ... )
        );

        add_filter(
            'cron_request',
            WPCron::cronWithBasicAuth( ... )
        );

        add_action(
            'wp_ajax_' . Controller::getHookName( 'run' ),
            Controller::ajaxRun( ... ),
            10,
            0
        );

        add_action(
            'wp_ajax_' . Controller::getHookName( 'poll_log' ),
            Controller::ajaxPollLog( ... ),
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'ui_save_options' ),
            Controller::UISaveOptions( ... ),
            10,
            0
        );

        add_action(
            Controller::getHookName( 'register_addon' ),
            Addons::registerAddon( ... ),
            10,
            5
        );

        add_action(
            Controller::getHookName( 'post_deploy_trigger' ),
            Controller::emailDeployNotification( ... ),
            10,
            0
        );

        add_action(
            Controller::getHookName( 'post_deploy_trigger' ),
            Controller::webhookDeployNotification( ... ),
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'post_processed_site_delete' ),
            Controller::adminPostProcessedSiteDelete( ... ),
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'post_processed_site_show' ),
            Controller::adminPostProcessedSiteShow( ... ),
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'log_delete' ),
            Controller::adminLogDelete( ... ),
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'delete_all_caches' ),
            Controller::adminDeleteAllCaches( ... ),
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'delete_jobs_queue' ),
            Controller::adminDeleteJobsQueue( ... ),
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'process_jobs_queue' ),
            Controller::adminProcessJobsQueue( ... ),
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'process_queue' ),
            self::adminPostProcessQueue( ... ),
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'detected_files_delete' ),
            Controller::adminDetectedFilesDelete( ... ),
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'detected_files_show' ),
            Controller::adminDetectedFilesShow( ... ),
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'deploy_cache_delete' ),
            Controller::adminDeployCacheDelete( ... ),
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'deploy_cache_show' ),
            Controller::adminDeployCacheShow( ... ),
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'crawled_files_delete' ),
            Controller::adminCrawledFilesDelete( ... ),
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'crawled_files_show' ),
            Controller::adminCrawledFilesShow( ... ),
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'ui_save_job_options' ),
            Controller::adminUISaveJobsOptions( ... ),
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'ui_save_advanced_options' ),
            Controller::adminUISaveAdvancedOptions( ... ),
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'manually_enqueue_jobs' ),
            Controller::adminManuallyEnqueueJobs( ... ),
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'toggle_addon' ),
            Controller::adminToggleAddon( ... ),
            10,
            0
        );

        add_action(
            Controller::getHookName( 'process_queue' ),
            Controller::processQueue( ... ),
            10,
            0
        );

        add_action(
            Controller::getHookName( 'headless_hook' ),
            Controller::runHeadless( ... ),
            10,
            0
        );

        add_action(
            Controller::getHookName( 'crawl' ),
            [ Crawler::class, 'crawl' ],
            10,
            3
        );

        add_action(
            'save_post',
            Controller::savePostHandler( ... ),
            0
        );

        add_action(
            'trashed_post',
            Controller::trashedPostHandler( ... ),
            0
        );

        /*
         * Register actions for when we should invalidate cache for
         * a URL(s) or whole site
         *
         */
        $single_url_invalidation_events = [
            'save_post',
            'deleted_post',
        ];

        foreach ( $single_url_invalidation_events as $single_url_invalidation_event ) {
            add_action(
                $single_url_invalidation_event,
                Controller::invalidateSingleURLCache( ... ),
                10,
                2
            );
        }
    }

    /**
     * Add plugin elements to WordPress Admin UI
     */
    public static function addAdminUIElements(): void {
        if ( is_admin() ) {
            add_action(
                'admin_menu',
                Controller::registerOptionsPage( ... )
            );
            add_filter( 'custom_menu_order', '__return_true' );
            add_filter( 'menu_order', Controller::setMenuOrder( ... ) );
            AdminBar::registerHooks();
        }
    }

    /*
     * Do security checks before calling Controller::processQueue
     */
    public static function adminPostProcessQueue(): void {
        $method = filter_input( INPUT_SERVER, 'REQUEST_METHOD', FILTER_SANITIZE_URL );
        if ( ! $method ) {
            $msg = 'Empty method in request to admin-post.php (adminPostProcessQueue)';
        } elseif ( 'POST' !== $method ) {
            $method = strval( $method );
            $msg = "Invalid method in request to admin-post.php (adminPostProcessQueue): {$method}";
        }
        $nonce_valid = isset( $_POST['_wpnonce'] ) && wp_verify_nonce(
            sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ),
            Controller::getHookName( 'process_queue' )
        );
        if ( ! $nonce_valid ) {
            $msg = 'Invalid nonce in request to admin-post.php (adminPostProcessQueue)';
        }

        if ( isset( $msg ) ) {
            if ( defined( 'STATIC_DEPLOY_ESCAPE_EXCEPTIONS' )
            && STATIC_DEPLOY_ESCAPE_EXCEPTIONS ) {
                throw WsLog::ex( esc_html( $msg ) );
            }
            throw WsLog::ex( $msg );
        }

        Controller::processQueue();
    }
}
