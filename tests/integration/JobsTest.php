<?php declare(strict_types=1);

namespace StaticDeploy;

use PHPUnit\Framework\TestCase;

final class JobsTest extends TestCase {
    use ITTrait;

    public function testAddJob(): void {
        $line = $this->pluginCli( [ 'jobs', 'add', 'detect' ] )['final_line'];
        $this->assertMatchesRegularExpression( '/Added job \d+/', $line );
    }

    public function testProcess(): void {
        $line = $this->pluginCli( [ 'jobs', 'process' ] )['final_line'];
        $this->assertMatchesRegularExpression( '/No jobs in queue/', $line );

        $this->pluginCli( [ 'jobs', 'add', 'detect' ] );
        $line = $this->pluginCli( [ 'jobs', 'process' ] )['final_line'];
        $this->assertMatchesRegularExpression( '/Done processing queue/', $line );
    }
}
