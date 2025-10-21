<?php

namespace StaticDeploy;

class CrawledFiles {

    public static function createTable(): void {
        global $wpdb;

        $table_name = self::getTableName();

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            path VARCHAR(2083) NOT NULL,
            path_hash CHAR(32) AS ( md5(path) ) PERSISTENT,
            content_hash CHAR(32) NULL,
            crawled_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            status SMALLINT DEFAULT 200 NOT NULL,
            redirect_to VARCHAR(2083) NULL,
            content_type VARCHAR(255) DEFAULT '' NOT NULL,
            PRIMARY KEY  (id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        Db::ensureIndex(
            $table_name,
            'path_hash',
            "CREATE UNIQUE INDEX path_hash ON {$table_name} (path_hash)"
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

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return $wpdb->get_col( $wpdb->prepare( 'SELECT path_hash FROM %i', $table_name ) );
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

            $values = [];
            foreach ( $paths as $path ) {
                $values[] = $path->path;
                $values[] = $path->content_type;
                $values[] = $path->redirect_to;
                $values[] = $path->status;
                $values[] = $path->getContentHash();
            }

            $placeholders = array_fill( 0, count( $paths ), '(%s,%s,%s,%s,%s,NOW())' );
            // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
            $query = $wpdb->prepare(
                'INSERT INTO %i ' .
                '(path,content_type,redirect_to,status,content_hash,crawled_at) ' .
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                'VALUES ' . implode( ',', $placeholders ) .
                'ON DUPLICATE KEY UPDATE ' .
                'path = VALUES(path), ' .
                'content_type = VALUES(content_type), ' .
                'redirect_to = VALUES(redirect_to), ' .
                'status = VALUES(status), ' .
                'content_hash = VALUES(content_hash), ' .
                'crawled_at = VALUES(crawled_at)',
                $table_name,
                ...$values
            );
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
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT cc.id, cc.content_hash, cc.content_type, cq.filename, ' .
                    'cc.path, cc.redirect_to, cc.status ' .
                    'FROM %i AS cc JOIN %i AS cq ON cc.path_hash = cq.path_hash ' .
                    'WHERE cc.id > %d ORDER BY cc.id ASC LIMIT %d',
                    $table_name,
                    $queue_table_name,
                    $last_id,
                    $batch_size,
                ),
            );

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
                    filename: $row->filename,
                    content_hash: $row->content_hash,
                    content_type: $row->content_type,
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
                    function ( string $dir ) use ( $path ): void {
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

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query(
            $wpdb->prepare(
                'INSERT INTO %i (crawled_at, path, content_hash, status, redirect_to) ' .
                'VALUES (NOW(), %s, %s, %s, %s) ' .
                'ON DUPLICATE KEY UPDATE crawled_at = NOW(), content_hash = %s, ' .
                'status = %s, redirect_to = %s',
                self::getTableName(),
                $path,
                $content_hash,
                $status,
                $redirect_to,
                $content_hash,
                $status,
                $redirect_to,
            ),
        );
    }

    public static function getUrl( string $path, string $content_hash ): string {
        global $wpdb;

        $path_hash = md5( $path );

        $table_name = self::getTableName();

        $sql = 'SELECT path FROM %i WHERE path_hash = %s and content_hash = %s LIMIT 1';
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $query = $wpdb->prepare( $sql, $table_name, $path_hash, $content_hash );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $path = $wpdb->get_var(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $query
        );

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

        $sql = 'SELECT id, path_hash, path, content_hash FROM %i ORDER BY path';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $rows = $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->prepare( $sql, $table_name )
        );

        foreach ( $rows as $row ) {
            $paths[ $row->id ] = $row;
        }

        return $paths;
    }

    public static function rmUrl( string $path ): void {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
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

        $ids = array_map( 'absint', $ids );
        $table_name = self::getTableName();
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $sql = "DELETE FROM %i WHERE ID IN({$placeholders})";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->prepare( $sql, $table_name, ...$ids )
        );
    }

    /**
     *  Clear crawled files via truncation
     */
    public static function truncate(): void {
        WsLog::l( 'Deleting crawled files' );

        global $wpdb;

        $table_name = self::getTableName();

        $sql = 'TRUNCATE TABLE %i';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->prepare( $sql, $table_name )
        );

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

        $sql = 'SELECT count(*) FROM %i';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return $wpdb->get_var(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->prepare( $sql, $table_name )
        );
    }

    /**
     * @return object[] redirects
     */
    public static function listRedirects(): array {
        global $wpdb;

        $table_name = self::getTableName();

        $sql = 'SELECT path, redirect_to FROM %i WHERE 0 < LENGTH(redirect_to)';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->prepare( $sql, $table_name )
        );
    }
}
