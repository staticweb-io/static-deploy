<?php

namespace StaticDeploy;

use GuzzleHttp\Psr7\Utils as Psr7Utils;

class DetectedFiles {

    public static function createTable(): void {
        global $wpdb;

        $table_name = self::getTableName();

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            path VARCHAR(2083) NOT NULL,
            path_hash CHAR(32) AS ( md5(path) ) PERSISTENT,
            filename VARCHAR(2083) DEFAULT '' NOT NULL,
            detected_at datetime DEFAULT NOW() NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        Db::ensureIndex(
            $table_name,
            'path_hash',
            "CREATE UNIQUE INDEX path_hash ON $table_name (path_hash)"
        );

        Db::ensureIndex(
            $table_name,
            'detected_at',
            "CREATE INDEX detected_at ON $table_name (detected_at)"
        );
    }

    public static function getTableName(): string {
        return Db::getTableName( 'detected_files' );
    }

    /**
     * Add an Iterator of paths, returning an Iterator of the same
     * paths once they have been added.
     *
     * @param \Iterator<PathInfo> $paths
     * @param bool $omit_unchanged_paths
     *  If true, will not yield paths that already exist in the DB
     *  unless they were updated, e.g., the filename changed.
     * @return \Iterator<PathInfo>
     */
    public static function addPathsIter(
        \Iterator $paths,
        bool $omit_unchanged_paths = false
    ): \Iterator {
        global $wpdb;

        $table_name = self::getTableName();

        foreach ( Utils::chunkIterator( $paths, 200 ) as $chunk ) {
            $hashes = [];
            $paths = [];
            foreach ( $chunk as $path ) {
                $hashes[] = md5( (string) $path->path );
                $paths[] = $path;
            }

            $placeholders = array_fill( 0, count( $hashes ), '%s' );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $existing_urls = $wpdb->get_results(
                // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
                $wpdb->prepare(
                    'SELECT path, filename FROM %i' .
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                    ' WHERE path_hash IN (' . implode( ',', $placeholders ) . ')',
                    $table_name,
                    ...$hashes
                ),
                OBJECT_K,
            );

            $insert_values = [];
            $update_values = [];
            $yield_paths = [];
            foreach ( $paths as $path ) {
                $filename = $path->filename ?? '';
                $p = $path->path;
                $url = rawurldecode( (string) $p );
                $hash = md5( $url );
                if ( ! isset( $existing_urls[ $p ] ) ) {
                    $yield_paths[] = $path;
                    $insert_values[] = $url;
                    $insert_values[] = $filename;
                } elseif ( $filename !== $existing_urls[ $p ]->filename ) {
                    $yield_paths[] = $path;
                    $update_values[] = $filename;
                    $update_values[] = $hash;
                } elseif ( ! $omit_unchanged_paths ) {
                    $yield_paths[] = $path;
                }
            }

            // INSERT IGNORE new paths
            if ( count( $insert_values ) > 0 ) {
                $insert_rows = count( $insert_values ) / 2;
                $placeholders = array_fill( 0, $insert_rows, '(%s,%s)' );
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->query(
                    // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
                    $wpdb->prepare(
                        'INSERT IGNORE INTO %i (path, filename)' .
                        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                        ' VALUES ' . implode( ',', $placeholders ),
                        $table_name,
                        ...$insert_values
                    )
                );
            }

            // UPDATE changed filenames
            if ( count( $update_values ) > 0 ) {
                $update_rows = count( $update_values ) / 2;
                for ( $i = 0; $i < $update_rows; $i++ ) {
                    $filename = $update_values[ $i * 2 ];
                    $hash = $update_values[ $i * 2 + 1 ];
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                    $wpdb->query(
                        $wpdb->prepare(
                            'UPDATE %i SET filename = %s, detected_at = NOW() WHERE path_hash = %s',
                            $table_name,
                            $filename,
                            $hash,
                        )
                    );
                }
            }

            foreach ( $yield_paths as $yield_path ) {
                yield $yield_path;
            }
        }
    }

