<?php

namespace WP2Static;

// TODO: add option in UI to also write to PHP error_log
class WsLog {
    public static function createTable() : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_log';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            log TEXT NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function l( string $text ) : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_log';

        $wpdb->insert(
            $table_name,
            [
                'log' => $text,
            ]
        );

        if ( defined( 'WP_CLI' ) ) {
            $date = current_time( 'c' );
            \WP_CLI::log(
                \WP_CLI::colorize( "%W[$date] %n$text" )
            );
        }
    }

    public static function w( string $text ) : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_log';

        $wpdb->insert(
            $table_name,
            [
                'log' => $text,
            ]
        );

        if ( defined( 'WP_CLI' ) ) {
            $date = current_time( 'c' );
            \WP_CLI::warning(
                \WP_CLI::colorize( "%W[$date] %n$text" )
            );
        }
    }

    /**
     * Log multiple lines at once
     *
     * @param string[] $lines List of lines to log
     */
    public static function lines( array $lines ) : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_log';

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
    public static function deleteOldLogs() : int {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_log';

        $max_id = $wpdb->get_var( "SELECT MAX(id) FROM $table_name" );

        // When near the max range of MEDIUMINT SIGNED, we need to
        // truncate the table and reset the id sequence.
        if ( $max_id > 8300000 ) {
            $wpdb->query( "TRUNCATE TABLE $table_name" );
            $wpdb->query( "ALTER TABLE $table_name AUTO_INCREMENT = 1" );
            WsLog::l( 'Truncated log table to avoid AUTO_INCREMENT overflow' );
            return 0;
        }

        $max_log_rows = intval( CoreOptions::getValue('maxLogRows') );

        if ( $max_log_rows < 1 ) {
            return 0;
        }

        $total_logs = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );

        if ( $total_logs > $max_log_rows ) {
            $wpdb->query( "
                DELETE FROM $table_name
                WHERE id NOT IN (
                    SELECT id FROM (
                        SELECT id FROM $table_name ORDER BY id DESC LIMIT $max_log_rows
                    ) AS sub
                )
            " );
        }

        return $total_logs;
    }

    /**
     * Get all log lines
     *
     * @return mixed[] array of Log items
     */
    public static function getAll() : array {
        self::deleteOldLogs();

        global $wpdb;
        $logs = [];

        $table_name = $wpdb->prefix . 'wp2static_log';

        $logs = $wpdb->get_results( "SELECT time, log FROM $table_name ORDER BY id DESC" );

        return $logs;
    }

    /**
     * Poll latest log lines
     */
    public static function poll() : string {
        self::deleteOldLogs();
        
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_log';

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
    public static function truncate() : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_log';

        $wpdb->query( "TRUNCATE TABLE $table_name" );

        self::l( 'Deleted all Logs' );
    }
}

