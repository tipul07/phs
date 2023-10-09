<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Plugin;
use phs\libraries\PHS_Has_db_settings;

/** @var null|\phs\system\core\models\PHS_Model_Tenants $tenants_model */
if (!($context_arr = $this->view_var('context_arr'))
    || !($tenants_model = $this->view_var('tenants_model'))
) {
    return $this->_pt('Error loading required resources.');
}

if (!($tenant_id = $this->view_var('tenant_id'))) {
    $tenant_id = 0;
}

if (!($back_page = $this->view_var('back_page'))) {
    $back_page = PHS::url(['p' => 'admin', 'a' => 'plugins_list']);
}
if (!($tenants_arr = $this->view_var('tenants_arr'))) {
    $tenants_arr = [];
}

$pid = $this->view_var('pid') ?? '';
$model_id = $this->view_var('model_id') ?? '';

/** @var PHS_Plugin $plugin_obj */
$plugin_obj = $context_arr['plugin_instance'] ?? null;

$models_arr = $context_arr['models_arr'] ?? [];

if( !$plugin_obj ) {
    $plugin_info = PHS_Plugin::core_plugin_details_fields();
} elseif( !($plugin_info = $plugin_obj->get_plugin_info()) ) {
    $plugin_info = [];
}

$current_user = PHS::user_logged_in();
$is_multitenant = PHS::is_multi_tenant();
?>
<form id="plugin_settings_form" name="plugin_settings_form" method="post"
      action="<?php echo PHS::url(['p' => 'admin', 'a' => 'settings', 'ad' => 'plugins'], ['pid' => $pid]); ?>">
    <input type="hidden" name="foobar" value="1" />
    <?php
    if (!empty($back_page)) {
        ?><input type="hidden" name="back_page" value="<?php echo form_str(safe_url($back_page)); ?>" /><?php
    }
?>
    <div class="form_container responsive">

        <?php
if (!empty($back_page)) {
    ?><i class="fa fa-chevron-left"></i> <a href="<?php echo form_str(from_safe_url($back_page)); ?>"><?php echo $this->_pt('Back'); ?></a><?php
}
?>

        <section class="heading-bordered">
            <h3>
                <?php echo $plugin_info['name'] ?? 'N/A'; ?>
                <small>
                <?php
    echo 'Db v'.$plugin_info['db_version'].' / S v'.$plugin_info['script_version'];

if (!empty($models_arr)) {
    echo ' - '.$this->_pt('%s models', count($models_arr));
}
?>
                </small>
            </h3>
        </section>

        <?php
    if (!empty($plugin_info['description'])) {
        ?>
        <div><small style="top:-15px;position:relative;"><?php echo $plugin_info['description']; ?></small></div>
        <?php
    }

    if (!empty($models_arr)) {
        ?>
        <div class="row form-group">
            <label for="model_id" class="col-sm-3 col-form-label"><?php echo $this->_pt('Select plugin or a model'); ?></label>
            <div class="col-sm-2" style="min-width:250px;max-width:360px;">
                <select name="model_id" id="model_id" class="chosen-select"
                        onchange="document.plugin_settings_form.submit()" style="min-width:250px;max-width:360px;">
                <option value=""><?php echo $plugin_info['name'].(!empty($plugin_obj) ? ' ('.$plugin_obj->instance_type().')' : ''); ?></option>
                <?php
                foreach ($models_arr as $m_id => $m_name) {
                    /** @var \phs\libraries\PHS_Model $model_instance */
                    if (!($model_instance = PHS::load_model($m_name, $plugin_obj?$plugin_obj->instance_name():null ))) {
                        continue;
                    }

                    ?><option value="<?php echo $m_name; ?>" <?php echo $model_id === $m_name ? 'selected="selected"' : ''; ?>
                    ><?php echo $model_instance->instance_name().' ('.$model_instance->instance_type().')'; ?></option><?php
                }
                ?></select>
            </div>
            <div class="col-sm-2">
            <input type="submit" id="select_module" name="select_module"
                   class="btn btn-primary btn-small ignore_hidden_required" value="&raquo;" />
            </div>
        </div>
            <?php
    }

    if ($is_multitenant) {
        ?>
        <div class="row form-group">
            <label for="tenant_id" class="col-sm-3 col-form-label"><?php echo $this->_pt('Select tenant'); ?></label>
            <div class="col-sm-2" style="min-width:250px;max-width:360px;">
                <select name="tenant_id" id="tenant_id" class="chosen-select"
                        onchange="document.plugin_settings_form.submit()" style="min-width:250px;max-width:360px;">
                <option value="0"><?php echo $this->_pt( '- Default settings -' )?></option>
                <?php
                foreach ($tenants_arr as $t_id => $t_arr) {
                    if ($tenants_model->is_deleted($t_arr)) {
                        continue;
                    }

                    ?><option value="<?php echo $t_id; ?>"
                    <?php echo $tenant_id === $t_id ? 'selected="selected"' : ''; ?>
                    ><?php echo $t_arr['name'].' ('.$t_arr['domain'].(!empty($t_arr['directory']) ? '/'.$t_arr['directory'] : '').')'; ?></option><?php
                }
                ?></select>
            </div>
            <div class="col-sm-2">
            <input type="submit" id="select_tenant" name="select_tenant"
                   class="btn btn-primary btn-small ignore_hidden_required" value="&raquo;" />
            </div>
        </div>
            <?php
    }

