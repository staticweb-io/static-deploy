<?php declare(strict_types=1);

namespace WP2Static;

/**
 * Integration test helper trait
 */
trait ITTrait {
    public static function runWpCli(array $args, array $expectWarnings = []): array
    {
        $wordpressDir = rtrim(getenv('WORDPRESS_DIR'), '/');
        $cmd = implode(' ', array_map('escapeshellarg', array_merge(['wp', '--path=' . $wordpressDir], $args)));
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

    public static function getCrawledFile(string $path): string
    {
        $wordpressDir = rtrim(getenv('WORDPRESS_DIR'), '/');
        $crawledSiteDir = $wordpressDir . '/wp-content/uploads/wp2static-crawled-site';
        $content = file_get_contents("{$crawledSiteDir}/$path");
        if ($content === false) {
            throw new \Exception("Failed to read file: {$crawledSiteDir}/$path");
        }
        return $content;
    }
}
