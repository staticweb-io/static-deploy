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
 * @var string[] $logs
 */
$logs = $view['logs'];
?>

<div class="wrap">
    <br>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>When</th>
                <th>What</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( ! $logs ) : ?>
                <tr>
                    <td colspan="2">Logs are empty.</td>
                </tr>
            <?php endif; ?>

            <?php foreach ( $logs as $log ) : ?>
                <tr>
                    <td><?php echo esc_html( $log->time ); ?></td>
                    <td><?php echo esc_html( $log->log ); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <br> 

    <?php if ( $view['logs'] ) : ?>
        <form
            name="<?php echo esc_attr( Controller::getHookName( 'log_delete' ) ); ?>"
            method="POST"
            action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

        <?php wp_nonce_field( $view['nonce_action'] ); ?>
        <input name="action" type="hidden" value="<?php echo esc_attr( Controller::getHookName( 'log_delete' ) ); ?>" />

        <button class="static-deploy-button button btn-danger">Delete Log</button>

        </form>
    <?php endif; ?>
</div>
