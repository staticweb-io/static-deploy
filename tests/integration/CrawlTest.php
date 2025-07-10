<?php declare(strict_types=1);

namespace WP2Static;

use PHPUnit\Framework\TestCase;

final class CrawlTest extends TestCase {

    use ITTrait;

    public function testIndexCrawl(): void
    {
        $this->runWpCli( [ 'wp2static', 'detect' ] );
        $this->runWpCli( [ 'wp2static', 'crawl' ] );

        $content = $this->getCrawledFile( 'index.html' );

        $this->assertStringContainsString( 'Welcome to WordPress', $content );
    }
}
