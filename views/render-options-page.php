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

echo '<h2>', esc_html( $view['title'] ), '</h2>';

$nonce_action = esc_attr( $view['nonce_action'] );
?>

<form
    name="<?php echo esc_attr( $nonce_action ); ?>"
    method="POST"
    action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

    <?php wp_nonce_field( $nonce_action ); ?>
    <input name="action" type="hidden" value="<?php echo esc_attr( $nonce_action ); ?>" />

<?php
foreach ( $view['sections'] as $section ) {
    echo '<h3>', esc_html( $section['title'] ), '</h3>';
    echo '<table class="widefat striped"><tbody>';
    foreach ( $section['options'] as $option_name ) {
        $row( $option_name );
    }
    echo '</tbody></table>';
}
?>

<br>
<button class="button btn-primary">Save Options</button>
</form>