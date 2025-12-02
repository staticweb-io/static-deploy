<?php
// phpcs:disable Generic.Files.LineLength.MaxExceeded
// phpcs:disable Generic.Files.LineLength.TooLong

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use StaticDeploy\Controller;
use StaticDeploy\OptionRenderer;

/**
 * @var mixed[] $view
 */

/**
 * @var array<string, \StaticDeploy\OptionData> $static_deploy_options
 */
$static_deploy_options = $view['options'];

$static_deploy_row = function ( $option_name ) use ( $static_deploy_options ): void {
    $option_data = $static_deploy_options[ $option_name ];
    echo '<tr><td style="width: 50%">';
    OptionRenderer::echoLabel( $option_data, true );
    echo '</td><td>';
    OptionRenderer::echoInput( $option_data );
    echo '</td></tr>';
};
?>

<div class="wrap">
    <form
        name="<?php echo esc_attr( Controller::getHookName( 'ui_advanced_options' ) ); ?>"
        method="POST"
        action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

    <h1>Advanced Options<h1>

    <h2>Logging Options</h2>

    <table class="widefat striped">
        <tbody>
            <?php $static_deploy_row( 'maxLogRows' ); ?>
        </tbody>
    </table>

    <p/>

    <h2>Detection Options</h2>

    <table class="widefat striped">
        <tbody>
            <?php $static_deploy_row( 'pathsToIgnore' ); ?>
        </tbody>
    </table>

    <p/>

    <h2>Crawling Options</h2>

    <table class="widefat striped">
        <tbody>
            <?php $static_deploy_row( 'crawledSitePath' ); ?>
            <?php $static_deploy_row( 'crawlConcurrency' ); ?>
        </tbody>
    </table>

    <p/>

    <h2>Post-processing Options</h2>

    <table class="widefat striped">
        <tbody>
            <?php $static_deploy_row( 'processedSitePath' ); ?>
            <?php $static_deploy_row( 'skipURLRewrite' ); ?>
            <?php $static_deploy_row( 'hostsToRewrite' ); ?>
        </tbody>
    </table>

    <p/>

    <h2>UI Options</h2>

    <table class="widefat striped">
        <tbody>
            <?php $static_deploy_row( 'adminBarMenuItems' ); ?>
        </tbody>
    </table>

    <p/>

    <?php wp_nonce_field( strval( $view['nonce_action'] ) ); ?>
    <input name="action" type="hidden" value="<?php echo esc_attr( Controller::getHookName( 'ui_save_advanced_options' ) ); ?>" />

    <button class="button btn-primary" type="submit">Save options</button>

    </form>
</div>
