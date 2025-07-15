<?php declare(strict_types=1);

namespace WP2Static;

use PHPUnit\Framework\TestCase;

final class PostProcessTest extends TestCase {

    use ITTrait;

    public function testProcessedSite(): void
    {
        $this->pluginCli( [ 'detect' ] );
        $this->pluginCli( [ 'crawl' ] );
        $this->pluginCli( [ 'post_process' ] );

        $content = $this->getProcessedFileContents( 'index.html' );
        $this->assertStringContainsString( 'Welcome to WordPress', $content );

        $content = $this->getProcessedFileContents( 'robots.txt' );
        $this->assertStringContainsString(
            'Sitemap: https://example.com/wp-sitemap.xml',
            $content
        );
    }
}
