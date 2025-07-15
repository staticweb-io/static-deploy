<?php
// phpcs:disable Generic.Files.LineLength.MaxExceeded
// phpcs:disable Generic.Files.LineLength.TooLong

/**
 * @var mixed[] $view
 */

use WP2Static\OptionRenderer;
use WP2Static\S3\S3Options;

/**
 * @var array<string, mixed> $options
 */
$options = $view['options'];

$row = function ( $slug ) use ( $options ) {
    $name = S3Options::getName( $slug );
    $opt = $options[ $name ];
    return '<tr><td style="width: 50%">' . OptionRenderer::optionLabel( $opt, true ) .
            '</td><td>' . OptionRenderer::optionInput( $opt ) . '</td></tr>';
};

$object_acl_name = S3Options::getName( 'objectAcl' );

?>

<h2>S3 Deployment Options</h2>

<h3>AWS Credentials</h3>

<table class="widefat striped">
    <tbody>
    <?php echo $row( 'awsAccessKeyId' ); ?>
    <?php echo $row( 'awsSecretAccessKey' ); ?>
    <?php echo $row( 'awsRegion' ); ?>
    <?php echo $row( 'awsProfile' ); ?>
    </tbody>
</table>

<h3>S3</h3>

<form
    name="wp2static-s3-save-options"
    method="POST"
    action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

    <?php wp_nonce_field( $view['nonce_action'] ); ?>
    <input name="action" type="hidden" value="<?php echo Controller::getHookName( 's3_save_options' ); ?>" />

<table class="widefat striped">
    <tbody>
    <?php echo $row( 'bucketName' ); ?>
    <?php echo $row( 'bucketPrefix' ); ?>
    <?php echo $row( 'headerCacheControl' ); ?>
    <tr>
        <td style="width:50%;">
            <label
                for="<?php echo $object_acl_name; ?>"
                style="font-weight: bold;"
            ><?php echo $view['options'][ $object_acl_name ]->option_spec->label; ?></label>
        </td>
        <td>
            <select
                id="<?php echo $object_acl_name; ?>"
                name="<?php echo $object_acl_name; ?>"
            >
                <option
                    <?php if ( $view['options'][ $object_acl_name ]->value === 'public-read' ) {
                        echo 'selected'; } ?>
                    value="public-read">public-read</option>
                <option
                    <?php if ( $view['options'][ $object_acl_name ]->value === 'private' ) {
                        echo 'selected'; } ?>
                    value="private">private</option>
            </select>
        </td>
    </tr>
    <?php echo $row( 'concurrency' ); ?>
    </tbody>
</table>

<h3>CloudFront</h3>

<table class="widefat striped">
    <tbody>
    <?php echo $row( 'distributionId' ); ?>
    <?php echo $row( 'maxPathsToInvalidate' ); ?>
    </tbody>
</table>

<br>

    <button class="button btn-primary">Save S3 Options</button>
</form>

