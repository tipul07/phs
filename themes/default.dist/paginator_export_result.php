<?php

use phs\PHS;
use phs\PHS_Ajax;
use phs\libraries\PHS_Utils;
use phs\libraries\PHS_Paginator;
use phs\system\core\views\PHS_View;
use phs\libraries\PHS_Model_Core_base;
use phs\plugins\accounts\models\PHS_Model_Accounts;
use phs\system\core\libraries\PHS_Paginator_exporter_manager;

/** @var PHS_View $this */
/** @var PHS_Paginator $paginator_obj */
if (!($paginator_obj = $this->view_var('paginator'))
    || !($paginator_action = $paginator_obj->get_paginator_action())
    || !($exporter_lib = PHS_Paginator_exporter_manager::get_instance())
    || !($accounts_model = PHS_Model_Accounts::get_instance())
    || !($current_user = PHS::user_logged_in())
    || !($export_status = $exporter_lib->read_export_details($paginator_action, $current_user))) {
    return '';
}

$show_cancel_button = $exporter_lib->can_cancel_export($export_status);
$show_reset_button = $exporter_lib->can_reset_export($export_status);
$show_download_button = $exporter_lib->is_success_status($export_status);

$is_finished = $exporter_lib->is_final_status($export_status);

$export_status['max_count'] ??= 0;
$export_status['current_count'] ??= 0;

