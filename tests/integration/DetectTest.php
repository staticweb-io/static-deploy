<?php declare(strict_types=1);

namespace StaticDeploy;

use PHPUnit\Framework\TestCase;

final class DetectTest extends TestCase {

    use ITTrait;

    public function testCount(): void {
        $this->pluginCli( [ 'detect' ] );
        $line = $this->pluginCli( [ 'detected_files', 'count' ] )['final_line'];
        $this->assertGreaterThan( 1000, (int) $line );
    }

    /**
     * Test that the detected files list includes
     * content that we added.
     */
    public function testIncludesAddedContent(): void {
        $dir = ITEnv::getTestContentDir();
        file_put_contents( $dir . '/new-content.html', 'New Content' );

        $this->pluginCli( [ 'detect' ] );
        $lines = $this->pluginCli( [ 'detected_files', 'list' ] )['output'];
        $this->assertContains(
            ITEnv::getTestContentPath() . '/new-content.html',
            $lines
        );
    }

    /**
     * Test that the detected files list contains expected URL
     */
    public function testList(): void {
        $this->pluginCli( [ 'detect' ] );
        $lines = $this->pluginCli( [ 'detected_files', 'list' ] )['output'];
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

        $this->pluginCli( [ 'detect' ] );
        $this->pluginCli( [ 'crawl' ] );

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
