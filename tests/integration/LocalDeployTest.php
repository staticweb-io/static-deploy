<?php declare(strict_types=1);

namespace StaticDeploy;

use PHPUnit\Framework\TestCase;

final class LocalDeployTest extends TestCase {

    use ITTrait;

    public function testLocalDeploy(): void {
        $this->pluginCli( [ 'addons', 'enable', 'static-deploy-addon-local' ] );
        $this->pluginCli( [ 'options', 'set', 'local_dirPath', '../localdeploy' ] );
        $this->pluginCli( [ 'full-workflow' ] );

        $content = $this->getLocalDeployFileContents( 'index.html' );
        $this->assertStringContainsString( 'Welcome to WordPress', $content );

        $content = $this->getLocalDeployFileContents( 'robots.txt' );
        $this->assertStringContainsString(
            'Sitemap: https://example.com/wp-sitemap.xml',
            $content
        );
    }

    public function testLocalDirectDeploy(): void {
        $this->pluginCli( [ 'addons', 'enable', 'static-deploy-addon-local' ] );
        $this->pluginCli( [ 'options', 'set', 'local_dirPath', '../localdeploy' ] );
        $this->pluginCli( [ 'direct-deploy' ] );

        $content = $this->getLocalDeployFileContents( 'index.html' );
        $this->assertStringContainsString( 'Welcome to WordPress', $content );

        $content = $this->getLocalDeployFileContents( 'robots.txt' );
        $this->assertStringContainsString(
            'Sitemap: https://example.com/wp-sitemap.xml',
            $content
        );
    }
}
