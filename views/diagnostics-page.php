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
?>

<div class="wrap">
    <br>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>Health check</th>
                <th>Status</th>
                <th>Advice</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>PHP memory_limit</td>
                <td>
                    <?php echo esc_html( $view['memoryLimit'] ); ?>

                </td>
                <td>Static Deploy will use as much memory as is available to it during processing. Allocating more of your system RAM to PHP should improve performance.</td>
            </tr>
            <tr>
                <td>Uploads directory writable</td>
                <td>
                    <?php echo $view['uploadsWritable'] ? 'Writable' : 'Non-writable'; ?>

                    <span
                        class="dashicons <?php echo $view['uploadsWritable'] ? 'dashicons-yes' : 'dashicons-no'; ?>"
                        style="color: <?php echo $view['uploadsWritable'] ? 'green' : 'red'; ?>;"
                    ></span>
                </td>
                <td>By default Static Deploy writes the generated static site under wp-content/uploads directory. Make sure Static Deploy has the permission to do so.</td>
            </tr>
            <tr>
                <td>PHP version</td>
                <td>
                    <?php echo esc_html( PHP_VERSION ); ?>

                    <span
                        class="dashicons <?php echo $view['phpOutOfDate'] ? 'dashicons-no' : 'dashicons-yes'; ?>"
                        style="color: <?php echo $view['phpOutOfDate'] ? 'red' : 'green'; ?>;"
                    ></span>
                </td>
                <td>
                <p>The current officially supported PHP versions can be found on <a href="http://php.net/supported-versions.php" target="_blank">PHP.net</a></p>

                <p>Static Deploy now requires a minimum of PHP 8.1.</p>
                </td>
            </tr>
            <tr>
                <td>cURL extension loaded</td>
                <td>
                    <?php echo $view['curlSupported'] ? 'Yes' : 'No'; ?>

                    <span
                        class="dashicons <?php echo $view['curlSupported'] ? 'dashicons-yes' : 'dashicons-no'; ?>"
                        style="color: <?php echo $view['curlSupported'] ? 'green' : 'red'; ?>;"
                    ></span>
                </td>
                <td>
                    <p>You need the cURL extension enabled on your web server</p>

                    <p>This is a library that allows the plugin to better export your static site out to services like GitHub, S3, Dropbox, BunnyCDN, etc. It's usually an easy fix to get this working. You can try Googling "How to enable cURL extension for PHP", along with the name of the environment you are using to run your WordPress site. This may be something like DigitalOcean, GoDaddy or LAMP, MAMP, WAMP for your webserver on your local computer.</p>
                </td>
            </tr>
            <tr>
                <td>WordPress Permalinks Compatible</td>
                <td>
                    <?php echo $view['permalinksAreCompatible'] ? 'Yes' : 'No'; ?>

                    <span
                        class="dashicons <?php echo $view['permalinksAreCompatible'] ? 'dashicons-yes' : 'dashicons-no'; ?>"
                        style="color: <?php echo $view['permalinksAreCompatible'] ? 'green' : 'red'; ?>;"
                    ></span>
                </td>
                <td>
                    <p>Due to the nature of how static sites work, you'll need to have some kind of permalinks structure defined in your <a href="<?php echo esc_url( admin_url( 'options-permalink.php' ) ); ?>">Permalink Settings</a> within WordPress. To learn more on how to do this, please see WordPress's official guide to the <a href="https://codex.wordpress.org/Settings_Permalinks_Screen">Settings Permalinks Screen</a>. The permalinks must end in a trailing slash (/).</p>
                </td>
            </tr>
        </tbody>
    </table>


    <h4>Loaded PHP extensions</h4>

    <table class="widefat striped">
        <tbody>

    <?php
    natcasesort( $view['extensions'] );
    $static_deploy_ar_list = $view['extensions'];
    $static_deploy_rows = (int) ceil( count( $static_deploy_ar_list ) / 5 );

    if ( $static_deploy_rows < 1 ) {
        echo '<tr>';
        echo '<td>No extensions loaded.</td>';
        echo '</tr>';
    } else {
        $static_deploy_lists = array_chunk( $static_deploy_ar_list, $static_deploy_rows );

        foreach ( $static_deploy_lists as $static_deploy_list ) {
            echo '<tr>';
            foreach ( $static_deploy_list as $static_deploy_item ) {
                $static_deploy_loaded_extension = strval( $static_deploy_item );
                echo '<td>', esc_html( $static_deploy_loaded_extension ), '</td>';
            }
            echo '</tr>';
        }
    }

    ?>
        </tbody>
    </table>

    <h4>Static Deploy Core Options</h4>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>Name</th>
                <th>Value</th>
            </tr>
        </thead>
        <tbody>

            <?php foreach ( $view['options'] as $static_deploy_option ) : ?>

            <tr>
            <td><?php echo esc_html( $static_deploy_option->option_spec->label ); ?></td>
            <td><?php echo esc_html( $static_deploy_option->value ); ?></td>
            </tr>

            <?php endforeach; ?>

        </tbody>
    </table>

    <h4>WordPress Site Info</h4>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>Name</th>
                <th>Value</th>
            </tr>
        </thead>
        <tbody>

            <?php
            foreach ( $view['site_info'] as $static_deploy_name => $static_deploy_value ) : ?>
            <tr>
            <td><?php echo esc_html( $static_deploy_name ); ?></td>
            <td><?php echo esc_html( $static_deploy_value ); ?></td>
            </tr>

            <?php endforeach; ?>

        </tbody>
    </table>
</div>
