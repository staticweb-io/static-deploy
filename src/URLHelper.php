<?php

namespace StaticDeploy;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriNormalizer;
use GuzzleHttp\Psr7\Utils as Psr7Utils;
use Psr\Http\Message\UriInterface;

class URLHelper {
    public static function makeAbsolutePath(
        string|UriInterface $uri,
    ): UriInterface {
        $uri = Psr7Utils::uriFor( $uri );

        try {
            if ( Uri::isAbsolute( $uri ) ) {
                return $uri->withScheme( '' )->withHost( '' )->withPort( null )->withUserInfo( '' );
            }
            if ( Uri::isNetworkPathReference( $uri ) ) {
                return $uri->withHost( '' )->withPort( null )->withUserInfo( '' );
            }
        } catch ( \Exception $e ) {
            $msg = 'Error making absolute path for ' . $uri . ': ' . $e->getMessage();
            if ( defined( 'STATIC_DEPLOY_ESCAPE_EXCEPTIONS' ) && STATIC_DEPLOY_ESCAPE_EXCEPTIONS ) {
                // We can't use the parent exception due to
                // https://github.com/WordPress/WordPress-Coding-Standards/issues/2447
                throw WsLog::ex( esc_html( $msg ) );
            }
            throw WsLog::ex( $msg, 0, $e );
        }

        return $uri;
    }

    public static function normalize(
        string|UriInterface $uri,
    ): UriInterface {
        return UriNormalizer::normalize(
            Psr7Utils::uriFor( $uri ),
            UriNormalizer::PRESERVING_NORMALIZATIONS
            | UriNormalizer::CAPITALIZE_PERCENT_ENCODING
            | UriNormalizer::CONVERT_EMPTY_PATH
            | UriNormalizer::DECODE_UNRESERVED_CHARACTERS
            | UriNormalizer::REMOVE_DEFAULT_HOST
            | UriNormalizer::REMOVE_DEFAULT_PORT
            | UriNormalizer::REMOVE_DOT_SEGMENTS
            | UriNormalizer::REMOVE_DUPLICATE_SLASHES
        );
    }

    public static function isSecure(): bool {
        if ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) {
            return true;
        }
        return isset( $_SERVER['SERVER_PORT'] ) && $_SERVER['SERVER_PORT'] === 443;
    }

    /**
     * Returns the current full URL including querystring
     */
    public static function getCurrent(): Uri {
        if ( ! isset( $_SERVER['HTTP_HOST'] ) ) {
            throw WsLog::ex( 'HTTP_HOST not set' );
        }

        $scheme = self::isSecure() ? 'https' : 'http';
        $url = $scheme . '://' . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );

        if ( isset( $_SERVER['REQUEST_URI'] ) ) {
            $url .= sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) );
        }

        $uri = Psr7Utils::uriFor( $url );

        // Only include port number if needed
        if ( isset( $_SERVER['SERVER_PORT'] )
        && ! in_array( $_SERVER['SERVER_PORT'], [ 80, 443 ], true ) ) {
            return $uri->withPort( (int) $_SERVER['SERVER_PORT'] );
        }

        return $uri;
    }

    /**
     * Returns a URL with given querystring modifications
     *
     * @param array<string|int> $changes  List of querystring params to set
     * @param string $url             A complete URL. Leave empty to use current URL
     * @return string                 The new URL
     * @throws StaticDeployException
     */
    public static function modifyUrl( array $changes, string $url = '' ): string {
        // If $url wasn't passed in, use the current url
        $uri = $url === '' ? self::getCurrent() : Psr7Utils::uriFor( $url );

        return Uri::withQueryValues( $uri, $changes );
    }

    /**
     * Takes either an http or https URL and returns a // protocol-relative URL
     *
     * @param string URL either http or https
     * @return string URL protocol-relative
     */
    public static function getProtocolRelativeURL( string $url ): string {
        return str_replace(
            [
                'https:',
                'http:',
            ],
            [
                '',
                '',
            ],
            $url
        );
    }

    public static function startsWithHash( string $url ): bool
    {
        // TODO: this won't fire for absolute URLs unless strip site_url first?
        // quickly abort for invalid URLs
        return $url[0] === '#';
    }

    public static function isMailto( string $url ): bool
    {
        return str_starts_with( $url, 'mailto:' );
    }

    public static function isProtocolRelative( string $url ): bool {
        if ( $url[0] !== '/' ) {
            return false;
        }
        return $url[1] === '/';
    }

    public static function protocolRelativeToAbsoluteURL(
        string $url,
        string $site_url
    ): string {

        return str_replace(
            self::getProtocolRelativeURL( $site_url ),
            $site_url,
            $url
        );
    }

    /**
     * Detect if a URL belongs to our WP site
     * We check against known internal prefixes and WP site host
     */
    public static function isInternalLink(
        string $url,
        string $site_url_host
    ): bool {
        // quickly match known internal links   ./   ../   /
        $first_char = $url[0];

        // TODO: // was false-positive for things like //fonts.google.com
        // add better detection for doc/site root relative protocol-rel URLs
        if ( $first_char === '.' ) {
            return true;
        }

        // site root relative URLs, like /alink
        if ( $url[0] === '/' && $url[1] !== '/' ) {
            return true;
        }

        $url_host = Psr7Utils::uriFor( $url )->getHost();
        return $url_host === $site_url_host;
    }
}
