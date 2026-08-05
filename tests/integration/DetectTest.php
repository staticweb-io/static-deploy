<?php declare(strict_types=1);

namespace StaticDeploy;

use PHPUnit\Framework\TestCase;

final class DetectTest extends TestCase {

    use ITTrait;

    public function testCount(): void {
        $this->pluginCli( [ 'detect' ] );
        $line = $this->pluginCli( [ 'detected-files', 'count' ] )['final_line'];
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
        $lines = $this->pluginCli( [ 'detected-files', 'list' ] )['output'];
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
        $lines = $this->pluginCli( [ 'detected-files', 'list' ] )['output'];
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

    public function testExtraDetectedFiles(): void
    {
        $plugin = <<<'PHP'
<?php

/**
 * Plugin Name: Extra Detected Files Test
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

use StaticDeploy\PathInfo;

function extra_detected_files_filter ( $iter ) {
    yield from $iter;
    yield new PathInfo( '/extra-detected-file.html' );
}
add_filter( 'static_deploy_extra_detected_files', 'extra_detected_files_filter' );
PHP;
        $this->installStringPlugin( 'extra-detected-files-test', $plugin );

        $this->pluginCli( [ 'detect' ] );
        $lines = $this->pluginCli( [ 'detected-files', 'list' ] )['output'];
        $this->assertContains( '/extra-detected-file.html', $lines );
    }

    /**
     * A robots.txt or sitemap request that fails with a connection error
     * should be logged and skipped, not crash the whole detect run.
     */
    public function testDetectSurvivesUnreachableSitemapHost(): void
    {
        $plugin = <<<'PHP'
<?php

/**
 * Plugin Name: Unreachable Site URL Test
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

function unreachable_site_url_filter ( $info ) {
    // Nothing is listening on this port, so the plugin's HTTP requests for
    // robots.txt and sitemaps fail with a connection error. The site_url is
    // only used for these requests; the database-backed detectors strip the
    // host from permalinks and so are unaffected.
    $info['site_url'] = 'http://localhost:59999/';
    return $info;
}
add_filter( 'static_deploy_siteinfo', 'unreachable_site_url_filter' );
PHP;
        $this->installStringPlugin( 'unreachable-site-url-test', $plugin );

        // pluginCli asserts a zero exit code, so the previously fatal
        // connection error would fail here. Detection should still complete
        // using the database-backed detectors.
        $this->pluginCli( [ 'detect' ] );
        $line = $this->pluginCli( [ 'detected-files', 'count' ] )['final_line'];
        $this->assertGreaterThan( 0, (int) $line );
    }
}
