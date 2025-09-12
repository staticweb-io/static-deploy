<?php
// phpcs:disable Generic.Files.LineLength.MaxExceeded                              
// phpcs:disable Generic.Files.LineLength.TooLong                                  

use StaticDeploy\Controller;
use StaticDeploy\OptionRenderer;

/**
 * @var mixed[] $view
 */

/**
 * @var array<string, \StaticDeploy\OptionData> $options
 */
$options = $view['options'];

$row = function ( $option_name ) use ( $options ) {
    $option_data = $options[ $option_name ];
    echo '<tr><td style="width: 50%">';
    OptionRenderer::echoLabel( $option_data, true );
    echo '</td><td>';
    OptionRenderer::echoInput( $option_data );
    echo '</td></tr>';
};
?>

<div class="wrap">
    <form
        name="<?php echo esc_attr( Controller::getHookName( 'ui_options' ) ); ?>"
        method="POST"
        action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

    <h2>Detection Options</h2>

    <h4>Control Detected URLs</h4>

    <p>Static Deploy will crawl these WordPress URLs to generate a static site.</p>

    <table class="striped widefat">
        <thead>
            <tr>
                <th style="width:50%;">URL Type</th>
                <th>Include in detection</th>
            </tr>
        </thead>
        <tbody>
            <?php $row( 'detectCustomPostTypes' ); ?>
            <?php $row( 'detectPages' ); ?>
            <?php $row( 'detectPosts' ); ?>
            <?php $row( 'detectUploads' ); ?>
        </tbody>
    </table>

    <h2>Crawling Options</h2>

    <table class="widefat striped">
        <tbody>
            <?php $row( 'basicAuthUser' ); ?>
            <?php $row( 'basicAuthPassword' ); ?>
        </tbody>
    </table>

    <h2>Post-processing Options</h2>

    <table class="widefat striped">
        <tbody>
            <?php $row( 'deploymentURL' ); ?>
        </tbody>
    </table>

    <h2>Deployment Options</h2>

    <table class="widefat striped">
        <tbody>
            <?php $row( 'completionEmail' ); ?>
            <tr>
                <td style="width:50%;">
                    <?php OptionRenderer::echoLabel( $options['completionWebhook'] ); ?>
                </td>
                <td>
                    <input
                        style="width:80%;"
                        type="url"
                        id="completionWebhook"
                        name="completionWebhook"
                        value="<?php echo esc_attr( $options['completionWebhook']->value !== '' ? $options['completionWebhook']->value : '' ); ?>"
                    />

                    <select
                        id="completionWebhookMethod"
                        name="completionWebhookMethod"
                        >
                        <option
                            value="POST"
                            <?php echo $options['completionWebhookMethod']->value === 'POST' ? 'selected' : ''; ?>
                            >POST</option>
                        <option
                            value="GET"
                            <?php echo $options['completionWebhookMethod']->value === 'GET' ? 'selected' : ''; ?>
                            >GET</option>
                    </select>
                </td>
            </tr>
        </tbody>
    </table>

    <br>

    <?php wp_nonce_field( strval( $view['nonce_action'] ) ); ?>
    <input name="action" type="hidden" value="<?php echo esc_attr( Controller::getHookName( 'ui_save_options' ) ); ?>" />

    <button class="button btn-primary" type="submit">Save options</button>

    </form>
</div>
