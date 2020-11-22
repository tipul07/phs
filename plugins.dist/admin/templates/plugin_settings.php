<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_Params;
    use \phs\libraries\PHS_Instantiable;
    use \phs\libraries\PHS_Plugin;

    if( !($form_data = $this->view_var( 'form_data' )) )
        $form_data = array();

    /** @var \phs\libraries\PHS_Plugin $plugin_obj */
    $plugin_obj = false;
    if( (empty( $form_data['pid'] ) || $form_data['pid'] !== PHS_Instantiable::CORE_PLUGIN)
     && !($plugin_obj = $this->view_var( 'plugin_obj' )) )
        return $this->_pt( 'Plugin ID is invalid or plugin was not found.' );

    if( !($back_page = $this->view_var( 'back_page' )) )
        $back_page = PHS::url( array( 'p' => 'admin', 'a' => 'plugins_list' ) );
    if( !($settings_fields = $this->view_var( 'settings_fields' )) )
        $settings_fields = array();
    if( !($modules_with_settings = $this->view_var( 'modules_with_settings' )) )
        $modules_with_settings = array();

    if( !($plugin_settings = $this->view_var( 'db_settings' )) )
        $plugin_settings = array();

    if( !($db_version = $this->view_var( 'db_version' )) )
        $db_version = '0.0.0';
    if( !($script_version = $this->view_var( 'script_version' )) )
        $script_version = '0.0.0';

    if( empty( $plugin_obj ) )
        $plugin_info = PHS_Plugin::core_plugin_details_fields();
    elseif( !($plugin_info = $plugin_obj->get_plugin_info()) )
        $plugin_info = $plugin_obj::default_plugin_details_fields();

    $current_user = PHS::user_logged_in();
?>
<div>
    <form id="plugin_settings_form" name="plugin_settings_form" action="<?php echo PHS::url( array( 'p' => 'admin', 'a' => 'plugin_settings' ), array( 'pid' => $form_data['pid'] ) )?>" method="post">
        <input type="hidden" name="foobar" value="1" />
        <?php
        if( !empty( $back_page ) )
        {
            ?><input type="hidden" name="back_page" value="<?php echo form_str( safe_url( $back_page ) )?>" /><?php
        }
        ?>
        <div class="form_container responsive">

            <?php
            if( !empty( $back_page ) )
            {
                ?><i class="fa fa-chevron-left"></i> <a href="<?php echo form_str( from_safe_url( $back_page ) ) ?>"><?php echo $this->_pt( 'Back' )?></a><?php
            }
            ?>

            <section class="heading-bordered">
                <h3>
                    <?php echo $plugin_info['name']?>
                    <small>
                    <?php
                    echo 'Db v'.$plugin_info['db_version'].' / S v'.$plugin_info['script_version'];

                    if( !empty( $plugin_info['models'] )
                     && is_array( $plugin_info['models'] ) )
                        echo ' - '.$this->_pt( '%s models', count( $plugin_info['models'] ) );
                    ?>
                    </small>
                </h3>
            </section>

            <?php
            if( !empty( $plugin_info['description'] ) )
            {
                ?><div class="clearfix"></div>
                <small style="top:-15px;position:relative;"><?php echo $plugin_info['description']?></small>
                <div class="clearfix"></div><?php
            }

            if( !empty( $modules_with_settings ) && is_array( $modules_with_settings ) )
            {
                ?>
                <div class="lineform">
                    <label for="selected_module"><?php echo $this->_pt( 'Settings for' )?>: </label>
                    <select name="selected_module" id="selected_module" class="chosen-select-nosearch" onchange="document.plugin_settings_form.submit()" style="min-width:250px;max-width:360px;">
                    <option value=""><?php echo $plugin_info['name'].(!empty( $plugin_obj )?' ('.$plugin_obj->instance_type().')':'')?></option>
                    <?php
                    foreach( $modules_with_settings as $model_id => $model_arr )
                    {
                        if( !is_array( $model_arr )
                         || empty( $model_arr['instance'] ) )
                            continue;

                        /** @var \phs\libraries\PHS_Model $model_instance */
                        $model_instance = $model_arr['instance'];

                        ?><option value="<?php echo $model_id?>" <?php echo ($form_data['selected_module']==$model_id?'selected="selected"':'')?>><?php echo $model_instance->instance_name().' ('.$model_instance->instance_type().')'?></option><?php
                    }
                    ?></select>
                    <input type="submit" id="select_module" name="select_module" class="btn btn-primary btn-small ignore_hidden_required" value="&raquo;" style="float:none;" />
                </div>
                <div class="clearfix" style="margin-bottom: 15px;"></div>
                <?php
            }

            ?><small><?php

                echo $this->_pt( 'Database version' ).': '.$db_version.', ';
                echo $this->_pt( 'Script version' ).': '.$script_version;

                if( version_compare( $db_version, $script_version, '<' ) )
                {
                    echo ' - <span style="color:red;">'.$this->_pt( 'Please upgrade the plugin' ).'</span>';
                }

            ?></small><?php

            if( empty( $settings_fields ) || !is_array( $settings_fields ) )
            {
                ?><p style="text-align: center;margin:30px auto;"><?php echo $this->_pt( 'Selected module doesn\'t have any settings.' )?></p><?php
            } else
            {
                phs_display_plugin_settings_all_fields( $settings_fields, $form_data, $plugin_settings, $this, $plugin_obj );
                ?>

                <fieldset>
                    <input type="submit" id="do_submit" name="do_submit" class="btn btn-primary submit-protection ignore_hidden_required" value="<?php echo $this->_pte( 'Save settings' ) ?>" />
                    <input type="button" id="cancel" class="btn btn-primary" style="margin-right:10px;" onclick="document.location='<?php echo $this::_e( $back_page, '\'' )?>';" value="<?php echo $this->_pte( 'Cancel' ) ?>" />
                </fieldset>
                <?php
            }
            ?>

        </div>
    </form>
