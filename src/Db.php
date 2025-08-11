<?php declare(strict_types=1);

namespace StaticDeploy;

final class Db {
    /**
     * Returns table name based on the slug, the wpdb prefix,
     * and the prefix for this plugin.
     */
    public static function getTableName( string $table_slug ): string {
        global $wpdb;

        return $wpdb->prefix . 'static_deploy_' . $table_slug;
    }

    /**
     * Checks if the named index exists. If it doesn't, create it. This won't
     * alter an existing index. If you need to change an index, give it a new name.
     *
     * WordPress's dbDelta is very unreliable for indexes. It tends to create duplicate
     * indexes, acts badly if whitespace isn't exactly what it expects, and fails
     * silently. It's okay to create the table and primary key with dbDelta,
     * but use ensureIndex for index creation.
     *
     * @param string $table_name The name of the table that the index is for.
     * @param string $index_name The name of the index.
     * @param string $create_index_sql The SQL to execute if the index needs to be created.
     * @return bool true if the index already exists or was created. false if creation failed.
     */
    public static function ensureIndex(
        string $table_name,
        string $index_name,
        string $create_index_sql
    ): bool {
        global $wpdb;

        $query = $wpdb->prepare(
            "SHOW INDEX FROM $table_name WHERE key_name = %s",
            $index_name
        );
        $indexes = $wpdb->query( $query );

        if ( 0 === $indexes ) {
            $result = $wpdb->query( $create_index_sql );
            if ( false === $result ) {
                WsLog::l( "Failed to create $index_name index on $table_name." );
            }
            return $result;
        }
        return true;
    }

    /**
     * Returns a lock name for an "attribute"
     * of a table. The "attribute" is any MySQL-legal
     * string whose meaning is defined by the caller.
     *
     * If necessary, hashes part or all of the lock name
     * to fit within the 64-character limit.
     *
     * See https://dev.mysql.com/doc/refman/8.4/en/locking-functions.html
     */
    public static function getLockName(
        string $table_name,
        ?string $attribute = null,
    ): string {
        if ( $attribute ) {
            $prefix = DB_NAME . '.' . $table_name;
            $lock_name = $prefix . '.' . $attribute;
            if ( strlen( $lock_name ) > 64 ) {
                if ( strlen( $attribute ) <= 32 ) {
                    $lock_name = md5( $prefix ) . '.' . $attribute;
                } else {
                    $lock_name = md5( $lock_name );
                }
            }
            return $lock_name;
        }
        return $table_name;
    }

    /**
     * Perform a $wpdb->query with error handling.
     * Throws an exception if $on_error is null and a
     * database error is detected.
     */
    public static function query(
        string $query,
        ?callable $on_error = null,
    ): int|bool {
        global $wpdb;

        $result = $wpdb->query( $query );
        if ( $result !== false ) {
            return $result;
        }

        if ( $on_error ) {
            if ( STATIC_DEPLOY_DEBUG ) {
                WsLog::d( 'Detected error in query: ' . $wpdb->last_error );
            }
            return $on_error( $wpdb->last_error ) || false;
        } else {
            throw WsLog::ex( 'Error in query: ' . $wpdb->last_error );
        }
    }

    /**
     * Returns whether a table with the given name exists in the db
     *
     * Example:
     * Db::tableExists( $wpdb->prefix . 'static_deploy_jobs' );
     */
    public static function tableExists( string $table_name ): bool {
        global $wpdb;

        $result = $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
        );

        return $result !== null;
    }
}
