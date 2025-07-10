<?php declare(strict_types=1);

namespace WP2Static;

use PHPUnit\Framework\TestCase;

final class CrawlTest extends TestCase
{

    public function testIndexCrawl(): void
    {
        ITHelp::runWpCli($this, ['wp2static', 'detect']);
        ITHelp::runWpCli($this, ['wp2static', 'crawl']);

        $content = ITHelp::getCrawledFile("index.html");

        $this->assertStringContainsString("Welcome to WordPress", $content);
    }
}
