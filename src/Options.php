<?php

namespace StaticDeploy;

/*
    Simple interface to the options DB table


*/
class Options {

    /**
     * @var ?array<string, OptionSpec>
     */
    private static $cached_option_specs;

    /**
     * @var ?array<string, OptionSpec>
     */
    private static $cached_core_option_specs;

    public static function init(): void {
        self::createTable();
        self::seedOptions( self::optionSpecs() );
    }

    public static function getTableName(): string {
        return Db::getTableName( 'options' );
    }

    public static function createTable(): void {
        global $wpdb;

        $table_name = self::getTableName();

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            value VARCHAR(249) NOT NULL,
            blob_value BLOB,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        Db::ensureIndex(
            $table_name,
            'name',
            "CREATE UNIQUE INDEX name ON $table_name (name)"
        );
    }

    /**
     * All OptionSpecs including core options and addon options
     *
     * @return array<string, OptionSpec>
     */
    public static function getSpecs(): array {
        if ( self::$cached_option_specs ) {
            return self::$cached_option_specs;
        }

        self::$cached_option_specs = array_merge(
            self::optionSpecs(),
            Local\LocalOptions::getSpecs(),
            S3\S3Options::getSpecs(),
        );

        return self::$cached_option_specs;
    }

    /**
     * Core OptionSpecs only without addon options
     *
     * @return array<string, OptionSpec>
     */
    public static function optionSpecs(): array {
        if ( self::$cached_core_option_specs ) {
            return self::$cached_core_option_specs;
        }

        $default_admin_bar_menu_items = [
            'static-deploy-github' => [
                'href' => 'https://github.com/staticweb-io/static-deploy',
                'label' => 'GitHub',
            ],
            'static-deploy-news' => [
                'href' => 'https://staticweb.io/news/',
                'label' => 'News',
            ],
            'static-deploy-platform' => [
                'href' => 'https://staticweb.io/platform/',
                'label' => 'Platform',
            ],
        ];

        $specs = [
            new OptionSpec(
                'object',
                'adminBarMenuItems',
                '1',
                'Admin Bar Menu Items',
                '',
                json_encode( $default_admin_bar_menu_items ),
            ),
            new OptionSpec(
                'boolean',
                'detectCustomPostTypes',
                '1',
                'Detect Custom Post Types',
                'Include Custom Post Types in URL Detection.'
            ),
            new OptionSpec(
                'boolean',
                'detectPages',
                '1',
                'Detect Pages',
                'Include Pages in URL Detection.'
            ),
            new OptionSpec(
                'boolean',
                'detectPosts',
                '1',
                'Detect Posts',
                'Include Posts in URL Detection.'
            ),
            new OptionSpec(
                'boolean',
                'detectUploads',
                '1',
                'Detect Uploads',
                'Include Uploads in URL Detection.'
            ),
            new OptionSpec(
                'boolean',
                'queueJobOnPostSave',
                '1',
                'Post Save',
                'Queues a new job every time a Post or Page is saved.'
            ),
            new OptionSpec(
                'boolean',
                'queueJobOnPostDelete',
                '1',
                'Post Delete',
                'Queues a new job every time a Post or Page is deleted.'
            ),
            new OptionSpec(
                'string',
                'processQueueImmediately',
                '0',
                'Process Queue Immediately',
                'Begin processing the queue as soon as a job is added, ' .
                'without waiting for WP-Cron.',
                allowed_values: [ '0', '1', '2' ],
            ),
            new OptionSpec(
                'integer',
                'processQueueInterval',
                '0',
                'Process Queue Interval',
                'WP-Cron will attempt to process the job queue at this interval',
                min_value: 0,
            ),
            new OptionSpec(
                'boolean',
                'autoJobQueueDetection',
                '1',
                'Detect URLs',
                ''
            ),
            new OptionSpec(
                'boolean',
                'autoJobQueueCrawling',
                '1',
                'Crawl Site',
                ''
            ),
            new OptionSpec(
                'boolean',
                'autoJobQueuePostProcessing',
                '1',
                'Post-Process',
                ''
            ),
            new OptionSpec(
                'boolean',
                'autoJobQueueDeployment',
                '1',
                'Deploy',
                ''
            ),
            new OptionSpec(
                'boolean',
                'autoJobQueueDirectDeploy',
                '0',
                'Direct Deploy',
                ''
            ),
            new OptionSpec(
                'boolean',
                'autoJobQueueDirectDeployPost',
                '0',
                'Direct Deploy Post',
                ''
            ),
            new OptionSpec(
                'string',
                'basicAuthUser',
                '',
                'Basic Auth User',
                'Username for basic authentication.'
            ),
            new OptionSpec(
                'string',
                'deploymentURL',
                'https://example.com',
                'Deployment URL',
                'URL your static site will be hosted at.'
            ),
            new OptionSpec(
                'password',
                'basicAuthPassword',
                '',
                'Basic Auth Password',
                'Password for basic authentication.'
            ),
            new OptionSpec(
                'string',
                'completionEmail',
                '',
                'Completion Email',
                'Email to send deployment completion notification to.'
            ),
            new OptionSpec(
                'string',
                'completionWebhook',
                '',
                'Completion Webhook',
                'Webhook to send deployment completion notification to.'
            ),
            new OptionSpec(
                'string',
                'completionWebhookMethod',
                'POST',
                'Completion Webhook Method',
                'How to send completion webhook payload (GET|POST).'
            ),

            // Advanced options
            new OptionSpec(
                'integer',
                'crawlConcurrency',
                '4',
                'Crawl Concurrency',
                'The maximum number of files that will be crawled at the same time.',
                min_value: 1,
            ),
            new OptionSpec(
                'string',
                'crawledSitePath',
                'static-deploy-crawled-site',
                'Crawled Site Path',
                'Path to the crawled site files.'
            ),
            new OptionSpec(
                'string',
                'processedSitePath',
                'static-deploy-processed-site',
                'Processed Site Path',
                'Path to the processed site files.'
            ),
            new OptionSpec(
                'array',
                'pathsToIgnore',
                '1',
                'Paths to Ignore',
                'Path matching these patterns will be ignored.' .
                ' Matches are not case-sensitive.' .
                ' Glob syntax is supported via ' .
                '<a href="' .
                'https://github.com/PHLAK/Splat?tab=readme-ov-file#matching-expressions' .
                '">Splat</a>.',
                implode(
                    "\n",
                    [
                        '**.bat',
                        '**.crt',
                        '**.data', // et-cache puts these in wp-content/et-cache
                        '**.DS_Store',
                        '**.git',
                        '**.idea',
                        '**.ini',
                        '**.less',
                        '**.map',
                        '**.md',
                        '**.mo',
                        '**.php*', // Don't crawl files like wp-config.php.save
                        '**.phtml',
                        '**.po',
                        '**.pot',
                        '**.scss',
                        '**.sh',
                        '**.sql',
                        '**.tar.gz',
                        '**.tpl',
                        '**.yarn',
                        '**.zip',
                        '__MACOSX',
                        '.babelrc',
                        '.git',
                        '.gitignore',
                        '.gitkeep',
                        '.htaccess',
                        '.php',
                        '.svn',
                        '.travis.yml',
                        'backwpup',
                        'bower_components',
                        'bower.json',
                        'composer.json',
                        'composer.lock',
                        'config.rb',
                        'current-export',
                        'Dockerfile',
                        'gulpfile.js',
                        'latest-export',
                        'LICENSE',
                        'Makefile',
                        'node_modules',
                        'package.json',
                        'pb_backupbuddy',
                        'previous-export',
                        'README',
                        '/tests/',
                        'thumbs.db',
                        'tinymce',
                        'wc-logs',
                        'wpallexport',
                        'wpallimport',
                        'wp-admin',
                        'wp-content/plugins/*/vendor',
                        '*-crawled-site',
                        '*-processed-site',
                        'yarn-error.log',
                        'yarn.lock',
                    ]
                )
            ),
            new OptionSpec(
                'array',
                'hostsToRewrite',
                '1',
                'Hosts to Rewrite',
                'Hosts to rewrite to the deployment URL.',
                'localhost'
            ),
            new OptionSpec(
                'integer',
                'maxLogRows',
                '500',
                'Max Log Rows',
                'The maximum number of log rows to retain. 0 means there is no limit.',
                min_value: 0,
            ),
            new OptionSpec(
                'boolean',
                'skipURLRewrite',
                '0',
                'Skip URL Rewrite',
                'Don\'t rewrite any URLs. This may give a slight speed-up when the'
                . ' deployment URL is the same as WordPress\'s URL.'
            ),
        ];

        $ret = [];
        foreach ( $specs as $s ) {
            $ret[ $s->name ] = $s;
        }
        self::$cached_option_specs = $ret;
        return $ret;
    }

