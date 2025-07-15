<?php

namespace WP2Static;

use WP_Error;
use WP_CLI;
use WP_Post;

class Controller {
    /**
     * @var string
     */
    public $bootstrap_file;

    /**
     * Main controller of WP2Static
     *
     * @var \WP2Static\Controller Instance.
     */
    protected static $plugin_instance = null;

    protected function __construct() {}

    /**
     * Returns instance of WP2Static Controller
     *
     * @return \WP2Static\Controller Instance of self.
     */
    public static function getInstance(): Controller {
        if ( null === self::$plugin_instance ) {
            self::$plugin_instance = new self();
        }

        return self::$plugin_instance;
    }

    public static function init( string $bootstrap_file ): Controller {
        $plugin_instance = self::getInstance();

        WordPressAdmin::registerHooks( $bootstrap_file );
        WordPressAdmin::buildUpdateChecker( $bootstrap_file );
        WordPressAdmin::addAdminUIElements();

        Utils::set_max_execution_time();

        new S3\Controller()->run();

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
        $file  = plugin_basename( __FILE__ );

        foreach ( $menu_order as $index => $item ) {
            if ( $item === 'index.php' ) {
                $order[] = $item;
            }
        }

        $order = [
            'index.php',
            'wp2static',
            'statichtmloutput',
        ];

        return $order;
    }

    public static function deactivateForSingleSite(): void {
        WPCron::clearRecurringEvent();
        S3\Controller::deactivateForSingleSite();
    }

