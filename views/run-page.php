<?php
// phpcs:disable Generic.Files.LineLength.MaxExceeded                              
// phpcs:disable Generic.Files.LineLength.TooLong                                  

StaticDeploy\Controller::init( __DIR__ . '/static-deploy.php' );

$run_nonce = wp_create_nonce( StaticDeploy\Controller::getHookName( 'run_page' ) );
?>

<script type="text/javascript">
var latest_log_row = 0;

jQuery(document).ready(function($){
    var run_data = {
        action: "<?php echo StaticDeploy\Controller::getHookName( 'run' ); ?>",
        security: '<?php echo $run_nonce; ?>',
    };

    var log_data = {
        dataType: 'text',
        action: "<?php echo StaticDeploy\Controller::getHookName( 'poll_log' ); ?>",
        startRow: latest_log_row,
        security: '<?php echo $run_nonce; ?>',
    };

    function responseErrorHandler( jqXHR, textStatus, errorThrown ) {
        $("#static-deploy-spinner").removeClass("is-active");
        $("#static-deploy-run" ).prop('disabled', false);

        console.log(errorThrown);
        console.log(jqXHR.responseText);

        alert(`${jqXHR.status} error code returned from server.
Please check your server's error logs or try increasing your max_execution_time limit in PHP if this consistently fails after the same duration.
More information of the error may be logged in your browser's console.`);
    }

    function pollLogs() {
        $.post(ajaxurl, log_data, function(response) {
            $('#static-deploy-run-log').val(response);
            $("#static-deploy-poll-logs" ).prop('disabled', false);
        });
    }

    $( "#static-deploy-run" ).click(function() {
        $("#static-deploy-spinner").addClass("is-active");
        $("#static-deploy-run" ).prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: run_data,
            timeout: 0,
            success: function() {
                $("#static-deploy-spinner").removeClass("is-active");
                $("#static-deploy-run" ).prop('disabled', false);
                pollLogs();
            },
            error: responseErrorHandler
        });

    });

    $( "#static-deploy-poll-logs" ).click(function() {
        $("#static-deploy-poll-logs" ).prop('disabled', true);
        pollLogs();
    });
});
</script>

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
