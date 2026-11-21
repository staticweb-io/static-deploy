<?php declare(strict_types=1);

namespace StaticDeploy;

use PHPUnit\Framework\TestCase;

final class WPCronTest extends TestCase {

    use ITTrait;

    public function testCronRequestWithBasicAuth(): void {
        $controller = Controller::class;
        $code = <<<PHP
            add_filter(
                {$controller}::getHookName( 'option_basicAuthPassword' ),
                fn() => 'pass'
            );
            add_filter(
                {$controller}::getHookName( 'option_basicAuthUser' ),
                fn() => 'user'
            );
            \$request = apply_filters(
                'cron_request',
                [
                    'args' => [
                        'headers' => [ 'X-Test' => 'test' ],
                        'timeout' => 0.01,
                    ],
                    'key' => 'key',
                    'url' => 'https://example.com/wp-cron.php',
                ]
            );
            echo json_encode( \$request );
            PHP;
        $request = json_decode(
            (string) $this->wpCli( [ 'eval', $code ] )['final_line'],
            true
        );

        $this->assertEquals(
            [
                'args' => [
                    'headers' => [
                        'Authorization' => 'Basic ' . base64_encode( 'user:pass' ),
                        'X-Test' => 'test',
                    ],
                    'timeout' => 0.01,
                ],
                'key' => 'key',
                'url' => 'https://example.com/wp-cron.php',
            ],
            $request
        );
    }
}
