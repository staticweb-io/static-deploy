<?php declare(strict_types=1);

namespace StaticDeploy;

use PHPUnit\Framework\TestCase;

final class PathsToIgnoreTest extends TestCase {

    use ITTrait;

    /**
     * Test that detect step ignores path patterns
     * specified by the pathsToIgnore option.
     */
    public function testDetectIgnores(): void {
        $dir = ITEnv::getTestContentDir();
        $path = ITEnv::getTestContentPath();
        file_put_contents( $dir . '/Dckrfile', 'RUN' );
        file_put_contents( $dir . '/Dockerfile', 'RUN' );
        file_put_contents( $dir . '/THUMBS.DB', 'RUN' );
        file_put_contents( $dir . '/wildcard.bat', 'RUN' );
        mkdir( $dir . '/node_modules', 0775, true );
        file_put_contents( $dir . '/node_modules/hello.html', 'RUN' );

        $this->pluginCli( [ 'detect' ] );
        $lines = $this->pluginCli( [ 'detected_files', 'list' ] )['output'];
        $this->assertContains(
            $path . '/Dckrfile',
            $lines,
            'Sanity check that custom files are included'
        );
        $this->assertNotContains(
            $path . '/Dockerfile',
            $lines,
            'Filenames matching literals are ignored'
        );
        $this->assertNotContains(
            $path . '/THUMBS.DB',
            $lines,
            'Ignore patterns are not case-sensitive'
        );
        $this->assertNotContains(
            $path . '/wildcard.bat',
            $lines,
            'File extension glob patterns are ignored'
        );
        $this->assertNotContains(
            $path . '/node_modules/hello.html',
            $lines,
            'Directory literals are ignored'
        );
    }

    /**
     * Test that detect step ignores path patterns
     * matching vendored files.
     */
    public function testVendorIgnores(): void {
        $wordpress_dir = ITEnv::getWordPressDir();
        file_put_contents(
            $wordpress_dir . '/wp-content/plugins/static-deploy/vendor/findme.html',
            '<html>'
        );

        $this->pluginCli( [ 'detect' ] );
        $lines = $this->pluginCli( [ 'detected_files', 'list' ] )['output'];
        $this->assertNotContains(
            '/wp-content/plugins/static-deploy/vendor/findme.html',
            $lines,
            'Vendored files are ignored'
        );
    }
}
