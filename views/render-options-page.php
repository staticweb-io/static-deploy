<?php
// phpcs:disable Generic.Files.LineLength.MaxExceeded
// phpcs:disable Generic.Files.LineLength.TooLong

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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

echo '<h2>', esc_html( $view['title'] ), '</h2>';

$static_deploy_nonce_action = esc_attr( $view['nonce_action'] );
?>

<form
    name="<?php echo esc_attr( $static_deploy_nonce_action ); ?>"
    method="POST"
    action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

    <?php wp_nonce_field( $static_deploy_nonce_action ); ?>
    <input name="action" type="hidden" value="<?php echo esc_attr( $static_deploy_nonce_action ); ?>" />

<?php
foreach ( $view['sections'] as $static_deploy_section ) {
    echo '<h3>', esc_html( $static_deploy_section['title'] ), '</h3>';
    echo '<table class="widefat striped"><tbody>';
    foreach ( $static_deploy_section['options'] as $static_deploy_option_name ) {
        $static_deploy_row( $static_deploy_option_name );
    }
    echo '</tbody></table>';
}
?>

<br>
<button class="button btn-primary">Save Options</button>
</form>