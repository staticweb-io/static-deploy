<?php declare(strict_types=1);

namespace StaticDeploy;

use PHPUnit\Framework\TestCase;

final class PathsToIgnoreTest extends TestCase {

    use ITTrait;

    /**
     * Test that detect step ignores path patterns
     * specified by the pathsToIgnore option.
     */
    public function testDetectIgnores(): void {
        $dir = ITEnv::getTestContentDir();
        $path = ITEnv::getTestContentPath();
        file_put_contents( $dir . '/Dckrfile', 'RUN' );
        file_put_contents( $dir . '/Dockerfile', 'RUN' );
        file_put_contents( $dir . '/THUMBS.DB', 'RUN' );
        file_put_contents( $dir . '/wildcard.bat', 'RUN' );
        mkdir( $dir . '/node_modules', 0775, true );
        file_put_contents( $dir . '/node_modules/hello.html', 'RUN' );

        $this->pluginCli( [ 'detect' ] );
        $lines = $this->pluginCli( [ 'detected_files', 'list' ] )['output'];
        $this->assertContains(
            $path . '/Dckrfile',
            $lines,
            'Sanity check that custom files are included'
        );
        $this->assertNotContains(
            $path . '/Dockerfile',
            $lines,
            'Filenames matching literals are ignored'
        );
        $this->assertNotContains(
            $path . '/THUMBS.DB',
            $lines,
            'Ignore patterns are not case-sensitive'
        );
        $this->assertNotContains(
            $path . '/wildcard.bat',
            $lines,
            'File extension glob patterns are ignored'
        );
        $this->assertNotContains(
            $path . '/node_modules/hello.html',
            $lines,
            'Directory literals are ignored'
        );
    }

    /**
     * Test that detect step ignores path patterns
     * matching vendored files.
     */
    public function testVendorIgnores(): void {
        $wordpress_dir = ITEnv::getWordPressDir();
        file_put_contents(
            $wordpress_dir . '/wp-content/plugins/staticweb-deploy/vendor/findme.html',
            '<html>'
        );

        $this->pluginCli( [ 'detect' ] );
        $lines = $this->pluginCli( [ 'detected-files', 'list' ] )['output'];
        $this->assertNotContains(
            '/wp-content/plugins/staticweb-deploy/vendor/findme.html',
            $lines,
            'Vendored files are ignored'
        );
    }

    /**
     * The default patterns ignore backups, database dumps and logs,
     * but not site files with similar names.
     */
    public function testDefaultsIgnoreBackups(): void {
        $ignored = [
            '/.env',
            '/wp-content/ai1wm-backups/file.txt',
            '/wp-content/backup-migration/file.txt',
            '/wp-content/backups-dup-lite/file.txt',
            '/wp-content/backups-dup-pro/file.txt',
            '/wp-content/debug.log',
            '/wp-content/updraft/file.txt',
            '/wp-content/uploads/backup-guard/file.txt',
            '/wp-content/uploads/backupbuddy_backups/file.txt',
            '/wp-content/uploads/db-backup/file.txt',
            '/wp-content/uploads/site.bak',
            '/wp-content/uploads/site.daf',
            '/wp-content/uploads/site.jpa',
            '/wp-content/uploads/site.sql.bz2',
            '/wp-content/uploads/site.sql.gz',
            '/wp-content/uploads/site.sql.zip',
            '/wp-content/uploads/site.wpress',
            '/wp-content/uploads/site-db.gz',
            '/wp-content/uploads/wp-migrate-db/file.txt',
            '/wp-content/uploads/wp-staging/file.txt',
            '/wp-content/wpvividbackups/file.txt',
            '/wp-snapshots/file.txt',
            '/x/.env',
            '/x/.env.production',
        ];
        $crawlable = [
            '/changelog/',
            '/wp-content/plugins/updraftplus/css/admin.css',
            '/wp-content/themes/theme/app.js.gz',
            '/wp-content/themes/theme/style.css.gz',
            '/wp-content/uploads/catalog.pdf',
            '/wp-content/uploads/db-backups-guide.pdf',
            '/wp-content/uploads/environment.png',
        ];

        $paths_json = json_encode( [ ...$ignored, ...$crawlable ] );
        $code = '$filtering = new ' . FileFiltering::class . '();'
            . ' $ignored = [];'
            . " foreach ( json_decode( '{$paths_json}' ) as \$path ) {"
            . '   if ( ! $filtering->pathLooksCrawlable( $path ) ) { $ignored[] = $path; }'
            . ' }'
            . ' echo json_encode( $ignored );';
        $actual = json_decode( (string) $this->wpCli( [ 'eval', $code ] )['final_line'], true );

        $this->assertSame( $ignored, $actual );
    }

    /**
     * A path detected before an ignore pattern matched it must be
     * removed rather than crawled and deployed again.
     */
    public function testNewlyIgnoredPathIsRemoved(): void {
        $path = ITEnv::getTestContentPath() . '/later-ignored.txt';
        file_put_contents( ITEnv::getTestContentDir() . '/later-ignored.txt', 'secret' );
        $this->pluginCli( [ 'detect' ] );
        $this->pluginCli( [ 'crawl' ] );
        $this->pluginCli( [ 'post_process' ] );
        $this->assertSame( 'secret', $this->getProcessedFileContents( $path ) );

        $paths_to_ignore = implode(
            "\n",
            $this->pluginCli( [ 'options', 'get', 'pathsToIgnore' ] )['output']
        );
        try {
            $this->setOptionValue( 'pathsToIgnore', $paths_to_ignore . "\nlater-ignored.txt" );
            $this->pluginCli( [ 'detect' ] );
            $this->pluginCli( [ 'crawl' ] );
            $this->pluginCli( [ 'post_process' ] );
        } finally {
            $this->setOptionValue( 'pathsToIgnore', $paths_to_ignore );
        }

        $this->assertNotContains(
            $path,
            $this->pluginCli( [ 'detected_files', 'list' ] )['output']
        );
        $uploads_dir = ITEnv::getWordPressDir() . '/wp-content/uploads/';
        foreach ( [ 'crawledSitePath', 'processedSitePath' ] as $option_name ) {
            $this->assertFileDoesNotExist(
                $uploads_dir . $this->getOptionValue( $option_name ) . $path
            );
        }
    }
}
