<?php

namespace StaticDeploy;

use WP_Error;
use WP_CLI;
use WP_Post;

class Controller {
    /**
     * Main controller
     *
     * @var \StaticDeploy\Controller Instance.
     */
    protected static $plugin_instance;

    protected function __construct() {}

    /**
     * @return \StaticDeploy\Controller Instance of self.
     */
    public static function getInstance(): Controller {
        if ( null === self::$plugin_instance ) {
            self::$plugin_instance = new self();
        }

        return self::$plugin_instance;
    }

    public static function init(): Controller {
        $bootstrap_file = STATIC_DEPLOY_PATH . 'static-deploy.php';
        $plugin_instance = self::getInstance();

        register_activation_hook(
            $bootstrap_file,
            self::activate( ... )
        );

        register_deactivation_hook(
            $bootstrap_file,
            self::deactivate( ... )
        );

        if ( ! $plugin_instance->loadAdmin() ) {
            return $plugin_instance;
        }

        WordPressAdmin::registerHooks();
        WordPressAdmin::buildUpdateChecker( $bootstrap_file );
        WordPressAdmin::addAdminUIElements();

        Utils::setMaxExecutionTime();

        Local\LocalDeployer::registerHooks();
        Local\LocalOptions::registerHooks();
        S3\Deployer::registerHooks();
        S3\S3Options::registerHooks();

        return $plugin_instance;
    }

    /**
     * Adjusts position of dashboard menu icons
     *
     * @param string[] $menu_order list of menu items
     * @return string[] list of menu items
     */
    public static function setMenuOrder( array $menu_order ): array {
        $order = [];

        foreach ( $menu_order as $item ) {
            if ( $item === 'index.php' ) {
                $order[] = $item;
            }
        }

        return [
            'index.php',
            'static-deploy',
        ];
    }

    public static function deactivateForSingleSite(): void {
        WPCron::clearRecurringEvent();
        Local\LocalOptions::deactivateForSingleSite();
        S3\S3Options::deactivateForSingleSite();
    }

    public static function deactivate(
        bool $network_wide = false,
    ): void {
        if ( $network_wide ) {
            global $wpdb;

            $site_ids = $wpdb->get_col(
                $wpdb->prepare(
                    'SELECT blog_id FROM %i WHERE site_id = %d;',
                    $wpdb->blogs,
                    $wpdb->siteid
                )
            );

            foreach ( $site_ids as $site_id ) {
                switch_to_blog( $site_id );
                self::deactivateForSingleSite();
            }

            restore_current_blog();
        } else {
            self::deactivateForSingleSite();
        }
    }

    public static function activateForSingleSite(): void {
        // prepare DB tables
        WsLog::createTable();
        Options::init();
        CrawledFiles::createTable();
        DetectedFiles::createTable();
        DeployCache::createTable();
        JobQueue::createTable();
        Addons::createTable();
        Local\LocalOptions::activateForSingleSite();
        S3\S3Options::activateForSingleSite();
    }

    public static function activate(
        bool $network_wide = false,
    ): void {
        if ( $network_wide ) {
            global $wpdb;

            $site_ids = $wpdb->get_col(
                $wpdb->prepare(
                    'SELECT blog_id FROM %s WHERE site_id = %d;',
                    $wpdb->blogs,
                    $wpdb->siteid
                )
            );

            foreach ( $site_ids as $site_id ) {
                switch_to_blog( $site_id );
                self::activateForSingleSite();
            }

            restore_current_blog();
        } else {
            self::activateForSingleSite();
        }
    }

    public static function getHookName( string $hook_slug ): string {
        return 'static_deploy_' . $hook_slug;
    }

