<?php
// phpcs:disable Generic.Files.LineLength.MaxExceeded                              
// phpcs:disable Generic.Files.LineLength.TooLong                                  

use StaticDeploy\Controller;

/**
 * @var mixed[] $view
 */

/**
 * @var mixed[] $addons
 */
$addons = $view['addons'];
?>

<div class="wrap">
    <br>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>Enabled</th>
                <th>Name</th>
                <th>Type</th>
                <th>Documentation URL</th>
                <th>Configure</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( ! $addons ) : ?>
                <tr>
                    <td colspan="4">No addons are installed.</td>
                </tr>
            <?php endif; ?>


            <?php foreach ( $addons as $addon ) : ?>
                <tr>
                    <td>
                        <form
                            name="<?php echo esc_attr( Controller::getHookName( 'toggle_addon' ) ); ?>"
                            method="POST"
                            action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

                        <?php wp_nonce_field( strval( $view['nonce_action'] ) ); ?>
                        <input name="action" type="hidden" value="<?php echo esc_attr( Controller::getHookName( 'toggle_addon' ) ); ?>" />
                        <input name="addon_slug" type="hidden" value="<?php echo esc_attr( $addon->slug ); ?>" />

                        <button><?php if ( $addon->enabled ) {
                            echo 'Enabled';
                                } else {
                                    echo 'Disabled'; } ?></button>

                        </form>

                    </td>
                    <td>
                        <?php echo esc_html( $addon->name ); ?>
                        <br>
                        <?php echo esc_html( $addon->description ); ?>
                    </td>
                    <td><?php echo esc_html( $addon->type ); ?></td>
                    <td>
                        <a href="<?php echo esc_url( $addon->docs_url ); ?>"><span class="dashicons dashicons-book-alt"></span></a>
                    </td>
                    <td>
                        <a href="<?php echo esc_url( admin_url( "admin.php?page={$addon->slug}" ) ); ?>"><span class="dashicons dashicons-admin-generic"></span></a>
                    </td>

                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <br>
</div>
