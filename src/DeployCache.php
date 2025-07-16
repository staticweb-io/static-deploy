<?php

namespace StaticDeploy;

class DeployCache {

    const DEFAULT_NAMESPACE = 'default';


    public static function getTableName(): string {
        return Controller::getTableName( 'deploy_cache' );
    }

    public static function createTable(): void {
        global $wpdb;

        $table_name = self::getTableName();

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            path_hash CHAR(32) NOT NULL,
            path VARCHAR(2083) NOT NULL,
            file_hash CHAR(32) NOT NULL,
            namespace VARCHAR(128) NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        Controller::ensureIndex(
            $table_name,
            'path_hash_ns_idx',
            "CREATE UNIQUE INDEX path_hash_ns_idx ON $table_name (path_hash, namespace)"
        );
    }

    public static function addFile(
        string $local_path,
        string $ns = self::DEFAULT_NAMESPACE,
        ?string $file_hash = null
    ): void {
        global $wpdb;

        $table_name = self::getTableName();

        $post_processed_dir = ProcessedSite::getPath();

        $deployed_file = $post_processed_dir . $local_path;

        $path_hash = md5( $deployed_file );

        if ( ! $file_hash ) {
            $file_contents = file_get_contents( $deployed_file );

            if ( ! $file_contents ) {
                return;
            }

            $file_hash = md5( $file_contents );
        }

        $sql = "INSERT INTO {$table_name} (path_hash,path,file_hash,namespace)" .
            ' VALUES (%s,%s,%s,%s) ON DUPLICATE KEY UPDATE file_hash = %s, namespace = %s';

        $sql = $wpdb->prepare(
            // Insert values
            $sql,
            $path_hash,
            $local_path,
            $file_hash,
            $ns,
            // Duplicate key values
            $file_hash,
            $ns
        );

        $wpdb->query( $sql );
    }

    /**
     * Checks if file can skip deployment
     *  - uses hash of file and path's hash
     */
    public static function fileisCached(
        string $local_path,
        string $ns = self::DEFAULT_NAMESPACE,
        ?string $file_hash = null
    ): bool {
        global $wpdb;

        $post_processed_dir = ProcessedSite::getPath();

        $deployed_file = $post_processed_dir . $local_path;

        $path_hash = md5( $deployed_file );

        if ( ! $file_hash ) {
            $file_contents = file_get_contents( $deployed_file );

            if ( ! $file_contents ) {
                return false;
            }

            $file_hash = md5( $file_contents );
        }

        $table_name = self::getTableName();

        $sql = $wpdb->prepare(
            "SELECT path_hash FROM $table_name WHERE" .
            ' path_hash = %s AND file_hash = %s AND namespace = %s LIMIT 1',
            $path_hash,
            $file_hash,
            $ns
        );

        $hash = $wpdb->get_var( $sql );

        return (bool) $hash;
    }

    public static function truncate(
        string $ns = ''
    ): void {
        WsLog::l( 'Deleting DeployCache' );

        global $wpdb;

        $table_name = self::getTableName();

        if ( ! $ns ) {
            $sql = "TRUNCATE TABLE $table_name";
        } else {
            $sql = "DELETE FROM $table_name WHERE namespace = %s";
            $sql = $wpdb->prepare( $sql, $ns );
        }
        $wpdb->query( $sql );
    }

    /**
     *  Count Paths in Deploy Cache for default or specific namespace
     */
    public static function getTotalByNamespace(
        string $ns = self::DEFAULT_NAMESPACE
    ): int {
        global $wpdb;

        $table_name = self::getTableName();

        $sql = "SELECT count(*) FROM $table_name WHERE namespace = %s";
        $sql = $wpdb->prepare( $sql, $ns );
        $total = $wpdb->get_var( $sql );

        return $total;
    }

    /**
     *  Count Paths in Deploy Cache across all namespaces
     *
     *  @return mixed[] namespace totals
     */
    public static function getTotal(): array {
        global $wpdb;
        $counts = [];

        $table_name = self::getTableName();

        $sql = "SELECT namespace, COUNT(*) AS count FROM $table_name GROUP BY namespace";
        $rows = $wpdb->get_results( $sql );

        foreach ( $rows as $row ) {
            $counts[ $row->namespace ] = $row->count;
        }

        return $counts;
    }


    /**
     *  Get all cached paths
     *
     *  @return string[] All cached paths
     */
    public static function getPaths(
        string $ns = self::DEFAULT_NAMESPACE
    ): array {
        global $wpdb;
        $urls = [];

        $table_name = self::getTableName();

        $sql = "SELECT path FROM $table_name WHERE namespace = %s ORDER BY path";
        $sql = $wpdb->prepare( $sql, $ns );
        $urls = $wpdb->get_col( $sql );

        return $urls;
    }
}
