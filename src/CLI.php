<?php

namespace StaticDeploy;

use WP_CLI;

/**
 * Generate a static copy of your website & publish remotely
 */
class CLI {
    /**
     * @var array<string>
     */
    private $assoc_args = null;

    /**
     * Display system information and health check
     */
    public function diagnostics(): void {
        WP_CLI::line(
            PHP_EOL . 'WP2Static' . PHP_EOL
        );

        $environmental_info = [
            [
                'key' => 'PLUGIN VERSION',
                'value' => STATIC_DEPLOY_VERSION,
            ],
            [
                'key' => 'PHP_VERSION',
                'value' => phpversion(),
            ],
            [
                'key' => 'PHP MAX EXECUTION TIME',
                'value' => ini_get( 'max_execution_time' ),
            ],
            [
                'key' => 'OS VERSION',
                'value' => php_uname(),
            ],
            [
                'key' => 'WP VERSION',
                'value' => get_bloginfo( 'version' ),
            ],
            [
                'key' => 'WP URL',
                'value' => get_bloginfo( 'url' ),
            ],
            [
                'key' => 'WP SITEURL',
                'value' => get_option( 'siteurl' ),
            ],
            [
                'key' => 'WP HOME',
                'value' => get_option( 'home' ),
            ],
            [
                'key' => 'WP ADDRESS',
                'value' => get_bloginfo( 'wpurl' ),
            ],
        ];

        WP_CLI\Utils\format_items(
            'table',
            $environmental_info,
            [ 'key', 'value' ]
        );

        $active_plugins = (array) get_option( 'active_plugins' );

        WP_CLI::line( PHP_EOL . 'Active plugins:' . PHP_EOL );

        foreach ( $active_plugins as $active_plugin ) {
            /**
             * @var string $active_plugin
             */
            WP_CLI::line( $active_plugin );
        }

        WP_CLI::line( PHP_EOL );

        WP_CLI::line(
            'There are a total of ' . count( $active_plugins ) .
            ' active plugins on this site.' . PHP_EOL
        );
    }

    public function microtime_diff(
        string $start,
        string $end = null
    ): float {
        if ( ! $end ) {
            $end = microtime();
        }

        list( $start_usec, $start_sec ) = explode( ' ', $start );
        list( $end_usec, $end_sec ) = explode( ' ', $end );

        $diff_sec = intval( $end_sec ) - intval( $start_sec );
        $diff_usec = floatval( $end_usec ) - floatval( $start_usec );

        return floatval( $diff_sec ) + $diff_usec;
    }

    /**
     * Deploy the generated static site.
     * ## OPTIONS
     *
     * @param string[] $args CLI args
     * @param string[] $assoc_args CLI args
     */
    public function deploy(
        array $args,
        array $assoc_args
    ): void {
        // We don't accept any arguments or parameters for this command
        if ( ! empty( $args ) || ! empty( $assoc_args ) ) {
            WP_CLI::error( 'No arguments or parameters are accepted for this command.' );
        }

        Options::init();
        $deployer = Addons::getDeployer();

        if ( ! $deployer ) {
            WP_CLI::line( 'No deployment add-ons are enabled, skipping deployment.' );
        } else {
            WsLog::l( 'Starting deployment' );
            do_action(
                Controller::getHookName( 'deploy' ),
                ProcessedSite::getPath(),
                $deployer
            );
        }
        WsLog::l( 'Starting post-deployment actions' );
        do_action(
            Controller::getHookName( 'post_deploy_trigger' ),
            $deployer
        );
    }

    /*
     * Crawls, processes and deploys files all in one pass.
     *
     * ## OPTIONS
     *
     * <post-id>
     * Post ID to deploy. Deploys all files if omitted.
     *
     */
    public function direct_deploy(
        array $args,
        array $assoc_args
    ): void {
        // We don't accept any parameters for this command
        if ( ! empty( $assoc_args ) ) {
            WP_CLI::error( 'No parameters are accepted for this command.' );
        }
        Options::init();
        WsLog::deleteOldLogs();

        $deployer = new DirectDeployer();

        if ( isset( $args[0] ) ) {
            $post_id = intval( $args[0] );
            $path = wp_make_link_relative( get_permalink( $post_id ) );
            $paths = new \ArrayIterator( [ [ 'path' => $path ] ] );
            $detected = DetectedFiles::addPathsIter( $paths );
            WsLog::l( 'Starting direct deployment for path ' . $path );
            $deployer->deployPaths( $detected );
        } else {
            WsLog::l( 'Starting direct deployment' );
            $deployer->deploy();
        }

        $deployer->deployComplete();
    }

