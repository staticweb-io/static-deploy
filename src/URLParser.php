<?php

namespace StaticDeploy;

use GuzzleHttp\Psr7\Utils as Psr7Utils;

trait URLParser {

    /**
     * URL encoder according to RFC 3986
     *
     * Originally forked from https://github.com/VIPnytt/SitemapParser
     *
     * Returns a string containing the encoded URL with disallowed characters
     * converted to their percentage encodings.
     *
     * @link http://publicmind.in/blog/url-encoding/
     *
     * @param string $url
     * @return string
     */
    protected function urlEncode( $url ) {
        $reserved = [
            ':' => '!%3A!ui',
            '/' => '!%2F!ui',
            '?' => '!%3F!ui',
            '#' => '!%23!ui',
            '[' => '!%5B!ui',
            ']' => '!%5D!ui',
            '@' => '!%40!ui',
            '!' => '!%21!ui',
            '$' => '!%24!ui',
            '&' => '!%26!ui',
            "'" => '!%27!ui',
            '(' => '!%28!ui',
            ')' => '!%29!ui',
            '*' => '!%2A!ui',
            '+' => '!%2B!ui',
            ',' => '!%2C!ui',
            ';' => '!%3B!ui',
            '=' => '!%3D!ui',
            '%' => '!%25!ui',
        ];
        return (string) preg_replace(
            array_values( $reserved ),
            array_keys( $reserved ),
            rawurlencode( $url )
        );
    }

    /**
     * Validate URL
     */
    protected function urlValidate( string $url ): bool {
        if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
            try {
                Psr7Utils::uriFor( $url );
                return true;
            } catch ( \Exception ) {
                return false;
            }
        }

        return false;
    }
}
