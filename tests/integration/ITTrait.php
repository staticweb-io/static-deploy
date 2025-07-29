<?php declare(strict_types=1);

namespace StaticDeploy;

/**
 * Integration test helper trait
 */
trait ITTrait {
    public function setUp(): void {
        exec( 'rm -rf ' . escapeshellarg( ITEnv::getLocalDeployDir() ) );
        exec( 'rm -rf ' . escapeshellarg( ITEnv::getTestContentDir() ) );

        $this->pluginCli( [ 'delete-all-cache', '--force' ] );
        $this->pluginCli( [ 'jobs', 'delete', '--yes' ] );
    }

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
            'final_line' => empty( $output ) ? null : $output[ count( $output ) - 1 ],
            'output' => $output,
        ];
    }

    public function pluginCli( array $args, array $expect_warnings = [] ): array {
        return $this->wpCli( [ 'static-deploy', ...$args ], $expect_warnings );
    }

    public function getFileContents(
        string $base_dir,
        string $path,
    ): string {
        $content = file_get_contents( "{$base_dir}/$path" );
        $this->assertNotFalse( $content, "Failed to read file: {$base_dir}/$path" );
        return $content;
    }

    public function getCrawledFileContents( string $path ): string
    {
        return $this->getFileContents(
            ITEnv::getWordPressDir() .
            '/wp-content/uploads/' .
            $this->getOptionValue( 'crawledSitePath' ),
            $path
        );
    }

    public function getLocalDeployFileContents( string $path ): string {
        return $this->getFileContents(
            ITEnv::getLocalDeployDir(),
            $path
        );
    }

    public function getProcessedFileContents( string $path ): string
    {
        return $this->getFileContents(
            ITEnv::getWordPressDir() .
            '/wp-content/uploads/' .
            $this->getOptionValue( 'processedSitePath' ),
            $path
        );
    }

    public function getOptionValue( string $option_name ): string {
        $lines = $this->pluginCli( [ 'options', 'get', $option_name ] )['output'];
        // Ignore extra lines from things like deprecation warnings
        return $lines[ count( $lines ) - 1 ];
    }

    public function setOptionValue(
        string $option_name,
        string $option_value,
    ): string {
        $args = [ 'options', 'set', $option_name, $option_value ];
        $lines = $this->pluginCli( $args )['output'];
        // Ignore extra lines from things like deprecation warnings
        return $lines[ count( $lines ) - 1 ];
    }
}