    /**
     * Read / write plugin options
     *
     * ## OPTIONS
     *
     * <list> [--reveal-sensitive-values]
     *
     * Get all option names and values (explicitly reveal sensitive values)
     *
     * <get> <option-name>
     *
     * Get or set a specific option via name
     *
     * <set> <option-name> <value>
     *
     * Set a specific option via name
     *
     *
     * ## EXAMPLES
     *
     * List all options
     *
     *     wp static-deploy options list
     *
     * List all options (revealing sensitive values)
     *
     *     wp static-deploy options list --reveal_sensitive_values
     *
     * Get option
     *
     *     wp static-deploy options get detectPages
     *
     * Set option
     *
     *     wp static-deploy options set detectPages 1
     *     wp static-deploy options set queueJobOnPostSave 1
     *
     * @param string[] $args CLI args
     * @param string[] $assoc_args CLI args
     */
    public function options(
        array $args,
        array $assoc_args
    ): void {
        // We don't accept any parameters for this command
        if ( ! empty( $assoc_args ) ) {
            WP_CLI::error( 'No parameters are accepted for this command.' );
        }
        $action = isset( $args[0] ) ? $args[0] : null;
        $option_name = isset( $args[1] ) ? $args[1] : null;
        $value = isset( $args[2] ) ? $args[2] : null;
        $reveal_sensitive_values = false;

        if ( empty( $action ) ) {
            WP_CLI::error( 'Missing required argument: <get|set|list>' );
        }

        Options::init();

        $plugin = Controller::getInstance();

        if ( $action === 'get' ) {
            if ( empty( $option_name ) ) {
                WP_CLI::error( 'Missing required argument: <option-name>' );
                return;
            }

            // decrypt basicAuthPassword
            if ( $option_name === 'basicAuthPassword' ) {
                $option_value = Options::encrypt_decrypt(
                    'decrypt',
                    Options::getValue( $option_name )
                );
            } else {
                $option_value = Options::getValue( $option_name );
            }

            WP_CLI::line( $option_value );
        }

        if ( $action === 'set' ) {
            if ( empty( $option_name ) ) {
                WP_CLI::error( 'Missing required argument: <option-name>' );
                return;
            }

            // encrypt basic auth pwd
            if ( ! empty( $value ) && $option_name === 'basicAuthPassword' ) {
                $value = Options::encrypt_decrypt(
                    'encrypt',
                    $value
                );
            }

            // TODO: assert expected result
            Options::save( $option_name, $value );

        }

        if ( $action === 'list' ) {
            $options = Options::getAll();

            WP_CLI\Utils\format_items(
                'table',
                $options,
                [ 'name', 'value' ]
            );
        }
    }

    /**
     * Print multilines of input text via WP-CLI
     */
    public function multilinePrint( string $str ): void {
        $msg = trim( str_replace( [ "\r", "\n" ], '', $str ) );

        $msg = preg_replace( '!\s+!', ' ', $msg );

        WP_CLI::line( PHP_EOL . $msg . PHP_EOL );
    }

    /**
     * Crawls site, creating or updating the static site
     *
     * @param string[] $args Arguments after command
     * @param string[] $assoc_args Parameters after command
     */
    public function crawl( array $args, array $assoc_args ): void {
        // We don't accept any arguments or parameters for this command
        if ( ! empty( $args ) || ! empty( $assoc_args ) ) {
            WP_CLI::error( 'No arguments or parameters are accepted for this command.' );
        }
        Options::init();
        Controller::crawl();
    }

    /**
     * Detect WordPress URLs to crawl, based on saved options
     */
    public function detect(): void {
        Options::init();
        $detected_count = URLDetector::enqueueURLs();
    }

    /**
     * Makes a copy of crawled static site with processing applied
     */
    public function post_process(): void {
        Options::init();
        $post_processor = new PostProcessor();
        $post_processor->processStaticSite( StaticSite::getPath() );
    }

    /**
     * Process any jobs in the queue.
     */
    public function process_queue(): void {
        $job_count = JobQueue::getWaitingJobsCount();

        if ( $job_count === 0 ) {
            WP_CLI::success( 'No jobs in queue' );
        } else {
            WP_CLI::line( ' Processing ' . $job_count . ' job' . ( $job_count > 1 ? 's' : '' ) );

            Controller::processQueue();

            WP_CLI::success( 'Done processing queue' );
        }
    }