?><p><small><?php

echo $this->_pt('Database version').': '.$plugin_info['db_version'].', ';
echo $this->_pt('Script version').': '.$plugin_info['script_version'];

if (version_compare($plugin_info['db_version'], $plugin_info['script_version'], '!=')) {
    echo ' - <span style="color:red;">'.$this->_pt('Please upgrade the plugin').'</span>';
}

?></small></p>

        <?php

        $settings_structure = $context_arr['settings_structure'] ?? [];

if (empty($settings_structure) || !is_array($settings_structure)) {
    ?><p style="text-align: center;margin:30px auto;"><?php echo $this->_pt('Selected module doesn\'t have any settings.'); ?></p><?php
} else {
    phs_display_plugin_settings_all_fields($settings_structure, $context_arr['form_data'] ?? [], $context_arr['db_settings'], $this, $plugin_obj);
    ?>
    <div class="form-group">
        <input type="submit" id="do_submit" name="do_submit" class="btn btn-primary submit-protection ignore_hidden_required"
               value="<?php echo $this->_pte('Save settings'); ?>" />
        <input type="submit" id="do_cancel" name="do_cancel" class="btn btn-danger ignore_hidden_required"
               onclick="return phs_plugin_settings_confirm_cancel_action();"
               value="<?php echo $this->_pte('Cancel'); ?>" />
    </div>
    <?php
}
?>
    </div>
</form>

<script type="text/javascript">
function phs_plugin_settings_confirm_cancel_action()
{
    if( !confirm("<?php echo $this->_pte('Are you sure you want to cancel changes in plugin settings?')?>") ) {
        return false;
    }

    show_submit_protection();
    return true;
}
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
$(window).resize(function () {
    // var width = $("#ddlSpecialtyContainer")[0].offsetWidth + "px";
    // $("#ddlSpecialtyContainer .chosen-container").css("width", width);
    console.log("resize");
    $(".chosen-select").each(function(index){
        console.log("Item [" + index + "]");
        $(this).trigger("chosen:updated");
    });
    // chosen( { disable_search_threshold: 7, search_contains: true } );
    // $(".chosen-select-nosearch").chosen({disable_search: true});
});
</script>
<?php
/**
 * @param array $settings_fields
 * @param array $form_data
 * @param array $plugin_settings
 * @param \phs\system\core\views\PHS_View $fthis
 * @param null|\phs\libraries\PHS_Plugin $plugin_obj
 */
