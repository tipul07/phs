<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_Utils;

    /** @var \phs\plugins\backup\PHS_Plugin_Backup $backup_plugin */
    /** @var \phs\plugins\backup\models\PHS_Model_Rules $rules_model */
    if( !($backup_plugin = $this->view_var( 'backup_plugin' ))
     || !($rules_model = $this->view_var( 'rules_model' )) )
        return $this->_pt( 'Couldn\'t load backup plugin.' );

    if( !($target_arr = $this->view_var( 'target_arr' )) )
        $target_arr = [];
    if( !($days_arr = $this->view_var( 'days_arr' )) )
        $days_arr = [];
    if( !($ftp_settings = $this->view_var( 'ftp_settings' )) )
        $ftp_settings = [];

    if( !($plugin_location = $this->view_var( 'plugin_location' )) )
        $plugin_location = [];
    if( !($rule_location = $this->view_var( 'rule_location' )) )
        $rule_location = [];
    if( !($rule_days = $this->view_var( 'rule_days' )) )
        $rule_days = [];
    if( !($targets_arr = $this->view_var( 'targets_arr' )) )
        $targets_arr = [];
    if( !($days_options_arr = $this->view_var( 'days_options_arr' )) )
        $days_options_arr = [];
    if( !($copy_results_arr = $this->view_var( 'copy_results_arr' )) )
        $copy_results_arr = [];
    if( !($ftp_connection_modes_arr = $this->view_var( 'ftp_connection_modes_arr' )) )
        $ftp_connection_modes_arr = [];

    $error_msg = '';
    $stats_str = '';
    if( empty( $rule_location )
     || !($location_details = $backup_plugin->resolve_directory_location( $rule_location['location_path'] )) )
        $error_msg = $this->_pt( 'Couldn\'t obtain current location details.' );

    elseif( empty( $location_details['location_exists'] ) )
        $error_msg = $this->_pt( 'At the moment directory doesn\'t exist. System will try creating it at first run.' );

    elseif( empty( $location_details['full_path'] )
        || !@is_writable( $location_details['full_path'] ) )
        $error_msg = $this->_pt( 'Resolved directory is not writeable.' );

    elseif( !($stats_arr = $backup_plugin->get_directory_stats( $location_details['full_path'] )) )
        $error_msg = $this->_pt( 'Couldn\'t obtain directory stats.' );

    else
        $stats_str = $this->_pt( 'Total space: %s, Free space: %s', format_filesize( $stats_arr['total_space'] ), format_filesize( $stats_arr['free_space'] ) );

    if( !($back_page = $this->view_var( 'back_page' )) )
        $back_page = PHS::url( [ 'p' => 'backup', 'a' => 'rules_list' ] );
