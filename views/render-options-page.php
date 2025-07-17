<?php
// phpcs:disable Generic.Files.LineLength.MaxExceeded
// phpcs:disable Generic.Files.LineLength.TooLong

use StaticDeploy\OptionRenderer;

/**
 * @var mixed[] $view
 */

$options = $view['options'];

$row = function ( $option_name ) use ( $options ) {
    $opt = $options[ $option_name ];
    return '<tr><td style="width: 50%">' . OptionRenderer::optionLabel( $opt, true ) .
            '</td><td>' . OptionRenderer::optionInput( $opt ) . '</td></tr>';
};


echo '<h2>' . $view['title'] . '</h2>';
?>

<form
    name="<?php echo $view['nonce_action']; ?>"
    method="POST"
    action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

    <?php wp_nonce_field( $view['nonce_action'] ); ?>
    <input name="action" type="hidden" value="<?php echo $view['nonce_action']; ?>" />

<?php
foreach ( $view['sections'] as $section ) {
    echo '<h3>' . $section['title'] . '</h3>';
    echo '<table class="widefat striped"><tbody>';
    foreach ( $section['options'] as $option_name ) {
        echo $row( $option_name );
    }
    echo '</tbody></table>';
}
?>

<br>
<button class="button btn-primary">Save Options</button>
</form>