<?php

namespace WP2Static;

class ParseHTML {
    /**
     * Return URLs found in a DOMNode, recursively.
     *
     * @param \DOMNode $node
     * @return \Iterator<string>
     */
    public static function parseURLsDOMNode( \DOMNode $node ): \Iterator {
        // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
        foreach ( $node->childNodes as $child ) {
            if ( $child instanceof \DOMElement ) {
                // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
                $tag_name = strtolower( $child->tagName );
            }
            switch ( $tag_name ) {
                case 'a':
                case 'link':
                    yield $child->getAttribute( 'href' );
                    break;
                case 'img':
                case 'script':
                case 'source':
                    yield $child->getAttribute( 'src' );
                    break;
            }
            foreach ( self::parseURLsDOMNode( $child ) as $url ) {
                yield $url;
            }
        }
    }

    /**
     * Return an iterator of URLs parsed from the provided HTML
     *
     * @param string $html
     * @return \Iterator<string>
     */
    public static function parseURLsString( string $html ): \Iterator {
        $html5 = new \Masterminds\HTML5();
        $dom = $html5->loadHTML( $html );
        return self::parseURLsDOMNode( $dom );
    }
}