?>
<div style="min-width:100%;max-width:1000px;margin: 0 auto;">
    <form id="edit_rule_form" name="edit_rule_form" action="<?php echo PHS::url( [ 'p' => 'backup', 'a' => 'rule_edit' ], [ 'rid' => $this->view_var( 'rid' ) ] )?>" method="post">
        <input type="hidden" name="foobar" value="1" />
        <?php
        if( !empty( $back_page ) )
        {
            ?><input type="hidden" name="back_page" value="<?php echo form_str( safe_url( $back_page ) )?>" /><?php
        }
        ?>

        <div class="form_container responsive" style="width: 700px;">

            <?php
            if( !empty( $back_page ) )
            {
                ?><i class="fa fa-chevron-left"></i> <a href="<?php echo form_str( from_safe_url( $back_page ) ) ?>"><?php echo $this->_pt( 'Back' )?></a><?php
            }
            ?>

            <section class="heading-bordered">
                <h3><?php echo $this->_pt( 'Edit Rule' )?></h3>
            </section>

            <fieldset class="form-group">
                <label for="title"><?php echo $this->_pt( 'Title' )?>:</label>
                <div class="lineform_line">
                <input type="text" id="title" name="title" class="form-control" required="required" value="<?php echo form_str( $this->view_var( 'title' ) )?>" style="width: 360px;" autocomplete="off" />
                </div>
            </fieldset>

            <fieldset class="form-group">
                <label for="location"><?php echo $this->_pt( 'Location' )?>:</label>
                <div class="lineform_line">
                <input type="text" id="location" name="location" class="form-control" value="<?php echo form_str( $this->view_var( 'location' ) )?>" style="width: 360px;" autocomplete="off" />
                <br/>
                <small>
                <?php
                echo $this->_pt( 'Leave blank to use location set in plugin settings%s.', (!empty( $plugin_location['location_path'] )?' ('.$plugin_location['location_path'].')':'') ).'<br/>';
                echo $this->_pt( 'If path is not absolute, it will be relative to framework uploads dir (%s).', PHS_UPLOADS_DIR );
                ?><br/></small>
                <?php
                if( !empty( $error_msg ) )
                {
                    ?><div style="color:red;"><?php echo $error_msg?></div><br/><?php
                } elseif( !empty( $stats_str ) )
                {
                    ?><small><?php
                    if( !empty( $rule_location['location_path'] ) )
                        echo $this->_pt( '%s stats:', $rule_location['location_path'] ).'<br/>';
                    ?><strong><?php echo $stats_str?></strong>
                    </small><br/><?php
                }
                ?>
                <small>
                </small>
                </div>
            </fieldset>

            <fieldset class="form-group">
                <label for="hour"><?php echo $this->_pt( 'Hour' )?>:</label>
                <div class="lineform_line">
                <select name="hour" id="hour" class="chosen-select" style="min-width:150px;">
                <option value="-1"><?php echo $this->_pt( ' - Choose - ' )?></option>
                <?php
                $selected_hour = $this->view_var( 'hour' );
                for( $hour = 0; $hour < 24; $hour++ )
                {
                    ?><option value="<?php echo $hour?>" <?php echo (($selected_hour !== false and $selected_hour==$hour)?'selected="selected"':'')?>><?php echo ($hour<10?'0':'').$hour?></option><?php
                }
                ?></select><br/>
                <small><?php echo $this->_pt( 'Current server time %s', date( 'd-m-Y H:i:s (PT)' ) )?></small>
                </div>
            </fieldset>

            <fieldset class="form-group">
                <label for="days_arr"><?php echo $this->_pt( 'Days' )?>:</label>
                <div class="lineform_line">
                <?php
                foreach( $rule_days as $day_id => $day_name )
                {
                    ?>
                    <div style="float:left; margin-right: 10px;">
                        <div style="float:left;"><input type="checkbox" id="days_arr_<?php echo $day_id ?>"
                                                        name="days_arr[]" value="<?php echo form_str( $day_id )?>" rel="skin_checkbox"
                                                        class="<?php echo (empty( $day_id )?'brule_each_day':'brule_day')?>"
                                                        <?php echo (in_array( $day_id, $days_arr ) ? 'checked="checked"' : '')?>
                                                        onclick="changed_days( this )" /></div>
                        <label style="margin-left:5px;width: auto !important;max-width: none !important;float:left;" for="days_arr_<?php echo $day_id ?>">
                            <?php echo $day_name?>
                        </label>
                    </div>
                    <?php

                    if( empty( $day_id ) )
                    {
                        ?><div class="clearfix"></div><?php
                    }
                }
                ?>
                </div>
            </fieldset>

            <fieldset class="form-group">
                <label for="email"><?php echo $this->_pt( 'Targets' )?>:</label>
                <div class="lineform_line">
                <?php
                foreach( $targets_arr as $target_id => $target_name )
                {
                    ?>
                    <div class="clearfix">
                        <div style="float:left;"><input type="checkbox" id="target_arr_<?php echo $target_id ?>"
                                                        name="target_arr[]" value="<?php echo form_str( $target_id )?>" rel="skin_checkbox"
                                                        <?php echo (in_array( $target_id, $target_arr ) ? 'checked="checked"' : '')?> /></div>
                        <label style="margin-left:5px;width: auto !important;max-width: none !important;float:left;" for="target_arr_<?php echo $target_id ?>">
                            <?php echo $target_name?>
                        </label>
                    </div>
                    <?php
                }
                ?>
                </div>
            </fieldset>

            <?php
            if( ($selected_delete_after_days = $this->view_var( 'delete_after_days' )) === false )
                $selected_delete_after_days = 0;
            if( !($cdelete_after_days = $this->view_var( 'cdelete_after_days' ))
             || $cdelete_after_days < 0 )
                $cdelete_after_days = 1;

            $selected_delete_after_days = (int)$selected_delete_after_days;
            ?>
            <fieldset class="form-group">
                <label for="delete_after_days"><?php echo $this->_pt( 'Delete backups' )?>:</label>
                <div class="lineform_line">
                    <div style="float:left;margin-right:5px;">
                    <select name="delete_after_days" id="delete_after_days" class="chosen-select" style="min-width:200px;" onchange="check_delete_after_days_change()">
                    <option value="-1"><?php echo $this->_pt( ' - Choose - ' )?></option>
                    <option value="0" <?php echo ($selected_delete_after_days===0?'selected="selected"':'')?>><?php echo $this->_pt( ' - Don\'t delete results - ' )?></option>
                    <option value="-2" <?php echo ($selected_delete_after_days===-2?'selected="selected"':'')?>><?php echo $this->_pt( ' - Custom value - ' )?></option>
                    <?php
                    foreach( $days_options_arr as $days_no => $days_text )
                    {
                        ?><option value="<?php echo $days_no?>" <?php echo (($selected_delete_after_days !== false and $selected_delete_after_days===$days_no)?'selected="selected"':'')?>><?php echo $days_text?></option><?php
                    }
                    ?></select></div>
                    <div id="delete_after_days_container" style="float:left;margin-right:5px;line-height: 1.5em;">
                        <div style="float:left;margin-right:5px;">
                        <input type="text" id="cdelete_after_days" name="cdelete_after_days" class="form-control" value="<?php echo form_str( $cdelete_after_days )?>" style="width: 60px;" />
                        </div>
                        <div style="float:left;line-height: 1.5em;"><small><?php echo $this->_pt( 'days' )?></small></div>
                    </div>
                    <div class="clearfix"></div>
                    <small><?php echo $this->_pt( 'Delete resulting backup files of this rule after provided number of days.' )?></small>
                </div>
            </fieldset>

            <?php
            if( ($selected_copy_results = $this->view_var( 'copy_results' )) === false )
                $selected_copy_results = 0;

            $selected_copy_results = (int)$selected_copy_results;
            ?>
            <fieldset class="form-group">
                <label for="copy_results"><?php echo $this->_pt( 'Copy results' )?>:</label>
                <div class="lineform_line">
                    <select name="copy_results" id="copy_results" class="chosen-select" style="min-width:200px;" onchange="check_copy_results_change()">
                    <option value="0"><?php echo $this->_pt( ' - Don\'t copy - ' )?></option>
                    <?php
                    foreach( $copy_results_arr as $copy_id => $copy_text )
                    {
                        ?><option value="<?php echo $copy_id?>" <?php echo ($selected_copy_results===$copy_id?'selected="selected"':'')?>><?php echo $copy_text?></option><?php
                    }
                    ?></select>
                    <br/>
                    <small><?php echo $this->_pt( 'After creating backup files, copy them in a different location.' )?></small>
                    <div class="clearfix"></div>
                    <div id="copy_results_container_<?php echo $rules_model::COPY_FTP?>" style="display:none;">

                        <div style="margin-bottom:10px;border-bottom: 1px solid black;" class="clearfix"><strong><?php echo $this->_pt( 'FTP Settings' )?></strong></div>
                        <div class="clearfix" style="margin-bottom:10px;">
                            <label for="ftp_settings_connection_mode" style="width:150px !important;">
                                <?php echo $this->_pt( 'Connection type' )?>
                            </label>
                            <div style="float:left;min-width:200px;max-width:200px;">
                            <select name="ftp_settings[connection_mode]" id="ftp_settings_connection_mode"
                                    class="chosen-select">
                            <option value="0"><?php echo $this->_pt( ' - Choose - ' )?></option>
                            <?php
                            if( !empty( $ftp_settings['connection_mode'] ) )
                                $selected_connection_mode = (int)$ftp_settings['connection_mode'];
                            else
                                $selected_connection_mode = 0;

                            foreach( $ftp_connection_modes_arr as $ctype_id => $ctype_text )
                            {
                                ?><option value="<?php echo $ctype_id?>" <?php echo ($selected_connection_mode===$ctype_id?'selected="selected"':'')?>><?php echo $ctype_text?></option><?php
                            }
                            ?>
                            </select></div>
                        </div>

                        <div style="margin-bottom:10px;">
                            <label for="ftp_settings_host" style="width:150px !important;"><?php echo $this->_pt( 'Host' )?></label>
                            <input type="text" class="form-control" style="width:250px;" autocomplete="off" required="required" rel="is_required"
                                   name="ftp_settings[host]"
                                   id="ftp_settings_host"
                                   value="<?php echo (!empty( $ftp_settings['host'] )?form_str( $ftp_settings['host'] ):'')?>" />
                        </div>

                        <div style="margin-bottom:10px;">
                            <label for="ftp_settings_port" style="width:150px !important;"><?php echo $this->_pt( 'Port' )?></label>
                            <input type="text" class="form-control" style="width:100px;" required="required" rel="is_required"
                                   name="ftp_settings[port]"
                                   id="ftp_settings_port"
                                   value="<?php echo (!empty( $ftp_settings['port'] )?form_str( $ftp_settings['port'] ):'')?>" />
                        </div>

                        <div style="margin-bottom:10px;">
                            <label for="ftp_settings_user" style="width:150px !important;"><?php echo $this->_pt( 'User' )?></label>
                            <input type="text" class="form-control" style="width:250px;" autocomplete="off" required="required" rel="is_required"
                                   name="ftp_settings[user]"
                                   id="ftp_settings_user"
                                   value="<?php echo (!empty( $ftp_settings['user'] )?form_str( $ftp_settings['user'] ):'')?>" />
                        </div>

                        <div style="margin-bottom:10px;">
                            <label for="ftp_settings_pass" style="width:150px !important;"><?php echo $this->_pt( 'Password' )?></label>
                            <input type="password" class="form-control" style="width:250px;" autocomplete="off" required="required" rel="is_required"
                                   name="ftp_settings[pass]"
                                   id="ftp_settings_pass"
                                   value="<?php echo (!empty( $ftp_settings['pass'] )?form_str( $ftp_settings['pass'] ):'')?>" />
                        </div>

                        <div style="margin-bottom:10px;">
                            <label for="ftp_settings_timeout" style="width:150px !important;"><?php echo $this->_pt( 'Timeout' )?></label>
                            <input type="text" class="form-control" style="width:100px;"
                                   name="ftp_settings[timeout]"
                                   id="ftp_settings_timeout"
                                   value="<?php echo (!empty( $ftp_settings['timeout'] )?form_str( $ftp_settings['timeout'] ):'')?>" />
                            <small><?php echo $this->_pt( 'seconds' )?></small>
                        </div>

                        <div style="margin-bottom:10px;">
                            <label for="ftp_settings_passive_mode" style="width:150px !important;"><?php echo $this->_pt( 'Passive mode' )?></label>
                            <input type="checkbox" rel="skin_checkbox"
                                   name="ftp_settings[passive_mode]"
                                   id="ftp_settings_passive_mode"
                                   value="1" <?php echo (!empty( $ftp_settings['passive_mode'] )?'checked="checked"':'')?> /><br/>
                            <small><?php echo $this->_pt( 'Not applicable for all connection modes.' )?></small>
                        </div>

                        <div style="margin-bottom:10px;">
                            <label for="ftp_settings_remote_dir" style="width:150px !important;"><?php echo $this->_pt( 'Remote directory' )?></label>
                            <input type="text" class="form-control" style="width:250px;" autocomplete="off"
                                   name="ftp_settings[remote_dir]"
                                   id="ftp_settings_remote_dir"
                                   value="<?php echo (!empty( $ftp_settings['remote_dir'] )?form_str( $ftp_settings['remote_dir'] ):'')?>" />
                        </div>

                        <div style="margin-bottom:10px;">
                            <input type="submit" id="do_test_ftp" name="do_test_ftp" class="btn btn-primary submit-protection ignore_hidden_required" value="<?php echo $this->_pte( 'Test connection' )?>" />
                        </div>
                    </div>
                </div>
            </fieldset>

            <fieldset class="form-group">
                <label for="email"><?php echo $this->_pt( 'Web Server Note' )?>:</label>
                <div class="lineform_line">
                    <a href="javascript:void(0);" style="margin:0 10px;" onclick="$('#server_apache_note').slideToggle();">Apache Note</a>
                    <a href="javascript:void(0);" style="margin:0 10px;" onclick="$('#server_nginx_note').slideToggle();">Nginx Note</a>

                    <div id="server_apache_note" style="display:none;">
                        <strong>Apache Note</strong><br/>
                        Please note that you will have to place a <em>.htaccess</em> file in location directory that will restrict access to your backup files.
                        <code>eg. <?php echo (!empty( $rule_location['full_path'] )?'<br/>'.$rule_location['full_path'].'/.htaccess':'')?><pre>
