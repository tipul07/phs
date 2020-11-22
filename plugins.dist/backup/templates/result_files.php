<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\PHS_Scope;
    use \phs\PHS_Ajax;
    use \phs\libraries\PHS_Notifications;

    $current_user = PHS::user_logged_in();

    /** @var \phs\plugins\backup\models\PHS_Model_Results $results_model */
    /** @var \phs\plugins\backup\models\PHS_Model_Rules $rules_model */
    /** @var \phs\plugins\backup\PHS_Plugin_Backup $backup_plugin */
    if( !($result_arr = $this->view_var( 'result_data' ))
     or !($rule_arr = $this->view_var( 'rule_data' ))
     or !($results_model = $this->view_var( 'results_model' ))
     or !($rules_model = $this->view_var( 'rules_model' ))
     or !($backup_plugin = $this->view_var( 'backup_plugin' )) )
        return $this->_pt( 'Could\'t loaded required resources for this view.' );

    if( !($result_files_arr = $this->view_var( 'result_files_arr' )) )
        $result_files_arr = array();

    if( !($back_page = $this->view_var( 'back_page' )) )
        $back_page = '';

    $current_scope = PHS_Scope::current_scope();

    $url_params = array( 'p' => 'backup', 'a' => 'result_files' );

    $url_args = array(
        'result_id' => $result_arr['id'],
    );
    if( !empty( $back_page ) )
        $url_args['back_page'] = $back_page;

    if( !($days_arr = $rules_model->get_rule_days()) )
        $days_arr = array();

    if( !($rule_days_arr = $rules_model->get_rule_days_as_array( $rule_arr['id'] )) )
        $rule_days_arr = array();

    $days_str_arr = array();
    foreach( $rule_days_arr as $day )
    {
        if( empty( $days_arr[$day] ) )
            continue;

        $days_str_arr[] = $days_arr[$day];
    }

    if( empty( $days_str_arr ) )
        $days_str_arr = '';
    else
        $days_str_arr = implode( ', ', $days_str_arr );

    $hour_str = '';
    if( isset( $rule_arr['hour'] ) )
        $hour_str = ($days_str_arr!=''?' @':'').$rule_arr['hour'].($rule_arr['hour']<12?'am':'pm');

    $running_times = $days_str_arr.$hour_str;

?>
<style>
.backup_result_files_message_container { margin-bottom: 0.5em; }
</style>

<div class="backup_result_files_message_container clearfix">
    <div id="backup_result_files_ajax_success_box" style="display:none;" class="success-box clearfix"><div class="dismissible"></div></div>
    <div id="backup_result_files_ajax_warnings_box" style="display:none;" class="warning-box clearfix"><div class="dismissible"></div></div>
    <div id="backup_result_files_ajax_errors_box" style="display:none;" class="error-box clearfix"><div class="dismissible"></div></div>
</div>

