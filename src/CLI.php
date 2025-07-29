<?php

namespace StaticDeploy;

use StaticDeploy\CLI\Args;
use WP_CLI;

/**
 * Generate a static copy of your website & publish remotely
 */
class CLI {
    /**
     * @var array<string>
     */
    private $assoc_args = null;

    public static function init(): void {
        WP_CLI::add_command( 'static-deploy', self::class );
        CLI\Jobs::registerCommands();
        CLI\Memcached::registerCommands();

        WP_CLI::add_hook(
            'find_command_to_run_pre',
            [ CLI\Subcommand::class, 'addHiddenCommands' ],
        );
    }

    /**
     * Display system information and health check
     */
    public function diagnostics(): void {
        WP_CLI::log(
            PHP_EOL . 'Static Deploy' . PHP_EOL
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

        WP_CLI::log( PHP_EOL . 'Active plugins:' . PHP_EOL );

        foreach ( $active_plugins as $active_plugin ) {
            /**
             * @var string $active_plugin
             */
            WP_CLI::log( $active_plugin );
        }

        WP_CLI::log( PHP_EOL );

        WP_CLI::log(
            'There are a total of ' . count( $active_plugins ) .
            ' active plugins on this site.' . PHP_EOL
        );
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
        $started_at = Utils::wpDateTime();

        if ( ! $deployer ) {
            WP_CLI::log( 'No deployment add-ons are enabled, skipping deployment.' );
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

        JobQueue::addCompletedJob(
            'deploy',
            $started_at,
        );
    }

    /**
     * Detects, crawls, processes and deploys files in a single pass.
     * This avoids writing files to disk and can be more efficient
     * than running the steps separately.
     *
     * ## OPTIONS
     *
     * [<post-id>]
     * : Post ID to deploy. Deploys all files if omitted.
     *
     * [--no-detect]
     * : Skip detect step and crawl only previously detected
     *   paths.
     *
     * [--path-hash-prefix=<prefix>]
     * : Ignore paths that do not match the hash prefix.
     *
     * @subcommand direct-deploy
     * @alias direct_deploy
     */
    public function direct_deploy(
        array $args,
        array $assoc_args
    ): void {
        $opts = [
            'post-id' => null,
        ];
        $assoc_opts = [
            'detect' => null,
            'path-hash-prefix' => null,
        ];
        $cfg = Args::parse( $args, $assoc_args, $opts, $assoc_opts );

        $path_hash_prefix = strval( $cfg['path-hash-prefix'] );

        Options::init();
        WsLog::deleteOldLogs();

        $crawl_config = new CrawlConfig(
            path_hash_prefix: $path_hash_prefix,
        );
        $direct_deploy_config = new DirectDeployConfig(
            crawl_config: $crawl_config,
            do_detect: ( $cfg['detect'] ?? true ) !== false,
        );
        $deployer = new DirectDeployer( $direct_deploy_config );
        if ( ! $deployer->ready ) {
            return;
        }

        $started_at = Utils::wpDateTime();

        if ( ( $cfg['post-id'] ?? null ) !== null ) {
            $post_id = intval( $cfg['post-id'] );
            $path = wp_make_link_relative( get_permalink( $post_id ) );
            $paths = new \ArrayIterator( [ new PathInfo( $path ) ] );
            $detected = DetectedFiles::addPathsIter( $paths );
            WsLog::l( 'Starting direct deployment for path ' . $path );
            $deployer->deployPaths( $detected );
        } else {
            $post_id = null;
            WsLog::l( 'Starting direct deployment' );
            $deployer->deploy();
        }

        $deployer->deployComplete();

        JobQueue::addCompletedJob(
            'direct_deploy',
            $started_at,
            $post_id,
        );
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
     *     wp static-deploy options list --reveal-sensitive-values
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
        $action = isset( $args[0] ) ? $args[0] : null;
        $option_name = isset( $args[1] ) ? $args[1] : null;
        $value = isset( $args[2] ) ? $args[2] : null;
        $reveal_sensitive_values = isset( $assoc_args['reveal-sensitive-values'] );

        if ( ! in_array( $action, [ 'get', 'set', 'list' ] ) ) {
            WP_CLI::error( 'Missing required argument: <get|set|list>' );
        }

        $option_specs = Options::getSpecs();

        Options::init();
        Options::seedOptions( $option_specs );

        $plugin = Controller::getInstance();

        if ( $action === 'get' || $action === 'set' ) {
            if ( empty( $option_name ) ) {
                WP_CLI::error( 'Missing required argument: <option-name>' );
                return;
            }

            $option_spec = $option_specs[ $option_name ];
            if ( ! $option_spec ) {
                WP_CLI::error( 'Unknown option: ' . $option_name );
                return;
            }

            if ( $action === 'set' ) {
                $option = OptionData::fromUserInput( $option_spec, $value );
                $option->save();
            } else {
                $option = Options::getOption( $option_spec );
            }

            if ( ! $reveal_sensitive_values && $option_spec->type === 'password' ) {
                WP_CLI::log( '********' );
            } elseif ( $option->blob_value ) {
                WP_CLI::log( $option->blob_value );
            } else {
                WP_CLI::log( $option->value );
            }
        } elseif ( $action === 'list' ) {
            $options = Options::getAll( $option_specs );

            $arr_options = [];
            foreach ( $options as $option ) {
                $value = $option->value;

                if ( ! $reveal_sensitive_values && $option->option_spec->type === 'password' ) {
                    $value = '********';
                }

                if ( $option->blob_value !== null ) {
                    if ( empty( $option->blob_value ) ) {
                        $value = '(Empty BLOB value)';
                    } else {
                        $value = '(' .
                        count( explode( PHP_EOL, $option->blob_value ) ) .
                        '-line BLOB value)';
                    }
                }

                $arr_options[] = [
                    'name' => $option->option_spec->name,
                    'value' => $value,
                ];
            }

            WP_CLI\Utils\format_items(
                'table',
                $arr_options,
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

        WP_CLI::log( PHP_EOL . $msg . PHP_EOL );
    }

    /**
     * Crawls site, creating or updating the static site
     *
     * ## OPTIONS
     *
     * [--path-hash-prefix=<prefix>]
     * : Crawl only paths whose hashes match the prefix.
     */
    public function crawl( array $args, array $assoc_args ): void {
        $opts = null;
        $assoc_opts = [
            'path-hash-prefix' => null,
        ];
        $cfg = Args::parse( $args, $assoc_args, $opts, $assoc_opts );

        $path_hash_prefix = strval( $cfg['path-hash-prefix'] );

        Options::init();
        $crawl_config = new CrawlConfig(
            path_hash_prefix: $path_hash_prefix,
        );
        $started_at = Utils::wpDateTime();
        Controller::crawl( $crawl_config );

        JobQueue::addCompletedJob(
            'crawl',
            $started_at,
        );
    }

    /**
     * Detect WordPress URLs to crawl, based on saved options
     */
    public function detect(): void {
        Options::init();
        $started_at = Utils::wpDateTime();
        URLDetector::enqueueURLs();
        JobQueue::addCompletedJob(
            'detect',
            $started_at,
        );
    }

    /**
     * Makes a copy of crawled static site with processing applied
     *
     * @subcommand post-process
     * @alias post_process
     */
    public function post_process(): void {
        Options::init();
        $started_at = Utils::wpDateTime();
        $post_processor = new PostProcessor();
        $post_processor->processStaticSite( StaticSite::getPath() );
        JobQueue::addCompletedJob(
            'post_process',
            $started_at,
        );
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
     * @subcommand crawled-files
     * @alias crawled_files
     *
     * @param string[] $args Arguments after command
     * @param string[] $assoc_args Parameters after command
     */
    public function crawled_files( array $args, array $assoc_args ): void {
        $action = isset( $args[0] ) ? $args[0] : null;

        if ( $action === 'list' ) {
            $urls = CrawledFiles::getHashes();

            foreach ( $urls as $url ) {
                WP_CLI::log( $url );
            }
        }

        if ( $action === 'count' ) {
            $urls = CrawledFiles::getHashes();

            WP_CLI::log( (string) count( $urls ) );
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
     * @subcommand detected-files
     * @alias detected_files
     *
     * @param string[] $args Arguments after command
     * @param string[] $assoc_args Parameters after command
     */
    public function detected_files( array $args, array $assoc_args ): void {
        $action = isset( $args[0] ) ? $args[0] : null;

        if ( $action === 'list' ) {
            $urls = DetectedFiles::getCrawlablePaths();

            foreach ( $urls as $url ) {
                WP_CLI::log( $url );
            }
        }

        if ( $action === 'count' ) {
            $count = DetectedFiles::getTotalCrawlableURLs();

            WP_CLI::log( (string) $count );
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
     * @subcommand processed-site
     * @alias processed_site
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
     * @subcommand static-site
     * @alias static_site
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
     * @subcommand deploy-cache
     * @alias deploy_cache
     *
     * @param string[] $args Arguments after command
     * @param string[] $assoc_args Parameters after command
     */
    public function deploy_cache( array $args, array $assoc_args ): void {
        $action = isset( $args[0] ) ? $args[0] : null;

        if ( $action === 'list' ) {
            $paths = DeployCache::getPaths();

            foreach ( $paths as $url ) {
                WP_CLI::log( $url );
            }
        }

        if ( $action === 'count' ) {
            WP_CLI::log( (string) count( DeployCache::getTotal() ) );
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
     * @subcommand full-workflow
     * @alias full_workflow
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
     * @subcommand delete-all-cache
     * @alias delete_all_cache
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

        switch ( $action ) {
            case 'disable':
            case 'enable':
                $addon_slug = isset( $args[1] ) ? $args[1] : null;
                Controller::adminSetAddonState(
                    $addon_slug,
                    $action === 'enable'
                );
                break;
            case 'list':
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
                break;
            case 'toggle':
                $addon_slug = isset( $args[1] ) ? $args[1] : null;

                if ( ! $addon_slug ) {
                    throw new StaticDeployException(
                        'No addon slug given for CLI toggling'
                    );

                }

                // TODO Output details on if addon was enabled or disabled
                Controller::adminToggleAddon( $addon_slug );
                break;
            default:
                WP_CLI::error( 'Unknown subcommand: ' . $action );
        }
    }

    /**
     * Import options from WP2Static
     *
     * <option-name>
     *
     * Option name to import or --all to import
     * all available options.
     *
     * @subcommand import-wp2static-options
     * @alias import_wp2static_options
     *
     * @param string[] $args Arguments after command
     * @param string[] $assoc_args Parameters after command
     * @throws StaticDeployException
     */
    public function import_wp2static_options( array $args, array $assoc_args ): void {
        global $wpdb;

        $all = isset( $assoc_args['all'] );

        if ( ! $all && ! isset( $args [0] ) ) {
            WP_CLI::error( 'No option name given for import. Specify an option name or "--all".' );
        }

        $wp2static_table_name = $wpdb->prefix . 'wp2static_core_options';

        if ( ! $wpdb->get_var( "SHOW TABLES LIKE '$wp2static_table_name'" ) ) {
            WP_CLI::error( 'WP2Static options table not found.' );
        }

        if ( $all ) {
            foreach ( Options::optionSpecs() as $option_spec ) {
                Options::importFromWP2Static( $option_spec );
            }
            foreach ( S3\S3Options::getSpecs() as $option_spec ) {
                Options::importFromWP2Static( $option_spec );
            }
            return;
        }

        foreach ( $args as $option_name ) {
            $option_spec = Options::optionSpecs()[ $option_name ] ?? null;

            if ( ! $option_spec ) {
                $option_spec = S3\S3Options::getSpecs()[ $option_name ] ?? null;
            }

            if ( ! $option_spec ) {
                WP_CLI::error(
                    'Unknown option: ' . $option_name
                );
            }

            Options::importFromWP2Static( $option_spec );
        }
    }
}
