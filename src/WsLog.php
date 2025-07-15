<?php

namespace WP2Static;

// TODO: add option in UI to also write to PHP error_log
class WsLog {
    private static bool $debug_logging;

    public static function getTableName(): string {
        return Controller::getTableName( 'log' );
    }

    public static function createTable(): void {
        global $wpdb;

        $table_name = self::getTableName();

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            level ENUM('debug', 'info', 'warn', 'error') NOT NULL DEFAULT 'info',
            log TEXT NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Log a debug message if debug logging is enabled
     */
    public static function d( string $text ): void {
        self::l( $text, 'debug' );
    }

    /**
     * Log an error message and return a throwable exception
     *
     * @param string $message
     * @param int $code (default 0)
     * @param \Throwable $previous (default null)
     * @return \WP2Static\WP2StaticException
     */
    public static function ex(
        string $message,
        int $code = 0,
        ?Throwable $previous = null
    ): WP2StaticException {
        self::l( $message, 'error' );
        return new WP2StaticException(
            $message,
            $code,
            $previous
        );
    }

    /**
     * Log a message at the specified level
     *
     * @param string $text The message to log
     * @param string $level The level to log at. One of
     *   [ 'debug', 'info', 'warn', 'error' ]
     * @param bool $option_lookups Whether option values
     *   can be looked up. Option value functions set this
     *   to false to prevent infinite recursion.
     */
    public static function l(
        string $text,
        string $level = 'info',
        bool $option_lookups = true,
    ): void {
        if ( $level === 'debug' ) {
            if ( $option_lookups && ! isset( self::$debug_logging ) ) {
                self::$debug_logging = Options::getValue( 'debugLogging' );
            }

            if ( ( ! isset( self::$debug_logging )
            || ! self::$debug_logging ) && defined( 'WP_CLI' ) ) {
                $date = current_time( 'c' );
                $colorized = \WP_CLI::colorize( "%W[$date] %n$text" );
                // --debug will show debug messages even if
                // debugLogging is not enabled
                \WP_CLI::debug( $colorized );
                return;
            }
        }

        global $wpdb;

        $table_name = self::getTableName();

        $wpdb->insert(
            $table_name,
            [
                'log' => $text,
                'level' => $level,
            ]
        );

        if ( defined( 'WP_CLI' ) ) {
            $date = current_time( 'c' );
            $colorized = \WP_CLI::colorize( "%W[$date] %n$text" );
            switch ( $level ) {
                case 'debug':
                    // WP_CLI hides debug level messages unless
                    // --debug is passed, so we use ::log when
                    // debugLogging is enabled.
                    \WP_CLI::log( $colorized );
                    break;
                case 'error':
                    \WP_CLI::error_multi_line( [ $colorized ] );
                    break;
                case 'info':
                    \WP_CLI::log( $colorized );
                    break;
                case 'warn':
                    \WP_CLI::warning( $colorized );
                    break;
                default:
                    self::ex( "Invalid log level: $level" );
                    break;
            }
        }
    }

    public static function w( string $text ): void {
        self::l( $text, 'warn' );
    }

    /**
     * Log multiple lines at once
     *
     * @param string[] $lines List of lines to log
     */
    public static function lines( array $lines ): void {
        global $wpdb;

        $table_name = self::getTableName();

        $query = "INSERT INTO $table_name (log) VALUES " .
            implode(
                ',',
                array_fill( 0, count( $lines ), '(%s)' )
            );

        $wpdb->query( $wpdb->prepare( $query, $lines ) );
    }

    /**
     * Delete oldest logs
     *
     * @return int Number of rows deleted
     */
    public static function deleteOldLogs(): int {
        global $wpdb;

        $table_name = self::getTableName();

        $max_id = $wpdb->get_var( "SELECT MAX(id) FROM $table_name" );

        // When near the max range of MEDIUMINT SIGNED, we need to
        // truncate the table and reset the id sequence.
        if ( $max_id > 8300000 ) {
            $wpdb->query( "TRUNCATE TABLE $table_name" );
            $wpdb->query( "ALTER TABLE $table_name AUTO_INCREMENT = 1" );
            self::l( 'Truncated log table to avoid AUTO_INCREMENT overflow' );
            return 0;
        }

        $max_log_rows = intval( Options::getValue( 'maxLogRows' ) );

        if ( $max_log_rows < 1 ) {
            return 0;
        }

        $total_logs = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );

        if ( $total_logs > $max_log_rows ) {
            $wpdb->query(
                "
                DELETE FROM $table_name
                WHERE id NOT IN (
                    SELECT id FROM (
                        SELECT id FROM $table_name ORDER BY id DESC LIMIT $max_log_rows
                    ) AS sub
                )
            "
            );
        }

        return $total_logs;
    }

    /**
     * Get all log lines
     *
     * @return mixed[] array of Log items
     */
    public static function getAll(): array {
        self::deleteOldLogs();

        global $wpdb;
        $logs = [];

        $table_name = self::getTableName();

        $logs = $wpdb->get_results( "SELECT time, log FROM $table_name ORDER BY id DESC" );

        return $logs;
    }

    /**
     * Poll latest log lines
     */
    public static function poll(): string {
        self::deleteOldLogs();

        global $wpdb;

        $table_name = self::getTableName();

        $logs = $wpdb->get_col(
            "SELECT CONCAT_WS(': ', time, log)
            FROM $table_name
            ORDER BY id DESC"
        );

        $logs = implode( PHP_EOL, $logs );

        return $logs;
    }

    /**
     *  Clear Log via truncation
     */
    public static function truncate(): void {
        global $wpdb;

        $table_name = self::getTableName();

        $wpdb->query( "TRUNCATE TABLE $table_name" );

        self::l( 'Deleted all Logs' );
    }
}
