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

        Db::ensureIndex(
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

        $table_name = self::getTableName();

        return $wpdb->get_col( "SELECT path_hash FROM $table_name" );
    }

    public static function getTableName(): string {
        return Db::getTableName( 'crawled_files' );
    }

    /**
     * Add an Iterator of paths to the DB,
     * returning an Iterator of the same paths.
     *
     * @param \Iterator<PathInfo> $paths
     * @return \Iterator<PathInfo>
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
                    $path->path,
                    $path->content_type,
                    $path->redirect_to,
                    $path->status,
                    $path->getContentHash(),
                );
            }

            $query = $wpdb->prepare( $sql, ...$values );
            Db::query( $query );

            foreach ( $paths as $path ) {
                yield $path;
            }
        }
    }

    /**
     * Yields all paths in the table.
     *
     * @return \Iterator<PathInfo>
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
                cc.content_hash,
                cc.content_type,
                cq.filename,
                cc.path,
                cc.redirect_to,
                cc.status
              FROM $table_name AS cc
              JOIN $queue_table_name AS cq
              ON cc.path_hash = cq.path_hash
              WHERE cc.id > %d
              ORDER BY cc.id ASC
              LIMIT %d";
            $q = $wpdb->prepare( $qs, $last_id, $batch_size );
            $rows = $wpdb->get_results( $q );

            foreach ( $rows as $row ) {
                if ( ! $row->filename ) {
                    $cc_path = FilesHelper::getFilePath(
                        $static_site_path,
                        $row->path
                    );
                    if ( file_exists( $cc_path ) ) {
                        $row->filename = $cc_path;
                    }
                }
                yield new PathInfo(
                    $row->path,
                    content_hash: $row->content_hash,
                    content_type: $row->content_type,
                    filename: $row->filename,
                    redirect_to: $row->redirect_to,
                    status: $row->status,
                );
                $last_id = $row->id;
            }

            if ( count( $rows ) < $batch_size ) {
                break;
            }
        }
    }

    /**
     * Remove outdated paths from the detected files, crawled files, and
     * files written to disk.
     *
     * Removes paths that return 404s and filenames that
     * no longer correspond to a file, such as filenames
     * whose path now refers to a directory.
     *
     * @param \Iterator<PathInfo> $paths
     * @return \Iterator<PathInfo>
     */
    public static function removeOutdated( \Iterator $paths ): \Iterator {
        foreach ( $paths as $path ) {
            $outdated = false;

            if ( isset( $path->status ) && $path->status === 404 ) {
                WsLog::l( '404 for URL ' . $path->path );
                $outdated = true;
            }

            if ( isset( $path->filename ) && ! file_exists( $path->filename ) ) {
                WsLog::l( 'File ' . $path->filename . ' does not exist' );
                $outdated = true;
            }

            if ( isset( $path->filename ) && is_dir( $path->filename ) ) {
                WsLog::l( 'File ' . $path->filename . ' is a directory' );
                $outdated = true;
            }

            if ( $outdated ) {
                self::rmUrl( $path->path );
                // Delete from detected files to prevent crawling not found urls forever.
                DetectedFiles::rmUrl( $path->path );
                // Delete previously generated files under the directories,
                // both the crawled and the processed.
                array_map(
                    function ( $dir ) use ( $path ): void {
                        $file_path = FilesHelper::getFilePath( $dir, $path->path );
                        $suffix = ltrim( $file_path, '/' );
                        $full_path = trailingslashit( $dir ) . $suffix;
                        if ( file_exists( $full_path ) && ! is_dir( $full_path ) ) {
                            FilesHelper::deleteFile( $full_path );
                        }
                    },
                    [ StaticSite::getPath(), ProcessedSite::getPath() ]
                );
            } else {
                yield $path;
            }
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

        return $wpdb->get_var( "SELECT count(*) FROM $table_name" );
    }

    /**
     * @return object[] redirects
     */
    public static function listRedirects(): array {
        global $wpdb;

        $table_name = self::getTableName();

        return $wpdb->get_results(
            "SELECT path, redirect_to FROM $table_name WHERE 0 < LENGTH(redirect_to)"
        );
    }
}
