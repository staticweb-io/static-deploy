<?php
/**
 * Plugin Name:       Static Deploy Object Cache for Memcached (Drop-in)
 * Plugin URI:        https://github.com/staticweb-io/static-deploy
 * Description:       Object caching for Memcached.
 * Version:           9.3.2
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
        public Memcached $mc;

        private string $cache_key_salt_hash;
        // Array of group_name => true
        private array $global_groups;
        // Prefix used for cache keys in global groups
        private string $global_prefix;
        // Temporary cache that vanishes at
        // the end of script execution.
        private array $local_cache;
        // Marker object used to represent missing cache items
        private object $local_missing_marker;
        // Prefix used for cache keys in non-global groups
        private string $non_global_prefix;
        // Array of group_name => array of keys => values
        private array $non_persistent_groups;

        public function __construct(
            string $persistent_id,
            array $servers,
            string $non_global_prefix,
            string $cache_key_salt = '',
            string $global_prefix = 'global',
        ) {
            if ( $cache_key_salt === '' ) {
                $this->cache_key_salt = '';
            } else {
                $this->cache_key_salt_hash = md5( $cache_key_salt );
            }

            $this->global_groups = [];
            $this->global_prefix = $global_prefix;
            $this->local_cache = [];
            $this->local_missing_marker = new stdClass();
            $this->non_global_prefix = $non_global_prefix;
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
                $prefix = $this->non_global_prefix;
            }

            $key = $prefix . $group . ':' . $key . $this->cache_key_salt_hash;

            // Unfortunately WordPress uses a lot of keys with spaces,
            // and we have to do something about them because memcached
            // doesn't allow that.
            // TODO: Check binary protocol
            $key = str_replace( ' ', '_', $key );

            // Truncate long keys and add a hash.
            // Memcached only allows 250 bytes for keys.
            if ( strlen( $key ) >= 250 ) {
                // Preserve as much of the key as we can.
                // Split the key without breaking up any
                // multi-byte characters.
                $key_start = mb_substr( $key, 0, 218 );
                $sl = strlen( $key_start );
                while ( $sl > 218 ) {
                    $key_start = mb_substr(
                        $key_start,
                        0,
                        218 - ( $sl - 218 ),
                    );

                    $sl = strlen( $key_start );
                }

                // Append the key's hash to the part of the
                // key that we were able to keep.
                $key = $key_start . md5( $key );
            }

            return $key;
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
            if ( $this->mc->add( $k, $data, $expire ) ) {
                $this->local_cache[ $k ] = self::maybe_clone( $data );
                return true;
            } else {
                // We don't know the state of the memcached item
                unset( $this->local_cache[ $k ] );
                return false;
            }
        }

        /**
         * Adds items to the cache only if the key is not
         * already present in the cache.
         *
         * $expire is ignored for non-persistent groups, because
         * they vanish at the end of script execution.
         */
        public function add_multiple(
            array $data,
            string $group = '',
            int $expire = 0,
        ): array {
            if ( empty( $data ) ) {
                return [];
            }

            if ( isset( $this->non_persistent_groups[ $group ] ) ) {
                $arr = [];
                foreach ( $data as $key => $v ) {
                    $k = $this->cache_key( $key, $group );
                    if ( array_key_exists( $k, $this->non_persistent_groups[ $group ] ) ) {
                        $arr[ $key ] = false;
                    } else {
                        $data = self::maybe_clone( $data );
                        $this->non_persistent_groups[ $group ][ $k ] = $data;
                        $arr[ $key ] = true;
                    }
                }
                return $arr;
            }

            $expire = self::to_memcache_expiration( $expire );
            $arr = [];
            // Memcached doesn't support addMulti, so we
            // iterater over many add() calls.
            foreach ( $data as $key => $v ) {
                $k = $this->cache_key( $key, $group );
                if ( $this->mc->add( $k, $v, $expire ) ) {
                    $this->local_cache[ $k ] = self::maybe_clone( $v );
                    $arr[ $key ] = true;
                } else {
                    // We don't know the state of the memcached item
                    unset( $this->local_cache[ $k ] );
                    $arr[ $key ] = false;
                }
            }

            return $arr;
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
         * Decrement the value of a numeric cache item.
         * Returns false if the cache item does not exist
         * or is not numeric.
         */
        public function decr(
            int|string $key,
            int $offset = 1,
            string $group = '',
        ): int|false {
            return $this->incr( $key, -$offset, $group );
        }

        /**
         * Deletes an item from the cache.
         */
        public function delete(
            int|string $key,
            string $group = '',
        ): bool {
            $k = $this->cache_key( $key, $group );

            if ( isset( $this->non_persistent_groups[ $group ] ) ) {
                unset( $this->non_persistent_groups[ $group ][ $k ] );
                return true;
            }

            if ( $this->mc->delete( $k ) ) {
                $this->local_cache[ $k ] = $this->local_missing_marker;
                return true;
            }
            return false;
        }

        /**
         * Deletes multiple items from the cache.
         *
         * Returns an array of bools grouped by key
         * indicating successful deletion.
         */
        public function delete_multiple(
            array $keys,
            string $group = '',
        ): array {
            if ( empty( $keys ) ) {
                return [];
            }

            $ks = array_map(
                fn( $k ) => $this->cache_key( $k, $group ),
                $keys,
            );

            if ( isset( $this->non_persistent_groups[ $group ] ) ) {
                foreach ( $ks as $k ) {
                    unset( $this->non_persistent_groups[ $group ][ $k ] );
                }
                return array_fill_keys( $ks, true );
            }

            $results = $this->mc->deleteMulti( $ks );

            foreach ( $results as $k => $v ) {
                if ( $v === true ) {
                    $this->local_cache[ $k ] = $this->local_missing_marker;
                } else {
                    // We aren't sure of the state of the item
                    unset( $this->local_cache[ $k ] );
                    // Turn Memcached::RES_* constants into false
                    $results[ $k ] = false;
                }
            }

            return $results;
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
            $this->local_cache = [];
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
         * $force determines whether we can serve the value
         * from our local cache or if we have to retrieve
         * the value from memcached.
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

            if ( ! $force && array_key_exists( $k, $this->local_cache ) ) {
                $v = $this->local_cache[ $k ];
                if ( $v === $this->local_missing_marker ) {
                    $found = false;
                    return false;
                }
                $found = true;
                return self::maybe_clone( $v );
            }

            $data = $this->mc->get( $k );
            $found = $this->mc->getResultCode() === Memcached::RES_SUCCESS;

            if ( $found ) {
                $this->local_cache[ $k ] = self::maybe_clone( $data );
            } else {
                $this->local_cache[ $k ] = $this->local_missing_marker;
            }

            return $data;
        }

        /**
         * Returns an array of items from the cache, if present.
         *
         * Array of return values, grouped by key. Each value is
         * either the cache contents on success, or false on
         * failure. If failure must be distinguished from a
         * false value, use get() with the &$found arg.
         *
         * $force determines whether we can serve values
         * from our local cache or if we have to retrieve
         * the values from memcached. If false, the response
         * data may include a mixture of data from the local
         * cache and from memcached.
         */
        public function get_multiple(
            array $keys,
            string $group = '',
            bool $force = false,
        ): mixed {
            if ( empty( $keys ) ) {
                return [];
            }

            $ks = array_map(
                fn( $k ) => $this->cache_key( $k, $group ),
                $keys,
            );

            if ( isset( $this->non_persistent_groups[ $group ] ) ) {
                $arr = [];
                foreach ( $ks as $k ) {
                    if ( array_key_exists( $k, $this->non_persistent_groups[ $group ] ) ) {
                        $arr[ $k ] = self::maybe_clone(
                            $this->non_persistent_groups[ $group ][ $k ],
                        );
                    } else {
                        $arr[ $k ] = false;
                    }
                }
                return $arr;
            }

            if ( ! $force ) {
                // Look up as many locally cached keys as we can
                $local = [];
                foreach ( $ks as $k ) {
                    if ( array_key_exists( $k, $this->local_cache ) ) {
                        $v = $this->local_cache[ $k ];
                        if ( $v === $this->local_missing_marker ) {
                            $local[ $k ] = false;
                        } else {
                            $local[ $k ] = self::maybe_clone( $v );
                        }
                    }
                }
            } else {
                $local = null;
            }

            // Don't hit memcached for items we found locally
            if ( ! empty( $local ) ) {
                $ks_to_keys = array_combine( $ks, $keys );
                $arr = [];
                foreach ( $ks_to_keys as $k => $key ) {
                    if ( array_key_exists( $k, $local ) ) {
                        $arr[ $key ] = $local[ $k ];
                    }
                }
                $ks = array_diff( $ks, array_keys( $local ) );

                // All keys were found locally
                if ( empty( $ks ) ) {
                    return $arr;
                }

                $local = $arr;
            }

            $result = $this->mc->getMulti( $ks );
            if ( $result === false ) {
                if ( empty( $local ) ) {
                    return array_fill_keys( $keys, false );
                } else {
                    return array_merge(
                        array_fill_keys( $keys, false ),
                        $local,
                    );
                }
            }

            if ( ! isset( $ks_to_keys ) ) {
                $ks_to_keys = array_combine( $ks, $keys );
            }

            // These do essentially the same thing if $local is
            // empty, but the then branch can skip the lookups.
            if ( empty( $local ) ) {
                $arr = [];
                foreach ( $ks_to_keys as $k => $key ) {
                    if ( isset( $result[ $k ] ) ) {
                        $v = $result[ $k ];
                        $this->local_cache[ $k ] = self::maybe_clone( $v );
                        $arr[ $key ] = $v;
                    } else {
                        unset( $this->local_cache[ $k ] );
                        $arr[ $key ] = false;
                    }
                }
                return $arr;
            } else {
                $arr = [];
                foreach ( $ks_to_keys as $k => $key ) {
                    if ( isset( $result[ $k ] ) ) {
                        $v = $result[ $k ];
                        $this->local_cache[ $k ] = self::maybe_clone( $v );
                        $arr[ $key ] = $v;
                    } elseif ( isset( $local[ $key ] ) ) {
                        $arr[ $key ] = $local[ $key ];
                    } else {
                        unset( $this->local_cache[ $k ] );
                        $arr[ $key ] = false;
                    }
                }
                return $arr;
            }
        }

        /**
         * Increment the value of a numeric cache item.
         * Returns false if the cache item does not exist
         * or is not numeric.
         */
        public function incr(
            int|string $key,
            int $offset = 1,
            string $group = '',
        ): int|false {
            $k = $this->cache_key( $key, $group );

            if ( isset( $this->non_persistent_groups[ $group ] ) ) {
                $v = $this->non_persistent_groups[ $group ][ $k ] ?? null;
                if ( is_numeric( $v ) ) {
                    $v += $offset;
                    $this->non_persistent_groups[ $group ][ $k ] = $v;
                    return $v;
                }
                return false;
            }

            $result = $this->mc->increment( $k, $offset );

            if ( $result === false ) {
                // We don't know the state of the memcached item
                unset( $this->local_cache[ $k ] );
                return false;
            } else {
                $this->local_cache[ $k ] = $result;
                return $result;
            }
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
            $result = $this->mc->replace( $k, $data, $expire );

            if ( $result === false ) {
                // We don't know the state of the memcached item
                unset( $this->local_cache[ $k ] );
                return false;
            } else {
                $this->local_cache[ $k ] = self::maybe_clone( $data );
                return true;
            }
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
            $result = $this->mc->set( $k, $data, $expire );

            if ( $result === false ) {
                // We don't know the state of the memcached item
                unset( $this->local_cache[ $k ] );
                return false;
            } else {
                $this->local_cache[ $k ] = self::maybe_clone( $data );
                return true;
            }
        }

        /**
         * Sets multiple items in the cache at once.
         *
         * $expire is ignored for non-persistent groups, because
         * they vanish at the end of script execution.
         */
        public function set_multiple(
            array $data,
            string $group = '',
            int $expire = 0,
        ): array {
            if ( empty( $data ) ) {
                return [];
            }

            if ( isset( $this->non_persistent_groups[ $group ] ) ) {
                $arr = [];
                foreach ( $data as $key => $v ) {
                    $k = $this->cache_key( $key, $group );
                    $data = self::maybe_clone( $data );
                    $this->non_persistent_groups[ $group ][ $k ] = $data;
                    $arr[ $key ] = true;
                }
                return $arr;
            }

            $expire = self::to_memcache_expiration( $expire );
            $d = [];
            foreach ( $data as $key => $v ) {
                $k = $this->cache_key( $key, $group );
                $d[ $k ] = $v;
            }

            $result = $this->mc->setMulti( $d, $expire );

            $arr = [];
            if ( $result ) {
                foreach ( $data as $key => $v ) {
                    $k = $this->cache_key( $key, $group );
                    $this->local_cache[ $k ] = self::maybe_clone( $v );
                    $arr[ $key ] = true;
                }
            } else {
                foreach ( $data as $key => $v ) {
                    $k = $this->cache_key( $key, $group );
                    // We don't know the state of the memcached items
                    unset( $this->local_cache[ $k ] );
                    $arr[ $key ] = false;
                }
            }

            return $arr;
        }

        /**
         * Returns true if we support the given feature.
         *
         * See https://developer.wordpress.org/reference/functions/wp_cache_supports/
         */
        public function supports(
            string $feature,
        ): bool {
            switch ( $feature ) {
                case 'add_multiple':
                case 'delete_multiple':
                case 'flush_runtime':
                case 'get_multiple':
                case 'set_multiple':
                    return true;
                default:
                    return false;
            }
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

    function wp_cache_add_multiple(
        array $data,
        string $group = '',
        int $expire = 0,
    ): array {
        global $wp_object_cache;
        return $wp_object_cache->add_multiple( $data, $group, $expire );
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

    function wp_cache_decr(
        int|string $key,
        int $offset = 1,
        string $group = '',
    ): int|false {
        global $wp_object_cache;
        return $wp_object_cache->decr( $key, $offset, $group );
    }

    function wp_cache_delete(
        int|string $key,
        string $group = '',
    ): bool {
        global $wp_object_cache;
        return $wp_object_cache->delete( $key, $group );
    }

    function wp_cache_delete_multiple(
        array $keys,
        string $group = '',
    ): array {
        global $wp_object_cache;
        return $wp_object_cache->delete_multiple( $keys, $group );
    }

    function wp_cache_flush(): bool {
        global $wp_object_cache;
        return $wp_object_cache->flush();
    }

    function wp_cache_flush_runtime(): bool {
        global $wp_object_cache;
        return $wp_object_cache->flush_runtime();
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

    function wp_cache_get_multiple(
        array $keys,
        string $group = '',
        bool $force = false,
    ): mixed {
        global $wp_object_cache;
        return $wp_object_cache->get_multiple( $keys, $group, $force );
    }

    function wp_cache_incr(
        int|string $key,
        int $offset = 1,
        string $group = '',
    ): int|false {
        global $wp_object_cache;
        return $wp_object_cache->incr( $key, $offset, $group );
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

    function wp_cache_set_multiple(
        array $data,
        string $group = '',
        int $expire = 0,
    ): array {
        global $wp_object_cache;
        return $wp_object_cache->set_multiple( $data, $group, $expire );
    }

    function wp_cache_supports(
        string $feature,
    ): bool {
        global $wp_object_cache;
        return $wp_object_cache->supports( $feature );
    }

    wp_cache_init();
}
