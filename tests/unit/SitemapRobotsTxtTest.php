<?php

declare(strict_types=1);

namespace StaticDeploy;

use PHPUnit\Framework\TestCase;

class SitemapRobotsTxtTest extends TestCase {

    /**
     * @group ExternalRequests
     * @dataProvider generateDataForTest
     * @param string $url URL
     * @param string $body URL body content
     * @param array<string, array<string, string|null>> $result Test result to match
     */
    public function testRobotsTxt( string $url, string $body, array $result ): void {
        $parser = new SitemapParser( 'SitemapParser' );
        $this->assertInstanceOf( \StaticDeploy\SitemapParser::class, $parser );
        $parser->parse( $url, $body );
        $this->assertEquals( $result, $parser->getSitemaps() );
        $this->assertEquals( [], $parser->getURLs() );
    }

    /**
     * Generate test data
     *
     * @return array<int, array<string|array<string, array<string, string|null>>>>
     */
    public function generateDataForTest(): array {
        return [
            [
                'http://www.example.com/robots.txt',
                <<<'ROBOTSTXT'
User-agent: *
Disallow: /
#Sitemap:http://www.example.com/sitemap.xml.gz
  Sitemap:http://www.example.com/sitemap.xml#comment
ROBOTSTXT
                ,
                $result = [
                    'http://www.example.com/sitemap.xml' => [
                        'loc' => 'http://www.example.com/sitemap.xml',
                        'lastmod' => null,
                    ],
                ],
            ],
        ];
    }
}
