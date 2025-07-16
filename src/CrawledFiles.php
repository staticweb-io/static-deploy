<?php

namespace StaticDeploy;

class CrawledFiles {

    public static function createTable(): void {
        global $wpdb;

        $table_name = self::getTableName();

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            path VARCHAR(2083) NOT NULL,
            path_hash CHAR(32) AS ( md5(path) ) PERSISTENT,
            content_hash CHAR(32) NULL,
            crawled_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            status SMALLINT DEFAULT 200 NOT NULL,
            redirect_to VARCHAR(2083) NULL,
            content_type VARCHAR(255) DEFAULT '' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        Controller::ensureIndex(
            $table_name,
            'path_hash',
            "CREATE UNIQUE INDEX path_hash ON $table_name (path_hash)"
        );
    }

    /**
     *  Get all crawled file path hashes
     *
     *  @return string[]
     */
    public static function getHashes(): array {
        global $wpdb;
        $hashes = [];

        $table_name = self::getTableName();

        $hashes = $wpdb->get_col( "SELECT path_hash FROM $table_name" );

        return $hashes;
    }

    public static function getTableName(): string {
        return Controller::getTableName( 'crawled_files' );
    }

    /**
     * Add an Iterator of paths to the DB,
     * returning an Iterator of the same paths.
     *
     * @param \Iterator $paths
     * @return \Iterator
     */
    public static function addPathsIter( \Iterator $paths ): \Iterator {
        global $wpdb;

        $table_name = self::getTableName();

        foreach ( Utils::chunkIterator( $paths, 200 ) as $chunk ) {
            $paths = [];
            foreach ( $chunk as $path ) {
                $paths[] = $path;
            }

            $placeholders = implode(
                ',',
                array_fill(
                    0,
                    count( $paths ),
                    '(%s,%s,%s,%s,%s,NOW())'
                )
            );
            $sql = "INSERT INTO $table_name
                    (path,content_type,redirect_to,status,content_hash,crawled_at)
                    VALUES $placeholders ON DUPLICATE KEY
                    UPDATE
                      path = VALUES(path),
                      content_type = VALUES(content_type),
                      redirect_to = VALUES(redirect_to),
                      status = VALUES(status),
                      content_hash = VALUES(content_hash),
                      crawled_at = VALUES(crawled_at)";

            $values = [];
            foreach ( $paths as $path ) {
                array_push(
                    $values,
                    $path['path'],
                    $path['content_type'],
                    $path['redirect_to'],
                    $path['status'],
                    $path['content_hash'] ?? null,
                );
            }

            $result = $wpdb->query( $wpdb->prepare( $sql, ...$values ) );

            if ( false === $result ) {
                WsLog::w( 'Error inserting into crawled files: ' . $wpdb->last_error );
            }

            foreach ( $paths as $path ) {
                yield $path;
            }
        }
    }

    /**
     * Yields all paths in the table.
     */
    public static function getPathsIter(): \Iterator {
        global $wpdb;

        $table_name = self::getTableName();
        $queue_table_name = DetectedFiles::getTableName();
        $batch_size = 1000;
        $last_id = 0;
        $static_site_path = StaticSite::getPath();
        while ( true ) {
            $qs = "SELECT
                cc.id,
                cc.path,
                cc.content_hash,
                cc.status,
                cc.redirect_to,
                cc.content_type,
                cq.filename
              FROM $table_name AS cc
              JOIN $queue_table_name AS cq
              ON cc.path_hash = cq.path_hash
              WHERE cc.id > %d
              ORDER BY cc.id ASC
              LIMIT %d";
            $q = $wpdb->prepare( $qs, $last_id, $batch_size );
            $rows = $wpdb->get_results( $q, ARRAY_A );

            foreach ( $rows as $row ) {
                if ( ! $row['filename'] ) {
                    $xform = StaticSite::transformPath( $row['path'] );
                    $cc_path = $static_site_path . $xform;
                    if ( $xform && file_exists( $cc_path ) ) {
                        $row['filename'] = $cc_path;
                    }
                }
                yield $row;
                $last_id = $row['id'];
            }

            if ( count( $rows ) < $batch_size ) {
                break;
            }
        }
    }

    /**
     * Remove 404 URLs from the detected files, crawled files, and
     * files written to disk.
     */
    public static function remove404s( \Iterator $paths ): \Iterator {
        foreach ( $paths as $path ) {
            if ( isset( $path['status'] ) && $path['status'] === 404 ) {
                WsLog::l( '404 for URL ' . $path['path'] );
                self::rmUrl( $path['path'] );
                // Delete from detected files to prevent crawling not found urls forever.
                DetectedFiles::rmUrl( $path['path'] );
                // Delete previously generated files under the directories,
                // both the crawled and the processed.
                array_map(
                    function ( $dir ) use ( $path ) {
                        $transformed_path = StaticSite::transformPath( $path['path'] );
                        $suffix = ltrim( $transformed_path, '/' );
                        $full_path = trailingslashit( $dir ) . $suffix;
                        if ( file_exists( $full_path ) && ! is_dir( $full_path ) ) {
                            unlink( $full_path );
                        }
                    },
                    [ StaticSite::getPath(), ProcessedSite::getPath() ]
                );
            } else {
                yield $path;
            }
        }
    }

