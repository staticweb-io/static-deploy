<?php

namespace WP2Static;

class CrawlQueue {

    public static function createTable() : void {
        global $wpdb;

        $table_name = self::getTableName();

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            url VARCHAR(2083) NOT NULL,
            hashed_url CHAR(32) AS ( md5(url) ) PERSISTENT,
            filename VARCHAR(2083) DEFAULT '' NOT NULL,
            detected_at datetime DEFAULT NOW() NOT NULL,
            content_hash CHAR(32),
            content_type VARCHAR(2083) DEFAULT '' NOT NULL,
            redirect_to VARCHAR(2083) DEFAULT '' NOT NULL,
            status smallint(6),
            crawled_at datetime,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        Controller::ensureIndex(
            $table_name,
            'hashed_url',
            "CREATE UNIQUE INDEX hashed_url ON $table_name (hashed_url)"
        );
    }

    public static function getTableName() : string {
        return Controller::getTableName( 'urls' );
    }

    /**
     * Add an Iterator of paths, returning an Iterator of the same
     * paths once they have been added.
     *
     * @param \Iterator $paths
     * @param bool $omit_unchanged_paths
     *  If true, will not yield paths that already exist in the DB
     *  unless they were updated, e.g., the filename changed.
     * @return \Iterator
     */
    public static function addPathsIter( 
        \Iterator $paths,
        bool $omit_unchanged_paths = false
    ) : \Iterator {
        global $wpdb;

        $table_name = self::getTableName();

        foreach ( Utils::chunkIterator( $paths, 200 ) as $chunk ) {
            $hashes = [];
            $paths = [];
            foreach ( $chunk as $path ) {
                $hashes[] = md5( $path['path'] );
                $paths[] = $path;
            }

            $placeholders = implode(',', array_fill(0, count($hashes), '%s'));
            $sql = "SELECT url, filename FROM $table_name WHERE hashed_url IN ($placeholders)";
            $existing_urls = $wpdb->get_results(
                $wpdb->prepare($sql, ...$hashes),
                OBJECT_K
            );

            $insert_values = [];
            $update_values = [];
            $yield_paths = [];
            foreach ( $paths as $path ) {
                $filename = $path['filename'] ?? '';
                $p = $path['path'];
                $url = rawurldecode( $p );
                $hash = md5( $url );
                if ( ! isset( $existing_urls[ $p ] ) ) {
                    $yield_paths[] = $path;
                    array_push(
                        $insert_values,
                        $url,
                        $filename
                    );
                } elseif ( $filename !== $existing_urls[ $p ]->filename ) {
                    $yield_paths[] = $path;
                    array_push(
                        $update_values,
                        $filename,
                        $hash
                    );
                } elseif ( ! $omit_unchanged_paths ) {
                    $yield_paths[] = $path;
                }
            }

            // INSERT IGNORE new URLs
            if ( count( $insert_values ) > 0 ) {
                $insert_rows = count( $insert_values ) / 2;
                $placeholders = array_fill( 0, $insert_rows, '(%s,%s)' );
                $query_string =
                    "INSERT IGNORE INTO $table_name (url, filename) " .
                    ' VALUES ' . implode( ',', $placeholders );
                $query = $wpdb->prepare( $query_string, ...$insert_values );
                $wpdb->query( $query );
            }

            // UPDATE changed filenames
            if ( count( $update_values ) > 0 ) {
                $update_rows = count( $update_values ) / 2;
                for ( $i = 0; $i < $update_rows; $i++ ) {
                    $filename = $update_values[ $i * 2 ];
                    $hash = $update_values[ $i * 2 + 1 ];
                    $query_string =
                        "UPDATE $table_name SET filename = %s, detected_at = NOW(), crawled_at = NULL WHERE hashed_url = %s";
                    $query = $wpdb->prepare( $query_string, $filename, $hash );
                    $wpdb->query( $query );
                }
            }

            foreach ( $yield_paths as $path ) {
                yield $path;
            }
        }
    }

    /**
     * Add an Iterator of paths, returning an Iterator
     * of all paths in the DB.
     * Includes the newly added paths and pre-existing paths.
     *
     */
    public static function withPathsIter( \Iterator $paths ) : \Iterator {
        global $wpdb;

        $db_now = $wpdb->get_var( "SELECT NOW()" );

        $table_name = self::getTableName();

        foreach ( self::addPathsIter( $paths, true ) as $path ) {
            yield $path;
        }

        $batch_size = 1000;
        $last_id = 0;
        while ( true ) {
            $qs = "SELECT id, url AS path, filename FROM $table_name WHERE id > %d AND detected_at < %s AND (crawled_at IS NULL OR crawled_at < %s) ORDER BY id ASC LIMIT %d";
            $q = $wpdb->prepare( $qs, $last_id, $db_now, $db_now, $batch_size );
            $rows = $wpdb->get_results( $q, ARRAY_A );

            foreach ( $rows as $row ) {
                yield $row;
                $last_id = $row['id'];
            }

            if ( count( $rows ) < $batch_size ) {
                break;
            }
        }
    }

    /**
     *  Get all crawlable URLs
     *
     *  @return \Iterator<string> All crawlable URLs
     */
    public static function getCrawlablePaths() : \Iterator {
        global $wpdb;

        $table_name = self::getTableName();

        $last_id = 0;
        $limit = 1000;

        do {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, url FROM $table_name WHERE id > %d ORDER BY id ASC LIMIT %d",
                    $last_id,
                    $limit
                )
            );

            foreach ( $rows as $row ) {
                yield $row->url;
                $last_id = $row->id;
            }

        } while ( count( $rows ) === $limit );
    }

    /**
     * Remove multiple URLs at once
     *
     * @param array<string> $ids
     * @return void
     */
    public static function rmUrlsById( array $ids ) : void {
        global $wpdb;

        $ids = implode( ',', array_map( 'absint', $ids ) );

        $table_name = self::getTableName();

        $wpdb->query( "DELETE FROM $table_name WHERE ID IN($ids)" );
    }

    public static function rmUrl( string $url ) : void {
        global $wpdb;

        $table_name = self::getTableName();

        $wpdb->delete(
            $table_name,
            [
                'hashed_url' => md5( $url ),
            ]
        );
    }

    /**
     *  Get total crawlable URLs
     *
     *  @return int Total crawlable URLs
     */
    public static function getTotalCrawlableURLs() : int {
        global $wpdb;

        $table_name = self::getTableName();

        $total_urls = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );

        return $total_urls;
    }

    /**
     *  Clear CrawlQueue via truncate or deletion
     */
    public static function truncate() : void {
        WsLog::l( 'Deleting CrawlQueue (Detected URLs)' );

        global $wpdb;

        $table_name = self::getTableName();

        $wpdb->query( "TRUNCATE TABLE $table_name" );

        $total_urls = self::getTotalCrawlableURLs();

        if ( $total_urls > 0 ) {
            WsLog::l( 'failed to truncate CrawlQueue: try deleting instead' );
        }
    }

    /**
     *  Count URLs in Crawl Queue
     */
    public static function getTotal() : int {
        global $wpdb;

        $table_name = self::getTableName();

        $total = $wpdb->get_var( "SELECT count(*) FROM $table_name" );

        return $total;
    }
}