function phs_display_plugin_settings_all_fields($settings_fields, $form_data, $plugin_settings, $fthis, $plugin_obj)
{
    foreach ($settings_fields as $field_name => $field_details) {
        if (!PHS_Plugin::settings_field_is_group($field_details)) {
            phs_display_plugin_settings_field($field_name, $field_details, $form_data, $plugin_settings, $fthis, $plugin_obj);
        } else {
            ?>
            <fieldset id="phs_group_<?php echo $field_name; ?>">
                <legend>
                    <?php
                        if (!empty($field_details['group_foldable'])) {
                        ?><div class="lineformgrouptrigger" onclick="phs_toggle_settings_group( '<?php echo $field_name; ?>' )"><?php
                            }

                            echo $field_details['display_name'];

                            if (!empty($field_details['group_foldable'])) {
                            ?> <i id="phs_group_arrow_icon_<?php echo $field_name; ?>" class="fa fa-arrow-circle-up"></i></div><?php
                    }
                    ?>
                </legend>
                <?php
                if (!empty($field_details['display_hint'])) {
                    ?><small style="top:-15px;position:relative;"><?php echo $field_details['display_hint']; ?></small><?php
                }
                ?>
                <div id="phs_group_content_<?php echo $field_name; ?>">
                    <?php
                    phs_display_plugin_settings_all_fields($field_details['group_fields'], $form_data, $plugin_settings, $fthis, $plugin_obj);
                    ?>
                </div>
            </fieldset>
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
function phs_display_plugin_settings_field($field_name, $field_details, $form_data, $plugin_settings, $fthis, $plugin_obj)
{
    if (!empty($field_details['skip_rendering'])) {
        return;
    }

    if (!empty($field_details['display_placeholder'])) {
        $field_placeholder = $field_details['display_placeholder'];
    } else {
        $field_placeholder = '';
    }

    $field_id = $field_name;
    $field_value = null;
    if (isset($form_data[$field_name])) {
        $field_value = $form_data[$field_name];
    } elseif (isset($plugin_settings[$field_name])) {
        $field_value = $plugin_settings[$field_name];
    } elseif ($field_details['default'] !== null) {
        $field_value = $field_details['default'];
    }

    ?>
    <div class="form-group row">
        <label for="<?php echo $field_id; ?>" class="col-sm-2 col-form-label"><?php echo $field_details['display_name']; ?>
            <?php echo empty($field_details['editable']) ? '<br/><small>'.$fthis->_pt('[Non-editable]').'</small>' : ''; ?></label>
        <div class="col-sm-10"><?php

            $use_custom_renderer = (!empty($field_details['custom_renderer']) && is_callable($field_details['custom_renderer']));
            $custom_renderer_get_preset_buffer = (!empty($field_details['custom_renderer_get_preset_buffer']));

            $callback_params = [];
            if ($use_custom_renderer) {
                $callback_params = PHS_Plugin::default_custom_renderer_params();
                $callback_params['field_id'] = $field_id;
                $callback_params['field_name'] = $field_name;
                $callback_params['field_details'] = $field_details;
                $callback_params['field_value'] = $field_value;
                $callback_params['form_data'] = $form_data;
                $callback_params['editable'] = (!empty($field_details['editable']));
                $callback_params['plugin_obj'] = $plugin_obj;

                // We should get default rendering of settings input...
                if ($custom_renderer_get_preset_buffer) {
                    ob_start();
                }
            }

            if ($use_custom_renderer
                && !$custom_renderer_get_preset_buffer) {
                // Default settings input rendering is not required...
                if (($cell_content = @call_user_func($field_details['custom_renderer'], $callback_params)) === false
                    || $cell_content === null) {
                    $cell_content = '['.$fthis->_pt('Render settings field call failed.').']';
                }

                echo $cell_content;
            } elseif (!empty($field_details['input_type'])) {
                switch ($field_details['input_type']) {
                    case PHS_Has_db_settings::INPUT_TYPE_KEY_VAL_ARRAY:

                        if (!is_array($field_value)) {
                            echo $fthis->_pt('Not a key-value array...');
                        } else {
                            foreach ($field_value as $field_value_key => $field_value_val) {
                                ?>
                                <div style="margin-bottom:15px;">
                                    <label for="<?php echo $field_id.'_'.$field_value_key; ?>" style="width:150px !important;"><?php echo $field_value_key; ?></label>
                                    <input type="text" id="<?php echo $field_id.'_'.$field_value_key; ?>" name="<?php echo $field_name; ?>[<?php echo $field_value_key; ?>]" class="form-control <?php echo $field_details['extra_classes']; ?>" value="<?php echo form_str($field_value_val); ?>" <?php echo empty($field_details['editable']) ? 'disabled="disabled" readonly="readonly"' : ''; ?> style="<?php echo $field_details['extra_style']; ?>" />
                                </div>
                                <div class="clearfix"></div>
                                <?php

                            }
                        }
                    break;

                    case PHS_Has_db_settings::INPUT_TYPE_TEMPLATE:

                        echo $fthis->_pt('Template file').': ';
                        if (is_string($field_value)) {
                            echo $field_value;
                        } elseif (!empty($field_value['file'])) {
                            echo $field_value['file'];
                        } else {
                            echo $fthis->_pt('N/A');
                        }

                        if (is_array($field_value)
                            && !empty($field_value['extra_paths']) && is_array($field_value['extra_paths'])) {
                            echo '<br/>'.$fthis->_pt('From paths').': ';

                            $paths_arr = [];
                            foreach ($field_value['extra_paths'] as $path_dir => $path_www) {
                                $paths_arr[] = $path_dir;
                            }

                            if (empty($paths_arr)) {
                                echo $fthis->_pt('N/A');
                            } else {
                                echo implode(', ', $paths_arr);
                            }
                        }
                    break;

                    case PHS_Has_db_settings::INPUT_TYPE_ONE_OR_MORE:
                        if (empty($field_details['values_arr'])
                            || !is_array($field_details['values_arr'])) {
                            echo $fthis->_pt('Values array should be provided');
                        } else {
                            if (empty($field_value) || !is_array($field_value)) {
                                $field_value = [];
                            }

                            foreach ($field_details['values_arr'] as $one_more_key => $one_more_text) {
                                $option_checked = in_array($one_more_key, $field_value, false);

                                $option_field_id = $field_id.'_'.$one_more_key;
                                ?>
                                <div style="float:left; margin-right:10px;">
                                    <input type="checkbox" id="<?php echo $option_field_id; ?>" name="<?php echo $field_name; ?>[]"
                                           class="<?php echo $field_details['extra_classes']; ?>" value="<?php echo form_str($one_more_key); ?>" rel="skin_checkbox"
                                        <?php echo(!empty($option_checked) ? 'checked="checked"' : '').(empty($field_details['editable']) ? 'disabled="disabled" readonly="readonly"' : ''); ?>
                                           style="<?php echo $field_details['extra_style']; ?>" />
                                    <label for="<?php echo $option_field_id; ?>" style="margin-left:5px;width:auto !important;float:right;"><?php echo $one_more_text; ?></label>
                                </div>
                                <?php
                            }
                        }
                    break;

                    case PHS_Has_db_settings::INPUT_TYPE_ONE_OR_MORE_MULTISELECT:
                        if (empty($field_details['values_arr'])
                            || !is_array($field_details['values_arr'])) {
                            echo $fthis->_pt('Values array should be provided');
                        } else {
                            if (empty($field_value) || !is_array($field_value)) {
                                $field_value = [];
                            }
                            ?>
                            <select id="<?php echo $field_id; ?>" name="<?php echo $field_name; ?>[]" multiple="multiple"
                                    class="chosen-select <?php echo $field_details['extra_classes']; ?>" style="width: 100%;<?php echo $field_details['extra_style']; ?>"
                                <?php echo empty($field_details['editable']) ? 'disabled="disabled" readonly="readonly"' : ''; ?>
                            >
                                <?php
                                foreach ($field_details['values_arr'] as $one_more_key => $one_more_text) {
                                    $option_checked = in_array($one_more_key, $field_value, false);

                                    $option_field_id = $field_id.'_'.$one_more_key;
                                    ?>
                                    <option value="<?php echo form_str($one_more_key); ?>" <?php echo !empty($option_checked) ? 'selected="selected"' : ''; ?>
                                            id="<?php echo $option_field_id; ?>"><?php echo $one_more_text; ?></option>
                                    <?php
                                }
                                ?>
                            </select>
                            <?php
                        }
                    break;

                    case PHS_Has_db_settings::INPUT_TYPE_TEXTAREA:
                        if (empty($field_details['extra_style'])) {
                            $field_details['extra_style'] = 'width:100%;height:100px;';
                        }
                        ?>
                        <textarea id="<?php echo $field_id; ?>" name="<?php echo $field_name; ?>"
                                  class="form-control <?php echo $field_details['extra_classes']; ?>" style="<?php echo $field_details['extra_style']; ?>"
                              <?php echo !empty($field_placeholder) ? 'placeholder="'.form_str($field_placeholder).'"' : ''; ?>
                            <?php echo empty($field_details['editable']) ? 'disabled="disabled" readonly="readonly"' : ''; ?>><?php echo textarea_str($field_value); ?></textarea>
                        <?php
                    break;
                }
            } else {
                if (!empty($field_details['values_arr'])
                    && is_array($field_details['values_arr'])) {
                    if (empty($field_details['extra_style'])) {
                        $field_details['extra_style'] = 'width:100%';
                    }
                    ?>
                    <select id="<?php echo $field_id; ?>" name="<?php echo $field_name; ?>"
                            class="chosen-select <?php echo $field_details['extra_classes']; ?>"
                            style="<?php echo $field_details['extra_style']; ?>"
                    <?php echo empty($field_details['editable']) ? 'disabled="disabled" readonly="readonly"' : ''; ?>><?php

                    foreach ($field_details['values_arr'] as $key => $val) {
                        ?><option value="<?php echo $key; ?>" <?php echo $field_value == $key ? 'selected="selected"' : ''; ?>><?php echo $val; ?></option><?php
                    }

                    ?></select><?php
                } else {
                    switch ($field_details['type']) {
                        case PHS_Params::T_DATE:
                            ?>
                            <input type="text" id="<?php echo $field_id; ?>" name="<?php echo $field_name; ?>" class="datepicker form-control <?php echo $field_details['extra_classes']; ?>" value="<?php echo form_str($field_value); ?>" <?php echo empty($field_details['editable']) ? 'disabled="disabled" readonly="readonly"' : ''; ?> style="<?php echo $field_details['extra_style']; ?>" /><?php
                        break;

                        case PHS_Params::T_BOOL:
                            ?><input type="checkbox" value="1" rel="skin_checkbox"
                                     id="<?php echo $field_id; ?>" name="<?php echo $field_name; ?>"
                                     class="<?php echo $field_details['extra_classes']; ?>"
                            <?php echo !empty($field_value) ? 'checked="checked"' : ''; ?>
                            <?php echo empty($field_details['editable']) ? 'disabled="disabled" readonly="readonly"' : ''; ?>
                                     style="<?php echo $field_details['extra_style']; ?>" /><?php
                        break;

                        default:
                            // if (empty($field_details['extra_style'])) {
                            //     $field_details['extra_style'] = 'width:100%';
                            // }
                            ?>
                            <input type="text" id="<?php echo $field_id; ?>" name="<?php echo $field_name; ?>"
                                   class="form-control  style="<?php echo $field_details['extra_style']; ?>" <?php echo $field_details['extra_classes']; ?>"
                            value="<?php echo form_str($field_value); ?>"
                            <?php echo !empty($field_placeholder) ? 'placeholder="'.form_str($field_placeholder).'"' : ''; ?>
                            <?php echo empty($field_details['editable']) ? 'disabled="disabled" readonly="readonly"' : ''; ?> /><?php
                        break;
                    }
                }
            }

            if (!empty($field_details['display_hint'])) {
                ?>
                <div><small><?php echo form_str($field_details['display_hint']); ?></small></div><?php
            }

            if ($use_custom_renderer
                && $custom_renderer_get_preset_buffer) {
                // We now have default rendering... call custom render function
                if (!($default_render_buf = @ob_get_clean())) {
                    $default_render_buf = '';
                }

                $callback_params['preset_content'] = $default_render_buf;
                $callback_params['callback_params'] = (!empty($field_details['custom_renderer_params']) ? $field_details['custom_renderer_params'] : false);

                if (($cell_content = @call_user_func($field_details['custom_renderer'], $callback_params)) === false
                    || $cell_content === null) {
                    $cell_content = '['.$fthis->_pt('Render settings field call failed.').']';
                }

                echo $cell_content;
            }

            ?></div>
    </div>
    <?php
}
