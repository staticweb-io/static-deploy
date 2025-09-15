<?php

namespace StaticDeploy;

class Addons {
    public static function getTableName(): string {
        return Db::getTableName( 'addons' );
    }

    public static function createTable(): void {
        global $wpdb;

        $table_name = self::getTableName();

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            slug VARCHAR(191) NOT NULL,
            type VARCHAR(249) NOT NULL,
            name VARCHAR(249) NOT NULL,
            docs_url VARCHAR(2083) NOT NULL,
            description VARCHAR(249) NOT NULL,
            enabled TINYINT(1) UNSIGNED DEFAULT 0 NOT NULL,
            PRIMARY KEY  (slug)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function registerAddon(
        string $slug,
        string $type,
        string $name,
        string $docs_url,
        string $description
    ): void {
        // TODO: guard against unknown addon type

        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                'INSERT IGNORE INTO %i (slug,type,name,docs_url,description)' .
                ' VALUES (%s,%s,%s,%s,%s)',
                self::getTableName(),
                $slug,
                $type,
                $name,
                $docs_url,
                $description,
            ),
        );
    }

    /**
     * Get all Addons
     *
     * @return mixed[] array of Addon objects
     */
    public static function getAll( string $type = 'all' ): array {
        global $wpdb;

        $table_name = self::getTableName();

        if ( $type !== 'all' ) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT * FROM %i WHERE type = %s ORDER BY type DESC',
                    $table_name,
                    $type
                )
            );
        } else {
            return $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT * FROM %i ORDER BY type DESC',
                    $table_name
                )
            );
        }
    }

    /**
     * Get enabled Addons of a given type
     *
     * @param string $type Type of addon to return
     * @return mixed[] array of Addon objects
     */
    public static function getType( string $type ): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE type = %s AND enabled = 1 ORDER BY slug',
                self::getTableName(),
                $type,
            ),
        );
    }

    /**
     *  Deregister Addons
     */
    public static function truncate(): void {
        global $wpdb;

        $table_name = self::getTableName();

        $wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $table_name ) );

        WsLog::l( 'Deregistered all Addons' );
    }

    /**
     * Get enabled deployer
     *
     * "There can be only one!"
     *
     * @return string|bool deployment add-on slug or false
     */
    public static function getDeployer() {
        $addons = self::getType( 'deploy' );

        if ( empty( $addons ) ) {
            return false;
        }

        return $addons[0]->slug;
    }
}
