<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;
use phs\system\core\models\PHS_Model_Request_queue;

$current_user = PHS::user_logged_in();

/** @var PHS_Model_Request_queue $requests_model */
if (!($request_arr = $this->view_var('request_data'))
 || !($requests_model = $this->view_var('requests_model'))) {
    return $this->_pt('Error loading required resources.');
}

$runs_arr = $this->view_var('runs_arr') ?: [];
?>
<div class="form-group row">
    <label class="col-sm-2 col-form-label"><?php echo $this->_pt('URL'); ?></label>
    <div class="col-sm-10"><?php echo strtoupper($request_arr['method'] ?? '').' '.$request_arr['url']; ?></div>
</div>

<div class="form-group row">
    <label class="col-sm-2 col-form-label"><?php echo $this->_pt('Retries'); ?></label>
    <div class="col-sm-10"><?php
        echo $this->_pt('Fails: %s, Max retries: %s', $request_arr['fails'], $request_arr['max_retries'] );
?></div>
</div>

<div class="form-group row">
    <label class="col-sm-2 col-form-label"><?php echo $this->_pt('Status'); ?></label>
    <div class="col-sm-10"><?php
    echo (!empty($request_arr['status']) && ($status_arr = $requests_model->valid_status($request_arr['status'])))
        ? $status_arr['title']
        : $this->_pt('N/A');
echo $request_arr['status_date'] ? ' - '.http_pretty_date($request_arr['status_date']) : '';

echo $requests_model->is_final($request_arr) ? ' ('.$this->_pt('Final').')' : '';
?></div>
</div>

<div class="form-group row">
    <label class="col-sm-2 col-form-label"><?php echo $this->_pt('Run after'); ?></label>
    <div class="col-sm-10"><?php echo $request_arr['run_after'] ? http_pretty_date($request_arr['run_after']) : $this->_pt('N/A'); ?></div>
</div>

<div class="form-group row">
    <label class="col-sm-2 col-form-label"><?php echo $this->_pt('Created'); ?></label>
    <div class="col-sm-10"><?php echo http_pretty_date($request_arr['cdate']); ?></div>
</div>

<table class="tgrid" style="width:100%">
    <thead>
    <tr>
        <th style="width:20px;text-align: center;">#</th>
        <th style="text-align: center;"><?php echo $this->_pt('Id'); ?></th>
        <th style="text-align: center;"><?php echo $this->_pt('HTTP Code'); ?></th>
        <th style="text-align: center;width:180px;"><?php echo $this->_pt('Response'); ?></th>
        <th style="text-align: center;"><?php echo $this->_pt('Status'); ?></th>
        <th class="date_th"><?php echo $this->_pt('Date'); ?></th>
    </tr>
    </thead>
    <?php
if (empty($runs_arr)) {
    ?>
        <tbody>
        <tr>
            <td colspan="6" style="padding:10px;text-align:center;">
                <?php echo $this->_pt('There are no requests for this HTTP call yet.'); ?>
            </td>
        </tr>
        </tbody>
        <?php
} else {
    ?>
        <tbody>
        <?php
    $knti = 1;
    foreach ($runs_arr as $run_id => $run_arr) {
        ?>
            <tr>
                <td style="text-align: right;"><?php echo $knti; ?></td>
                <td style="text-align: center;"><?php echo $run_arr['id']; ?></td>
                <td style="text-align: center;"><?php
                echo $run_arr['http_code'];

        if (!empty($run_arr['error'])) {
            ?>
                        <a href="javascript:void(0)" onclick="phs_httpcalls_request_error( '<?php echo $run_arr['id']; ?>' )"
                           title="<?php echo $this->_pte('View request error'); ?>"
                           onfocus="this.blur()"><i class="fa fa-exclamation action-icons"></i></a>
                        <div id="phs_httpcalls_request_error_<?php echo $run_arr['id']; ?>" style="display:none;">
                            <?php echo str_replace('  ', ' &nbsp;', nl2br(htmlspecialchars($run_arr['error']))); ?>
                        </div>
                        <?php
        }
        ?></td>
                <td style="text-align: center;"><?php
            if (empty($run_arr['response'])) {
                echo '-';
            } else {
                ?>
                    <a href="javascript:void(0)" onclick="phs_httpcalls_request_response_body( '<?php echo $run_arr['id']; ?>' )"
                       title="<?php echo $this->_pte('View response body'); ?>"
                       onfocus="this.blur()"><i class="fa fa-sign-out action-icons"></i></a>
                    <div id="phs_httpcalls_request_response_body_<?php echo $run_arr['id']; ?>" style="display:none;">
                        <?php echo str_replace('  ', ' &nbsp;', nl2br(htmlspecialchars($run_arr['response']))); ?>
                    </div>
                    <?php
            }
        ?></td>
                <td style="text-align: center;"><?php
            echo (!empty($run_arr['status']) && ($status_arr = $requests_model->valid_status($run_arr['status'])))
                ? $status_arr['title']
                : $this->_pt('N/A');
        ?></td>
                <td class="date"><?php echo http_pretty_date($run_arr['cdate']); ?></td>
            </tr>
            <?php
            $knti++;
    }
    ?>
        <tr>
            <td colspan="6" style="text-align: right;padding:5px;">
                <strong><?php echo $this->_pt('There are %s requests for current HTTP call.', $knti - 1); ?></strong>
            </td>
        </tr>
        </tbody>
        <?php
}
?>
</table>

<script type="text/javascript">
    function phs_httpcalls_request_response_body( id )
    {
        const container_obj = $("#phs_httpcalls_request_response_body_" + id);
        if( !container_obj )
            return;

        container_obj.show();

        PHS_JSEN.createAjaxDialog( {
            suffix: "phs_httpcall_response_body_",
            width: 700,
            height: 650,
            title: "<?php echo $this->_pte('Response Body'); ?>",
            resizable: true,
            close_outside_click: false,
            source_obj: container_obj,
            source_not_cloned: true,
            onbeforeclose: () => phs_httpcalls_request_response_body_close(id)
        });

        return false;
    }
    function phs_httpcalls_request_response_body_close(id)
    {
        const container_obj = $("#phs_httpcalls_request_response_body_" + id);
        if( !container_obj )
            return;

        container_obj.hide();
    }

    function phs_httpcalls_request_error( id )
    {
        const container_obj = $("#phs_httpcalls_request_error_" + id);
        if( !container_obj )
            return;

        container_obj.show();

        PHS_JSEN.createAjaxDialog( {
            suffix: "phs_httpcall_response_error_",
            width: 700,
            height: 650,
            title: "<?php echo $this->_pte('Request Error'); ?>",
            resizable: true,
            close_outside_click: false,
            source_obj: container_obj,
            source_not_cloned: true,
            onbeforeclose: () => phs_httpcalls_request_error_close(id)
        });

        return false;
    }
    function phs_httpcalls_request_error_close(id)
    {
        const container_obj = $("#phs_httpcalls_request_error_" + id);
        if( !container_obj )
            return;

        container_obj.hide();
    }
</script>
