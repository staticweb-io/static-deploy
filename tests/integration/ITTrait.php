<?php declare(strict_types=1);

namespace WP2Static;

/**
 * Integration test helper trait
 */
trait ITTrait {
    public function wpCli( array $args, array $expect_warnings = [] ): array
    {
        $wordpress_dir = ITEnv::getWordPressDir();
        $cmd = implode(
            ' ',
            array_map(
                'escapeshellarg',
                array_merge( [ 'wp', '--path=' . $wordpress_dir ], $args )
            )
        );
        $output = [];
        $exit_code = 0;
        exec( $cmd . ' 2>&1', $output, $exit_code );

        foreach ( $expect_warnings as $pattern => $expected_count ) {
            $matches = array_filter( $output, fn( $line ) => preg_match( $pattern, $line ) );
            $this->assertCount(
                $expected_count,
                $matches,
                "Expected $expected_count matches for pattern: $pattern"
            );
        }

        $this->assertSame(
            0,
            $exit_code,
            "WP CLI command failed: $cmd\nOutput: " . implode( "\n", $output )
        );

        return [
            'exit' => $exit_code,
            'output' => $output,
        ];
    }

    public function getCrawledFileContents( string $path ): string
    {
        $wordpress_dir = ITEnv::getWordPressDir();
        $crawled_site_dir = $wordpress_dir . '/wp-content/uploads/wp2static-crawled-site';
        $content = file_get_contents( "{$crawled_site_dir}/$path" );
        $this->assertNotFalse( $content, "Failed to read file: {$crawled_site_dir}/$path" );
        return $content;
    }

    public function getProcessedFileContents( string $path ): string
    {
        $wordpress_dir = ITEnv::getWordPressDir();
        $processed_site_dir = $wordpress_dir . '/wp-content/uploads/wp2static-processed-site';
        $content = file_get_contents( "{$processed_site_dir}/$path" );
        $this->assertNotFalse( $content, "Failed to read file: {$processed_site_dir}/$path" );
        return $content;
    }

    public function getOptionValue( string $option_name ): string {
        $lines = $this->wpCli( [ 'wp2static', 'options', 'get', $option_name ] )['output'];
        // Ignore extra lines from things like deprecation warnings
        return $lines[ count( $lines ) - 1 ];
    }
}
