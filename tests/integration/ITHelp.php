<?php declare(strict_types=1);

namespace WP2Static;

use PHPUnit\Framework\TestCase;

/**
 * Integration test helpers
 */
class ITHelp {
    private static self $_instance;
    private static string $wordpressDir;

    private final function __construct() {
        self::$wordpressDir = rtrim(getenv('WORDPRESS_DIR'), '/');
    }

    private static function init() : void {
        if ( ! isset( self::$_instance ) ) {
            self::$_instance = new self();
        }
    }

    public static function runWpCli(TestCase $testCase, array $args, array $expectWarnings = []): array
    {
        self::init();
        $cmd = implode(' ', array_map('escapeshellarg', array_merge(['wp', '--path=' . self::$wordpressDir], $args)));
        $output = [];
        $exitCode = 0;
        exec($cmd . ' 2>&1', $output, $exitCode);

        foreach ($expectWarnings as $pattern => $expectedCount) {
            $matches = array_filter($output, fn($line) => preg_match($pattern, $line));
            $testCase->assertCount($expectedCount, $matches, "Expected $expectedCount matches for pattern: $pattern");
        }

        if ($exitCode !== 0) {
            throw new \Exception("WP CLI command failed: $cmd\nOutput: " . implode("\n", $output));
        }

        return ['exit' => $exitCode, 'output' => $output];
    }

    public static function getCrawledFile(string $path): string
    {
        self::init();
        $crawledSiteDir = self::$wordpressDir . '/wp-content/uploads/wp2static-crawled-site';
        $content = file_get_contents("{$crawledSiteDir}/$path");
        if ($content === false) {
            throw new \Exception("Failed to read file: {$crawledSiteDir}/$path");
        }
        return $content;
    }
}
