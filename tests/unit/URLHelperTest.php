<?php

namespace StaticDeploy;

use PHPUnit\Framework\TestCase;

final class URLHelperTest extends TestCase {


    /**
     * @dataProvider protocolRelativeURLProvider
     */
    public function testgetProtocolRelativeURL( string $url, string $expectation ): void {
        $protocol_relative_url = URLHelper::getProtocolRelativeURL( $url );

        $this->assertEquals(
            $expectation,
            $protocol_relative_url
        );
    }

    /**
     * @return array<string, string[]>
     */
    public function protocolRelativeURLProvider(): array {
        return [
            'http link becomes protocol relative' => [
                'http://myplaceholderdomain.com/some-post/',
                '//myplaceholderdomain.com/some-post/',
            ],
            'https link becomes protocol relative' => [
                'https://myplaceholderdomain.com/some-post/',
                '//myplaceholderdomain.com/some-post/',
            ],
            'doc relative link remains unchanged' => [
                'some-post/',
                'some-post/',
            ],
            'protocol relative link remains unchanged' => [
                '//some-post/',
                '//some-post/',
            ],
            'site root relative link remains unchanged' => [
                '/some-post/',
                '/some-post/',
            ],
            'url containing http but no colon remains unchanged' => [
                'myplaceholderdomain.com/some-post-with-http-in-url/',
                'myplaceholderdomain.com/some-post-with-http-in-url/',
            ],
        ];
    }

    /**
     * @dataProvider startsWithHashProvider
     */
    public function teststartsWithHash( string $url, bool $expectation ): void {
        $this->assertEquals(
            $expectation,
            URLHelper::startsWithHash( $url )
        );
    }

    /**
     * @return array<string, array<string|bool>>
     */
    public function startsWithHashProvider(): array {
        return [
            'doc relative url starting with hash returns true' => [
                '#somehash',
                true,
            ],
            'site root relative url starting with / returns false' => [
                '/someurl',
                false,
            ],
        ];
    }

    /**
     * @dataProvider isMailtoProvider
     */
    public function testisMailto( string $url, bool $expectation ): void {
        $this->assertEquals(
            $expectation,
            URLHelper::isMailto( $url )
        );
    }

    /**
     * @return array<string, array<string|bool>>
     */
    public function isMailtoProvider(): array {
        return [
            'doc relative url starting with mailto returns true' => [
                'mailto:leon@wp2static.com',
                true,
            ],
            'site root relative url starting with / returns false' => [
                '/someurl',
                false,
            ],
        ];
    }

    /**
     * @dataProvider isProtocolRelativeProvider
     */
    public function testisProtocolRelative( string $url, bool $expectation ): void {
        $this->assertEquals(
            $expectation,
            URLHelper::isProtocolRelative( $url )
        );
    }

    /**
     * @return array<string, array<string|bool>>
     */
    public function isProtocolRelativeProvider(): array {
        return [
            'protocol relative URL returns true' => [
                '//mydomain.com/animage.jpg',
                true,
            ],
            'site root relative url starting with / returns false' => [
                '/someurl',
                false,
            ],
        ];
    }

    /**
     * @dataProvider protocolRelativeToAbsoluteURLProvider
     */
    public function testprotocolRelativeToAbsoluteURL(
        string $url,
        string $site_url,
        string $expectation
    ): void {
        $url = URLHelper::protocolRelativeToAbsoluteURL( $url, $site_url );

        $this->assertEquals(
            $expectation,
            $url
        );
    }

    /**
     * @return array<string, string[]>
     */
    public function protocolRelativeToAbsoluteURLProvider(): array {
        return [
            'same domain host returns abs url' => [
                '//mydomain.com/animage.jpg',
                'http://mydomain.com/',
                'http://mydomain.com/animage.jpg',
            ],
            'different domain host returns unchanged protocol rel url' => [
                '//mydomain.com/animage.jpg',
                'http://example.com/',
                '//mydomain.com/animage.jpg',
            ],
        ];
    }

    /**
     * @dataProvider isInternalLinkProvider
     */
    public function testisInternalLink(
        string $url,
        string $site_url_host,
        bool $expectation
    ): void {
        $this->assertEquals(
            $expectation,
            URLHelper::isInternalLink( $url, $site_url_host )
        );
    }

    /**
     * @return array<string, array<string|bool>>
     */
    public function isInternalLinkProvider(): array {
        return [
            'first char /, 2nd char other is site root rel internal link' => [
                '/somelink',
                'anyhost.com',
                true,
            ],
            'starts with . is internal' => [
                './somelink',
                'anyhost.com',
                true,
            ],
            'matching URL hosts is internal' => [
                'http://mywpsite.com/some/image.jpg',
                'mywpsite.com',
                true,
            ],
            'different URL hosts is false' => [
                'http://someremsite.com/some/image.jpg',
                'mywpsite.com',
                false,
            ],
        ];
    }
}
