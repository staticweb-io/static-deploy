<?php
/*
    Crawler

    Crawls URLs in WordPressSite, saving them to StaticSite

*/

namespace WP2Static;

use WP2StaticGuzzleHttp\Client;
use WP2StaticGuzzleHttp\Psr7\Request;
use WP2StaticGuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use WP2StaticGuzzleHttp\Exception\RequestException;
use WP2StaticGuzzleHttp\Exception\TooManyRedirectsException;
use WP2StaticGuzzleHttp\Pool;
use WP2StaticGuzzleHttp\Promise;
use WP2StaticGuzzleHttp\Promise\FulfilledPromise;
use WP2StaticGuzzleHttp\Promise\PromiseInterface;

define( 'WP2STATIC_REDIRECT_CODES', [ 301, 302, 303, 307, 308 ] );

class Crawler {

    /**
     * @var Client
     */
    private $client;
    /**
     * @var string
     */
    private $site_path;

    /**
     * @var integer
     */
    private $crawled = 0;

    /**
     * @var integer
     */
    private $cache_hits = 0;

    /**
     * @var bool
     */
    private $use_crawl_cache = false;

    /**
     * Crawler constructor
     */
    public function __construct() {
        $this->site_path = rtrim( SiteInfo::getURL( 'site' ), '/' );

        $port_override = apply_filters(
            Controller::getHookName( 'curl_port' ),
            null
        );

        $base_uri = $this->site_path;

        if ( $port_override ) {
            $base_uri = "{$base_uri}:{$port_override}";
        }

        $opts = [
            'base_uri' => $base_uri,
            'verify' => false,
            'http_errors' => false,
            'allow_redirects' => [
                'max' => 2,
                // required to get effective_url
                'track_redirects' => true,
            ],
            'connect_timeout'  => 0,
            'timeout' => 600,
            'headers' => [
                'User-Agent' => apply_filters(
                    'wp2static_curl_user_agent',
                    'WP2Static.com',
                ),
            ],
        ];

        $auth_user = CoreOptions::getValue( 'basicAuthUser' );

        if ( $auth_user ) {
            $auth_password = CoreOptions::getValue( 'basicAuthPassword' );

            if ( $auth_password ) {
                WsLog::l( 'Using basic auth credentials to crawl' );
                $opts['auth'] = [ $auth_user, $auth_password ];
            }
        }

        $this->client = new Client( $opts );

        WsLog::l( 'Starting crawl.' );

        $this->use_crawl_cache = CoreOptions::getValue( 'useCrawlCaching' );

        WsLog::l( ( $this->use_crawl_cache ? 'Using' : 'Not using' ) . ' CrawlCache.' );
    }

    public static function wp2staticCrawl( string $crawler_slug ) : void {
        global $wpdb;

        if ( 'wp2static' === $crawler_slug ) {
            $crawler = new Crawler();
            $url_discovery = new URLDiscovery();

            $detected = CrawlQueue::getPathsIter();
            $last_now = $wpdb->get_var( 'SELECT NOW()' );
            $crawled = $crawler->crawlIter( $detected );
            $crawled = CrawlCache::remove404s( $crawled );
            $crawled = CrawlCache::writeFilesIter( $crawled );
            if ( $crawler->use_crawl_cache ) {
                $crawled = CrawlCache::addPathsIter( $crawled );
            }
            $crawled = $url_discovery->discoverURLs( $crawled );
            foreach ( $crawled as $_ ) { }

            $has_new = true;
            while ( $has_new ) {
                $detected = CrawlQueue::getPathsIter( $last_now );
                $last_now = $wpdb->get_var( 'SELECT NOW()' );
                $crawled = $crawler->crawlIter( $detected );
                $crawled = CrawlCache::writeFilesIter( $crawled );
                if ( $crawler->use_crawl_cache ) {
                    $crawled = CrawlCache::addPathsIter( $crawled );
                }
                $crawled = $url_discovery->discoverURLs( $crawled );
                $has_new = false;
                foreach ( $crawled as $_ ) {
                    $has_new = true;
                }
            }
            $crawler->crawlComplete();
        }
    }