<form id="backup_result_files" name="backup_result_files" action="<?php echo PHS::url( $url_params, $url_args )?>" method="post">
<input type="hidden" name="foobar" value="1" />
<?php
if( $current_scope == PHS_Scope::SCOPE_AJAX )
{
    ?><input type="hidden" name="do_submit" value="1" /><?php
}
?>
<input type="hidden" name="result_id" value="<?php echo $result_arr['id']?>" />

    <div class="form_container clearfix" style="width: 98%;">

        <?php
        if( $current_scope != PHS_Scope::SCOPE_AJAX )
        {
        ?>
        <section class="heading-bordered">
            <h3><?php echo $this->_pt( 'Backup Result Files' )?></h3>
        </section>
        <?php
        }
        ?>

        <fieldset class="form-group">
            <label><?php echo $this->_pt( 'For rule' )?></label>
            <div class="lineform_line">
                <?php echo $rule_arr['title'].(!empty( $running_times )?' - '.$running_times:'')?>
            </div>
        </fieldset>

        <fieldset class="form-group">
            <label><?php echo $this->_pt( 'Started' )?></label>
            <div class="lineform_line">
                <?php echo date( 'd-m-Y H:i', parse_db_date( $result_arr['cdate'] ) )?>
            </div>
        </fieldset>

        <fieldset class="form-group">
            <label><?php echo $this->_pt( 'Status' )?></label>
            <div class="lineform_line">
                <?php echo ((!empty( $result_arr['status'] ) and $status_arr = $results_model->valid_status( $result_arr['status'] ))?$status_arr['title']:$this->_pt( 'N/A' ))?>
                <?php echo (!empty_db_date( $result_arr['status_date'] )?' - '.date( 'd-m-Y H:i', parse_db_date( $result_arr['status_date'] ) ):'')?>
            </div>
        </fieldset>

        <fieldset class="form-group">
            <label><?php echo $this->_pt( 'Total size' )?></label>
            <div class="lineform_line">
                <?php echo format_filesize( $result_arr['size'] ).' - '.$this->_pt( '%s bytes', number_format( $result_arr['size'] ) )?>
            </div>
        </fieldset>

        <fieldset class="form-group">
            <table style="width:100%">
            <thead>
                <tr>
                    <th style="width:20px;text-align: center;">#</th>
                    <th><?php echo $this->_pt( 'File' )?></th>
                    <th style="text-align: center;" style="width:80px;"><?php echo $this->_pt( 'Size' )?></th>
                    <th style="width:80px;">&nbsp;</th>
                </tr>
            </thead>
            <?php
            if( empty( $result_files_arr ) or !is_array( $result_files_arr ) )
            {
                ?>
                <tbody>
                <tr>
                    <td colspan="4" style="padding:10px;text-align:center;"><?php echo $this->_pt( 'It seems that backup result has no backup files...' )?></td>
                </tr>
                </tbody>
                <?php
            } else
            {
            ?>
            <tbody>
                <?php
                $knti = 1;
                foreach( $result_files_arr as $file_id => $file_arr )
                {
                    ?>
                    <tr>
                        <td style="text-align: center;"><?php echo $knti?></td>
                        <td><div style="white-space: nowrap;overflow: hidden;" title="<?php echo self::_e( $file_arr['file'] )?>" class="no-title-skinning"><?php echo $file_arr['file']?></div></td>
                        <td><div style="text-align: center;" title="<?php echo self::_e( $this->_pt( '%s bytes', number_format( $file_arr['size'] ) ) )?>"><?php echo format_filesize( $file_arr['size'] )?></div></td>
                        <td>
                            <a href="<?php echo PHS::url( array( 'p' => 'backup', 'a' => 'd' ), array( 'brfid' => $file_arr['id'] ) )?>" onfocus="this.blur()"><i class="fa fa-download action-icons" title="<?php echo $this->_pt( 'Download result file' )?>"></i></a>
                            <a href="javascript:void(0)" onfocus="this.blur()" onclick="result_file_delete_result_file( '<?php echo $file_arr['id']?>' )"><i class="fa fa-times action-icons" title="<?php echo $this->_pt( 'Delete result file' )?>"></i></a>
                        </td>
                    </tr>
                    <?php
                    $knti++;
                }
                ?>
                <tr>
                    <td colspan="2" style="text-align: right;padding:5px;"><strong><?php echo $this->_pt( 'TOTAL' )?></strong></td>
                    <td><div style="text-align: center;" title="<?php echo self::_e( $this->_pt( '%s bytes', number_format( $result_arr['size'] ) ) )?>"><strong><?php echo format_filesize( $result_arr['size'] )?></strong></div></td>
                    <td>&nbsp;</td>
                </tr>
            </tbody>
            <?php
            }
            ?>
            </table>
        </fieldset>

    </div>

</form>

