<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\PHS_Scope;
    use \phs\PHS_ajax;

    $current_user = PHS::user_logged_in();

    /** @var \phs\plugins\backup\models\PHS_Model_Results $results_model */
    /** @var \phs\plugins\backup\models\PHS_Model_Rules $rules_model */
    /** @var \phs\plugins\backup\PHS_Plugin_Backup $backup_plugin */
    if( !($result_arr = $this->context_var( 'result_data' ))
     or !($rule_arr = $this->context_var( 'rule_data' ))
     or !($results_model = $this->context_var( 'results_model' ))
     or !($rules_model = $this->context_var( 'rules_model' ))
     or !($backup_plugin = $this->context_var( 'backup_plugin' )) )
        return $this->_pt( 'Could\'t loaded required resources for this view.' );

    if( !($result_files_arr = $this->context_var( 'result_files_arr' )) )
        $result_files_arr = array();

    if( !($back_page = $this->context_var( 'back_page' )) )
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
    <div id="backup_result_files_ajax_warning_box" style="display:none;" class="warning-box clearfix"><div class="dismissible"></div></div>
    <div id="backup_result_files_ajax_error_box" style="display:none;" class="error-box clearfix"><div class="dismissible"></div></div>
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
            <table style="width:100%">
            <thead>
                <tr>
                    <th>#</th>
                    <th>File</th>
                    <th>Size</th>
                    <th>&nbsp;</th>
                </tr>
            </thead>
            <?php
            if( !empty( $result_files_arr ) and is_array( $result_files_arr ) )
            {
            ?>
            <tbody>
                <?php
                $knti = 1;
                foreach( $result_files_arr as $file_id => $file_arr )
                {
                    ?>
                    <tr>
                        <td><?php echo $knti?></td>
                        <td><?php echo $file_arr['file']?></td>
                        <td><span title="<?php echo self::_e( $this->_pt( '%s bytes', number_format( $file_arr['size'] ) ) )?>"><?php echo format_filesize( $file_arr['size'] )?></span></td>
                        <td>bubu</td>
                    </tr>
                    <?php
                    $knti++;
                }
                ?>
                <tr>
                    <td colspan="2" style="text-align: right;padding:5px;"><strong><?php echo $this->_pt( 'TOTAL' )?></strong></td>
                    <td><span title="<?php echo self::_e( $this->_pt( '%s bytes', number_format( $result_arr['size'] ) ) )?>"><strong><?php echo format_filesize( $result_arr['size'] )?></strong></span></td>
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

