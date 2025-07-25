<?php
/**
 * Plugin Name:       Static Deploy Object Cache for Memcached (Drop-in)
 * Plugin URI:        https://github.com/staticweb-io/static-deploy
 * Description:       Object caching for Memcached.
 * Version:           9.2.1
 * Author:            StaticWeb.io
 * Author URI:        https://github.com/staticweb-io/static-deploy
 * Text Domain:       static-deploy
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * License URI:       https://github.com/staticweb-io/static-deploy/blob/develop/LICENSE
 * License:           Unlicense
 */

// phpcs:disable Squiz.PHP.DiscouragedFunctions
// Allow discouraged functions so we can use error_log here.
// phpcs:disable Universal.Files.SeparateFunctionsFromOO

declare(strict_types=1);

if ( ! class_exists( 'Memcached' ) ) {
    wp_using_ext_object_cache( false );
} else {
    class StaticDeployMemcached {
        private Memcached $mc;

        // Array of group_name => true
        private array $global_groups;
        // Prefix used for cache keys in global groups
        private string $global_prefix;
        // Prefix used for cache keys in non-global groups
        private string $local_prefix;
        // Array of group_name => array of keys => values
        private array $non_persistent_groups;

        public function __construct(
            string $persistent_id,
            array $servers,
            string $local_prefix,
            string $cache_key_salt = '',
            string $global_prefix = 'global',
        ) {
            $this->cache_key_salt = $cache_key_salt;
            $this->global_groups = [];
            $this->global_prefix = $global_prefix;
            $this->local_prefix = $local_prefix;
            $this->non_persistent_groups = [];

            // https://www.php.net/manual/en/memcached.construct.php
            $mc = new Memcached( $persistent_id );

            // Since the Memcached instance persists across
            // requests, we must take care not to add servers
            // that are already in the list.
            if ( empty( $mc->getServerList() ) ) {
                $mc->addServers( $servers );
            }

            $this->mc = $mc;
        }

        private function can_add(): bool {
            if ( wp_suspend_cache_addition() ) {
                return false;
            }

            return true;
        }

        private function cache_key(
            int|string $key,
            string $group,
        ): string {
            if ( $key === '' ) {
                throw new Exception( 'Cache key cannot be empty' );
            }

            // For compatibility with WordPress
            if ( $group === '' ) {
                $group = 'default';
            }

            if ( isset( $this->global_groups[ $group ] ) ) {
                $prefix = $this->global_prefix;
            } else {
                $prefix = $this->local_prefix;
            }

            return $this->cache_key_salt . $prefix . $group . ':' . $key;
        }

        /**
         * Convert WordPress $expire args to Memcache
         * expiration.
         *
         * WordPress $expire args to cache functions are
         * always the number of seconds that the item expires in,
         * with 0 meaning no expiration.
         *
         * Memcached expiration behaves the same for 0 and values
         * under 60*60*24*30 (number of seconds in 30 days). But if
         * the value is larger than that, it is treated as a
         * Unix timestamp.
         *
         * If $expire is under this limit, we return it unchanged.
         * Otherwise, we convert it to a Unix timestamp.
         *
         * See https://www.php.net/manual/en/memcached.expiration.php
         */
        public static function to_memcache_expiration(
            int $expire,
        ): int {
            // Number of seconds in 30 days
            if ( $expire <= 2592000 ) {
                return $expire;
            } else {
                return $expire + time();
            }
        }

        /**
         * Clone $data if it is a type that is passed
         * by reference.
         */
        public static function maybe_clone(
            mixed $data,
        ): mixed {
            if ( is_object( $data ) ) {
                return clone $data;
            }
            return $data;
        }

        /**
         * Adds data to the cache only if the key is not
         * already present in the cache.
         *
         * $expire is ignored for non-persistent groups, because
         * they vanish at the end of script execution.
         */
        public function add(
            int|string $key,
            mixed $data,
            string $group = '',
            int $expire = 0,
        ): bool {
            if ( ! $this->can_add() ) {
                return false;
            }

            $k = $this->cache_key( $key, $group );

            if ( isset( $this->non_persistent_groups[ $group ] )
            && ! array_key_exists( $k, $this->non_persistent_groups[ $group ] ) ) {
                $data = self::maybe_clone( $data );
                $this->non_persistent_groups[ $group ][ $k ] = $data;
                return true;
            }

            $expire = self::to_memcache_expiration( $expire );
            return $this->mc->add( $k, $data, $expire );
        }

        public function add_global_groups(
            array $groups,
        ): void {
            $this->global_groups = array_merge(
                $this->global_groups,
                array_fill_keys( $groups, true ),
            );
        }

        public function add_non_persistent_groups(
            array $groups,
        ): void {
            $this->non_persistent_groups = array_merge(
                $this->non_persistent_groups,
                array_fill_keys( $groups, [] ),
            );
        }

        /**
         * Deletes an item from the cache.
         */
        public function delete(
            int|string $key,
            string $group = '',
        ): bool {
            $k = $this->cache_key( $key, $group );
            return $this->mc->delete( $k );
        }

        /**
         * Removes all cache items.
         */
        public function flush(): bool {
            return $this->mc->flush() && $this->flush_runtime();
        }

        /**
         * Removes all cache items.
         */
        public function flush_runtime(): bool {
            $this->non_persistent_groups =
                array_fill_keys(
                    array_keys( $this->non_persistent_groups ),
                    []
                );
            return true;
        }

        /**
         * Returns data from the cache, if present.
         *
         * Returns false if the key is not found. Note that
         * false could also be a value that was found in the
         * cache. In order to distinguish between these
         * circumstances, the $found parameter is set to true
         * if the key was found, false otherwise.
         *
         * $force is unused since we do not cache any
         * persistent values locally.
         */
        public function get(
            int|string $key,
            string $group = '',
            bool $force = false,
            ?bool &$found = null
        ): mixed {
            $k = $this->cache_key( $key, $group );

            if ( isset( $this->non_persistent_groups[ $group ] ) ) {
                if ( array_key_exists( $k, $this->non_persistent_groups[ $group ] ) ) {
                    $found = true;
                    return self::maybe_clone( $this->non_persistent_groups[ $group ][ $k ] );
                } else {
                    $found = false;
                    return false;
                }
            }

            $data = $this->mc->get( $k );
            $found = $this->mc->getResultCode() === Memcached::RES_SUCCESS;
            return $data;
        }

        /**
         * Replaces data in the cache only if the key is
         * already present in the cache.
         *
         * $expire is ignored for non-persistent groups, because
         * they vanish at the end of script execution.
         */
        public function replace(
            int|string $key,
            mixed $data,
            string $group = '',
            int $expire = 0,
        ): bool {
            if ( ! $this->can_add() ) {
                return false;
            }

            $k = $this->cache_key( $key, $group );

            if ( isset( $this->non_persistent_groups[ $group ] )
            && array_key_exists( $k, $this->non_persistent_groups[ $group ] ) ) {
                $data = self::maybe_clone( $data );
                $this->non_persistent_groups[ $group ][ $k ] = $data;
                return true;
            }

            $expire = self::to_memcache_expiration( $expire );
            return $this->mc->replace( $k, $data, $expire );
        }

        /**
         * Adds data to the cache, overwriting any existing data.
         *
         * $expire is ignored for non-persistent groups, because
         * they vanish at the end of script execution.
         */
        public function set(
            int|string $key,
            mixed $data,
            string $group = '',
            int $expire = 0,
        ): bool {
            if ( ! $this->can_add() ) {
                return false;
            }

            $k = $this->cache_key( $key, $group );

            if ( isset( $this->non_persistent_groups[ $group ] ) ) {
                $data = self::maybe_clone( $data );
                $this->non_persistent_groups[ $group ][ $k ] = $data;
                return true;
            }

            $expire = self::to_memcache_expiration( $expire );
            return $this->mc->set( $k, $data, $expire );
        }

        /**
         * Returns true if we support the given feature.
         *
         * See https://developer.wordpress.org/reference/functions/wp_cache_supports/
         */
        public function supports(
            string $feature,
        ): bool {
            return $feature === 'flush_runtime';
        }
    }

    function wp_cache_add(
        int|string $key,
        mixed $data,
        string $group = '',
        int $expire = 0,
    ): bool {
        global $wp_object_cache;
        return $wp_object_cache->add( $key, $data, $group, $expire );
    }

    function wp_cache_add_global_groups(
        string|array $groups,
    ): void {
        global $wp_object_cache;
        $groups = (array) $groups;
        $wp_object_cache->add_global_groups( $groups );
    }

    function wp_cache_add_non_persistent_groups(
        string|array $groups,
    ): void {
        global $wp_object_cache;
        $groups = (array) $groups;
        $wp_object_cache->add_non_persistent_groups( $groups );
    }

    function wp_cache_close(): true {
        return true;
    }

    function wp_cache_delete(
        int|string $key,
        string $group = '',
    ): bool {
        global $wp_object_cache;
        return $wp_object_cache->delete( $key, $group );
    }

    function wp_cache_get(
        int|string $key,
        string $group = '',
        bool $force = false,
        ?bool &$found = null
    ): mixed {
        global $wp_object_cache;
        return $wp_object_cache->get( $key, $group, $force, $found );
    }

    function wp_cache_flush(): bool {
        global $wp_object_cache;
        return $wp_object_cache->flush();
    }

    function wp_cache_flush_runtime(): bool {
        global $wp_object_cache;
        return $wp_object_cache->flush_runtime();
    }

    function wp_cache_init(): void {
        if ( ! defined( 'WP_CACHE_KEY_SALT' ) ) {
            define( 'WP_CACHE_KEY_SALT', '' );
        }

        if ( ! defined( 'STATIC_DEPLOY_MEMCACHED_PERSISTENT_ID' ) ) {
            define( 'STATIC_DEPLOY_MEMCACHED_PERSISTENT_ID', 'sd-mc' );
        }

        global $memcached_servers;

        if ( isset( $memcached_servers ) ) {
            $servers = $memcached_servers;
        } else {
            $servers = [ [ '127.0.0.1', 11211 ] ];
        }

        global $wp_object_cache;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride
        $wp_object_cache = new StaticDeployMemcached(
            STATIC_DEPLOY_MEMCACHED_PERSISTENT_ID,
            $servers,
            (string) get_current_blog_id(),
            WP_CACHE_KEY_SALT,
        );
        wp_using_ext_object_cache( true );
    }

    function wp_cache_replace(
        int|string $key,
        mixed $data,
        string $group = '',
        int $expire = 0,
    ): bool {
        global $wp_object_cache;
        return $wp_object_cache->replace( $key, $data, $group, $expire );
    }

    function wp_cache_set(
        int|string $key,
        mixed $data,
        string $group = '',
        int $expire = 0,
    ): bool {
        global $wp_object_cache;
        return $wp_object_cache->set( $key, $data, $group, $expire );
    }

    function wp_cache_supports(
        string $feature,
    ): bool {
        global $wp_object_cache;
        return $wp_object_cache->supports( $feature );
    }

    wp_cache_init();
}
