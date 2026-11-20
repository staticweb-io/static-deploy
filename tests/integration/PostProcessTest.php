<?php declare(strict_types=1);

namespace StaticDeploy;

use PHPUnit\Framework\TestCase;

final class PostProcessTest extends TestCase {

    use ITTrait;

    public function testProcessedSite(): void
    {
        $this->pluginCli( [ 'detect' ] );
        $this->pluginCli( [ 'crawl' ] );
        $this->pluginCli( [ 'post-process' ] );

        $content = $this->getProcessedFileContents( 'index.html' );
        $this->assertStringContainsString( 'Welcome to WordPress', $content );

        $content = $this->getProcessedFileContents( 'robots.txt' );
        $this->assertStringContainsString(
            'Sitemap: https://example.com/wp-sitemap.xml',
            $content
        );
    }

    /**
     * A file that no longer exists must be removed from the processed
     * site, or it would still be deployed.
     */
    public function testDeletedFileIsRemoved(): void {
        $path = ITEnv::getTestContentPath() . '/deleted.txt';
        $filename = ITEnv::getTestContentDir() . '/deleted.txt';
        file_put_contents( $filename, 'deleted' );
        $this->pluginCli( [ 'detect' ] );
        $this->pluginCli( [ 'crawl' ] );
        $this->pluginCli( [ 'post-process' ] );
        $this->assertSame( 'deleted', $this->getProcessedFileContents( $path ) );

        unlink( $filename );
        $this->pluginCli( [ 'crawl' ] );

        $this->assertFileDoesNotExist(
            ITEnv::getWordPressDir() . '/wp-content/uploads/'
            . $this->getOptionValue( 'processedSitePath' ) . $path
        );
    }
}