    public function crawlComplete() : void {
        WsLog::l(
            "Crawling complete. $this->crawled crawled, $this->cache_hits skipped (cached)."
        );

        $args = [
            'crawled' => $this->crawled,
            'cache_hits' => $this->cache_hits,
        ];

        do_action( 'wp2static_crawling_complete', $args );
    }

    public function crawlPath(array $detected, array $site_urls) : PromiseInterface {
        $filename = $detected['filename'] ?? null;
        $path = $detected['path'];

        $absolute_uri = (new URL( $this->site_path . $path ))->get();
        try {
            if ( $filename ) {
                $request = new Request( 'HEAD', $absolute_uri );
            } else {
                $request = new Request( 'GET', $absolute_uri );
            }
        } catch ( \InvalidArgumentException $e ) {
            return new FulfilledPromise( $e );
        }

        $promise = $this->client->sendAsync( $request )->then(
            function ( $response ) use ( &$filename, &$path, &$site_urls ) {
                $status = $response->getStatusCode();

                $body = null;
                $redirect_to = null;
                if ( in_array( $status, WP2STATIC_REDIRECT_CODES ) ) {
                    $redirect_history =
                        $response->getHeaderLine( 'X-Guzzle-Redirect-History' );

                    if ( $redirect_history ) {
                        $redirects = explode( ', ', $redirect_history );
                        $effective_url = end( $redirects );
                    }

                    $redirect_to =
                        (string) str_replace( $site_urls, '', $effective_url );
                } else if ( ! $filename && $status !== 404 ) {
                    $body = (string) $response->getBody();
                }

                return [
                    'body' => $body,
                    'content_type' => $response->getHeaderLine( 'Content-Type' ),
                    'filename' => $filename,
                    'redirect_to' => $redirect_to,
                    'path' => $path,
                    'status' => $status,
                ];
            },
            function () use ( &$path ) {
                return [
                    'error' => 'Error crawling ' . $path,
                    'path' => $path
                ];
            }
        );

        return $promise;
    }

    public function crawlIter( \Iterator $path_iter ) : \Iterator {
        $site_host = parse_url( $this->site_path, PHP_URL_HOST );
        $site_port = parse_url( $this->site_path, PHP_URL_PORT );
        $site_host = $site_port ? $site_host . ":$site_port" : $site_host;
        $site_urls = [ "http://$site_host", "https://$site_host" ];

        $concurrency = intval( CoreOptions::getValue( 'crawlConcurrency' ) );
        $in_flight = [];

        $startNext = function() use ( &$in_flight, &$path_iter, &$site_urls ) {
            $detected = $path_iter->current();
            $path = $detected['path'];
            $in_flight[$path] = $this->crawlPath( $detected, $site_urls );
            $path_iter->next();
        };

        $i = 0;
        while ( $i++ < $concurrency && $path_iter->valid() ) {
            $startNext();
        }

        $last_log_time = microtime( true );

        $responses = function ( &$path_iter ) use ( &$in_flight, $last_log_time, $startNext ) {
            while ( ! empty( $in_flight ) ) {
                $response = Promise\Utils::any( $in_flight )->wait( true );

                if ( $response instanceof \Throwable ) {
                    WsLog::l( 'Error crawling: ' . $response->getMessage() );
                    return;
                }

                unset( $in_flight[ $response['path'] ] );
                
                $this->crawled++;
                $now = microtime( true );
                
                if ( $now - $last_log_time >= 60 ) {
                    WsLog::l( 'Crawled ' . $response['path'] );
                    $notice = "Crawling progress: $this->crawled crawled," .
                                " $this->cache_hits skipped (cached).";
                    WsLog::l( $notice );
                    $last_log_time = microtime( true );
                }

                if ( $response['error'] ?? false ) {
                    WsLog::l( $response['error'] );
                } else {
                    yield $response;
                }
                
                if ( $path_iter->valid() ) {
                    $startNext();
                }
            }
        };

        return $responses( $path_iter );
    }
}