    /**
     * Seed options
     *
     * @param array<string, OptionSpec> $option_specs
     */
    public static function seedOptions(
        array $option_specs,
    ): void {
        global $wpdb;

        $table_name = self::getTableName();

        $query_string =
            "INSERT IGNORE INTO $table_name (name, value, blob_value)
VALUES (%s, %s, %s);";

        foreach ( $option_specs as $os ) {
            $query = $wpdb->prepare(
                $query_string,
                $os->name,
                $os->default_value,
                $os->default_blob_value
            );
            $wpdb->query( $query );
        }
    }

    public static function getOption(
        OptionSpec $option_spec,
    ): OptionData {
        $name = $option_spec->name;
        if ( STATIC_DEPLOY_DEBUG ) {
            WsLog::d( "Getting value of option: $name", );
        }

        global $wpdb;

        $table_name = self::getTableName();

        $sql = $wpdb->prepare(
            "SELECT value,blob_value FROM $table_name WHERE name=%s",
            $name
        );

        $row = $wpdb->get_row( $sql );

        if ( $row ) {
            return new OptionData(
                $option_spec,
                $row->blob_value,
                $row->value,
            );
        }
        return new OptionData(
            $option_spec,
            null,
            null,
        );
    }

    /**
     * Get option value by name
     * Works for core options, but doesn't recognize addon options.
     *
     * @throws StaticDeployException
     * @return string option value
     */
    public static function getValue( string $name ): string {
        $option_spec = self::optionSpecs()[ $name ];

        if ( ! $option_spec ) {
            WsLog::d( "Unknown option: $name" );
            throw new StaticDeployException( "Unknown option: $name" );
        }

        return self::getOption( $option_spec )->value;
    }