    public static function registerOptionsPage(): void {
        add_menu_page(
            'Static Deploy',
            'Static Deploy',
            'manage_options',
            'static-deploy',
            ViewRenderer::renderRunPage( ... ),
            'dashicons-shield-alt'
        );

        /** @var array<string, callable> $submenu_pages */
        $submenu_pages = [
            'run' => ViewRenderer::renderRunPage( ... ),
            'options' => ViewRenderer::renderOptionsPage( ... ),
            'jobs' => ViewRenderer::renderJobsPage( ... ),
            'caches' => ViewRenderer::renderCachesPage( ... ),
            'diagnostics' => ViewRenderer::renderDiagnosticsPage( ... ),
            'logs' => ViewRenderer::renderLogsPage( ... ),
            'addons' => ViewRenderer::renderAddonsPage( ... ),
            'advanced' => ViewRenderer::renderAdvancedOptionsPage( ... ),
        ];

        foreach ( $submenu_pages as $slug => $method ) {
            if ( $slug === 'run' ) {
                $page = 'static-deploy';
            } else {
                $page = self::getHookName( $slug );
            }

            $title = ucfirst( $slug );

            add_submenu_page(
                'static-deploy',
                'Static Deploy ' . ucfirst( $slug ),
                $title,
                'manage_options',
                $page,
                $method
            );
        }

        add_submenu_page(
            '',
            'Static Deploy Detected Files',
            'Detected Files',
            'manage_options',
            self::getHookName( 'detected_files' ),
            ViewRenderer::renderDetectedFiles( ... )
        );

        add_submenu_page(
            '',
            'Static Deploy Crawled Files',
            'Crawled Files',
            'manage_options',
            self::getHookName( 'crawled_files' ),
            ViewRenderer::renderCrawledFiles( ... )
        );

        add_submenu_page(
            '',
            'Static Deploy Deploy Cache',
            'Deploy Cache',
            'manage_options',
            self::getHookName( 'deploy_cache' ),
            ViewRenderer::renderDeployCache( ... )
        );

        add_submenu_page(
            '',
            'Static Deploy Static Site',
            'Static Site',
            'manage_options',
            self::getHookName( 'static_site' ),
            ViewRenderer::renderStaticSitePaths( ... )
        );

        add_submenu_page(
            '',
            'Static Deploy Post Processed Site',
            'Post Processed Site',
            'manage_options',
            self::getHookName( 'post_processed_site' ),
            ViewRenderer::renderPostProcessedSitePaths( ... )
        );
    }

    public function loadAdmin(): bool {
        if ( defined( 'WP_CLI' ) ) {
            return true;
        }

        require_once ABSPATH . 'wp-includes/pluggable.php';
        return is_admin();
    }

    public function deleteDeployCache(): void {
        DeployCache::truncate();
    }

    public static function getAdminUrl( string $slug ): string {
        if ( $slug === 'run' ) {
            $page = 'static-deploy';
        } else {
            $page = self::getHookName( $slug );
        }

        return admin_url( 'admin.php?page=' . $page );
    }

    public static function getAdminAjaxUrl( string $slug ): string {
        if ( $slug === 'run' ) {
            $action = 'static-deploy';
        } else {
            $action = self::getHookName( $slug );
        }

        return admin_url( 'admin-ajax.php?action=' . $action );
    }

    public static function getAdminPostUrl( string $slug ): string {
        if ( $slug === 'run' ) {
            $page = 'static-deploy';
        } else {
            $page = self::getHookName( $slug );
        }

        return admin_url( 'admin-post.php?page=' . $page );
    }

    public static function UISaveOptions(): void {
        check_admin_referer( self::getHookName( 'ui_options' ) );

        Options::savePosted( 'core' );

        do_action(
            self::getHookName( 'addon_ui_save_options' )
        );

        wp_safe_redirect( self::getAdminUrl( 'options' ) );
        exit;
    }

    public static function adminDetectedFilesDelete(): void {
        check_admin_referer( self::getHookName( 'caches_page' ) );

        DetectedFiles::truncate();

        wp_safe_redirect( self::getAdminUrl( 'caches' ) );
        exit;
    }

    public static function adminDetectedFilesShow(): void {
        check_admin_referer( self::getHookName( 'caches_page' ) );

        wp_safe_redirect( self::getAdminUrl( 'detected_files' ) );
        exit;
    }

    public static function adminDeleteJobsQueue(): void {
        check_admin_referer( self::getHookName( 'delete_jobs_queue' ) );

        JobQueue::truncate();

        wp_safe_redirect( self::getAdminUrl( 'jobs' ) );
        exit;
    }

    public static function adminDeleteAllCaches(): void {
        check_admin_referer( self::getHookName( 'caches_page' ) );

        self::deleteAllCaches();

        wp_safe_redirect( self::getAdminUrl( 'caches' ) );
        exit;
    }

    public static function deleteAllCaches(): void {
        DetectedFiles::truncate();
        CrawledFiles::truncate();
        StaticSite::delete();
        ProcessedSite::delete();
        DeployCache::truncate();
    }

    public static function adminProcessJobsQueue(): void {
        check_admin_referer( self::getHookName( 'ui_job_options' ) );

        WsLog::l( 'Manually processing JobQueue' );

        self::processQueue();

        wp_safe_redirect( self::getAdminUrl( 'jobs' ) );
        exit;
    }

