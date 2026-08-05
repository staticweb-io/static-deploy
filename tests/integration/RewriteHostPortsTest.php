<?php declare(strict_types=1);

namespace StaticDeploy;

use PHPUnit\Framework\TestCase;

/**
 * Tests the rewriting of hosts that carry a port number, controlled by the
 * rewriteHostPorts option.
 */
final class RewriteHostPortsTest extends TestCase {

    use ITTrait;

    /**
     * Write robots.txt, run the crawl/post-process pipeline and return the
     * post-processed robots.txt. robots.txt is post-processed as plain text,
     * so it is a convenient vehicle for exercising the URL rewriter.
     */
    private function postProcessRobotsTxt( string $contents ): string {
        file_put_contents( ITEnv::getWordPressDir() . '/robots.txt', $contents );

        $this->pluginCli( [ 'detect' ] );
        $this->pluginCli( [ 'crawl' ] );
        $this->pluginCli( [ 'post-process' ] );

        return $this->getProcessedFileContents( 'robots.txt' );
    }

    /**
     * With rewriteHostPorts enabled, a rewritten host has any port dropped
     * rather than carried over to the destination host.
     */
    public function testStripsPortsWhenEnabled(): void {
        $this->setOptionValue( 'deploymentURL', 'https://example.com' );
        $this->setOptionValue( 'hostsToRewrite', 'localhost' );
        $this->setOptionValue( 'rewriteHostPorts', '1' );

        $content = $this->postProcessRobotsTxt(
            "http port: http://localhost:821/page\n"
            . "https port: https://localhost:8080/other\n"
            . "protocol relative: //localhost:3000/rel\n"
            . "no port: http://localhost/noport\n"
            . 'escaped json: http:\/\/localhost:9\/json' . "\n"
            . "different host: http://other.com:9000/keep\n"
            . "subdomain: http://sub.localhost:82/keep\n"
        );

        // Ports on the rewritten host are dropped.
        $this->assertStringContainsString( 'https://example.com/page', $content );
        $this->assertStringContainsString( 'https://example.com/other', $content );
        $this->assertStringContainsString( '//example.com/rel', $content );
        $this->assertStringContainsString( 'https://example.com/noport', $content );

        // The backslash-escaped form found in JSON is handled too.
        $this->assertStringContainsString( 'https:\/\/example.com\/json', $content );

        // Hosts we are not rewriting keep their port and are left untouched.
        $this->assertStringContainsString( 'http://other.com:9000/keep', $content );
        $this->assertStringContainsString( 'http://sub.localhost:82/keep', $content );

        // The stripped ports are gone.
        $this->assertStringNotContainsString( 'localhost:821', $content );
        $this->assertStringNotContainsString( 'localhost:8080', $content );
        $this->assertStringNotContainsString( 'localhost:3000', $content );
    }

    /**
     * The port is stripped before the host replacement runs, not after.
     *
     * This matters when the deployment URL has its own port: stripping first
     * yields https://example.com:9000/page, whereas replacing first would
     * strand the source port on the destination host
     * (https://example.com:9000:821/page), since the port pattern is keyed on
     * the source host and would no longer match once it has been rewritten.
     */
    public function testStripsPortBeforeReplacement(): void {
        $this->setOptionValue( 'deploymentURL', 'https://example.com:9000' );
        $this->setOptionValue( 'hostsToRewrite', 'localhost' );
        $this->setOptionValue( 'rewriteHostPorts', '1' );

        $content = $this->postProcessRobotsTxt(
            "http://localhost:821/page\n"
        );

        // The destination's own port is preserved; the source port is dropped.
        $this->assertStringContainsString( 'https://example.com:9000/page', $content );
        $this->assertStringNotContainsString( '9000:821', $content );
        $this->assertStringNotContainsString( 'localhost:821', $content );
    }

    /**
     * Port stripping is opt-in: with the option disabled (the default), the
     * port is carried over to the destination host as before.
     */
    public function testKeepsPortsWhenDisabled(): void {
        $this->setOptionValue( 'deploymentURL', 'https://example.com' );
        $this->setOptionValue( 'hostsToRewrite', 'localhost' );
        $this->setOptionValue( 'rewriteHostPorts', '0' );

        $content = $this->postProcessRobotsTxt(
            "http port: http://localhost:821/page\n"
        );

        $this->assertStringContainsString( 'https://example.com:821/page', $content );
    }

    /**
     * A host listed with a port keeps its exact-match behaviour: only that
     * exact host:port is rewritten, and other ports are left alone, even with
     * rewriteHostPorts enabled.
     */
    public function testHostListedWithPortMatchesExactly(): void {
        $this->setOptionValue( 'deploymentURL', 'https://example.com' );
        $this->setOptionValue( 'hostsToRewrite', 'localhost:821' );

        $this->setOptionValue( 'rewriteHostPorts', '0' );
        $content = $this->postProcessRobotsTxt(
            "exact: http://localhost:821/x\n"
            . "other port: http://localhost:999/y\n"
        );
        $this->assertStringContainsString( 'https://example.com/x', $content );
        $this->assertStringContainsString( 'http://localhost:999/y', $content );

        $this->setOptionValue( 'rewriteHostPorts', '1' );
        $content = $this->postProcessRobotsTxt(
            "exact: http://localhost:821/x\n"
            . "other port: http://localhost:999/y\n"
        );
        $this->assertStringContainsString( 'https://example.com/x', $content );
        $this->assertStringContainsString( 'http://localhost:999/y', $content );
    }
}