    /**
     * Get option BLOB value
     *
     * @throws StaticDeployException
     * @return string option BLOB value
     */
    public static function getBlobValue( string $name ): string {
        if ( STATIC_DEPLOY_DEBUG ) {
            WsLog::d( "Getting blob value of option: $name" );
        }

        $option_spec = self::optionSpecs()[ $name ];

        if ( ! $option_spec ) {
            throw WsLog::ex( "Unknown option: $name" );
        }

        return self::getOption( $option_spec )->blob_value;
    }

    /**
     * @return array<string>
     */
    public static function getLineDelimitedBlobValue( string $name ): array {
        $vals = preg_split(
            '/\r\n|\r|\n/',
            self::getBlobValue( $name )
        );

        if ( ! $vals ) {
            return [];
        }

        return $vals;
    }

    /**
     * Get option default BLOB value
     *
     * @throws StaticDeployException
     * @return string option default BLOB value
     */
    public static function getdefault_blob_value( string $name ): string {
        $val = self::optionSpecs()[ $name ]->default_blob_value;
        return $val ? $val : '';
    }

    /**
     * @return array<string>
     */
    public static function getDefaultLineDelimitedBlobValue( string $name ): array {
        $vals = preg_split(
            '/\r\n|\r|\n/',
            self::getdefault_blob_value( $name )
        );

        if ( ! $vals ) {
            return [];
        }

        return $vals;
    }

    /**
     * Get all options (value, description, label, etc)
     *
     * @return array<string, OptionData> array of option name to option object
     */
    public static function getAll(
        ?array $option_specs = null
    ) {
        global $wpdb;

        if ( $option_specs === null ) {
            $option_specs = self::optionSpecs();
        }

        $table_name = self::getTableName();

        $sql = "SELECT name, value, blob_value FROM $table_name";

        $options = $wpdb->get_results( $sql );

        $options_map = [];
        foreach ( $options as $opt ) {
            $options_map[ $opt->name ] = $opt;
        }

        $ret = [];
        foreach ( $option_specs as $opt_spec ) {
            $name = $opt_spec->name;
            $opt = $options_map[ $name ];
            if ( $opt ) {
                $opt = new OptionData(
                    $opt_spec,
                    $opt->blob_value,
                    $opt->value,
                );
            } else {
                $opt = new OptionData(
                    $opt_spec,
                    $opt_spec->default_blob_value,
                    $opt_spec->default_value,
                );
            }
            $ret[ $name ] = $opt;
        }

        return $ret;
    }

    /*
     * Naive encypting/decrypting
     *
     * @throws StaticDeployException
     */
    public static function encrypt_decrypt( string $action, string $str ): string {
        $encrypt_method = 'AES-256-CBC';

        /**
         * @var string $secret_key
         */
        $secret_key =
            defined( 'AUTH_KEY' ) ?
            constant( 'AUTH_KEY' ) :
            'LC>_cVZv34+W.P&_8d|ejfr]d31h)J?z5n(LB6iY=;P@?5/qzJSyB3qctr,.D$[L';
        /**
         * @var string $secret_iv
         */
        $secret_iv =
            defined( 'AUTH_SALT' ) ?
            constant( 'AUTH_SALT' ) :
            'ec64SSHB{8|AA_ThIIlm:PD(Z!qga!/Dwll 4|i.?UkC§NNO}z?{Qr/q.KpH55K9';

        $key = hash( 'sha256', $secret_key );
        $variate = substr( hash( 'sha256', $secret_iv ), 0, 32 );
        $hex_key = (string) hex2bin( $key );
        $hex_iv = (string) hex2bin( $variate );

        if ( $action === 'decrypt' ) {
            return (string) openssl_decrypt(
                base64_decode( $str ),
                $encrypt_method,
                $hex_key,
                0,
                $hex_iv
            );
        }

        $output = openssl_encrypt( $str, $encrypt_method, $hex_key, 0, $hex_iv );

        return base64_encode( (string) $output );
    }

