<?php

namespace WP2Static;

/*
    Simple interface to the options DB table


*/
class Options {

    /**
     * @var ?array<string, OptionSpec>
     */
    private static $cached_option_specs = null;

    public static function init(): void {
        self::createTable();
        self::seedOptions( self::optionSpecs() );
    }

    public static function getTableName(): string {
        return Controller::getTableName( 'options' );
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

        Controller::ensureIndex(
            $table_name,
            'name',
            "CREATE UNIQUE INDEX name ON $table_name (name)"
        );
    }

    /**
     * @return array<string, OptionSpec>
     */
    public static function optionSpecs(): array {
        if ( self::$cached_option_specs ) {
            return self::$cached_option_specs;
        }

        $specs = [
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
                'boolean',
                'processQueueImmediately',
                '0',
                'Process Queue Immediately',
                'Begin processing the queue as soon as a job is added, without waiting for WP-Cron.'
            ),
            new OptionSpec(
                'integer',
                'processQueueInterval',
                '0',
                'Process Queue Interval',
                'WP-Cron will attempt to process the job queue at this interval'
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
                'The maximum number of files that will be crawled at the same time.'
            ),
            new OptionSpec(
                'string',
                'crawledSitePath',
                'wp2static-crawled-site',
                'Crawled Site Path',
                'Path to the crawled site files.'
            ),
            new OptionSpec(
                'string',
                'processedSitePath',
                'wp2static-processed-site',
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
                        '**.php',
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
                        'static-html-output-plugin',
                        '/tests/',
                        'thumbs.db',
                        'tinymce',
                        'wc-logs',
                        'wpallexport',
                        'wpallimport',
                        'wp-admin',
                        'wp-content/plugins/*/vendor',
                        'wp-content/plugins/wp2static',
                        'wp-static-html-output', // exclude earlier version exports
                        'wp2static-addon',
                        'wp2static-crawled-site',
                        'wp2static-processed-site',
                        'wp2static-working-files',
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
                'boolean',
                'debugLogging',
                '0',
                'Debug Logging',
                'Enable debug logging.',
            ),
            new OptionSpec(
                'integer',
                'maxLogRows',
                '500',
                'Max Log Rows',
                'The maximum number of log rows to retain. 0 means there is no limit.',
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

    /**
     * Get option value
     *
     * @throws WP2StaticException
     * @return string option value
     */
    public static function getValue( string $name ): string {
        $option_lookups = ( $name !== 'debugLogging' );
        WsLog::l(
            "Getting value of option: $name",
            $level = 'debug',
            $option_lookups = $option_lookups,
        );

        global $wpdb;

        $opt_spec = self::optionSpecs()[ $name ];

        if ( ! $opt_spec ) {
            WsLog::l(
                "Unknown option: $name",
                $level = 'error',
                $option_lookups = $option_lookups,
            );
            throw new WP2StaticException( "Unknown option: $name" );
        }

        $table_name = self::getTableName();

        $sql = $wpdb->prepare(
            "SELECT value FROM $table_name WHERE" . ' name = %s LIMIT 1',
            $name
        );

        $option_value = $wpdb->get_var( $sql );

        if ( ! is_string( $option_value ) ) {
            $option_value = (string) $opt_spec->default_value;
        }

        if ( $opt_spec->type === 'password' ) {
            $option_value = self::encrypt_decrypt( 'decrypt', $option_value );
        }

        // default deploymentURL is '/', else remove trailing slash
        if ( $name === 'deploymentURL' ) {
            if ( $option_value !== '/' ) {
                $option_value = untrailingslashit( $option_value );
            }
        }

        $option_value = apply_filters( (string) $opt_spec->filter_name, $option_value );

        return $option_value;
    }

    /**
     * Get option BLOB value
     *
     * @throws WP2StaticException
     * @return string option BLOB value
     */
    public static function getBlobValue( string $name ): string {
        WsLog::d( "Getting blob value of option: $name" );

        global $wpdb;

        $table_name = self::getTableName();

        $sql = $wpdb->prepare(
            "SELECT blob_value FROM $table_name WHERE" . ' name = %s LIMIT 1',
            $name
        );

        $option_value = $wpdb->get_var( $sql );

        if ( ! is_string( $option_value ) ) {
            $os = self::optionSpecs()[ $name ];
            if ( ! $os ) {
                return '';
            }
            $option_value = (string) $os['default_blob_value'];
        }

        return $option_value;
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
     * @throws WP2StaticException
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
    public static function getAll() {
        global $wpdb;

        $table_name = self::getTableName();

        $sql = "SELECT name, value, blob_value FROM $table_name";

        $options = $wpdb->get_results( $sql );

        $options_map = [];
        foreach ( $options as $opt ) {
            $options_map[ $opt->name ] = $opt;
        }

        $ret = [];
        foreach ( self::optionSpecs() as $opt_spec ) {
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
     * @throws WP2StaticException
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
                (string) base64_decode( $str ),
                $encrypt_method,
                $hex_key,
                0,
                $hex_iv
            );
        }

        $output = openssl_encrypt( $str, $encrypt_method, $hex_key, 0, $hex_iv );

        return (string) base64_encode( (string) $output );
    }

    /**
     * Save options saved from the admin UI
     *
     * @param array<OptionData> $option_specs
     */
    public static function saveFromAdmin(
        array $option_specs,
    ): void {
        global $wpdb;

        $table_name = self::getTableName();

        foreach ( $option_specs as $option_spec ) {
            $name = $option_spec->name;
            $v = isset( $_POST[ $name ] ) ? $_POST[ $name ] : '';
            $column = 'value';

            switch ( $option_spec->type ) {
                case 'array':
                    $column = 'blob_value';
                    $value = preg_replace(
                        '/^\s+|\s+$/m',
                        '',
                        strval( $v )
                    );
                    break;
                case 'boolean':
                    $value = isset( $_POST[ $name ] ) ? '1' : '0';
                    break;
                case 'integer':
                    $value = (string) intval( $v );
                    break;
                case 'password':
                    $value = sanitize_text_field( strval( $v ) );
                    $value = self::encrypt_decrypt( 'encrypt', $value );
                    break;
                case 'string':
                    $value = sanitize_text_field( strval( $v ) );
                    break;
                case 'url':
                    $value = esc_url_raw( strval( $v ) );
                    break;
                default:
                    throw WsLog::ex(
                        'Unknown option type: ' . $option_spec->type
                        . ' for option: ' . $option_spec->name
                    );
            }

            $wpdb->update(
                $table_name,
                [ $column => $value ],
                [ 'name' => $name ]
            );
        }
    }

    /**
     * Save all options POST'ed via UI
     */
    public static function savePosted( string $screen = 'core' ): void {
        global $wpdb;

        $table_name = self::getTableName();

        switch ( $screen ) {
            case 'core':
                $wpdb->update(
                    $table_name,
                    [ 'value' => isset( $_POST['detectCustomPostTypes'] ) ? 1 : 0 ],
                    [ 'name' => 'detectCustomPostTypes' ]
                );

                $wpdb->update(
                    $table_name,
                    [ 'value' => isset( $_POST['detectPosts'] ) ? 1 : 0 ],
                    [ 'name' => 'detectPosts' ]
                );

                $wpdb->update(
                    $table_name,
                    [ 'value' => isset( $_POST['detectPages'] ) ? 1 : 0 ],
                    [ 'name' => 'detectPages' ]
                );

                $wpdb->update(
                    $table_name,
                    [ 'value' => isset( $_POST['detectUploads'] ) ? 1 : 0 ],
                    [ 'name' => 'detectUploads' ]
                );

                $wpdb->update(
                    $table_name,
                    [
                        'value' =>
                        esc_url_raw( strval( filter_input( INPUT_POST, 'deploymentURL' ) ) ),
                    ],
                    [ 'name' => 'deploymentURL' ]
                );

                $wpdb->update(
                    $table_name,
                    [
                        'value' =>
                        sanitize_text_field(
                            strval( filter_input( INPUT_POST, 'basicAuthUser' ) )
                        ),
                    ],
                    [ 'name' => 'basicAuthUser' ]
                );

                $wpdb->update(
                    $table_name,
                    [
                        'value' =>
                        self::encrypt_decrypt(
                            'encrypt',
                            sanitize_text_field(
                                strval( filter_input( INPUT_POST, 'basicAuthPassword' ) )
                            )
                        ),
                    ],
                    [ 'name' => 'basicAuthPassword' ]
                );

                $wpdb->update(
                    $table_name,
                    [
                        'value' =>
                        sanitize_text_field(
                            strval( filter_input( INPUT_POST, 'completionEmail' ) )
                        ),
                    ],
                    [ 'name' => 'completionEmail' ]
                );

                $wpdb->update(
                    $table_name,
                    [
                        'value' =>
                        esc_url_raw( strval( filter_input( INPUT_POST, 'completionWebhook' ) ) ),
                    ],
                    [ 'name' => 'completionWebhook' ]
                );

                $wpdb->update(
                    $table_name,
                    [
                        'value' =>
                        sanitize_text_field(
                            strval( filter_input( INPUT_POST, 'completionWebhookMethod' ) )
                        ),
                    ],
                    [ 'name' => 'completionWebhookMethod' ]
                );

                break;
            case 'jobs':
                $queue_on_post_save = isset( $_POST['queueJobOnPostSave'] ) ? 1 : 0;
                $queue_on_post_delete = isset( $_POST['queueJobOnPostDelete'] ) ? 1 : 0;
                $process_queue_immediately = isset( $_POST['processQueueImmediately'] )
                ? intval( $_POST['processQueueImmediately'] )
                    : 0;

                $wpdb->update(
                    $table_name,
                    [ 'value' => $queue_on_post_save ],
                    [ 'name' => 'queueJobOnPostSave' ]
                );

                $wpdb->update(
                    $table_name,
                    [ 'value' => $queue_on_post_delete ],
                    [ 'name' => 'queueJobOnPostDelete' ]
                );

                $wpdb->update(
                    $table_name,
                    [ 'value' => $process_queue_immediately ],
                    [ 'name' => 'processQueueImmediately' ]
                );

                /**
                 * @var int $process_queue_interval
                 */
                $process_queue_interval =
                    isset( $_POST['processQueueInterval'] ) ?
                    $_POST['processQueueInterval'] : 0;

                $wpdb->update(
                    $table_name,
                    [ 'value' => $process_queue_interval ],
                    [ 'name' => 'processQueueInterval' ]
                );

                WPCron::setRecurringEvent( $process_queue_interval );

                $wpdb->update(
                    $table_name,
                    [ 'value' => isset( $_POST['autoJobQueueDetection'] ) ? 1 : 0 ],
                    [ 'name' => 'autoJobQueueDetection' ]
                );

                $wpdb->update(
                    $table_name,
                    [ 'value' => isset( $_POST['autoJobQueueCrawling'] ) ? 1 : 0 ],
                    [ 'name' => 'autoJobQueueCrawling' ]
                );

                $wpdb->update(
                    $table_name,
                    [ 'value' => isset( $_POST['autoJobQueuePostProcessing'] ) ? 1 : 0 ],
                    [ 'name' => 'autoJobQueuePostProcessing' ]
                );

                $wpdb->update(
                    $table_name,
                    [ 'value' => isset( $_POST['autoJobQueueDeployment'] ) ? 1 : 0 ],
                    [ 'name' => 'autoJobQueueDeployment' ]
                );

                $wpdb->update(
                    $table_name,
                    [ 'value' => isset( $_POST['autoJobQueueDirectDeploy'] ) ? 1 : 0 ],
                    [ 'name' => 'autoJobQueueDirectDeploy' ]
                );

                $wpdb->update(
                    $table_name,
                    [ 'value' => isset( $_POST['autoJobQueueDirectDeployPost'] ) ? 1 : 0 ],
                    [ 'name' => 'autoJobQueueDirectDeployPost' ]
                );

                break;
            case 'advanced':
                $crawl_concurrency = intval( $_POST['crawlConcurrency'] );
                $wpdb->update(
                    $table_name,
                    [ 'value' => $crawl_concurrency < 1 ? 1 : $crawl_concurrency ],
                    [ 'name' => 'crawlConcurrency' ]
                );

                $wpdb->update(
                    $table_name,
                    [
                        'value' =>
                        sanitize_text_field(
                            strval( filter_input( INPUT_POST, 'crawledSitePath' ) )
                        ),
                    ],
                    [ 'name' => 'crawledSitePath' ]
                );

                $wpdb->update(
                    $table_name,
                    [
                        'value' =>
                        sanitize_text_field(
                            strval( filter_input( INPUT_POST, 'processedSitePath' ) )
                        ),
                    ],
                    [ 'name' => 'processedSitePath' ]
                );

                $paths_to_ignore = preg_replace(
                    '/^\s+|\s+$/m',
                    '',
                    strval( filter_input( INPUT_POST, 'pathsToIgnore' ) )
                );
                $wpdb->update(
                    $table_name,
                    [ 'blob_value' => $paths_to_ignore ],
                    [ 'name' => 'pathsToIgnore' ]
                );

                $hosts_to_rewrite = preg_replace(
                    '/^\s+|\s+$/m',
                    '',
                    strval( filter_input( INPUT_POST, 'hostsToRewrite' ) )
                );
                $wpdb->update(
                    $table_name,
                    [ 'blob_value' => $hosts_to_rewrite ],
                    [ 'name' => 'hostsToRewrite' ]
                );

                $debug_logging = intval( $_POST['debugLogging'] );
                $wpdb->update(
                    $table_name,
                    [ 'value' => $debug_logging < 0 ? 0 : $debug_logging ],
                    [ 'name' => 'debugLogging' ]
                );

                $max_log_rows = intval( $_POST['maxLogRows'] );
                $wpdb->update(
                    $table_name,
                    [ 'value' => $max_log_rows < 0 ? 0 : $max_log_rows ],
                    [ 'name' => 'maxLogRows' ]
                );

                $wpdb->update(
                    $table_name,
                    [ 'value' => isset( $_POST['skipURLRewrite'] ) ? 1 : 0 ],
                    [ 'name' => 'skipURLRewrite' ]
                );
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

        // TODO: some validation on save types
        $wpdb->update(
            $table_name,
            [ 'value' => $value ],
            [ 'name' => $name ]
        );
    }
}
