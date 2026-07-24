<?php

declare(strict_types=1);

namespace StaticDeploy;

use PHPUnit\Framework\TestCase;

class SitemapStrictTest extends TestCase {

    /**
     * @group ExternalRequests
     * @dataProvider generateDataForTest
     * @param string $url URL
     * @param string $body URL body content
     */
    public function testStrict( string $url, string $body ): void {
        $parser = new SitemapParser( 'SitemapParser', [] );
        $this->assertInstanceOf( \StaticDeploy\SitemapParser::class, $parser );
        $parser->parse( $url, $body );
        $this->assertEquals( [], $parser->getSitemaps() );
        $this->assertEquals( [], $parser->getURLs() );
    }

    /**
     * Generate test data
     *
     * @return array<int, string[]>
     */
    public function generateDataForTest(): array {
        return [
            [
                'http://www.example.com/sitemap.txt',
                <<<'TEXT'
http://www.example.com/sitemap1.xml
http://www.example.com/sitemap2.xml
http://www.example.com/sitemap3.xml.gz
http://www.example.com/page1/
http://www.example.com/page2/
http://www.example.com/page3/file.gz
TEXT
                ,
            ],
        ];
    }
}
