<?php declare(strict_types=1);

namespace WP2Static;

use PHPUnit\Framework\TestCase;

final class CrawlTest extends TestCase
{
    private string $wordpressDir;
    private string $uploadsDir;
    private string $robotsFile;
    private string $sitemapFile;

    protected function setUp(): void
    {
        $this->wordpressDir = rtrim(getenv('WORDPRESS_DIR'), '/');
        $this->uploadsDir = "{$this->wordpressDir}/wp-content/uploads/wp2static-crawled-site";
    }

    private function runWpCli(array $args, array $expectWarnings = []): array
    {
        $cmd = implode(' ', array_map('escapeshellarg', array_merge(['wp', '--path=' . $this->wordpressDir], $args)));
        $output = [];
        $exitCode = 0;
        exec($cmd . ' 2>&1', $output, $exitCode);

        foreach ($expectWarnings as $pattern => $expectedCount) {
            $matches = array_filter($output, fn($line) => preg_match($pattern, $line));
            $this->assertCount($expectedCount, $matches, "Expected $expectedCount matches for pattern: $pattern");
        }

        if ($exitCode !== 0) {
            throw new \Exception("WP CLI command failed: $cmd\nOutput: " . implode("\n", $output));
        }

        return ['exit' => $exitCode, 'output' => $output];
    }

    private function getCrawledFile(string $path): string
    {
        $content = file_get_contents("{$this->uploadsDir}/$path");
        if ($content === false) {
            throw new \Exception("Failed to read file: {$this->uploadsDir}/$path");
        }
        return $content;
    }

    public function testIndexCrawl(): void
    {
        $this->runWpCli(['wp2static', 'detect']);
        $this->runWpCli(['wp2static', 'crawl']);

        $content = $this->getCrawledFile("index.html");

        $this->assertStringContainsString("Welcome to WordPress", $content);
    }
}
