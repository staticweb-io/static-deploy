<?php

namespace StaticDeploy;

use GuzzleHttp\Psr7\Exception\MalformedUriException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Utils as Psr7Utils;
use Psr\Http\Message\UriInterface;

class URLDiscovery {
    private string $destination_host;
    private string $destination_url;
    private FileFiltering $file_filtering;

    public function __construct() {
        $this->destination_url = untrailingslashit(
            apply_filters(
                Controller::getHookName( 'set_destination_url' ),
                Options::getValue( 'deploymentURL' )
            )
        );
        $this->destination_host = Psr7Utils::uriFor( $this->destination_url )->getHost();
        $this->file_filtering = new FileFiltering();
    }

    /**
     * @param \Iterator<PathInfo>
     * @return \Iterator<PathInfo>
     */
    public function discoverURLs( \Iterator $iterator ): \Iterator {
        global $wpdb;

        $table_name = DetectedFiles::getTableName();

        foreach ( $iterator as $path_info ) {
            if ( isset( $path_info->content_type )
            && str_starts_with( $path_info->content_type, 'text/html' ) ) {
                $page_uri = Psr7Utils::uriFor( $path_info->path );
                // Store indexed for easy deduplication
                $uris = [];
                foreach ( $this->parseURLs( $path_info ) as $url ) {
                    $uri = Psr7Utils::uriFor( $url );
                    $uri = URIResolver::resolve( $page_uri, $uri );
                    $uri = URLHelper::makeAbsolutePath( $uri );

                    $msg = PathInfo::pathErrorMessage( $uri );
                    if ( $msg ) {
                        WsLog::w(
                            'Skipping invalid path found in detected files table: '
                            . "$uri ($msg)",
                        );
                        continue;
                    }

                    $uris[ (string) $uri ] = true;
                }
                if ( empty( $uris ) ) {
                    yield $path_info;
                    continue;
                }
                $placeholders = array_fill( 0, count( $uris ), '(%s)' );
                $sql = "INSERT IGNORE INTO $table_name (path)
                  VALUES " . implode( ',', $placeholders );
                $query = $wpdb->prepare( $sql, ...array_keys( $uris ) );
                Db::query( $query );
                yield $path_info;
            } else {
                yield $path_info;
            }
        }
    }

    public function isURLLocal(
        UriInterface $base_uri,
        UriInterface $uri,
    ): bool {
        if ( Uri::isSameDocumentReference( $uri ) ) {
            return false;
        }

        if ( ! Uri::isAbsolute( $uri ) ) {
            $uri = UriResolver::resolve( $base_uri, $uri );
            $path = $uri->getPath();
            return $this->file_filtering->pathLooksCrawlable( $path );
        }

        $path = $uri->getPath();
        $scheme = $uri->getScheme();

        if ( ( 'http' === $scheme || 'https' === $scheme )
        && $uri->getHost() === $this->destination_host
        && $path !== ''
        && $this->file_filtering->pathLooksCrawlable( $path ) ) {
            return true;
        }

        return false;
    }

    /**
     * @return \Iterator<string>
     */
    public function parseURLs( PathInfo $path_info ): \Iterator {
        $body = null;
        if ( isset( $path_info->body ) ) {
            $body = $path_info->body;
        } elseif ( $path_info->filename ) {
            $body = file_get_contents( $path_info->filename );
        }

        if ( ! $body ) {
            return;
        }

        $page_url = Psr7Utils::uriFor( $this->destination_url . $path_info->path );
        foreach ( ParseHTML::parseURLsString( $body ) as $url ) {
            try {
                $discovered_url = Psr7Utils::uriFor( $url )->withFragment( '' )->withQuery( '' );
            } catch ( MalformedUriException $e ) {
                if ( STATIC_DEPLOY_DEBUG ) {
                    WsLog::d( 'Skipping invalid URL discovered: ' . $url );
                }
                continue;
            }

            if ( $this->isURLLocal( $page_url, $discovered_url ) ) {
                $discovered_url = URLHelper::makeAbsolutePath( $discovered_url );
                yield (string) $discovered_url;
            }
        }
    }
}