$progress_perc = min(100, (!(float)$export_status['max_count'] ? 0 : ceil(($export_status['current_count'] * 100) / $export_status['max_count'])));
?>
<div class="list_filters_container list_export_container">
    <div class="form_container" style="padding: 5px;">
        <div class="text-center">
            <div class="row justify-content-start" style="margin: 0 10px;">
                <div style="padding: 5px;float: left;line-height:3em;">
                    <?php echo $this::_t('Format'); ?>: <?php echo strtoupper($export_status['export_format'] ?? 'CSV'); ?>,
                    <?php echo $this::_t('Status'); ?>:
                    <span id="phs_paginator_export_result_status_title"><?php echo $exporter_lib->get_status_title($export_status['status']) ?: $this::_t('N/A'); ?></span>,
                </div>
                <div style="padding: 5px;float: left;line-height:3em;">
                    <?php echo $this::_t('Started'); ?>:
                    <?php echo !empty($export_status['start_time']) ? $paginator_obj->pretty_date_independent(date(PHS_Model_Core_base::DATETIME_DB, $export_status['start_time']), ['date_format' => 'd-m-Y H:i']) : $this::_t('N/A'); ?>
                </div>
                <div style="padding: 5px;float: left;margin-top: 1em;line-height: 3em;">
                    <div style="width:200px;height:10px;background-color:red;"><div id="phs_paginator_export_result_status_progress_bar" style="width:<?php echo $progress_perc; ?>%;height:10px;background-color:green;"></div></div>
                </div>
                <div style="padding: 5px;float: left;line-height:3em;">
                    <span id="phs_paginator_export_result_current_count"><?php echo $export_status['current_count']; ?></span>
                    /
                    <span id="phs_paginator_export_result_max_count"><?php echo $export_status['max_count']; ?></span>
                    (<span id="phs_paginator_export_result_progress_perc"><?php echo $progress_perc; ?></span>%)
                    - <span id="phs_paginator_export_result_time_taken"><?php echo PHS_Utils::parse_period(max(0, (($export_status['end_time'] ?? 0) ?: time()) - $export_status['start_time'])); ?></span>
                </div>
                <div style="padding: 5px;float: left;line-height:3em;"><span id="phs_paginator_export_result_msg"><?php echo $export_status['msg'] ?? ''; ?></span></div>

                <div id="phs_paginator_export_result_button_cancel"
                     style="padding: 5px;float: left;line-height:3em;display: <?php echo $show_cancel_button ? 'block' : 'none'; ?>">
                    <button class="btn btn-danger" type="button"
                            onclick="this.blur();phs_paginator_export_action_cancel_export()"><?php echo $this::_te('Cancel'); ?></button>
                </div>
                <div id="phs_paginator_export_result_button_reset"
                     style="padding: 5px;float: left;line-height:3em;display: <?php echo $show_reset_button ? 'block' : 'none'; ?>">
                    <button class="btn btn-primary" type="button"
                            onclick="this.blur();phs_paginator_export_action_reset_export()"><?php echo $this::_te('Reset export'); ?></button>
                </div>
                <div id="phs_paginator_export_result_button_download"
                     style="padding: 5px;float: left;line-height:3em;display: <?php echo $show_download_button ? 'block' : 'none'; ?>">
                    <button class="btn btn-primary" type="button"
                            onclick="this.blur();phs_paginator_export_action_download_export()"><?php echo $this::_te('Download'); ?></button>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function phs_paginator_export_action_download_export()
{
    <?php
        $url_params = [];
$url_params['action'] = [
    'action' => $paginator_action::ACTION_DOWNLOAD_EXPORT,
];
?>document.location = "<?php echo $paginator_obj->get_full_url($url_params); ?>";
}
function phs_paginator_export_action_reset_export()
{
    if( !confirm( "<?php echo $this::_te('Are you sure you want to reset export details?', '"'); ?>" ) ) {
        return
    }

    show_submit_protection("<?php echo $this::_te('Resetting export...'); ?>");

    <?php
$url_params = [];
$url_params['action'] = [
    'action' => $paginator_action::ACTION_RESET_EXPORT,
];
?>document.location = "<?php echo $paginator_obj->get_full_url($url_params); ?>";
}
function phs_paginator_export_action_cancel_export()
{
    if( !confirm( "<?php echo $this::_te('Cancel request will be sent to the background script which might take some time. Export will be cancelled as soon as possible.', '"'); ?>" + "\n" +
        "<?php echo $this::_te('Are you sure you want to CANCEL the export?', '"'); ?>" + "\n" +
        "<?php echo $this::_te('NOTE: You cannot undo this action!', '"'); ?>" ) ) {
        return
    }

    show_submit_protection("<?php echo $this::_te('Cancelling export...'); ?>");

    <?php
$url_params = [];
$url_params['action'] = [
    'action' => $paginator_action::ACTION_CANCEL_EXPORT,
];
?>document.location = "<?php echo $paginator_obj->get_full_url($url_params); ?>";
}
<?php
if ($accounts_model->acc_is_operator($current_user)) {
    ?>
function refresh_export_data()
{
    const ajax_params = {
        cache_response: false,
        method: 'post',
        url_data: {"action_class": "<?php echo str_replace('\\', '\\\\', $paginator_action::class); ?>"},
        data_type: 'json',

        onsuccess: function (response, status, ajax_obj, response_data) {
            if (!response) {
                return
            }

            console.log(response);

            if (typeof response.status_title !== "undefined" && response.status_title.length) {
                $("#phs_paginator_export_result_status_title").html(response.status_title);
            }
            if (typeof response.current_count !== "undefined" && response.current_count) {
                $("#phs_paginator_export_result_current_count").html(response.current_count);
            }
            if (typeof response.max_count !== "undefined" && response.max_count) {
                $("#phs_paginator_export_result_max_count").html(response.max_count);
            }
            if (typeof response.time_taken !== "undefined" && response.time_taken.length) {
                $("#phs_paginator_export_result_time_taken").html(response.time_taken);
            }
            if (typeof response.progress_perc !== "undefined") {
                $("#phs_paginator_export_result_status_progress_bar").css("width", response.progress_perc+"%");
                $("#phs_paginator_export_result_progress_perc").html(response.progress_perc);
            }
            if (typeof response.msg !== "undefined" && response.msg.length) {
                $("#phs_paginator_export_result_msg").html(response.msg);
            }

            if (typeof response.is_final !== "undefined" && !response.is_final) {
                $("#phs_paginator_export_result_button_cancel").show();
                queue_refresh_export_data();
            } else {
                $("#phs_paginator_export_result_button_cancel").hide();
                $("#phs_paginator_export_result_button_reset").show();
                if (typeof response.is_success !== "undefined" && response.is_success) {
                    $("#phs_paginator_export_result_button_download").show();
                }
            }
        },

        onfailed: function (ajax_obj, status, error_exception) {
            PHS_JSEN.js_message_warning("<?php echo $this::_t('Error updating export status. You can refresh the page for status update.'); ?>");
        }
    };

    PHS_JSEN.do_ajax( "<?php echo PHS_Ajax::url(['a' => 'paginator_export_status_ajax', 'ad' => 'paginator']); ?>", ajax_params );
}
function queue_refresh_export_data()
{
    setTimeout(refresh_export_data, 5000);
}
<?php
if (!$is_finished) {
    ?>queue_refresh_export_data();<?php
}
}
?>
</script>