    public static function adminDeployCacheDelete(): void {
        check_admin_referer( self::getHookName( 'caches_page' ) );

        $deploy_namespace = strval( filter_input( INPUT_POST, 'deploy_namespace' ) );
        if ( $deploy_namespace !== '' ) {
            DeployCache::truncate( $deploy_namespace );
        } else {
            DeployCache::truncate();
        }

        wp_safe_redirect( self::getAdminUrl( 'caches' ) );
        exit;
    }

    public static function adminDeployCacheShow(): void {
        check_admin_referer( self::getHookName( 'caches_page' ) );

        $deploy_namespace = strval( filter_input( INPUT_POST, 'deploy_namespace' ) );
        $admin_url = self::getAdminUrl( 'deploy_cache' );
        if ( $deploy_namespace !== '' ) {
            wp_safe_redirect(
                $admin_url . '&deploy_namespace=' . urlencode( $deploy_namespace )
            );
        } else {
            wp_safe_redirect( $admin_url );
        }

        exit;
    }

    public static function adminCrawledFilesDelete(): void {
        check_admin_referer( self::getHookName( 'caches_page' ) );

        CrawledFiles::truncate();

        wp_safe_redirect( self::getAdminUrl( 'caches' ) );
        exit;
    }

    public static function adminCrawledFilesShow(): void {
        check_admin_referer( self::getHookName( 'caches_page' ) );

        wp_safe_redirect( self::getAdminUrl( 'crawled_files' ) );
        exit;
    }

    public static function adminPostProcessedSiteDelete(): void {
        check_admin_referer( self::getHookName( 'caches_page' ) );

        ProcessedSite::delete();

        wp_safe_redirect( self::getAdminUrl( 'caches' ) );
        exit;
    }

    public static function adminPostProcessedSiteShow(): void {
        check_admin_referer( self::getHookName( 'caches_page' ) );

        wp_safe_redirect( self::getAdminUrl( 'post_processed_site' ) );
        exit;
    }

    public static function adminLogDelete(): void {
        check_admin_referer( self::getHookName( 'log_page' ) );

        WsLog::truncate();

        wp_safe_redirect( self::getAdminUrl( 'logs' ) );
        exit;
    }

    public static function adminStaticSiteDelete(): void {
        check_admin_referer( self::getHookName( 'caches_page' ) );

        StaticSite::delete();

        wp_safe_redirect( self::getAdminUrl( 'caches' ) );
        exit;
    }

    public static function adminStaticSiteShow(): void {
        check_admin_referer( self::getHookName( 'caches_page' ) );

        wp_safe_redirect( self::getAdminUrl( 'static_site' ) );
        exit;
    }

    public static function adminUISaveJobsOptions(): void {
        check_admin_referer( self::getHookName( 'ui_job_options' ) );

        Options::savePosted( 'jobs' );

        do_action(
            self::getHookName( 'addon_ui_save_job_options' )
        );

        wp_safe_redirect( self::getAdminUrl( 'jobs' ) );
        exit;
    }

    public static function savePostHandler( int $post_id ): void {
        if ( Options::getValue( 'queueJobOnPostSave' ) &&
            get_post_status( $post_id ) === 'publish' ) {
            self::enqueueJobs( $post_id );
        }
    }

    public static function trashedPostHandler(): void {
        if ( Options::getValue( 'queueJobOnPostDelete' ) ) {
            self::enqueueJobs();
        }
    }

    public static function adminUISaveAdvancedOptions(): void {
        check_admin_referer( self::getHookName( 'ui_advanced_options' ) );

        Options::savePosted( 'advanced' );

        do_action(
            self::getHookName( 'addon_ui_save_advanced_options' )
        );

        wp_safe_redirect( self::getAdminUrl( 'advanced' ) );
        exit;
    }

    public static function enqueueJobs( ?int $post_id = null ): void {
        // check each of these in order we want to enqueue
        $job_types = [
            'autoJobQueueDirectDeployPost' => 'direct_deploy_post',
            'autoJobQueueDetection' => 'detect',
            'autoJobQueueCrawling' => 'crawl',
            'autoJobQueuePostProcessing' => 'post_process',
            'autoJobQueueDeployment' => 'deploy',
            'autoJobQueueDirectDeploy' => 'direct_deploy',
        ];

        foreach ( $job_types as $key => $job_type ) {
            if ( (int) Options::getValue( $key ) === 1 ) {
                JobQueue::addJob( $job_type, $post_id );
            }
        }

        $immediate_mode = intval( Options::getValue( 'processQueueImmediately' ) );
        if ( $immediate_mode === 1 ) {
            self::processQueueAdminPost();
        } elseif ( $immediate_mode === 2 ) {
            shell_exec( 'wp static-deploy process_queue > /dev/null 2>&1 &' );
            usleep( 100000 ); // 100,000 microseconds = 0.1 seconds
        }
    }

