<?php
// phpcs:disable Generic.Files.LineLength.MaxExceeded                              
// phpcs:disable Generic.Files.LineLength.TooLong                                  

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

StaticDeploy\Controller::init();

$static_deploy_run_nonce = wp_create_nonce( StaticDeploy\Controller::getHookName( 'run_page' ) );

// Enqueue jQuery (WordPress core dependency)
wp_enqueue_script( 'jquery' );

// Enqueue and localize the run page script
wp_enqueue_script(
    'static-deploy-run-page',
    '',
    [ 'jquery' ],
    STATIC_DEPLOY_VERSION,
    true
);

// Add inline script with localized data
$static_deploy_script_data = [
    'runAction' => StaticDeploy\Controller::getHookName( 'run' ),
    'pollLogAction' => StaticDeploy\Controller::getHookName( 'poll_log' ),
    'nonce' => $static_deploy_run_nonce,
    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
];

wp_register_script(
    'static-deploy-run-page',
    '',
    [],
    1,
    [
        'in_footer' => false,
    ],
);
wp_enqueue_script( 'static-deploy-run-page' );

wp_add_inline_script(
    'static-deploy-run-page',
    'var staticDeployRunPage = ' . wp_json_encode( $static_deploy_script_data ) . ';'
);

wp_add_inline_script(
    'static-deploy-run-page',
    '
var latest_log_row = 0;

jQuery(document).ready(function($){
    var run_data = {
        action: staticDeployRunPage.runAction,
        security: staticDeployRunPage.nonce,
    };

    var log_data = {
        dataType: "text",
        action: staticDeployRunPage.pollLogAction,
        startRow: latest_log_row,
        security: staticDeployRunPage.nonce,
    };

    function responseErrorHandler( jqXHR, textStatus, errorThrown ) {
        $("#static-deploy-spinner").removeClass("is-active");
        $("#static-deploy-run" ).prop("disabled", false);

        console.log(errorThrown);
        console.log(jqXHR.responseText);

        alert(jqXHR.status + " error code returned from server.\nPlease check your server\'s error logs or try increasing your max_execution_time limit in PHP if this consistently fails after the same duration.\nMore information of the error may be logged in your browser\'s console.");
    }

    function pollLogs() {
        $.post(staticDeployRunPage.ajaxUrl, log_data, function(response) {
            $("#static-deploy-run-log").val(response);
            $("#static-deploy-poll-logs" ).prop("disabled", false);
        });
    }

    $( "#static-deploy-run" ).click(function() {
        $("#static-deploy-spinner").addClass("is-active");
        $("#static-deploy-run" ).prop("disabled", true);

        $.ajax({
            url: staticDeployRunPage.ajaxUrl,
            type: "POST",
            data: run_data,
            timeout: 0,
            success: function() {
                $("#static-deploy-spinner").removeClass("is-active");
                $("#static-deploy-run" ).prop("disabled", false);
                pollLogs();
            },
            error: responseErrorHandler
        });

    });

    $( "#static-deploy-poll-logs" ).click(function() {
        $("#static-deploy-poll-logs" ).prop("disabled", true);
        pollLogs();
    });
});
'
);
?>

<div class="wrap">
    <br>

    <button class="button button-primary" id="static-deploy-run">Generate static site</button>

    <div id="static-deploy-spinner" class="spinner" style="padding:2px;float:none;"></div>

    <br>
    <br>

    <button class="button" id="static-deploy-poll-logs">Refresh logs</button>
    <br>
    <br>
    <textarea id="static-deploy-run-log" rows=30 style="width:99%;">
    Logs will appear here on completion or click "Refresh logs" to check progress
    </textarea>
</div>