    /**
     * Save options saved from the admin UI
     *
     * @param array<OptionData> $option_specs
     */
    public static function saveFromAdmin(
        array $option_specs,
    ): void {
        foreach ( $option_specs as $option_spec ) {
            $name = $option_spec->name;
            $v = $_POST[ $name ] ?? '';
            OptionData::fromUserInput( $option_spec, $v )->save();
        }
    }

    /**
     * Save all options POST'ed via UI
     */
    public static function savePosted( string $screen = 'core' ): void {
        switch ( $screen ) {
            case 'core':
                $names = [
                    'basicAuthPassword',
                    'basicAuthUser',
                    'completionEmail',
                    'completionWebhook',
                    'completionWebhookMethod',
                    'deploymentURL',
                    'detectCustomPostTypes',
                    'detectPages',
                    'detectPosts',
                    'detectUploads',
                ];
                $option_specs = array_intersect_key(
                    self::optionSpecs(),
                    array_flip( $names )
                );
                self::saveFromAdmin( $option_specs );
                break;
            case 'jobs':
                $names = [
                    'queueJobOnPostSave',
                    'queueJobOnPostDelete',
                    'processQueueImmediately',
                    'processQueueInterval',
                    'autoJobQueueDetection',
                    'autoJobQueueCrawling',
                    'autoJobQueuePostProcessing',
                    'autoJobQueueDeployment',
                    'autoJobQueueDirectDeploy',
                    'autoJobQueueDirectDeployPost',
                ];
                $option_specs = array_intersect_key(
                    self::optionSpecs(),
                    array_flip( $names )
                );
                self::saveFromAdmin( $option_specs );

                WPCron::setRecurringEvent( self::getValue( 'processQueueInterval' ) );
                break;
            case 'advanced':
                $names = [
                    'adminBarMenuItems',
                    'crawlConcurrency',
                    'crawledSitePath',
                    'hostsToRewrite',
                    'maxLogRows',
                    'pathsToIgnore',
                    'processedSitePath',
                    'skipURLRewrite',
                ];
                $option_specs = array_intersect_key(
                    self::optionSpecs(),
                    array_flip( $names )
                );
                self::saveFromAdmin( $option_specs );
                break;
        }
    }

    /**
     * Save individual option
     *
     * @param mixed $value Updated option value
     */
    public static function save( string $name, $value ): void {
        global $wpdb;

        $table_name = self::getTableName();

        $wpdb->update(
            $table_name,
            [ 'value' => $value ],
            [ 'name' => $name ]
        );
    }

    /**
     * Import option value from WP2Static
     */
    public static function importFromWP2Static(
        OptionSpec $option_spec,
    ): void {
        global $wpdb;

        if ( $option_spec->wp2static_table ) {
            $wp2static_table_name = $wpdb->prefix . $option_spec->wp2static_table;
        } else {
            $wp2static_table_name = $wpdb->prefix . 'wp2static_core_options';
        }

        $wp2static_name = $option_spec->wp2static_name ?? $option_spec->name;

        $blob = false;
        if ( $option_spec->type === 'array' ) {
            $blob = true;
        }

        if ( $blob ) {

            $wp2static_option = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT value, blob_value FROM $wp2static_table_name WHERE name = %s;",
                    $wp2static_name
                )
            );

            if ( ! $wp2static_option ) {
                WsLog::l( "Option $option_spec->name not found in WP2Static options" );
                return;
            }

            $opt = new OptionData(
                $option_spec,
                $wp2static_option->blob_value,
                $wp2static_option->value,
            );

            WsLog::l(
                "Imported option $option_spec->name from WP2Static" .
                ' with value: ' . $opt->value .
                ' and blob value: ' . $opt->blob_value
            );

            $table_name = self::getTableName();

            $wpdb->update(
                $table_name,
                [
                    'blob_value' => $opt->blob_value,
                    'value' => $opt->value,
                ],
                [ 'name' => $option_spec->name ]
            );
        } else {
            $wp2static_option = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT value FROM $wp2static_table_name WHERE name = %s;",
                    $wp2static_name
                )
            );

            if ( ! $wp2static_option ) {
                WsLog::l( "Option $option_spec->name not found in WP2Static options" );
                return;
            }

            $opt = new OptionData(
                $option_spec,
                null,
                $wp2static_option->value,
            );

            WsLog::l(
                "Imported option $option_spec->name from WP2Static" .
                ' with value: ' . $opt->value
            );

            $table_name = self::getTableName();

            $wpdb->update(
                $table_name,
                [ 'value' => $opt->value ],
                [ 'name' => $option_spec->name ]
            );
        }
    }
}
