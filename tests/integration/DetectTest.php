<?php declare(strict_types=1);

namespace WP2Static;

use PHPUnit\Framework\TestCase;

final class DetectTest extends TestCase {

    use ITTrait;

    public function testCount(): void {
        $this->wpCli( [ 'wp2static', 'detect' ] );
        $lines = $this->wpCli( [ 'wp2static', 'crawl_queue', 'count' ] )['output'];
        $count = (int) $lines[ count( $lines ) - 1 ];
        $this->assertGreaterThan( 1000, $count );
    }

    /**
     * Test that the crawl queue list contains expected URL
     */
    public function testList(): void {
        $this->wpCli( [ 'wp2static', 'detect' ] );
        $lines = $this->wpCli( [ 'wp2static', 'crawl_queue', 'list' ] )['output'];
        $this->assertContains( '/hello-world/', $lines );
    }

    public function testSitemapDoubleSlashes(): void
    {
        $robotstxt = ITEnv::getWordPressDir() . '/robots.txt';
        $robotstxt_content = 'User-agent: *
Disallow: /wp-admin/
Allow: /wp-admin/admin-ajax.php

Sitemap: http://localhost:8888//wp-sitemap.xml';
        file_put_contents( $robotstxt, $robotstxt_content );

        $this->wpCli( [ 'wp2static', 'detect' ] );
        $this->wpCli( [ 'wp2static', 'crawl' ] );

        $content = $this->getCrawledFileContents( 'robots.txt' );
        $this->assertStringContainsString(
            'http://localhost:8888//wp-sitemap.xml',
            $content
        );

        $content = $this->getCrawledFileContents( 'wp-sitemap-posts-post-1.xml' );
        $this->assertStringContainsString(
            'http://localhost:8888/hello-world/',
            $content
        );

        exec( 'rm -f ' . escapeshellarg( $robotstxt ) );
    }
}
