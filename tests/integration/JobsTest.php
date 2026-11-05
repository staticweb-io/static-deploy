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

    /**
     * A job can wait in the queue while earlier jobs crawl, and those
     * paths must not count towards its progress. Holding the job's lock
     * stops the page from marking it as failed.
     */
    public function testJobsPageCountsPathsCrawledSinceJobStarted(): void {
        $code = <<<'PHP'
            use StaticDeploy\{CrawledFiles, Db, JobQueue, ViewRenderer};

            global $wpdb;

            $jobs_table = JobQueue::getTableName();
            $id = JobQueue::addJob( 'direct_deploy' );
            $wpdb->query(
                $wpdb->prepare(
                    'UPDATE %i SET created_at = NOW() - INTERVAL 1 HOUR WHERE id = %d',
                    $jobs_table,
                    $id
                )
            );

            CrawledFiles::addUrl( '/crawled-while-waiting/', '', 200, null );
            $wpdb->query(
                $wpdb->prepare(
                    'UPDATE %i SET crawled_at = NOW() - INTERVAL 30 MINUTE',
                    CrawledFiles::getTableName()
                )
            );

            JobQueue::setStatus( $id, 'processing' );
            CrawledFiles::addUrl( '/crawled-while-running/', '', 200, null );

            $lock = Db::getLockName( $jobs_table, 'direct_deploy' );
            $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) );

            ViewRenderer::renderJobsPage();
            PHP;

        $output = implode( "\n", $this->wpCli( [ 'eval', $code ] )['output'] );

        $this->assertStringContainsString( '1 path crawled', $output );
    }
}
