<?php

namespace StaticDeploy;

class URLDiscovery {
    private string $destination_host;
    private string $destination_url;
    private bool $discover_complete = false;
    private FileFiltering $file_filtering;
    private string $site_host;

    public function __construct() {
        $this->destination_url = untrailingslashit(
            apply_filters(
                Controller::getHookName( 'set_destination_url' ),
                Options::getValue( 'deploymentURL' )
            )
        );
        $this->destination_host = \Wa72\Url\Url::parse( $this->destination_url )->getHost();
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
                $urls = [];
                foreach ( $this->parseURLs( $path_info ) as $url ) {
                    $urls[ $url ] = true;
                }
                if ( empty( $urls ) ) {
                    yield $path_info;
                    continue;
                }
                $placeholders = array_fill( 0, count( $urls ), '(%s)' );
                $sql = "INSERT IGNORE INTO $table_name (path)
                  VALUES " . implode( ',', $placeholders );
                $query = $wpdb->prepare( $sql, ...array_keys( $urls ) );
                Controller::query( $query );
                yield $path_info;
            } else {
                yield $path_info;
            }
        }

        $this->discover_complete = true;
    }

    public function isURLLocal( \Wa72\Url\Url $base_url, \Wa72\Url\Url $url ): bool {
        if ( ! $url->getPath() && ! $url->getHost() ) {
            // fragment-only URL
            return false;
        }

        $scheme = $url->getScheme();

        if ( ! $scheme ) {
            $url = $url->makeAbsolute( $base_url );
            $scheme = $url->getScheme();
        }

        $path = $url->getPath();

        if ( ( 'http' === $scheme || 'https' === $scheme ) &&
                $url->equalsHost( $this->destination_host ) && 0 < strlen( $url->getPath() ) &&
                $this->file_filtering->pathLooksCrawlable( $path ) ) {
            return true;
        }

        return false;
    }

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

        $page_url = \Wa72\Url\Url::parse( $this->destination_url . $path_info->path );
        foreach ( ParseHTML::parseURLsString( $body ) as $url ) {
            $discovered_url = \Wa72\Url\Url::parse( $url );
            $discovered_url->setFragment( '' );
            $discovered_url->setQuery( '' );
            $is_local = $this->isURLLocal( $page_url, $discovered_url );
            $discovered_url->setHost( '' );
            $discovered_url->setScheme( '' );
            if ( $is_local ) {
                yield $discovered_url->write();
            }
        }
    }
}