    public static function deactivate( bool $network_wide = null ): void {
        if ( $network_wide ) {
            global $wpdb;

            $query = 'SELECT blog_id FROM %s WHERE site_id = %d;';

            $site_ids = $wpdb->get_col(
                sprintf(
                    $query,
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
        S3\Controller::activateForSingleSite();
    }

    public static function activate( bool $network_wide = null ): void {
        if ( $network_wide ) {
            global $wpdb;

            $query = 'SELECT blog_id FROM %s WHERE site_id = %d;';

            $site_ids = $wpdb->get_col(
                sprintf(
                    $query,
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

    /**
     * Checks if the named index exists. If it doesn't, create it. This won't
     * alter an existing index. If you need to change an index, give it a new name.
     *
     * WordPress's dbDelta is very unreliable for indexes. It tends to create duplicate
     * indexes, acts badly if whitespace isn't exactly what it expects, and fails
     * silently. It's okay to create the table and primary key with dbDelta,
     * but use ensureIndex for index creation.
     *
     * @param string $table_name The name of the table that the index is for.
     * @param string $index_name The name of the index.
     * @param string $create_index_sql The SQL to execute if the index needs to be created.
     * @return bool true if the index already exists or was created. false if creation failed.
     */
    public static function ensureIndex(
        string $table_name,
        string $index_name,
        string $create_index_sql
    ): bool {
        global $wpdb;

        $query = $wpdb->prepare(
            "SHOW INDEX FROM $table_name WHERE key_name = %s",
            $index_name
        );
        $indexes = $wpdb->query( $query );

        if ( 0 === $indexes ) {
            $result = $wpdb->query( $create_index_sql );
            if ( false === $result ) {
                WsLog::l( "Failed to create $index_name index on $table_name." );
            }
            return $result;
        } else {
            return true;
        }
    }

    public static function getHookName( string $hook_slug ): string {
        return 'wp2static_' . $hook_slug;
    }

    public static function getTableName( string $table_slug ): string {
        global $wpdb;

        return $wpdb->prefix . 'wp2static_' . $table_slug;
    }

    public static function registerOptionsPage(): void {
        add_menu_page(
            'WP2Static',
            'WP2Static',
            'manage_options',
            'wp2static',
            [ ViewRenderer::class, 'renderRunPage' ],
            'dashicons-shield-alt'
        );

        /** @var array<string, callable> $submenu_pages */
        $submenu_pages = [
            'run' => [ ViewRenderer::class, 'renderRunPage' ],
            'options' => [ ViewRenderer::class, 'renderOptionsPage' ],
            'jobs' => [ ViewRenderer::class, 'renderJobsPage' ],
            'caches' => [ ViewRenderer::class, 'renderCachesPage' ],
            'diagnostics' => [ ViewRenderer::class, 'renderDiagnosticsPage' ],
            'logs' => [ ViewRenderer::class, 'renderLogsPage' ],
            'addons' => [ ViewRenderer::class, 'renderAddonsPage' ],
            'advanced' => [ ViewRenderer::class, 'renderAdvancedOptionsPage' ],
        ];

        foreach ( $submenu_pages as $slug => $method ) {
            $menu_slug =
                $slug === 'run' ? 'wp2static' : 'wp2static-' . $slug;

            $title = ucfirst( $slug );

            add_submenu_page(
                'wp2static',
                'WP2Static ' . ucfirst( $slug ),
                $title,
                'manage_options',
                $menu_slug,
                $method
            );
        }

        add_submenu_page(
            '',
            'WP2Static Detected Files',
            'Detected Files',
            'manage_options',
            'wp2static-detected-files',
            [ ViewRenderer::class, 'renderDetectedFiles' ]
        );

        add_submenu_page(
            '',
            'WP2Static Crawled Files',
            'Crawled Files',
            'manage_options',
            'wp2static-crawled-files',
            [ ViewRenderer::class, 'renderCrawledFiles' ]
        );

        add_submenu_page(
            '',
            'WP2Static Deploy Cache',
            'Deploy Cache',
            'manage_options',
            'wp2static-deploy-cache',
            [ ViewRenderer::class, 'renderDeployCache' ]
        );

        add_submenu_page(
            '',
            'WP2Static Static Site',
            'Static Site',
            'manage_options',
            'wp2static-static-site',
            [ ViewRenderer::class, 'renderStaticSitePaths' ]
        );

        add_submenu_page(
            '',
            'WP2Static Post Processed Site',
            'Post Processed Site',
            'manage_options',
            'wp2static-post-processed-site',
            [ ViewRenderer::class, 'renderPostProcessedSitePaths' ]
        );
    }

    public function userIsAllowed(): bool {
        if ( defined( 'WP_CLI' ) ) {
            return true;
        }

        $referred_by_admin = check_admin_referer( 'wp2static-options' );
        $user_can_manage_options = current_user_can( 'manage_options' );

        return $referred_by_admin && $user_can_manage_options;
    }

    public function resetDefaultSettings(): void {
        Options::seedOptions( Options::optionSpecs() );
    }

    public function deleteDeployCache(): void {
        DeployCache::truncate();
    }

    public static function wp2staticUISaveOptions(): void {
        Options::savePosted( 'core' );

        do_action(
            self::getHookName( 'addon_ui_save_options' )
        );

        check_admin_referer( 'wp2static-ui-options' );

        wp_safe_redirect( admin_url( 'admin.php?page=wp2static-options' ) );
        exit;
    }

    public static function wp2staticDetectedFilesDelete(): void {
        check_admin_referer( 'wp2static-caches-page' );

        DetectedFiles::truncate();

        wp_safe_redirect( admin_url( 'admin.php?page=wp2static-caches' ) );
        exit;
    }

    public static function wp2staticDetectedFilesShow(): void {
        check_admin_referer( 'wp2static-caches-page' );

        wp_safe_redirect( admin_url( 'admin.php?page=wp2static-detected-files' ) );
        exit;
    }

    public static function wp2staticDeleteJobsQueue(): void {
        check_admin_referer( 'wp2static-ui-job-options' );

        JobQueue::truncate();

        wp_safe_redirect( admin_url( 'admin.php?page=wp2static-jobs' ) );
        exit;
    }

    public static function wp2staticDeleteAllCaches(): void {
        check_admin_referer( 'wp2static-caches-page' );

        self::deleteAllCaches();

        wp_safe_redirect( admin_url( 'admin.php?page=wp2static-caches' ) );
        exit;
    }

    public static function deleteAllCaches(): void {
        DetectedFiles::truncate();
        CrawledFiles::truncate();
        StaticSite::delete();
        ProcessedSite::delete();
        DeployCache::truncate();
    }

    public static function wp2staticProcessJobsQueue(): void {
        check_admin_referer( 'wp2static-ui-job-options' );

        WsLog::l( 'Manually processing JobQueue' );

        self::wp2staticProcessQueue();

        wp_safe_redirect( admin_url( 'admin.php?page=wp2static-jobs' ) );
        exit;
    }

    public static function wp2staticDeployCacheDelete(): void {
        check_admin_referer( 'wp2static-caches-page' );

        $deploy_namespace = strval( filter_input( INPUT_POST, 'deploy_namespace' ) );
        if ( $deploy_namespace !== '' ) {
            DeployCache::truncate( $deploy_namespace );
        } else {
            DeployCache::truncate();
        }

        wp_safe_redirect( admin_url( 'admin.php?page=wp2static-caches' ) );
        exit;
    }

    public static function wp2staticDeployCacheShow(): void {
        check_admin_referer( 'wp2static-caches-page' );

        $deploy_namespace = strval( filter_input( INPUT_POST, 'deploy_namespace' ) );
        if ( $deploy_namespace !== '' ) {
            wp_safe_redirect(
                admin_url(
                    'admin.php?page=wp2static-deploy-cache&deploy_namespace=' .
                    urlencode( $deploy_namespace )
                )
            );
        } else {
            wp_safe_redirect( admin_url( 'admin.php?page=wp2static-deploy-cache' ) );
        }

        exit;
    }

    public static function wp2staticCrawledFilesDelete(): void {
        check_admin_referer( 'wp2static-caches-page' );

        CrawledFiles::truncate();

        wp_safe_redirect( admin_url( 'admin.php?page=wp2static-caches' ) );
        exit;
    }

    public static function wp2staticCrawledFilesShow(): void {
        check_admin_referer( 'wp2static-caches-page' );

        wp_safe_redirect( admin_url( 'admin.php?page=wp2static-crawled-files' ) );
        exit;
    }

    public static function wp2staticPostProcessedSiteDelete(): void {
        check_admin_referer( 'wp2static-caches-page' );

        ProcessedSite::delete();

        wp_safe_redirect( admin_url( 'admin.php?page=wp2static-caches' ) );
        exit;
    }

    public static function wp2staticPostProcessedSiteShow(): void {
        check_admin_referer( 'wp2static-caches-page' );

        wp_safe_redirect( admin_url( 'admin.php?page=wp2static-post-processed-site' ) );
        exit;
    }

    public static function wp2staticLogDelete(): void {
        check_admin_referer( 'wp2static-log-page' );

        WsLog::truncate();

        wp_safe_redirect( admin_url( 'admin.php?page=wp2static-logs' ) );
        exit;
    }

    public static function wp2staticStaticSiteDelete(): void {
        check_admin_referer( 'wp2static-caches-page' );

        StaticSite::delete();

        wp_safe_redirect( admin_url( 'admin.php?page=wp2static-caches' ) );
        exit;
    }

    public static function wp2staticStaticSiteShow(): void {
        check_admin_referer( 'wp2static-caches-page' );

        wp_safe_redirect( admin_url( 'admin.php?page=wp2static-static-site' ) );
        exit;
    }

    public static function wp2staticUISaveJobOptions(): void {
        Options::savePosted( 'jobs' );

        do_action(
            self::getHookName( 'addon_ui_save_job_options' )
        );

        check_admin_referer( 'wp2static-ui-job-options' );

        wp_safe_redirect( admin_url( 'admin.php?page=wp2static-jobs' ) );
        exit;
    }

    public static function wp2staticSavePostHandler( int $post_id ): void {
        if ( Options::getValue( 'queueJobOnPostSave' ) &&
            get_post_status( $post_id ) === 'publish' ) {
            self::wp2staticEnqueueJobs( $post_id );
        }
    }

    public static function wp2staticTrashedPostHandler(): void {
        if ( Options::getValue( 'queueJobOnPostDelete' ) ) {
            self::wp2staticEnqueueJobs();
        }
    }

    public static function wp2staticUISaveAdvancedOptions(): void {
        Options::savePosted( 'advanced' );

        do_action(
            self::getHookName( 'addon_ui_save_advanced_options' )
        );

        check_admin_referer( 'wp2static-ui-advanced-options' );

        wp_safe_redirect( admin_url( 'admin.php?page=wp2static-advanced' ) );
        exit;
    }

    public static function wp2staticEnqueueJobs( ?int $post_id = null ): void {
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
            self::wp2staticProcessQueueAdminPost();
        } elseif ( $immediate_mode === 2 ) {
            shell_exec( 'wp wp2static process_queue > /dev/null 2>&1 &' );
            usleep( 100000 ); // 100,000 microseconds = 0.1 seconds
        }
    }

    public static function wp2staticToggleAddon( string $addon_slug = null ): void {
        if ( defined( 'WP_CLI' ) ) {
            if ( ! $addon_slug ) {
                throw WsLog::ex(
                    'No addon slug given for CLI toggling'
                );
            }

            $addon_slug = sanitize_text_field( $addon_slug );
        } else {
            check_admin_referer( 'wp2static-addons-page' );

            $addon_slug = sanitize_text_field( strval( filter_input( INPUT_POST, 'addon_slug' ) ) );
        }

        global $wpdb;

        $table_name = Addons::getTableName();

        // get target addon's current state
        $addon =
            $wpdb->get_row( "SELECT enabled, type FROM $table_name WHERE slug = '$addon_slug'" );

        // if deploy type, disable other deployers when enabling this one
        if ( $addon->type === 'deploy' ) {
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
            [ 'enabled' => ! $addon->enabled ],
            [ 'slug' => $addon_slug ]
        );

        if ( ! defined( 'WP_CLI' ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=wp2static-addons' ) );
        }

        exit;
    }

    public static function wp2staticManuallyEnqueueJobs(): void {
        check_admin_referer( 'wp2static-manually-enqueue-jobs' );

        // TODO: consider using a transient based notifications system to
        // persist through wp_safe_redirect calls
        // ie, https://github.com/wpscholar/wp-transient-admin-notices/blob/master/TransientAdminNotices.php

        self::wp2staticEnqueueJobs();

        wp_safe_redirect( admin_url( 'admin.php?page=wp2static-jobs' ) );
        exit;
    }

    /*
        Should only process at most 4 jobs here (1 per type), with
        earlier jobs of the same type having been "squashed" first
    */
    public static function wp2staticProcessQueue(): void {
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
            $lock = JobQueue::getTableName() . '.' . $job->job_type;
            $query = "SELECT GET_LOCK('$lock', 30) AS lck";
            $locked = intval( $wpdb->get_row( $query )->lck );
            if ( ! $locked ) {
                WsLog::l( "Failed to acquire \"$lock\" lock." );
                return;
            }
            try {
                JobQueue::setStatus( $job->id, 'processing' );

                switch ( $job->job_type ) {
                    case 'detect':
                        WsLog::l( 'Starting URL detection' );
                        $detected_count = URLDetector::enqueueURLs();
                        WsLog::l( "URL detection completed ($detected_count URLs detected)" );
                        break;
                    case 'crawl':
                        self::wp2staticCrawl();
                        break;
                    case 'post_process':
                        WsLog::l( 'Starting post-processing' );
                        $post_processor = new PostProcessor();
                        $post_processor->processStaticSite( StaticSite::getPath() );
                        WsLog::l( 'Post-processing completed' );
                        break;
                    case 'deploy':
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

                        break;
                    case 'direct_deploy':
                        $deployer = new DirectDeployer();
                        WsLog::l( 'Starting direct deployment' );
                        $deployer->deploy();
                        $deployer->deployComplete();
                        break;
                    case 'direct_deploy_post':
                        $deployer = new DirectDeployer();
                        $post_id = $job->triggering_post_id;
                        if ( $post_id ) {
                            $path = wp_make_link_relative( get_permalink( $post_id ) );
                            $paths = new \ArrayIterator( [ [ 'path' => $path ] ] );
                            $detected = DetectedFiles::addPathsIter( $paths );
                            WsLog::l( 'Starting direct deployment for path ' . $path );
                            $deployer->deployPaths( $detected );
                        } else {
                            WsLog::w( 'No post ID found for direct deployment post' );
                        }
                        $deployer->deployComplete();
                        break;
                    default:
                        WsLog::l( 'Trying to process unknown job type' );
                }

                JobQueue::setStatus( $job->id, 'completed' );
            } catch ( \Throwable $e ) {
                JobQueue::setStatus( $job->id, 'failed' );
                // We don't want to crawl and deploy if the detect step fails.
                // Skip all waiting jobs when one fails.
                $table_name = JobQueue::getTableName();
                $wpdb->query(
                    "UPDATE $table_name
                     SET status = 'skipped'
                     WHERE status = 'waiting'"
                );
                throw $e;
            } finally {
                $wpdb->query( "DO RELEASE_LOCK('$lock')" );
            }
        }
    }

    /**
     *  Make a non-blocking POST request to run wp2staticProcessQueue.
     */
    public static function wp2staticProcessQueueAdminPost(): void {
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
                'Error in wp2staticProcessQueueAdminPost. Request to admin-post.php failed: ' .
                json_encode( $result->errors )
            );
        }
    }

    public static function wp2staticHeadless(): void {
        WsLog::l( 'Running WP2Static in Headless mode' );
        WsLog::l( 'Starting URL detection' );
        $detected_count = URLDetector::enqueueURLs();
        WsLog::l( "URL detection completed ($detected_count URLs detected)" );

        self::wp2staticCrawl();

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
        WP_Post $post = null
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
        $subject = 'WP2Static deployment complete on site: ' .
            $site_title = get_bloginfo( 'name' );
        $body = 'WP2Static deployment complete!';
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

        $body = $http_method === 'POST' ? 'WP2Static deployment complete!' :
            [ 'message' => 'WP2Static deployment complete!' ];

        $webhook_response = wp_remote_request(
            $webhook_url,
            [
                'method' => Options::getValue( 'completionWebhookMethod' ),
                'timeout' => 30,
                'user-agent' =>
                    apply_filters(
                        self::getHookName( 'deploy_webhook_user_agent' ),
                        'WP2Static.com'
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

    public static function wp2staticRun(): void {
        check_ajax_referer( self::getHookName( 'run_page' ), 'security' );

        WsLog::l( 'Running full workflow from UI' );

        self::wp2staticHeadless();

        wp_die();
    }

    public static function wp2staticCrawl(): void {
        $crawlers = Addons::getType( 'crawl' );
        $crawler_slug = empty( $crawlers ) ? 'wp2static' : $crawlers[0]->slug;
        do_action(
            self::getHookName( 'crawl' ),
            $crawler_slug
        );
        WsLog::l( 'Crawling completed' );
    }

    /**
     * Give logs to UI
     */
    public static function wp2staticPollLog(): void {
        check_ajax_referer( self::getHookName( 'run_page' ), 'security' );

        $logs = WsLog::poll();

        echo $logs;

        wp_die();
    }
}