</div>
<div class="clearfix"></div>
<script type="text/javascript">
function phs_toggle_settings_group( id )
{
    if( $('#phs_group_content_'+id).css('display') === 'none' )
    {
        $('#phs_group_arrow_icon_'+id).removeClass( 'fa-arrow-circle-down' ).addClass( 'fa-arrow-circle-up' );
    } else
    {
        $('#phs_group_arrow_icon_'+id).removeClass( 'fa-arrow-circle-up' ).addClass( 'fa-arrow-circle-down' );
    }

    $("#phs_group_content_"+id).toggle();
}
</script>
<?php
/**
 * @param array $settings_fields
 * @param array $form_data
 * @param array $plugin_settings
 * @param \phs\system\core\views\PHS_View $fthis
 * @param \phs\libraries\PHS_Plugin $plugin_obj
 */
function phs_display_plugin_settings_all_fields( $settings_fields, $form_data, $plugin_settings, $fthis, $plugin_obj )
{
    foreach( $settings_fields as $field_name => $field_details )
    {
        if( !PHS_Plugin::settings_field_is_group( $field_details ) )
            phs_display_plugin_settings_field( $field_name, $field_details, $form_data, $plugin_settings, $fthis, $plugin_obj );

        else
        {
            ?>
            <div class="lineformgroup" id="phs_group_<?php echo $field_name?>">
            <section class="heading-bordered">
                <h4><?php
                    if( !empty( $field_details['group_foldable'] ) )
                    {
                        ?><div class="lineformgrouptrigger" onclick="phs_toggle_settings_group( '<?php echo $field_name?>' )"><?php
                    }

                    echo $field_details['display_name'];

                    if( !empty( $field_details['group_foldable'] ) )
                    {
                        ?> <i id="phs_group_arrow_icon_<?php echo $field_name?>" class="fa fa-arrow-circle-up"></i></div><?php
                    }
                ?></h4>
            </section>
            <div class="clearfix"></div>
            <?php
            if( !empty( $field_details['display_hint'] ) )
            {
                ?><small style="top:-15px;position:relative;"><?php echo $field_details['display_hint']?></small><?php
            }
            ?>
            <div class="clearfix"></div>
            <div class="lineformgroupcontent" id="phs_group_content_<?php echo $field_name?>">
            <?php
            phs_display_plugin_settings_all_fields( $field_details['group_fields'], $form_data, $plugin_settings, $fthis, $plugin_obj );
            ?>
            </div>
            <div class="clearfix"></div>
            </div>
            <div class="clearfix"></div>
            <?php
        }
    }
}
/**
 * @param string $field_name
 * @param array $field_details
 * @param array $form_data
 * @param array $plugin_settings
 * @param \phs\system\core\views\PHS_View $fthis
 * @param \phs\libraries\PHS_Plugin $plugin_obj
 */
