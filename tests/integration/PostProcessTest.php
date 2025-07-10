<?php declare(strict_types=1);

namespace WP2Static;

use PHPUnit\Framework\TestCase;

final class PostProcessTest extends TestCase {

    use ITTrait;

    public function testProcessedSite(): void
    {
        $this->wpCli( [ 'wp2static', 'detect' ] );
        $this->wpCli( [ 'wp2static', 'crawl' ] );
        $this->wpCli( [ 'wp2static', 'post_process' ] );

        $content = $this->getProcessedFileContents( 'index.html' );
        $this->assertStringContainsString( 'Welcome to WordPress', $content );

        $content = $this->getProcessedFileContents( 'robots.txt' );
        $this->assertStringContainsString(
            'Sitemap: https://example.com/wp-sitemap.xml',
            $content
        );
    }
}