    public static function adminSetAddonState(
        ?string $addon_slug = null,
        bool $enabled = false,
    ): void {
        if ( defined( 'WP_CLI' ) ) {
            if ( ! $addon_slug ) {
                throw WsLog::ex( 'No addon slug given' );
            }

            $addon_slug = sanitize_text_field( $addon_slug );
        } else {
            check_admin_referer( self::getHookName( 'addons_page' ) );

            $addon_slug = sanitize_text_field( strval( filter_input( INPUT_POST, 'addon_slug' ) ) );
        }

        global $wpdb;

        $table_name = Addons::getTableName();

        $addon_type = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT type FROM %i WHERE slug = %s',
                $table_name,
                $addon_slug
            )
        );

        // if deploy type, disable other deployers when enabling this one
        if ( $enabled && $addon_type === 'deploy' ) {
            $wpdb->update(
                $table_name,
                [ 'enabled' => 0 ],
                [
                    'enabled' => 1,
                    'type' => 'deploy',
                ]
            );
        }

        // toggle the target addon's state
        $wpdb->update(
            $table_name,
            [ 'enabled' => $enabled ],
            [ 'slug' => $addon_slug ]
        );

        $enabled_str = $enabled ? 'enabled' : 'disabled';
        WsLog::l( "Addon {$addon_slug} {$enabled_str}" );

        if ( ! defined( 'WP_CLI' ) ) {
            wp_safe_redirect( self::getAdminUrl( 'addons' ) );
            exit;
        }
    }

    public static function adminToggleAddon(
        ?string $addon_slug = null,
    ): void {
        if ( defined( 'WP_CLI' ) ) {
            if ( ! $addon_slug ) {
                throw WsLog::ex(
                    'No addon slug given for CLI toggling'
                );
            }

            $addon_slug = sanitize_text_field( $addon_slug );
        } else {
            check_admin_referer( self::getHookName( 'addons_page' ) );

            $addon_slug = sanitize_text_field( strval( filter_input( INPUT_POST, 'addon_slug' ) ) );
        }

        global $wpdb;

        $table_name = Addons::getTableName();

        // get target addon's current state
        $enabled = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT enabled FROM %i WHERE slug = %s',
                $table_name,
                $addon_slug
            )
        );

        self::adminSetAddonState( $addon_slug, ! $enabled );
    }

    public static function adminManuallyEnqueueJobs(): void {
        check_admin_referer( self::getHookName( 'manually_enqueue_jobs' ) );

        // TODO: consider using a transient based notifications system to
        // persist through wp_safe_redirect calls
        // ie, https://github.com/wpscholar/wp-transient-admin-notices/blob/master/TransientAdminNotices.php

        self::enqueueJobs();

        wp_safe_redirect( self::getAdminUrl( 'jobs' ) );
        exit;
    }

    /*
        Should only process at most 4 jobs here (1 per type), with
        earlier jobs of the same type having been "squashed" first
    */
    public static function processQueue(): void {
        global $wpdb;

        WsLog::deleteOldLogs();

        JobQueue::markFailedJobs();
        // skip any earlier jobs of same type still in 'waiting' status
        JobQueue::squashQueue();

        if ( JobQueue::jobsInProgress() ) {
            WsLog::l(
                'Job in progress when attempting to process queue.
                  No new jobs will be processed until current in progress is complete.'
            );

            return;
        }

        // get all with status 'waiting' in order of oldest to newest
        $jobs = JobQueue::getProcessableJobs();

        foreach ( $jobs as $job ) {
            JobQueue::process( $job );
        }
    }

    /**
     *  Make a non-blocking POST request to run processQueue.
     */
    public static function processQueueAdminPost(): void {
        $url = admin_url( 'admin-post.php' ) . '?action=' . self::getHookName( 'process_queue' );
        $nonce = wp_create_nonce( self::getHookName( 'process_queue' ) );
        $result = wp_remote_post(
            $url,
            [
                'blocking' => false,
                'body' => [ '_wpnonce' => $nonce ],
                'cookies' => $_COOKIE,
                'sslverify' => false,
                'timeout' => 0.01,
            ]
        );

        if ( is_wp_error( $result ) ) {
            WsLog::l(
                'Error in processQueueAdminPost. Request to admin-post.php failed: ' .
                json_encode( $result->errors )
            );
        }
    }

    public static function runHeadless(): void {
        WsLog::l( 'Running in headless mode' );
        WsLog::l( 'Starting URL detection' );
        $detected_count = URLDetector::enqueueURLs();
        WsLog::l( "URL detection completed ($detected_count URLs detected)" );

        self::crawl();

        WsLog::l( 'Starting post-processing' );
        $post_processor = new PostProcessor();
        $post_processor->processStaticSite( StaticSite::getPath() );
        WsLog::l( 'Post-processing completed' );

        $deployer = Addons::getDeployer();

        if ( ! $deployer ) {
            WsLog::l( 'No deployment add-ons are enabled, skipping deployment.' );
        } else {
            WsLog::l( 'Starting deployment' );
            do_action(
                self::getHookName( 'deploy' ),
                ProcessedSite::getPath(),
                $deployer
            );
        }
        WsLog::l( 'Starting post-deployment actions' );
        do_action(
            self::getHookName( 'post_deploy_trigger' ),
            $deployer
        );
    }

    public static function invalidateSingleURLCache(
        int $post_id = 0,
        ?WP_Post $post = null,
    ): void {
        if ( ! $post ) {
            return;
        }

        $permalink = get_permalink(
            $post->ID
        );

        $site_url = SiteInfo::getUrl( 'site' );

        if ( ! is_string( $permalink ) || ! is_string( $site_url ) ) {
            return;
        }

        $url = str_replace(
            $site_url,
            '/',
            $permalink
        );

        CrawledFiles::rmUrl( $url );
    }

    public static function emailDeployNotification(): void {
        if ( empty( Options::getValue( 'completionEmail' ) ) ) {
            return;
        }

        WsLog::l( 'Sending deployment notification email...' );

        $to = Options::getValue( 'completionEmail' );
        $subject = 'Static Deploy deployment complete on site: ' .
            $site_title = get_bloginfo( 'name' );
        $body = 'Static Deploy deployment complete!';
        $headers = [];

        if ( wp_mail( $to, $subject, $body, $headers ) ) {
            WsLog::l( 'Deployment notification email sent without error.' );
        } else {
            WsLog::l( 'Failed to send deployment notificaiton email.' );
        }
    }

    public static function webhookDeployNotification(): void {
        $webhook_url = Options::getValue( 'completionWebhook' );

        if ( empty( $webhook_url ) ) {
            return;
        }

        WsLog::l( 'Sending deployment notification webhook...' );

        $http_method = Options::getValue( 'completionWebhookMethod' );

        $body = $http_method === 'POST' ? 'Static Deploy deployment complete!' :
            [ 'message' => 'Static Deploy deployment complete!' ];

        $webhook_response = wp_remote_request(
            $webhook_url,
            [
                'method' => Options::getValue( 'completionWebhookMethod' ),
                'timeout' => 30,
                'user-agent' =>
                    apply_filters(
                        self::getHookName( 'deploy_webhook_user_agent' ),
                        'staticdeploy.com'
                    ),
                'body' => apply_filters(
                    self::getHookName( 'deploy_webhook_body' ),
                    $body
                ),
                'headers' => apply_filters(
                    self::getHookName( 'deploy_webhook_headers' ),
                    []
                ),
            ]
        );

        WsLog::l(
            'Webhook response code: ' . wp_remote_retrieve_response_code( $webhook_response )
        );
    }

    public static function ajaxRun(): void {
        check_ajax_referer( self::getHookName( 'run_page' ), 'security' );

        WsLog::l( 'Running full workflow from UI' );

        self::runHeadless();

        wp_die();
    }

    public static function crawl(
        ?CrawlConfig $crawl_config = null
    ): void {
        if ( ! $crawl_config ) {
            $crawl_config = new CrawlConfig();
        }

        $crawlers = Addons::getType( 'crawl' );
        $crawler_slug = empty( $crawlers ) ? 'static-deploy' : $crawlers[0]->slug;
        do_action(
            self::getHookName( 'crawl' ),
            $crawler_slug,
            $crawl_config,
        );
        WsLog::l( 'Crawling completed' );
    }

    /**
     * Give logs to UI
     */
    public static function ajaxPollLog(): void {
        check_ajax_referer( self::getHookName( 'run_page' ), 'security' );

        $logs = WsLog::poll();

        echo esc_html( $logs );

        wp_die();
    }
}
