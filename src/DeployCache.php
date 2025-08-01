<?php

namespace StaticDeploy;

class DeployCache {

    const DEFAULT_NAMESPACE = 'default';


    public static function getTableName(): string {
        return Db::getTableName( 'deployed_files' );
    }

    public static function createTable(): void {
        global $wpdb;

        $table_name = self::getTableName();

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            path VARCHAR(2083) NOT NULL,
            path_hash CHAR(32) AS ( md5(path) ) PERSISTENT,
            data_hash CHAR(32) NOT NULL,
            namespace VARCHAR(128) NOT NULL,
            deployed_at datetime DEFAULT NOW() NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        Db::ensureIndex(
            $table_name,
            'path_hash_ns_idx',
            "CREATE UNIQUE INDEX path_hash_ns_idx ON $table_name (path_hash, namespace)"
        );

        Db::ensureIndex(
            $table_name,
            'deployed_at_idx',
            "CREATE INDEX deployed_at_idx ON $table_name (deployed_at)"
        );
    }

    /**
     * Adds deploy cache data to PathInfos that don't
     * already have it.
     *
     * @param \Iterator<PathInfo>
     * @return \Iterator<PathInfo>
     */
    public static function addCacheData(
        \Iterator $path_infos,
    ): \Iterator {
        global $wpdb;

        $chunks = Utils::chunkIterator( $path_infos, 200 );
        foreach ( $chunks as $chunk ) {
            $to_lookup = [];
            foreach ( $chunk as $pi ) {
                if ( isset( $pi->deploy_cache ) ) {
                    yield $pi;
                } else {
                    $to_lookup[] = $pi;
                }
            }

            if ( empty( $to_lookup ) ) {
                continue;
            }

            $path_hashes = array_map(
                function ( PathInfo $pi ): string {
                    return $pi->getPathHash();
                },
                $to_lookup,
            );

            $placeholders = implode( ',', array_fill( 0, count( $path_hashes ), '%s' ) );
            $table_name = self::getTableName();
            $sql = "SELECT path_hash,data_hash,namespace
                 FROM $table_name
                 WHERE path_hash IN ($placeholders)";
            $query = $wpdb->prepare( $sql, ...$path_hashes );
            $results = $wpdb->get_results( $query );

            $results_by_hash = [];
            foreach ( $results as $result ) {
                $current = $results_by_hash[ $result->path_hash ] ?? [];
                $current[ $result->namespace ] = $result->data_hash;
                $results_by_hash[ $result->path_hash ] = $current;
            }
            foreach ( $to_lookup as $pi ) {
                $cached = $results_by_hash[ $pi->getPathHash() ] ?? null;

                if ( $cached ) {
                    if ( STATIC_DEPLOY_DEBUG ) {
                        WsLog::d(
                            'Adding deploy cache data for '
                            . $pi->path . ': ' . json_encode( $cached )
                        );
                    }
                    yield $pi->withDeployCache( $cached );
                } else {
                    yield $pi;
                }
            }
        }
    }

    public static function addFile(
        string $path,
        string $ns = self::DEFAULT_NAMESPACE,
        string $data_hash,
    ): void {
        global $wpdb;

        $table_name = self::getTableName();

        $sql = "INSERT INTO $table_name
                (path,data_hash,namespace,deployed_at)
                VALUES (%s,%s,%s,NOW())
                ON DUPLICATE KEY UPDATE
                    path = VALUES(path),
                    data_hash = VALUES(data_hash),
                    deployed_at = VALUES(deployed_at)";

        $sql = $wpdb->prepare(
            $sql,
            $path,
            $data_hash,
            $ns,
        );

        $wpdb->query( $sql );
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