    /**
     * Add an Iterator of paths, returning an Iterator
     * of all paths in the DB.
     * Includes the newly added paths and pre-existing paths.
     *
     * @param \Iterator<PathInfo> $paths
     * @return \Iterator<PathInfo>
     */
    public static function withPathsIter( \Iterator $paths ): \Iterator {
        global $wpdb;

        $db_now = Db::now();

        $table_name = self::getTableName();

        foreach ( self::addPathsIter( $paths, true ) as $path ) {
            yield $path;
        }

        $batch_size = 1000;
        $last_id = 0;
        while ( true ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT id, path, filename FROM %i
                     WHERE id > %d AND detected_at < %s
                     ORDER BY id ASC LIMIT %d',
                    $table_name,
                    $last_id,
                    $db_now,
                    $batch_size,
                )
            );

            foreach ( $rows as $row ) {
                $uri = Psr7Utils::uriFor( $row->path );
                $msg = PathInfo::pathErrorMessage( $uri );

                if ( $msg ) {
                    WsLog::w(
                        'Skipping invalid path found in detected files table:'
                        . " $row->path ($msg)"
                    );
                    continue;
                }

                yield new PathInfo(
                    $uri,
                    filename: $row->filename,
                );
                $last_id = $row->id;
            }

            if ( count( $rows ) < $batch_size ) {
                break;
            }
        }
    }

    /**
     * Yields all paths in the table.
     *
     * @param string $detected_since default to '0000-00-00 00:00:00'
     * @return \Iterator<PathInfo>
     */
    public static function getPathsIter(
        string $detected_since = '0000-00-00 00:00:00'
    ): \Iterator {
        global $wpdb;

        $table_name = self::getTableName();
        $batch_size = 1000;
        $last_id = 0;
        while ( true ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT id, path, filename FROM %i
                     WHERE id > %d AND detected_at >= %s
                     ORDER BY id ASC LIMIT %d',
                    $table_name,
                    $last_id,
                    $detected_since,
                    $batch_size,
                ),
            );

            foreach ( $rows as $row ) {
                $uri = Psr7Utils::uriFor( $row->path );
                $msg = PathInfo::pathErrorMessage( $uri );

                if ( $msg ) {
                    WsLog::w(
                        'Skipping invalid path found in detected files table:'
                        . " $row->path ($msg)"
                    );
                    continue;
                }

                yield new PathInfo(
                    $uri,
                    filename: $row->filename,
                );
                $last_id = $row->id;
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
    public static function getCrawlablePaths(): \Iterator {
        foreach ( self::getPathsIter() as $path ) {
            yield $path->path;
        }
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query(
            // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "DELETE FROM %i WHERE ID IN($placeholders)",
                $table_name,
                ...$ids
            )
        );
    }

    public static function rmUrl( string $url ): void {
        global $wpdb;

        $table_name = self::getTableName();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->delete(
            $table_name,
            [ 'path_hash' => md5( $url ) ],
        );
    }

    /**
     *  Get total crawlable URLs
     *
     *  @return int Total crawlable URLs
     */
    public static function getTotalCrawlableURLs(): int {
        global $wpdb;

        $table_name = self::getTableName();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table_name ) );
    }

    /**
     *  Clear Detected via truncate or deletion
     */
    public static function truncate(): void {
        WsLog::l( 'Deleting Detected (Detected URLs)' );

        global $wpdb;

        $table_name = self::getTableName();

        $wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $table_name ) );

        $total_urls = self::getTotalCrawlableURLs();

        if ( $total_urls > 0 ) {
            WsLog::l( 'failed to truncate Detected: try deleting instead' );
        }
    }

    /**
     *  Count detected files
     */
    public static function getTotal(): int {
        global $wpdb;

        $table_name = self::getTableName();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return $wpdb->get_var( $wpdb->prepare( 'SELECT count(*) FROM %i', $table_name ) );
    }
}
