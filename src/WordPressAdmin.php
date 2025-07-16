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
     * WordPressAdmin constructor
     */
    public function __construct() {
    }

    /**
     * Build update checker
     *
     * @param string $bootstrap_file main plugin filepath
     */
    public static function buildUpdateChecker( string $bootstrap_file ): void {
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
    public static function registerHooks( string $bootstrap_file ): void {
        register_activation_hook(
            $bootstrap_file,
            [ Controller::class, 'activate' ]
        );

        register_deactivation_hook(
            $bootstrap_file,
            [ Controller::class, 'deactivate' ]
        );

        add_filter(
            // phpcs:ignore WordPress.WP.CronInterval -- namespaces not yet fully supported
            'cron_schedules',
            [ WPCron::class, 'customCronSchedules' ]
        );

        add_filter(
            'cron_request',
            [ WPCron::class, 'cronWithBasicAuth' ]
        );

        add_action(
            'wp_ajax_' . Controller::getHookName( 'run' ),
            [ Controller::class, 'ajaxRun' ],
            10,
            0
        );

        add_action(
            'wp_ajax_' . Controller::getHookName( 'poll_log' ),
            [ Controller::class, 'ajaxPollLog' ],
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'ui_save_options' ),
            [ Controller::class, 'UISaveOptions' ],
            10,
            0
        );

        add_action(
            Controller::getHookName( 'register_addon' ),
            [ Addons::class, 'registerAddon' ],
            10,
            5
        );

        add_action(
            Controller::getHookName( 'post_deploy_trigger' ),
            [ Controller::class, 'emailDeployNotification' ],
            10,
            0
        );

        add_action(
            Controller::getHookName( 'post_deploy_trigger' ),
            [ Controller::class, 'webhookDeployNotification' ],
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'post_processed_site_delete' ),
            [ Controller::class, 'adminPostProcessedSiteDelete' ],
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'post_processed_site_show' ),
            [ Controller::class, 'adminPostProcessedSiteShow' ],
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'log_delete' ),
            [ Controller::class, 'adminLogDelete' ],
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'delete_all_caches' ),
            [ Controller::class, 'adminDeleteAllCaches' ],
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'delete_jobs_queue' ),
            [ Controller::class, 'adminDeleteJobsQueue' ],
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'process_jobs_queue' ),
            [ Controller::class, 'adminProcessJobsQueue' ],
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'process_queue' ),
            [ self::class, 'adminPostProcessQueue' ],
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'detected_files_delete' ),
            [ Controller::class, 'adminDetectedFilesDelete' ],
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'detected_files_show' ),
            [ Controller::class, 'adminDetectedFilesShow' ],
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'deploy_cache_delete' ),
            [ Controller::class, 'adminDeployCacheDelete' ],
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'deploy_cache_show' ),
            [ Controller::class, 'adminDeployCacheShow' ],
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'crawled_files_delete' ),
            [ Controller::class, 'adminCrawledFilesDelete' ],
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'crawled_files_show' ),
            [ Controller::class, 'adminCrawledFilesShow' ],
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'static_site_delete' ),
            [ Controller::class, 'adminStaticSiteDelete' ],
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'static_site_show' ),
            [ Controller::class, 'adminStaticSiteShow' ],
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'ui_save_job_options' ),
            [ Controller::class, 'adminUISaveJobsOptions' ],
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'ui_save_advanced_options' ),
            [ Controller::class, 'adminUISaveAdvancedOptions' ],
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'manually_enqueue_jobs' ),
            [ Controller::class, 'adminManuallyEnqueueJobs' ],
            10,
            0
        );

        add_action(
            'admin_post_' . Controller::getHookName( 'toggle_addon' ),
            [ Controller::class, 'adminToggleAddon' ],
            10,
            0
        );

        add_action(
            Controller::getHookName( 'process_queue' ),
            [ Controller::class, 'processQueue' ],
            10,
            0
        );

        add_action(
            Controller::getHookName( 'headless_hook' ),
            [ Controller::class, 'runHeadless' ],
            10,
            0
        );

        add_action(
            Controller::getHookName( 'crawl' ),
            [ Crawler::class, 'crawl' ],
            10,
            2
        );

        add_action(
            Controller::getHookName( 'process_html' ),
            [ SimpleRewriter::class, 'rewrite' ],
            10,
            1
        );

        add_action(
            Controller::getHookName( 'process_css' ),
            [ SimpleRewriter::class, 'rewrite' ],
            10,
            1
        );

        add_action(
            Controller::getHookName( 'process_js' ),
            [ SimpleRewriter::class, 'rewrite' ],
            10,
            1
        );

        add_action(
            Controller::getHookName( 'process_robots_txt' ),
            [ SimpleRewriter::class, 'rewrite' ],
            10,
            1
        );

        add_action(
            Controller::getHookName( 'process_xml' ),
            [ SimpleRewriter::class, 'rewrite' ],
            10,
            1
        );

        add_action(
            'save_post',
            [ Controller::class, 'savePostHandler' ],
            0
        );

        add_action(
            'trashed_post',
            [ Controller::class, 'trashedPostHandler' ],
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

        $full_site_invalidation_events = [
            'switch_theme',
        ];

        foreach ( $single_url_invalidation_events as $invalidation_events ) {
            add_action(
                $invalidation_events,
                [ Controller::class, 'invalidateSingleURLCache' ],
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
                [ Controller::class, 'registerOptionsPage' ]
            );
            add_filter( 'custom_menu_order', '__return_true' );
            add_filter( 'menu_order', [ Controller::class, 'setMenuOrder' ] );
        }
    }

    /*
     * Do security checks before calling Controller::processQueue
     */
    public static function adminPostProcessQueue(): void {
        $method = filter_input( INPUT_SERVER, 'REQUEST_METHOD' );
        if ( ! $method ) {
            $msg = 'Empty method in request to admin-post.php (adminPostProcessQueue)';
        } elseif ( 'POST' !== $method ) {
            $method = strval( $method );
            $msg = "Invalid method in request to admin-post.php (adminPostProcessQueue): $method";
        }
        $nonce = filter_input( INPUT_POST, '_wpnonce' );
        $nonce_valid = $nonce && wp_verify_nonce(
            strval( $nonce ),
            Controller::getHookName( 'process_queue' )
        );
        if ( ! $nonce_valid ) {
            $msg = 'Invalid nonce in request to admin-post.php (adminPostProcessQueue)';
        }

        if ( isset( $msg ) ) {
            WsLog::l( $msg );
            throw new \RuntimeException( $msg );
        }

        Controller::processQueue();
    }
}
