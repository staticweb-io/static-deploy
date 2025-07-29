<?php declare(strict_types=1);

namespace StaticDeploy;

use PHPUnit\Framework\TestCase;

final class JobsTest extends TestCase {
    use ITTrait;

    public function testAddJob(): void {
        $line = $this->pluginCli( [ 'jobs', 'add', 'detect' ] )['final_line'];
        $this->assertMatchesRegularExpression( '/Added job \d+/', $line );
    }
}
