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

        $rand = rand( 1, $total_weight );
        $current = 0;

        foreach ( $servers as $s ) {
            $weight = $s['weight'] ?? 1;
            $current += $weight;
            if ( $rand <= $current ) {
                return $s;
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
        int &$error_code = null,
        string &$error_message = null,
    ) {
        $server = self::getRandomServer( $mc );
        $host = $server['host'];
        $port = $server['port'];

        $sock = fsockopen(
            $host,
            $port,
            $error_code,
            $error_message,
            1.0
        );

        return $sock;
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
                'Failed to connect to Memcached at ' .
                $host . ':' . $port . ': ' . $error_code . ' ' . $error_message
            );
        }

        fwrite( $sock, $command . "\r\n" );

        $output = [];
        $count = 0;
        while ( ! feof( $sock ) ) {
            $line = fgets( $sock );
            if ( $line === false || rtrim( $line ) === 'END' ) {
                return;
            }
            yield $line;
        }

        fclose( $sock );
    }

    /**
     * Returns the output of lru_crawler metadump
     * See https://github.com/memcached/memcached/blob/master/doc/protocol.txt
     */
    public static function metadump(
        \Memcached $mc,
    ): \Iterator {
        yield from self::doCommand(
            $mc,
            'lru_crawler metadump all'
        );
    }
}
