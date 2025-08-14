<?php declare(strict_types=1);

namespace StaticDeploy;

class Memcached {
    /*
     * Returns the \Memcached instance used by the
     * object cache. Returns null if the object cache
     * is not loaded or is not managed by this plugin.
     *
     * @param bool $required If true, throw an exception
     * if unable to find a \Memcached instance.
     */
    public static function getMemcached(
        $required = false,
    ): ?\Memcached {
        global $wp_object_cache;
        if ( class_exists( 'StaticDeployMemcached' )
        && $wp_object_cache instanceof \StaticDeployMemcached ) {
            return $wp_object_cache->mc;
        }
        if ( $required ) {
            throw WsLog::ex( 'Memcached is not enabled or is not managed by this plugin.' );
        }
        return null;
    }

    /**
     * Returns a server at random, respecting weight.
     *
     * Returns an array of the form:
     * [
     *     'host' => '127.0.0.1',
     *     'port' => 11211,
     *     'weight' => 20,
     * ]
     */
    public static function getRandomServer(
        \Memcached $mc,
    ): array {
        $servers = $mc->getServerList();
        $total_weight = 0;

        foreach ( $servers as $s ) {
            $total_weight += $s['weight'] ?? 1;
        }

        $rand = mt_rand( 1, $total_weight );
        $current = 0;

        foreach ( $servers as $server ) {
            $weight = $server['weight'] ?? 1;
            $current += $weight;
            if ( $rand <= $current ) {
                return $server;
            }
        }
    }

    /**
     * Returns a socket to a server in the memcached pool
     *
     * @param int &$error_code Error code passed to fsockopen
     * @param string &$error_message Error message passed to fsockopen
     */
    public static function getSocket(
        \Memcached $mc,
        ?int &$error_code = null,
        ?string &$error_message = null,
    ) {
        $server = self::getRandomServer( $mc );
        $host = $server['host'];
        $port = $server['port'];

        return fsockopen(
            $host,
            $port,
            $error_code,
            $error_message,
            1.0
        );
    }

    /**
     * Execute a command over the memcached text protocol.
     * Returns an \Iterator of the result lines.
     */
    public static function doCommand(
        \Memcached $mc,
        string $command,
    ): \Iterator {
        $error_code = null;
        $error_message = null;
        $sock = self::getSocket( $mc, $error_code, $error_message );
        if ( ! $sock ) {
            throw WsLog::ex(
                'Failed to connect to Memcached: ' .
                $error_code . ' ' . $error_message
            );
        }

        fwrite( $sock, $command . "\r\n" );
        while ( ! feof( $sock ) ) {
            $line = fgets( $sock );
            if ( $line === false || rtrim( $line ) === 'END' ) {
                return;
            }
            yield $line;
        }

        fclose( $sock );
    }

    public static function parseItem( string $line ): array {
        $line = trim( $line );
        $pairs = explode( ' ', $line );
        $arr = [];
        foreach ( $pairs as $pair ) {
            [$k, $v] = explode( '=', $pair, 2 );
            $arr[ $k ] = $v;
        }
        return $arr;
    }

    /**
     * Returns the output of lru_crawler metadump
     * See https://github.com/memcached/memcached/blob/master/doc/protocol.txt
     */
    public static function metadump(
        \Memcached $mc,
    ): \Iterator {
        $lines = self::doCommand(
            $mc,
            'lru_crawler metadump all'
        );
        foreach ( $lines as $line ) {
            yield self::parseItem( $line );
        }
    }
}