<script type="text/javascript">
function result_file_delete_result_file( id )
{
    if( !confirm( "<?php echo $this::_te( 'Are you sure you want to delete this backup file?' )?>" ) )
        return false;

    backup_result_file_hide_messages();

    show_submit_protection( "<?php echo $this->_pte( 'Sending delete request... Please wait.' )?>" );

    var ajax_params = {
        cache_response: false,
        method: 'post',
        url_data: { action: 'delete', result_id: <?php echo $result_arr['id']?>, brfid: id },
        data_type: 'json',

        onsuccess: function( response, status, ajax_obj, response_data ) {
            hide_submit_protection();

            var new_data = response_data;
            var we_have_error_messages = false;
            if( typeof response_data != "undefined" && response_data
             && typeof response_data.status != 'undefined' && response_data.status )
            {
                if( typeof response_data.status.success_messages != 'undefined' && response_data.status.success_messages.length )
                    backup_result_messages( response_data.status.success_messages, "success" );
                if( typeof response_data.status.warning_messages != 'undefined' && response_data.status.warning_messages.length )
                {
                    we_have_error_messages = true;
                    backup_result_messages( response_data.status.warning_messages, "warnings" );
                }
                if( typeof response_data.status.error_messages != 'undefined' && response_data.status.error_messages.length )
                {
                    we_have_error_messages = true;
                    backup_result_messages( response_data.status.error_messages, "errors" );
                }

                new_data.status.success_messages = [];
                new_data.status.warning_messages = [];
                new_data.status.error_messages = [];
            }

            if( we_have_error_messages
             && response_data && typeof response_data.redirect_to_url != 'undefined' && response_data.redirect_to_url )
                new_data.redirect_to_url = '';

            <?php
            if( PHS_Scope::current_scope() == PHS_Scope::SCOPE_AJAX )
            {
                ?>
                if( !we_have_error_messages
                 && new_data.redirect_to_url.length )
                {
                    // Reload AJAX modal...
                    PHS_JSEN.reloadAjaxDialog({
                        suffix: "backup_result_files_",
                        url: new_data.redirect_to_url,
                        url_data: { result_id: <?php echo $result_arr['id']?>, back_page: "<?php echo $this->_e( $back_page )?>" }
                    });

                    // Foobar response so PHS_JSEN will not parse results (we must reload modal)
                    return { we_got_this: 1 };
                }
                <?php
            }
            ?>

            return new_data;
        },

        onfailed: function( ajax_obj, status, error_exception ) {
            hide_submit_protection();

            backup_result_messages( [ "<?php echo $this->_pt( 'Error sending request to delete backup file. Please retry.' )?>" ], "errors" );
        }
    };

    var ajax_obj = PHS_JSEN.do_ajax( "<?php echo PHS_Ajax::url( array( 'p' => 'backup', 'a' => 'result_files' ) )?>", ajax_params );
}

function backup_result_file_hide_messages()
{
    backup_result_file_hide_message( "success" );
    backup_result_file_hide_message( "warnings" );
    backup_result_file_hide_message( "errors" );
}

function backup_result_file_hide_message( type )
{
    var message_box = $("#backup_result_files_ajax_" + type + "_box");
    if( message_box )
    {
        message_box.find( ".dismissible" ).html( "" );
        message_box.hide();
    }
}

function backup_result_messages( messages_arr, type )
{
    if( typeof messages_arr == "undefined" || !messages_arr
     || typeof messages_arr.length == "undefined" || !messages_arr.length )
        return;

    var message_box = $("#backup_result_files_ajax_" + type + "_box");
    if( message_box )
    {
        for( var i = 0; i < messages_arr.length; i++ )
            message_box.find( ".dismissible" ).append( "<p>" + messages_arr[i] + "</p>" );
        message_box.show();
    }
}
$(document).ready(function(){
    phs_refresh_input_skins();
    <?php
    if( ($all_notifications = PHS_Notifications::get_all_notifications()) )
    {
        foreach( $all_notifications as $type => $notifications_arr )
        {
            if( empty( $notifications_arr ) or !is_array( $notifications_arr ) )
                continue;

            ?>
            backup_result_messages( <?php echo @json_encode( $notifications_arr )?>, "<?php echo $type?>" );
            <?php
        }
    }
    ?>
});
</script>