function phs_display_plugin_settings_field( $field_name, $field_details, $form_data, $plugin_settings, $fthis, $plugin_obj )
{
    if( !empty( $field_details['display_placeholder'] ) )
        $field_placeholder = $field_details['display_placeholder'];
    else
        $field_placeholder = '';

    $field_id = $field_name;
    $field_value = null;
    if( isset( $form_data[$field_name] ) && $form_data[$field_name] !== null )
        $field_value = $form_data[$field_name];
    elseif( isset( $plugin_settings[$field_name] ) )
        $field_value = $plugin_settings[$field_name];
    elseif( $field_details['default'] !== null )
        $field_value = $field_details['default'];

    ?>
    <fieldset class="lineformwide">
        <label for="<?php echo $field_id ?>"><strong><?php echo $field_details['display_name']; ?></strong>
            <?php echo (empty( $field_details['editable'] )?'<br/><small>'.$fthis->_pt( '[Non-editable]' ).'</small>':'')?></label>
        <div class="lineformwide_line"><?php

        if( !empty( $field_details['custom_renderer'] )
         && is_callable( $field_details['custom_renderer'] ) )
        {
            $callback_params = PHS_Plugin::default_custom_renderer_params();
            $callback_params['field_id'] = $field_id;
            $callback_params['field_name'] = $field_name;
            $callback_params['field_details'] = $field_details;
            $callback_params['field_value'] = $field_value;
            $callback_params['form_data'] = $form_data;
            $callback_params['editable'] = (empty( $field_details['editable'] )?false:true);
            $callback_params['plugin_obj'] = $plugin_obj;

            if( ($cell_content = @call_user_func( $field_details['custom_renderer'], $callback_params )) === false
             || $cell_content === null )
                $cell_content = '[' . $fthis->_pt( 'Render settings field call failed.' ) . ']';

            echo $cell_content;

        } elseif( !empty( $field_details['input_type'] ) )
        {
            switch( $field_details['input_type'] )
            {
                case PHS_Plugin::INPUT_TYPE_KEY_VAL_ARRAY:

                    if( !is_array( $field_value ) )
                        echo $fthis->_pt( 'Not a key-value array...' );

                    else
                    {
                        foreach( $field_value as $field_value_key => $field_value_val )
                        {
                            ?>
                            <div style="margin-bottom:15px;">
                            <label for="<?php echo $field_id.'_'.$field_value_key?>" style="width:150px !important;"><?php echo $field_value_key?></label>
                            <input type="text" id="<?php echo $field_id.'_'.$field_value_key ?>" name="<?php echo $field_name ?>[<?php echo $field_value_key?>]" class="form-control <?php echo $field_details['extra_classes'] ?>" value="<?php echo form_str( $field_value_val )?>" <?php echo (empty( $field_details['editable'] )?'disabled="disabled" readonly="readonly"' : '') ?> style="<?php echo $field_details['extra_style'] ?>" />
                            </div>
                            <div class="clearfix"></div>
                            <?php

                        }
                    }
                break;

                case PHS_Plugin::INPUT_TYPE_TEMPLATE:

                    echo $fthis->_pt( 'Template file' ).': ';
                    if( is_string( $field_value ) )
                        echo $field_value;

                    elseif( !empty( $field_value['file'] ) )
                        echo $field_value['file'];

                    else
                        echo $fthis->_pt( 'N/A' );

                    if( is_array( $field_value )
                     && !empty( $field_value['extra_paths'] ) && is_array( $field_value['extra_paths'] ) )
                    {
                        echo '<br/>'.$fthis->_pt( 'From paths' ).': ';

                        $paths_arr = array();
                        foreach( $field_value['extra_paths'] as $path_dir => $path_www )
                        {
                            $paths_arr[] = $path_dir;
                        }

                        if( empty( $paths_arr ) )
                            echo $fthis->_pt( 'N/A' );

                        else
                            echo implode( ', ', $paths_arr );
                    }
                break;

                case PHS_Plugin::INPUT_TYPE_ONE_OR_MORE:
                    if( empty( $field_details['values_arr'] )
                     || !is_array( $field_details['values_arr'] ) )
                        echo $fthis->_pt( 'Values array should be provided' );

                    else
                    {
                        if( empty( $field_value ) || !is_array( $field_value ) )
                            $field_value = array();

                        foreach( $field_details['values_arr'] as $one_more_key => $one_more_text )
                        {
                            $option_checked = in_array( $one_more_key, $field_value, false );

                            $option_field_id = $field_id.'_'.$one_more_key;
                            ?>
                            <div style="float:left; margin-right:10px;">
                            <input type="checkbox" id="<?php echo $option_field_id ?>" name="<?php echo $field_name ?>[]"
                                   class="<?php echo $field_details['extra_classes'] ?>" value="<?php echo form_str( $one_more_key )?>" rel="skin_checkbox"
                                   <?php echo (!empty($option_checked) ? 'checked="checked"' : '').(empty( $field_details['editable'] )?'disabled="disabled" readonly="readonly"' : '') ?>
                                   style="<?php echo $field_details['extra_style'] ?>" />
                            <label for="<?php echo $option_field_id?>" style="margin-left:5px;width:auto !important;float:right;"><?php echo $one_more_text?></label>
                            </div>
                            <?php
                        }
                    }
                break;

                case PHS_Plugin::INPUT_TYPE_ONE_OR_MORE_MULTISELECT:
                    if( empty( $field_details['values_arr'] )
                     || !is_array( $field_details['values_arr'] ) )
                        echo $fthis->_pt( 'Values array should be provided' );

                    else
                    {
                        if( empty( $field_value ) || !is_array( $field_value ) )
                            $field_value = array();
                        ?>
                        <select id="<?php echo $field_id ?>" name="<?php echo $field_name ?>[]" multiple="multiple"
                                class="chosen-select <?php echo $field_details['extra_classes'] ?>" style="width: 100%;<?php echo $field_details['extra_style'] ?>"
                                <?php echo (empty( $field_details['editable'] )?'disabled="disabled" readonly="readonly"' : '')?>
                        >
                        <?php
                        foreach( $field_details['values_arr'] as $one_more_key => $one_more_text )
                        {
                            $option_checked = in_array( $one_more_key, $field_value, false );

                            $option_field_id = $field_id.'_'.$one_more_key;
                            ?>
                            <option value="<?php echo form_str( $one_more_key )?>" <?php echo (!empty($option_checked) ? 'selected="selected"' : '')?>
                                    id="<?php echo $option_field_id?>"><?php echo $one_more_text?></option>
                            <?php
                        }
                        ?>
                        </select>
                        <?php
                    }
                break;
            }
        } else
        {
            if( !empty( $field_details['values_arr'] )
             && is_array( $field_details['values_arr'] ) )
            {
                if( empty( $field_details['extra_style'] ) )
                    $field_details['extra_style'] = 'width:100%';
                ?>
                <select id="<?php echo $field_id ?>" name="<?php echo $field_name ?>" class="chosen-select <?php echo $field_details['extra_classes'] ?>" style="<?php echo $field_details['extra_style'] ?>" <?php echo (empty( $field_details['editable'] )?'disabled="disabled" readonly="readonly"' : '')?>><?php

                foreach( $field_details['values_arr'] as $key => $val )
                {
                    ?><option value="<?php echo $key ?>" <?php echo($field_value == $key ? 'selected="selected"' : '') ?>><?php echo $val ?></option><?php
                }

                ?></select><?php
            }
            else
            {
                switch( $field_details['type'] )
                {
                    case PHS_Params::T_DATE:
                        ?>
                        <input type="text" id="<?php echo $field_id ?>" name="<?php echo $field_name ?>" class="datepicker form-control <?php echo $field_details['extra_classes'] ?>" value="<?php echo form_str( $field_value ) ?>" <?php echo (empty( $field_details['editable'] )?'disabled="disabled" readonly="readonly"' : '')?> style="<?php echo $field_details['extra_style'] ?>" /><?php
                    break;

                    case PHS_Params::T_BOOL:
                        ?><input type="checkbox" id="<?php echo $field_id ?>" name="<?php echo $field_name ?>" class="<?php echo $field_details['extra_classes'] ?>" value="1" rel="skin_checkbox" <?php echo (!empty($field_value) ? 'checked="checked"' : '') ?> <?php echo (empty( $field_details['editable'] )?'disabled="disabled" readonly="readonly"' : '')?> style="<?php echo $field_details['extra_style'] ?>" /><?php
                    break;

                    default:
                        if( empty( $field_details['extra_style'] ) )
                            $field_details['extra_style'] = 'width:100%';
                        ?>
                        <input type="text" id="<?php echo $field_id ?>" name="<?php echo $field_name ?>" class="form-control <?php echo $field_details['extra_classes'] ?>" value="<?php echo form_str( $field_value ) ?>" <?php echo(!empty($field_placeholder) ? 'placeholder="' . form_str( $field_placeholder ) . '"' : '') ?> <?php echo (empty( $field_details['editable'] )?'disabled="disabled" readonly="readonly"' : '')?> style="<?php echo $field_details['extra_style'] ?>" /><?php
                    break;
                }
            }
        }

        if( !empty($field_details['display_hint']) )
        {
            ?>
            <div class="clearfix"></div>
            <small><?php echo form_str( $field_details['display_hint'] ) ?></small><?php
        }

        ?></div>
    </fieldset>
    <?php
}
