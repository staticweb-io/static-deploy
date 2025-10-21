<?php

namespace StaticDeploy;

use PHPUnit\Framework\TestCase;

class SitemapInvalidURLTest extends TestCase {

    /**
     * @group ExternalRequests
     * @dataProvider generateDataForTest
     * @param string $url URL
     */
    public function testInvalidURL( string $url ): void {
        $this->expectException( \StaticDeploy\StaticDeployException::class );
        $parser = new SitemapParser( 'SitemapParser' );
        $this->assertInstanceOf( \StaticDeploy\SitemapParser::class, $parser );
        $parser->parse( $url );
    }

    /**
     * Generate test data
     *
     * @return string[][]
     */
    public function generateDataForTest(): array {
        return [
            [
                'htt://www.example.c/',
            ],
            [
                'http:/www.example.com/',
            ],
            [
                'https//www.example.com/',
            ],
        ];
    }
}
