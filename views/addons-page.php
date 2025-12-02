<?php
// phpcs:disable Generic.Files.LineLength.MaxExceeded
// phpcs:disable Generic.Files.LineLength.TooLong

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use StaticDeploy\Controller;

/**
 * @var mixed[] $view
 */

/**
 * @var mixed[] $static_deploy_addons
 */
$static_deploy_addons = $view['addons'];
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
            <?php if ( ! $static_deploy_addons ) : ?>
                <tr>
                    <td colspan="4">No addons are installed.</td>
                </tr>
            <?php endif; ?>


            <?php foreach ( $static_deploy_addons as $static_deploy_addon ) : ?>
                <tr>
                    <td>
                        <form
                            name="<?php echo esc_attr( Controller::getHookName( 'toggle_addon' ) ); ?>"
                            method="POST"
                            action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

                        <?php wp_nonce_field( strval( $view['nonce_action'] ) ); ?>
                        <input name="action" type="hidden" value="<?php echo esc_attr( Controller::getHookName( 'toggle_addon' ) ); ?>" />
                        <input name="addon_slug" type="hidden" value="<?php echo esc_attr( $static_deploy_addon->slug ); ?>" />

                        <button>
                        <?php
                        if ( $static_deploy_addon->enabled ) {
                            echo 'Enabled';
                        } else {
                            echo 'Disabled';
                        }
                        ?>
                        </button>

                        </form>

                    </td>
                    <td>
                        <?php echo esc_html( $static_deploy_addon->name ); ?>
                        <br>
                        <?php echo esc_html( $static_deploy_addon->description ); ?>
                    </td>
                    <td><?php echo esc_html( $static_deploy_addon->type ); ?></td>
                    <td>
                        <a href="<?php echo esc_url( $static_deploy_addon->docs_url ); ?>"><span class="dashicons dashicons-book-alt"></span></a>
                    </td>
                    <td>
                        <a href="<?php echo esc_url( admin_url( "admin.php?page={$static_deploy_addon->slug}" ) ); ?>"><span class="dashicons dashicons-admin-generic"></span></a>
                    </td>

                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <br>
</div>
