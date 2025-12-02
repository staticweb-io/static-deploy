<?php
// phpcs:disable Generic.Files.LineLength.MaxExceeded                              
// phpcs:disable Generic.Files.LineLength.TooLong                                  

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * @var mixed[] $view
 */

use StaticDeploy\Controller;
use StaticDeploy\OptionRenderer;
use StaticDeploy\Utils;

/**
 * @var mixed[] $static_deploy_jobs
 */
$static_deploy_jobs = $view['jobs'];

/**
 * @var array<string, \StaticDeploy\OptionData> $static_deploy_options
 */
$static_deploy_options = $view['jobOptions'];

$static_deploy_input = ( fn( string $name ) => OptionRenderer::echoInput( $static_deploy_options[ $name ] ) );

$static_deploy_label = ( fn( string $name, bool $description = false ) => OptionRenderer::echoLabel( $static_deploy_options[ $name ], $description ) );
?>

<div class="wrap">
    <form
        name="<?php echo esc_attr( Controller::getHookName( 'job_options' ) ); ?>"
        method="POST"
        action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

    <br>

    <table class="widefat striped">
        <thead>
            <tr>
                <td style="width:33%;">
                    Events to queue new jobs
                </td>
                <td>
                    &nbsp;
                </td>
                <td>
                    Enabled?
                </td>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="width:33%;">
                    <?php $static_deploy_label( 'queueJobOnPostSave' ); ?>
                </td>
                <td>
                    <?php echo esc_html( $static_deploy_options['queueJobOnPostSave']->option_spec->description ); ?>
                </td>
                <td>
                    <?php $static_deploy_input( 'queueJobOnPostSave' ); ?>
                </td>
            </tr>
            <tr>
                <td style="width:33%;">
                    <?php $static_deploy_label( 'queueJobOnPostDelete' ); ?>
                </td>
                <td>
                    <?php echo esc_html( $static_deploy_options['queueJobOnPostDelete']->option_spec->description ); ?>
                </td>
                <td>
                    <?php $static_deploy_input( 'queueJobOnPostDelete' ); ?>
                </td>
            </tr>
        </tbody>
    </table>


    <h4>Jobs that will be added to queue</h4>

    <table class="widefat striped">
        <thead>
            <tr>
                <td style="text-align:center;">
                    <?php $static_deploy_label( 'autoJobQueueDetection' ); ?>
                </td>
                <td style="text-align:center;">
                    <?php $static_deploy_label( 'autoJobQueueCrawling' ); ?>
                </td>
                <td style="text-align:center;">
                    <?php $static_deploy_label( 'autoJobQueuePostProcessing' ); ?>
                </td>
                <td style="text-align:center;">
                    <?php $static_deploy_label( 'autoJobQueueDeployment' ); ?>
                </td>
                <td style="text-align:center;">
                    <?php $static_deploy_label( 'autoJobQueueDirectDeploy' ); ?>
                </td>
                <td style="text-align:center;">
                    <?php $static_deploy_label( 'autoJobQueueDirectDeployPost' ); ?>
                </td>
            </tr>
        </thead>
        <tbody>
            <tr style="text-align:center;">
                <td><?php $static_deploy_input( 'autoJobQueueDetection' ); ?></td>
                <td><?php $static_deploy_input( 'autoJobQueueCrawling' ); ?></td>
                <td><?php $static_deploy_input( 'autoJobQueuePostProcessing' ); ?></td>
                <td><?php $static_deploy_input( 'autoJobQueueDeployment' ); ?></td>
                <td><?php $static_deploy_input( 'autoJobQueueDirectDeploy' ); ?></td>
                <td><?php $static_deploy_input( 'autoJobQueueDirectDeployPost' ); ?></td>
            </tr>
        </tbody>
    </table>

    <p/>

    <table class="widefat striped">
        <tbody>
            <tr>
                <td style="width: 50%">
                    <?php $static_deploy_label( 'processQueueInterval', true ); ?>
                    <p><i>If WP-Cron is not expected to be triggered by site visitors, you can also call `wp-cron.php` directly, run the WP-CLI command `wp static-deploy process_queue` or call the hook `<?php echo esc_html( Controller::getHookName( 'process_queue' ) ); ?>` from within your own theme or plugin.</i></p>
                </td>
                <td>
                    <select
                        id="processQueueInterval"
                        name="processQueueInterval"
                        value="<?php echo (int) $static_deploy_options['processQueueInterval']->value; ?>"
                    >
                    <option
                        <?php echo (int) $static_deploy_options['processQueueInterval']->value === 0 ? 'selected' : ''; ?>
                        value="0">disable (never)</option>
                    <option
                        <?php echo (int) $static_deploy_options['processQueueInterval']->value === 1 ? 'selected' : ''; ?>
                        value="1">every minute</option>
                    <option
                        <?php echo (int) $static_deploy_options['processQueueInterval']->value === 5 ? 'selected' : ''; ?>
                        value="5">every 5 minutes</option>
                    <option
                        <?php echo (int) $static_deploy_options['processQueueInterval']->value === 10 ? 'selected' : ''; ?>
                        value="10">every 10 minutes</option>
                    </select>
                </td>
            </tr>
            <tr>
                <td style="width: 50%">
                    <?php $static_deploy_label( 'processQueueImmediately', true ); ?>
                </td>
                <td>
                    <select
                        id="processQueueImmediately"
                        name="processQueueImmediately"
                        value="<?php echo (int) $static_deploy_options['processQueueImmediately']->value; ?>"
                    >
                    <option
                        <?php echo (int) $static_deploy_options['processQueueImmediately']->value === 0 ? 'selected' : ''; ?>
                        value="0">disabled</option>
                    <?php
                    if ( defined( 'STATIC_DEPLOY_WP_ORG_MODE' ) && STATIC_DEPLOY_WP_ORG_MODE ) :
                        ?>
                    <option
                        <?php echo (int) $static_deploy_options['processQueueImmediately']->value === 1 ? 'selected' : ''; ?>
                        value="1">enabled</option>
                    <?php else : ?>
                    <option
                        <?php echo (int) $static_deploy_options['processQueueImmediately']->value === 1 ? 'selected' : ''; ?>
                        value="1">Using wp-admin.php</option>
                    <option
                        <?php echo (int) $static_deploy_options['processQueueImmediately']->value === 2 ? 'selected' : ''; ?>
                        value="2">Using WordPress CLI</option>
                    <?php endif; ?>
                    </select>
                </td>
            </tr>
        </tbody>
    </table>

    <p/>

    <button class="button btn-primary">Save Job Automation Settings</button>
    <?php wp_nonce_field( strval( $view['nonce_action'] ) ); ?>
    <input name="action" type="hidden" value="<?php echo esc_attr( Controller::getHookName( 'ui_save_job_options' ) ); ?>" />
    </form>

    <p/>

    <form
        name="<?php echo esc_attr( Controller::getHookName( 'manually_enqueue_jobs' ) ); ?>"
        method="POST"
        action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

        <?php wp_nonce_field( Controller::getHookName( 'manually_enqueue_jobs' ) ); ?>
        <input name="action" type="hidden" value="<?php echo esc_attr( Controller::getHookName( 'manually_enqueue_jobs' ) ); ?>" />

        <button class="button">Manually Enqueue Jobs Now</button>
    </form>

    <hr>

    <h3>Job Queue/History</h3>

    <p><i><a href="<?php echo esc_url( Controller::getAdminUrl( 'jobs' ) ); ?>">Refresh page</a> to see latest status</i><p>

    <hr>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>Date</th>
                <th>Job</th>
                <th>Status</th>
                <th>Duration</th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ( $static_deploy_jobs as $static_deploy_job ) : ?>
            <tr>
                <td>
                    <?php echo esc_html( $static_deploy_job->created_at ); ?>
                    (<?php echo esc_html( human_time_diff( Utils::wpDateTime( $static_deploy_job->created_at )->getTimestamp() ) ); ?> ago)
                </td>
                <td><?php echo esc_html( $static_deploy_job->job_type ); ?></td>
                <td><?php echo esc_html( $static_deploy_job->status ); ?>
                (<?php echo esc_html( human_time_diff( Utils::wpDateTime( $static_deploy_job->status_updated_at )->getTimestamp() ) ); ?> ago)
                </td>
                <td>
                <?php
                $static_deploy_from = Utils::wpDateTime( $static_deploy_job->created_at );
                if ( $static_deploy_job->status === 'processing' ) {
                    $static_deploy_to = Utils::wpDateTime( 'now' );
                } else {
                    $static_deploy_to = Utils::wpDateTime( $static_deploy_job->status_updated_at );
                }

                if ( $static_deploy_job->status !== 'waiting' ) {
                    $static_deploy_interval = Utils::formatIntervalPretty( $static_deploy_from->diff( $static_deploy_to ), 2 );
                    if ( $static_deploy_interval ) {
                        echo esc_html( $static_deploy_interval );
                    } else {
                        echo '1 second';
                    }
                }

                if ( $static_deploy_job->status === 'processing' ) {
                    echo ' (still in progress)';
                }
                ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <br>

    <form
        name="<?php echo esc_attr( Controller::getHookName( 'delete_jobs_queue' ) ); ?>"
        method="POST"
        action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

    <?php wp_nonce_field( Controller::getHookName( 'delete_jobs_queue' ) ); ?>
    <input name="action" type="hidden" value="<?php echo esc_attr( Controller::getHookName( 'delete_jobs_queue' ) ); ?>" />

    <button class="static-deploy-button button btn-danger">Delete all Jobs from Queue</button>

    </form>
</div>