    /**
     * Crawled Files
     *
     * <list>
     *
     * List all crawled files
     *
     * <count>
     *
     * Show total number of crawled files
     *
     * <delete>
     *
     * Delete all crawled files
     *
     * @param string[] $args Arguments after command
     * @param string[] $assoc_args Parameters after command
     */
    public function crawled_files( array $args, array $assoc_args ): void {
        $action = isset( $args[0] ) ? $args[0] : null;

        if ( $action === 'list' ) {
            $urls = CrawledFiles::getHashes();

            foreach ( $urls as $url ) {
                WP_CLI::line( $url );
            }
        }

        if ( $action === 'count' ) {
            $urls = CrawledFiles::getHashes();

            WP_CLI::line( (string) count( $urls ) );
        }

        if ( $action === 'delete' ) {

            if ( ! isset( $assoc_args['force'] ) ) {
                $this->multilinePrint(
                    "no --force given. Please type 'yes' to confirm
                    deletion of crawled files"
                );

                $userval = trim( (string) fgets( STDIN ) );

                if ( $userval !== 'yes' ) {
                    WP_CLI::error( 'Failed to delete crawled files' );
                }
            }

            CrawledFiles::truncate();

            WP_CLI::success( 'Deleted crawled files' );
        }
    }

    /**
     * Detected Files
     *
     * <list>
     *
     * List all detected files
     *
     * <count>
     *
     * Show total number of detected files
     *
     * <delete>
     *
     * Empty all detected files
     *
     * @param string[] $args Arguments after command
     * @param string[] $assoc_args Parameters after command
     */
    public function detected_files( array $args, array $assoc_args ): void {
        $action = isset( $args[0] ) ? $args[0] : null;

        if ( $action === 'list' ) {
            $urls = DetectedFiles::getCrawlablePaths();

            foreach ( $urls as $url ) {
                WP_CLI::line( $url );
            }
        }

        if ( $action === 'count' ) {
            $count = DetectedFiles::getTotalCrawlableURLs();

            WP_CLI::line( (string) $count );
        }

        if ( $action === 'delete' ) {

            if ( ! isset( $assoc_args['force'] ) ) {
                $this->multilinePrint(
                    "no --force given. Please type 'yes' to confirm
                    deletion of detected files"
                );

                $userval = trim( (string) fgets( STDIN ) );

                if ( $userval !== 'yes' ) {
                    WP_CLI::error( 'Failed to delete detected files' );
                }
            }

            DetectedFiles::truncate();

            WP_CLI::success( 'Deleted detected files' );
        }
    }

    /**
     * Processed Site
     *
     * <delete>
     *
     * Delete all generated Processed Site files from server
     *
     * @param string[] $args Arguments after command
     * @param string[] $assoc_args Parameters after command
     */
    public function processed_site( array $args, array $assoc_args ): void {
        $action = isset( $args[0] ) ? $args[0] : null;

        // also validate expected $action vs any
        if ( empty( $action ) ) {
            WP_CLI::error(
                'Missing required argument: ' .
                '<delete>'
            );
        }

        if ( $action === 'delete' ) {
            if ( ! isset( $assoc_args['force'] ) ) {
                $this->multilinePrint(
                    "no --force given. Please type 'yes' to confirm deletion
                     of ProcessedSite file cache"
                );

                $userval = trim( (string) fgets( STDIN ) );

                if ( $userval !== 'yes' ) {
                    WP_CLI::error( 'Failed to delete Processed Static Site file cache' );
                }
            }

            ProcessedSite::delete();
        }
    }

    /**
     * Static Site
     *
     * <delete>
     *
     * Delete all generated Static Site files from server
     *
     *   -- also deletes the crawled files
     *
     * @param string[] $args Arguments after command
     * @param string[] $assoc_args Parameters after command
     */
    public function static_site( array $args, array $assoc_args ): void {
        $action = isset( $args[0] ) ? $args[0] : null;

        // also validate expected $action vs any
        if ( empty( $action ) ) {
            WP_CLI::error(
                'Missing required argument: ' .
                '<delete>'
            );
        }

        if ( $action === 'delete' ) {
            if ( ! isset( $assoc_args['force'] ) ) {
                $this->multilinePrint(
                    "no --force given. Please type 'yes' to confirm deletion
                     of StaticSite file cache"
                );

                $userval = trim( (string) fgets( STDIN ) );

                if ( $userval !== 'yes' ) {
                    WP_CLI::error( 'Failed to delete Static Site file cache' );
                }
            }

            StaticSite::delete();
        }
    }