    /**
     * Write path contents to the crawled site dir,
     * returning an Iterator of the same paths.
     *
     * @param \Iterator $paths
     * @return \Iterator
     */
    public static function writeFilesIter( \Iterator $paths ): \Iterator {
        $cache_hits = 0;
        foreach ( $paths as $path ) {
            $body = $path['body'] ?? null;
            $is_cacheable = true;
            $status = $path['status'];

            if ( $status === 404 ) {
                $is_cacheable = false;
            } elseif ( in_array( $status, WP2STATIC_REDIRECT_CODES ) ) {
                $is_cacheable = false;
            }

            $content_hash = null;
            if ( $is_cacheable && $body ) {
                $content_hash = md5( $body );
                $path['content_hash'] = $content_hash;
            }

            if ( $is_cacheable && $content_hash && self::getUrl( $path['path'], $content_hash ) ) {
                ++$cache_hits;
            } elseif ( $body ) {
                $static_path = StaticSite::transformPath( $path['path'] );
                StaticSite::add( $static_path, $body );
            }

            yield $path;
        }
    }

    public static function addUrl(
        string $path,
        string $content_hash,
        int $status,
        ?string $redirect_to
    ): void {
        global $wpdb;

        $table_name = self::getTableName();
        $sql = "insert into {$table_name} (crawled_at, path, content_hash, status, redirect_to)
                VALUES (%s, %s, %s, %s, %s) ON DUPLICATE KEY
                UPDATE crawled_at = %s, content_hash = %s, status = %s, redirect_to = %s";
        $sql = $wpdb->prepare(
            $sql,
            current_time( 'mysql' ),
            $path,
            $content_hash,
            $status,
            $redirect_to,
            current_time( 'mysql' ),
            $content_hash,
            $status,
            $redirect_to
        );

        $wpdb->query( $sql );
    }

    public static function getUrl( string $path, string $content_hash ): string {
        global $wpdb;

        $path_hash = md5( $path );

        $table_name = self::getTableName();

        $sql = $wpdb->prepare(
            "SELECT path FROM $table_name WHERE" .
            ' path_hash = %s and content_hash = %s  LIMIT 1',
            [ $path_hash, $content_hash ]
        );

        $path = $wpdb->get_var( $sql );

        return (string) $path;
    }

    /**
     *  Get all crawled files
     *
     *  @return object[] {
     *      All crawlable paths
     *
     *      @type int      $id                   ID
     *      @type string   $path_hash            MD5 hashed path
     *      @type string   $path                 Path in plain text
     *      @type string   $content_hash            MD5 hashed page
     *  }
     */
    public static function getURLs(): array {
        global $wpdb;
        $paths = [];

        $table_name = self::getTableName();

        $rows = $wpdb->get_results(
            "
            SELECT id, path_hash, path, content_hash
            FROM $table_name
            ORDER BY path
            "
        );

        foreach ( $rows as $row ) {
            $paths[ $row->id ] = $row;
        }

        return $paths;
    }

    public static function rmUrl( string $path ): void {
        global $wpdb;

        $wpdb->delete(
            self::getTableName(),
            [
                'path_hash' => md5( $path ),
            ]
        );
    }

    /**
     * Remove multiple URLs at once
     *
     * @param array<string> $ids
     * @return void
     */
    public static function rmUrlsById( array $ids ): void {
        global $wpdb;

        $ids = implode( ',', array_map( 'absint', $ids ) );

        $table_name = self::getTableName();

        $wpdb->query( "DELETE FROM $table_name WHERE ID IN($ids)" );
    }

    /**
     *  Clear crawled files via truncation
     */
    public static function truncate(): void {
        WsLog::l( 'Deleting crawled files' );

        global $wpdb;

        $table_name = self::getTableName();

        $wpdb->query( "TRUNCATE TABLE $table_name" );

        $total_crawled_files = self::getTotal();

        if ( $total_crawled_files > 0 ) {
            WsLog::l( 'Failed to truncate crawled files: try deleting instead' );
        }
    }

    /**
     *  Count crawled files
     */
    public static function getTotal(): int {
        global $wpdb;

        $table_name = self::getTableName();

        $total = $wpdb->get_var( "SELECT count(*) FROM $table_name" );

        return $total;
    }

    /**
     * @return object[] redirects
     */
    public static function listRedirects(): array {
        global $wpdb;

        $table_name = self::getTableName();

        $rows = $wpdb->get_results(
            "SELECT path, redirect_to FROM $table_name WHERE 0 < LENGTH(redirect_to)"
        );

        return $rows;
    }
}
