<?php declare(strict_types=1);

namespace StaticDeploy;

use PHPUnit\Framework\TestCase;

final class CrawlTest extends TestCase {

    use ITTrait;

    public function testIndexCrawl(): void
    {
        $this->pluginCli( [ 'detect' ] );
        $this->pluginCli( [ 'crawl' ] );

        $content = $this->getCrawledFileContents( 'index.html' );
        $this->assertStringContainsString( 'Welcome to WordPress', $content );

        $content = $this->getCrawledFileContents( 'robots.txt' );
        $this->assertStringContainsString(
            'Sitemap: http://localhost:8888/wp-sitemap.xml',
            $content
        );
    }
}
