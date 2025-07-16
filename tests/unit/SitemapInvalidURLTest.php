<?php

namespace StaticDeploy;

use PHPUnit\Framework\TestCase;

class SitemapInvalidURLTest extends TestCase {

    /**
     * @group ExternalRequests
     * @dataProvider generateDataForTest
     * @param string $url URL
     */
    public function testInvalidURL( $url ) {
        $this->expectException( 'StaticDeploy\StaticDeployException' );
        $parser = new SitemapParser( 'SitemapParser' );
        $this->assertInstanceOf( 'StaticDeploy\SitemapParser', $parser );
        $parser->parse( $url );
    }

    /**
     * Generate test data
     *
     * @return array
     */
    public function generateDataForTest() {
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
