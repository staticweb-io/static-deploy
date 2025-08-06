<?php declare(strict_types=1);

namespace StaticDeploy;

use PHPUnit\Framework\TestCase;

final class OptionsTest extends TestCase {

    use ITTrait;

    public function testCliCommands(): void {
        $output = $this->pluginCli( [ 'options', 'list' ] )['output'];

        $basic_auth_password = 0;
        $deployment_url = 0;
        $local_dir_path = 0;
        $process_queue_immediately = 0;
        foreach ( $output as $line ) {
            if ( strpos( $line, 'basicAuthPassword' ) !== false ) {
                ++$basic_auth_password;
            }
            if ( strpos( $line, 'deploymentURL' ) !== false
            && strpos( $line, 'https://example.com' ) !== false ) {
                ++$deployment_url;
            }
            if ( strpos( $line, 'local_dirPath' ) !== false ) {
                ++$local_dir_path;
            }
            if ( strpos( $line, 'processQueueImmediately' ) !== false
            && strpos( $line, '0' ) !== false ) {
                ++$process_queue_immediately;
            }
        }
        $this->assertEquals(
            1,
            $basic_auth_password,
            'basicAuthPassword option is shown with blank value'
        );
        $this->assertEquals(
            1,
            $deployment_url,
            'deploymentURL option is shown with default value'
        );
        $this->assertEquals(
            1,
            $local_dir_path,
            'Options from addons are listed'
        );
        $this->assertEquals(
            1,
            $process_queue_immediately,
            'processQueueImmediately option is shown with default value'
        );

        $this->assertEquals(
            '4',
            $this->getOptionValue( 'crawlConcurrency' ),
            'Can get options'
        );
        $this->assertEquals(
            '16',
            $this->setOptionValue( 'crawlConcurrency', '16' ),
            'Can set options to valid values'
        );
        $this->assertEquals(
            '1',
            $this->setOptionValue( 'crawlConcurrency', '0' ),
            'Validation changes invalid value to min_value'
        );
        $this->assertEquals(
            '1',
            $this->setOptionValue( 'crawlConcurrency', 'x' ),
            'Validation changes invalid value to min_value'
        );
        $this->assertEquals(
            '4',
            $this->setOptionValue( 'crawlConcurrency', '4' ),
            'Can set options back to default'
        );

        $this->assertEquals(
            '4',
            $this->getOptionValue( 's3_concurrency' ),
            'Can get addon options'
        );
        $this->assertEquals(
            '16',
            $this->setOptionValue( 's3_concurrency', '16' ),
            'Can set addon options to a valid value'
        );
        $this->assertEquals(
            '4',
            $this->setOptionValue( 's3_concurrency', '4' ),
            'Can set addon options back to default'
        );
    }

    public function testOptionFilters(): void
    {
        $plugin_dir = ITEnv::getWordPressDir() . '/wp-content/plugins/options-test';
        exec( 'rm -rf ' . escapeshellarg( $plugin_dir ) );

        $this->assertEquals( '0', $this->getOptionValue( 'processQueueImmediately' ) );

        exec( 'mkdir -p ' . escapeshellarg( $plugin_dir ) );
        file_put_contents( $plugin_dir . '/options-test.php', $this->optionsTestFilters() );
        $this->wpCli( [ 'plugin', 'activate', 'options-test' ] );
        $this->assertEquals( '1', $this->getOptionValue( 'processQueueImmediately' ) );
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