    /**
     * Deploy Cache
     *
     * <list>
     *
     * List all URLs in the DeployCache
     *
     * <count>
     *
     * Show total number of URLs in DeployCache
     *
     * <delete>
     *
     * Empty all URLs from DeployCache
     *
     * @param string[] $args Arguments after command
     * @param string[] $assoc_args Parameters after command
     */
    public function deploy_cache( array $args, array $assoc_args ): void {
        $action = isset( $args[0] ) ? $args[0] : null;

        if ( $action === 'list' ) {
            $paths = DeployCache::getPaths();

            foreach ( $paths as $url ) {
                WP_CLI::line( $url );
            }
        }

        if ( $action === 'count' ) {
            WP_CLI::line( (string) count( DeployCache::getTotal() ) );
        }

        if ( $action === 'delete' ) {

            if ( ! isset( $assoc_args['force'] ) ) {
                $this->multilinePrint(
                    "no --force given. Please type 'yes' to confirm
                    deletion of Deploy Cache"
                );

                $userval = trim( (string) fgets( STDIN ) );

                if ( $userval !== 'yes' ) {
                    WP_CLI::error( 'Failed to delete Deploy Cache' );
                }
            }

            DeployCache::truncate();

            WP_CLI::success( 'Deleted Deploy Cache' );
        }
    }

    /**
     * Full Workflow
     *
     * Executes all core workflows: detect, crawl, post_process & deploy
     *
     * @param string[] $args Arguments after command
     * @param string[] $assoc_args Parameters after command
     */
    public function full_workflow( array $args, array $assoc_args ): void {
        // We don't accept any arguments or parameters for this command
        if ( ! empty( $args ) || ! empty( $assoc_args ) ) {
            WP_CLI::error( 'No arguments or parameters are accepted for this command.' );
        }
        WsLog::deleteOldLogs();
        $this->detect();
        $this->crawl( [], [] );
        $this->post_process();
        $this->deploy( [], [] );
    }

    /**
     * delete_all_cache
     *
     * Deletes all caches
     *
     * @param string[] $args Arguments after command
     * @param string[] $assoc_args Parameters after command
     */
    public function delete_all_cache( array $args, array $assoc_args ): void {
        if ( ! isset( $assoc_args['force'] ) ) {
            $this->multilinePrint(
                "no --force given. Please type 'yes' to confirm
                deletion of all caches"
            );

            $userval = trim( (string) fgets( STDIN ) );

            if ( $userval !== 'yes' ) {
                WP_CLI::error( 'Failed to delete all caches' );
            }
        }

        Controller::deleteAllCaches();
    }

    /**
     * Addons
     *
     * <list>
     *
     * List all registered Add-ons
     *
     * @param string[] $args Arguments after command
     * @param string[] $assoc_args Parameters after command
     * @throws StaticDeployException
     */
    public function addons( array $args, array $assoc_args ): void {
        // We don't accept any parameters for this command
        if ( ! empty( $assoc_args ) ) {
            WP_CLI::error( 'No parameters are accepted for this command.' );
        }
        $action = isset( $args[0] ) ? $args[0] : null;

        if ( $action === 'list' ) {
            $addons = Addons::getAll();

            $pretty_addons = [];

            foreach ( $addons as $addon ) {
                $pretty_addons[] = [
                    'Enabled' => $addon->enabled,
                    'Slug' => $addon->slug,
                    'Name' => $addon->name,
                    'Description' => $addon->description,
                    'Docs' => $addon->docs_url,
                ];
            }

            WP_CLI\Utils\format_items(
                'table',
                $pretty_addons,
                [ 'Enabled', 'Slug', 'Name', 'Description', 'Docs' ]
            );
        }

        if ( $action === 'toggle' ) {
            $addon_slug = isset( $args[1] ) ? $args[1] : null;

            if ( ! $addon_slug ) {
                throw new StaticDeployException(
                    'No addon slug given for CLI toggling'
                );

            }

            // TODO Output details on if addon was enabled or disabled
            Controller::adminToggleAddon( $addon_slug );
        }
    }
}
