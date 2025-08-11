<?php
/*
    Crawler

    Crawls URLs in WordPressSite, saving them to StaticSite

*/

namespace StaticDeploy;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TooManyRedirectsException;
use GuzzleHttp\Pool;
use GuzzleHttp\Promise;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;

define( 'STATIC_DEPLOY_REDIRECT_CODES', [ 301, 302, 303, 307, 308 ] );

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

    private int $concurrency;

    /**
     * Crawler constructor
     */
    public function __construct(
        public CrawlConfig $crawl_config,
    ) {
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
            'allow_redirects' => false,
            'connect_timeout'  => 0,
            'timeout' => 600,
            'headers' => [
                'User-Agent' => apply_filters(
                    Controller::getHookName( 'curl_user_agent' ),
                    'staticdeploy.com',
                ),
            ],
        ];

        $auth_user = Options::getValue( 'basicAuthUser' );

        if ( $auth_user ) {
            $auth_password = Options::getValue( 'basicAuthPassword' );

            if ( $auth_password ) {
                WsLog::l( 'Using basic auth credentials to crawl' );
                $opts['auth'] = [ $auth_user, $auth_password ];
            }
        }

        $this->client = new Client( $opts );

        WsLog::l( 'Starting crawl.' );
    }

    public static function crawl(
        string $crawler_slug,
        CrawlConfig $crawl_config,
    ): void {
        global $wpdb;

        if ( 'static-deploy' === $crawler_slug ) {
            $crawler = new Crawler( $crawl_config );
            $url_discovery = new URLDiscovery();

            $detected = DetectedFiles::getPathsIter();
            $last_now = $wpdb->get_var( 'SELECT NOW()' );
            $crawled = $crawler->crawlIter( $detected );
            $crawled = CrawledFiles::removeOutdated( $crawled );
            $crawled = $crawler->writeFilesIter( $crawled );
            $crawled = CrawledFiles::addPathsIter( $crawled );
            $crawled = $url_discovery->discoverURLs( $crawled );
            foreach ( $crawled as $_ ) {
                // Intentionally empty to consume the iterator
                continue;
            }

            $has_new = true;
            while ( $has_new ) {
                $detected = DetectedFiles::getPathsIter( $last_now );
                $last_now = $wpdb->get_var( 'SELECT NOW()' );
                $crawled = $crawler->crawlIter( $detected );
                $crawled = $crawler->writeFilesIter( $crawled );
                $crawled = CrawledFiles::addPathsIter( $crawled );
                $crawled = $url_discovery->discoverURLs( $crawled );
                $has_new = false;
                foreach ( $crawled as $_ ) {
                    $has_new = true;
                }
            }
            $crawler->crawlComplete();
        }
    }

    public function crawlComplete(): void {
        WsLog::l(
            "Crawling complete. $this->crawled crawled, $this->cache_hits skipped (cached)."
        );

        $args = [
            'crawled' => $this->crawled,
            'cache_hits' => $this->cache_hits,
        ];

        do_action( Controller::getHookName( 'crawling_complete' ), $args );
    }

    public function crawlPath( PathInfo $detected, array $site_urls ): PromiseInterface {
        $absolute_uri = URLHelper::normalize( $this->site_path . $detected->path );
        try {
            if ( $detected->filename ) {
                $request = new Request( 'HEAD', $absolute_uri );
            } else {
                $request = new Request( 'GET', $absolute_uri );
            }
        } catch ( \InvalidArgumentException $e ) {
            return new FulfilledPromise( $e );
        }

        return $this->client->sendAsync( $request )->then(
            function ( $response ) use ( &$detected, &$site_urls ) {
                $status = $response->getStatusCode();

                if ( in_array( $status, STATIC_DEPLOY_REDIRECT_CODES ) ) {
                    $location = $response->getHeaderLine( 'Location' );
                    $redirect_to = (string) str_replace( $site_urls, '', $location );
                    $path_info = new PathInfo(
                        $detected->path,
                        redirect_to: $redirect_to,
                        status: $status,
                    );

                    if ( STATIC_DEPLOY_DEBUG ) {
                        WsLog::d(
                            'Crawler encountered redirect from '
                            . $absolute_uri . ' to ' . $redirect_to
                        );
                    }
                } elseif ( $status === 404 ) {
                    $path_info = new PathInfo(
                        $detected->path,
                        status: $status,
                    );
                } elseif ( $detected->filename ) {
                    $path_info = new PathInfo(
                        $detected->path,
                        content_type: $response->getHeaderLine( 'Content-Type' ),
                        filename: $detected->filename,
                        status: $status,
                    );
                } else {
                    $body = (string) $response->getBody();
                    $path_info = new PathInfo(
                        $detected->path,
                        body: $body,
                        content_type: $response->getHeaderLine( 'Content-Type' ),
                        status: $status,
                    );
                }

                return [
                    'path' => $path_info,
                ];
            },
            function () use ( &$detected ) {
                return [
                    'error' => 'Error crawling ' . $detected->path,
                    'path' => $detected,
                ];
            }
        );
    }

    /**
     * @param \Iterator<PathInfo> $path_iter
     * @return \Iterator<PathInfo>
     */
    public function crawlIter( \Iterator $path_iter ): \Iterator {
        if ( ! isset( $this->concurrency ) ) {
            $this->concurrency = intval( Options::getValue( 'crawlConcurrency' ) );
        }

        $path_hash_prefix = $this->crawl_config->path_hash_prefix;
        if ( $path_hash_prefix !== null && $path_hash_prefix !== '' ) {
            $path_iter = new \CallbackFilterIterator(
                $path_iter,
                fn( $path ) => str_starts_with( md5( (string) $path->path ), $path_hash_prefix )
            );
            $path_iter->rewind();
        }

        $site_host = parse_url( $this->site_path, PHP_URL_HOST );
        $site_port = parse_url( $this->site_path, PHP_URL_PORT );
        $site_host = $site_port ? $site_host . ":$site_port" : $site_host;
        $site_urls = [ "http://$site_host", "https://$site_host" ];

        $in_flight = [];
        $start_next = function () use ( &$in_flight, &$path_iter, &$site_urls ) {
            $detected = $path_iter->current();
            $path = $detected->path;
            $in_flight[ $path ] = $this->crawlPath( $detected, $site_urls );
            $path_iter->next();
        };

        $i = 0;
        while ( $i++ < $this->concurrency && $path_iter->valid() ) {
            $start_next();
        }

        $last_log_time = microtime( true );

        $responses = function ( &$path_iter ) use ( &$in_flight, $last_log_time, $start_next ) {
            while ( ! empty( $in_flight ) ) {
                $response = Promise\Utils::any( $in_flight )->wait( true );

                if ( $response instanceof \Throwable ) {
                    WsLog::l( 'Error crawling: ' . $response->getMessage() );
                    return;
                }

                unset( $in_flight[ $response['path']->path ] );

                ++$this->crawled;
                $now = microtime( true );

                if ( $now - $last_log_time >= 60 ) {
                    WsLog::l( 'Crawled ' . $response['path']->path );
                    $notice = "Crawling progress: $this->crawled crawled," .
                                " $this->cache_hits skipped (cached).";
                    WsLog::l( $notice );
                    $last_log_time = microtime( true );
                }

                if ( $response['error'] ?? false ) {
                    WsLog::w( $response['error'] );
                } else {
                    yield $response['path'];
                }

                if ( $path_iter->valid() ) {
                    $start_next();
                }
            }
        };

        return $responses( $path_iter );
    }

    /**
     * Write path contents to the crawled site dir,
     * returning an Iterator of the same paths.
     *
     * @param \Iterator<PathInfo> $paths
     * @return \Iterator<PathInfo>
     */
    public function writeFilesIter( \Iterator $paths ): \Iterator {
        foreach ( $paths as $path ) {
            $is_cacheable = true;

            if ( $path->status === 404 ) {
                $is_cacheable = false;
            } elseif ( in_array( $path->status, STATIC_DEPLOY_REDIRECT_CODES ) ) {
                $is_cacheable = false;
            }

            $content_hash = $path->getContentHash();
            if ( $is_cacheable
            && $content_hash
            && CrawledFiles::getUrl( $path->path, $content_hash ) ) {
                ++$this->cache_hits;
            } elseif ( $path->body ) {
                StaticSite::add( $path );
            }

            yield $path;
        }
    }
}