&lt;Files "*.*"&gt;
    Require all denied
&lt;/Files&gt;
</pre></code>
                    </div>
                    <div id="server_nginx_note" style="display:none;">
                        <strong>Nginx Note</strong><br/>
                        Please note that you will have to place a <em>nginx.conf</em> file in location directory that will restrict access to your backup files.
                        <code>eg. <?php echo (!empty( $rule_location['full_path'] )?'<br/>'.$rule_location['full_path'].'/nginx.conf':'')?><pre>
location /backup_location_dir {
    deny all;
    return 404;
}
</pre></code>
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <input type="submit" id="do_submit" name="do_submit" class="btn btn-primary submit-protection ignore_hidden_required" value="<?php echo $this->_pte( 'Save changes' )?>" />
            </fieldset>

        </div>
    </form>
</div>
<div class="clearfix"></div>
<script type="text/javascript">
function changed_days( el )
{
    if( !el )
        return;

    var el_obj = $(el);
    if( !el_obj )
        return;

    var el_val = el_obj.val();
    if( el_val != 0 )
        $("#days_arr_0").prop("checked", false);

    else
    {
        $(".brule_day").each(function(){
            $(this).prop( "checked", false );
        });
    }
}
function check_delete_after_days_change()
{
    var delete_after_days_obj = $("#delete_after_days");
    if( !delete_after_days_obj )
        return;

    var option_val = delete_after_days_obj.val();

    if( option_val == -1 || option_val == 0 )
    {
        $("#delete_after_days_container").hide();
    } else if( option_val == -2 )
    {
        $("#delete_after_days_container").show();
    } else
        $("#delete_after_days_container").hide();
}
function check_copy_results_change()
{
    var copy_results_obj = $("#copy_results");
    if( !copy_results_obj )
        return;

    var option_val = copy_results_obj.val();

    if( option_val == 0 )
    {
        $("#copy_results_container_<?php echo $rules_model::COPY_FTP?>").hide();
        $("#copy_results_container_<?php echo $rules_model::COPY_FTP?>").find("*[required]").removeAttr( 'required' );
    } else if( option_val == <?php echo $rules_model::COPY_FTP?> )
    {
        $("#copy_results_container_<?php echo $rules_model::COPY_FTP?>").show();
        $("#copy_results_container_<?php echo $rules_model::COPY_FTP?>").find("*[rel='is_required']").attr( 'required', 'required' );
    }
}
$(document).ready(function(){
    check_delete_after_days_change();
    check_copy_results_change();
});
</script>
