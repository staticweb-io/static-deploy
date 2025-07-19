<?php

namespace StaticDeploy;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

class DetectSitemapsURLs {

    /**
     * Detect Authors URLs
     *
     * @return \Iterator<array> list of URLs
     * @throws StaticDeployException
     */
    public static function detect( string $wp_site_url ): \Iterator {
        if ( STATIC_DEPLOY_DEBUG ) {
            WsLog::d( 'Detecting sitemap URLs' );
        }

        $opts = [
            'http_errors' => false,
            'verify' => false,
        ];

        $auth_user = Options::getValue( 'basicAuthUser' );

        if ( $auth_user ) {
            $auth_password = Options::getValue( 'basicAuthPassword' );

            if ( $auth_password ) {
                if ( STATIC_DEPLOY_DEBUG ) {
                    WsLog::d( 'Using basic auth credentials to crawl' );
                }
                $opts['auth'] = [ $auth_user, $auth_password ];
            }
        }

        $parser = new SitemapParser(
            'staticdeploy.com',
            [
                'guzzle' => $opts,
                'strict' => false,
            ]
        );

        $site_path = rtrim( SiteInfo::getURL( 'site' ), '/' );

        $port_override = apply_filters(
            Controller::getHookName( 'curl_port' ),
            null
        );

        $base_uri = $site_path;

        if ( $port_override ) {
            $base_uri = "{$base_uri}:{$port_override}";
        }

        $client = new Client(
            [
                'verify' => false,
                'http_errors' => false,
                'allow_redirects' => [
                    'max' => 1,
                    // required to get effective_url
                    'track_redirects' => true,
                ],
                'connect_timeout'  => 0,
                'timeout' => 600,
                'headers' => [
                    'User-Agent' => apply_filters(
                        Controller::getHookName( 'curl_user_agent' ),
                        'staticdeploy.com',
                    ),
                ],
            ]
        );

        $headers = [];

        if ( $auth_user && $auth_password ) {
            $headers['auth'] = [ $auth_user, $auth_password ];
        }

        $request = new Request( 'GET', $base_uri . '/robots.txt', $headers );

        $response = $client->send( $request );

        $robots_exists = $response->getStatusCode() === 200;

        try {
            $sitemaps = [];

            // if robots exists, parse for possible sitemaps
            if ( $robots_exists ) {
                if ( STATIC_DEPLOY_DEBUG ) {
                    WsLog::d( 'Parsing robots.txt for sitemaps' );
                }
                $robotsmaps = $parser->parseRobotstxt( $response->getBody()->getContents() );
                foreach ( $robotsmaps as $map ) {
                    $sitemaps[ $map ] = [];
                }
                if ( STATIC_DEPLOY_DEBUG && count( $sitemaps ) > 0 ) {
                    WsLog::d( 'Found sitemaps: ' . implode( ', ', array_keys( $sitemaps ) ) );
                }
            }

            // if no sitemaps add known sitemaps
            if ( $sitemaps === [] ) {
                if ( STATIC_DEPLOY_DEBUG ) {
                    WsLog::d( 'No sitemaps found in robots.txt. Using default sitemaps.' );
                }
                $sitemaps = [
                    // we're assigning empty arrays to match sitemaps library
                    'sitemap.xml' => [], // normal sitemap
                    'sitemap_index.xml' => [], // yoast sitemap
                    'wp-sitemap.xml' => [], // default WordPress sitemap
                ];
            }

            foreach ( array_keys( $sitemaps ) as $sitemap ) {
                if ( ! is_string( $sitemap ) ) {
                    continue;
                }

                $sitemap = '/' . str_replace(
                    $wp_site_url,
                    '',
                    $sitemap
                );

                if ( STATIC_DEPLOY_DEBUG ) {
                    WsLog::d( 'Detecting URLs from sitemap: ' . $sitemap );
                }

                $request = new Request( 'GET', $base_uri . $sitemap, $headers );

                $response = $client->send( $request );

                $status_code = $response->getStatusCode();

                if ( $status_code === 200 ) {
                    yield [ 'url' => $sitemap ];

                    $parser->parse( $wp_site_url . $sitemap );

                    $extract_sitemaps = $parser->getSitemaps();

                    foreach ( $extract_sitemaps as $url => $tags ) {
                        $url = '/' . str_replace(
                            $wp_site_url,
                            '',
                            $url
                        );
                        yield [ 'url' => $url ];
                    }
                }
            }
        } catch ( StaticDeployException $e ) {
            throw WsLog::ex( $e->getMessage(), 0, $e );
        }
    }
}
