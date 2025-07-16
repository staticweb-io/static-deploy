<?php declare(strict_types=1);

namespace StaticDeploy;

use PHPUnit\Framework\TestCase;

final class OptionsTest extends TestCase {

    use ITTrait;

    public function testOptionFilters(): void
    {
        $plugin_dir = ITEnv::getWordPressDir() . '/wp-content/plugins/options-test';
        exec( 'rm -rf ' . escapeshellarg( $plugin_dir ) );

        $this->assertEquals( '0', $this->getOptionValue( 'processQueueImmediately' ) );

        exec( 'mkdir -p ' . escapeshellarg( $plugin_dir ) );
        file_put_contents( $plugin_dir . '/options-test.php', $this->optionsTestFilters() );
        $this->wpCli( [ 'plugin', 'activate', 'options-test' ] );
        $this->assertEquals( '1', $this->getOptionValue( 'processQueueImmediately' ) );

        $this->wpCli( [ 'plugin', 'deactivate', 'options-test' ] );
        exec( 'rm -rf ' . escapeshellarg( $plugin_dir ) );
    }

    private function optionsTestFilters(): string
    {
        return <<<PHP
<?php

/**
 * Plugin Name: Options Test
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

function processQueueImmediately_filter ( $val ) {
    return '1';
}
add_filter( 'static_deploy_option_processQueueImmediately', 'processQueueImmediately_filter' );
PHP;
    }
}
