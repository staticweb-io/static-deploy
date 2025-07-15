<?php
// phpcs:disable Generic.Files.LineLength.MaxExceeded
// phpcs:disable Generic.Files.LineLength.TooLong
/**
 * @var mixed[] $view
 */

use WP2Static\OptionRenderer;

/**
 * @var array<string, mixed> $options
 */
$options = $view['options'];

$row = function ( $name ) use ( $options ) {
    $opt = $options[ $name ];
    return '<tr><td style="width: 50%">' . OptionRenderer::optionLabel( $opt, true ) .
            '</td><td>' . OptionRenderer::optionInput( $opt ) . '</td></tr>';
}

?>

<div class="wrap">
    <form
        name="wp2static-ui-advanced-options"
        method="POST"
        action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

    <h1>Advanced Options<h1>

    <h2>Logging Options</h2>

    <table class="widefat striped">
        <tbody>
            <?php echo $row( 'debugLogging' ); ?>
            <?php echo $row( 'maxLogRows' ); ?>
        </tbody>
    </table>

    <p/>

    <h2>Detection Options</h2>

    <table class="widefat striped">
        <tbody>
            <?php echo $row( 'pathsToIgnore' ); ?>
        </tbody>
    </table>

    <p/>

    <h2>Crawling Options</h2>

    <table class="widefat striped">
        <tbody>
            <?php echo $row( 'crawledSitePath' ); ?>
            <?php echo $row( 'crawlConcurrency' ); ?>
        </tbody>
    </table>

    <p/>

    <h2>Post-processing Options</h2>

    <table class="widefat striped">
        <tbody>
            <?php echo $row( 'processedSitePath' ); ?>
            <?php echo $row( 'skipURLRewrite' ); ?>
            <?php echo $row( 'hostsToRewrite' ); ?>
        </tbody>
    </table>

    <p/>

    <?php wp_nonce_field( strval( $view['nonce_action'] ) ); ?>
    <input name="action" type="hidden" value="wp2static_ui_save_advanced_options" />

    <button class="button btn-primary" type="submit">Save options</button>

    </form>
</div>
