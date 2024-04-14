<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;
use phs\PHS_Tenants;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Plugin;
use phs\system\core\views\PHS_View;
use phs\libraries\PHS_Has_db_settings;

/** @var null|\phs\plugins\admin\PHS_Plugin_Admin $admin_plugin */
/** @var null|\phs\system\core\models\PHS_Model_Tenants $tenants_model */
/** @var null|\phs\system\core\models\PHS_Model_Plugins $plugins_model */
if (!($context_arr = $this->view_var('context_arr'))
    || !($admin_plugin = $this->view_var('admin_plugin'))
    || !($tenants_model = $this->view_var('tenants_model'))
    || !($plugins_model = $this->view_var('plugins_model'))
) {
    return $this->_pt('Error loading required resources.');
}

const SETTINGS_OBFUSCATE_MASK_STR = '**********';

$tenant_id = $this->view_var('tenant_id') ?: 0;
$back_page = $this->view_var('back_page') ?: PHS::url(['p' => 'admin', 'a' => 'list', 'ad' => 'plugins']);
$tenants_arr = $this->view_var('tenants_arr') ?: [];

$pid = $this->view_var('pid') ?? '';
$model_id = $this->view_var('model_id') ?? '';

/** @var PHS_Plugin $plugin_obj */
/** @var PHS_Has_db_settings $instance_obj */
$plugin_obj = $context_arr['plugin_instance'] ?? null;
$instance_obj = $context_arr['model_instance'] ?? $context_arr['plugin_instance'] ?? null;
$models_arr = $context_arr['models_arr'] ?? [];
$is_multitenant = $context_arr['is_multi_tenant'] ?? false;
$settings_structure = $context_arr['settings_structure'] ?? [];

if( !$plugin_obj ) {
    $plugin_info = PHS_Plugin::core_plugin_details_fields();
} elseif( !($plugin_info = $plugin_obj->get_plugin_info()) ) {
    $plugin_info = [];
}

$models_with_settings = [];
foreach ($models_arr as $m_id => $m_name) {
    /** @var \phs\libraries\PHS_Model $model_instance */
    if (!($model_instance = PHS::load_model($m_name, $plugin_obj?$plugin_obj->instance_name():null ))
        || !$model_instance->get_settings_structure()) {
        continue;
    }

    $models_with_settings[$m_id] = [
        'name' => $m_name,
        'instance' => $model_instance,
    ];
}
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

        if(empty($plugin_info['is_multi_tenant']) && PHS::is_multi_tenant()) {
            ?>
            <p class="text-center"><small><?php echo $this->_pt('This plugin is not a multi-tenant plugin! Settings will be same for all tenants.')?></small></p>
            <?php
        }

        $selected_tenant = null;
        if ((!empty( $settings_structure ) || !empty( $models_with_settings ))
            && $is_multitenant) {
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

                            if($tenant_id === $t_id) {
                                $selected_tenant = $t_arr;
                            }

                            ?><option value="<?php echo $t_id; ?>"
                            <?php echo $tenant_id === $t_id ? 'selected="selected"' : ''; ?>
                            ><?php echo PHS_Tenants::get_tenant_details_for_display($t_arr); ?></option><?php
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

        if( !empty( $models_with_settings ) ) {
            ?>
            <div class="row form-group">
                <label for="model_id" class="col-sm-3 col-form-label"><?php echo $this->_pt('Select plugin or a model'); ?></label>
                <div class="col-sm-2" style="min-width:250px;max-width:360px;">
                    <select name="model_id" id="model_id" class="chosen-select"
                            onchange="document.plugin_settings_form.submit()" style="min-width:250px;max-width:360px;">
                        <option value=""><?php echo $plugin_info['name'].(!empty($plugin_obj) ? ' ('.$plugin_obj->instance_type().')' : ''); ?></option>
                        <?php
                        foreach ($models_with_settings as $m_id => $m_arr) {
                            /** @var \phs\libraries\PHS_Model $model_instance */
                            $model_instance = $m_arr['instance'];

                            ?><option value="<?php echo $m_arr['name']; ?>" <?php echo $model_id === $m_arr['name'] ? 'selected="selected"' : ''; ?>
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

        ?><p><small><?php

                $context_arr['db_version'] ??= '0.0.0';
                $context_arr['script_version'] ??= '0.0.0';

                echo $this->_pt('Database version').': '.$context_arr['db_version'].', ';
                echo $this->_pt('Script version').': '.$context_arr['script_version'];

                if( $instance_obj ) {
                    echo ', Instance Id: '.$instance_obj->instance_id();
                }

                if (version_compare($plugin_info['db_version'], $plugin_info['script_version'], '!=')) {
                    echo ' - <span style="color:red;">'.$this->_pt('Please upgrade the plugin').'</span>';
                }

                ?></small></p><?php

        if($is_multitenant && $tenant_id && $plugin_obj
           && ($tenant_status = $plugins_model->get_status_of_tenant($plugin_obj->instance_id(), $tenant_id))
           && ($tenant_status_arr = $plugins_model->valid_status($tenant_status))) {
            ?><p class="text-center"><?php
            echo $this->_pt('For selected tenant, current plugin status is %s.',
                '<strong>'.($tenant_status_arr['title'] ?? 'N/A').'</strong>');

            if( $admin_plugin->can_admin_manage_plugins() ) {
                ?> - <a href="<?php echo PHS::url(['p' => 'admin', 'a' => 'plugins', 'ad' => 'tenants'],
                    ['tenant_id' => $tenant_id])?>"><?php echo $this->_pt('Manage plugins for this tenant')?></a><?php
            }
            ?></p><?php
        }

if (empty($settings_structure) || !is_array($settings_structure)) {
    ?><p style="text-align: center;margin:30px auto;"><?php echo $this->_pt('Selected module doesn\'t have any settings.'); ?></p><?php
} else {
    phs_display_plugin_settings_all_fields($settings_structure, $context_arr, $this, $plugin_obj);
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

function toggle_obfuscated_settings_field(id)
{
    const el = document.getElementById(id);
    if( el.type === "text" ) {
        el.type = "password";
        $('#settings_eye_'+id).removeClass( 'fa-eye-slash' ).addClass( 'fa-eye' );
    } else {
        el.type = 'text';
        $('#settings_eye_'+id).removeClass( 'fa-eye' ).addClass( 'fa-eye-slash' );
    }
}
<?php
if($is_multitenant) {
    ?>
function toggle_custom_tenant_value_section(section_id)
{
    $("#"+section_id).toggle();
    if($("#"+section_id).is(":visible")) {
        $("#"+section_id+"_main_value").css("opacity", "0.5");
    } else {
        $("#"+section_id+"_main_value").css("opacity", "1");
    }

    $(".phs_custom_tenant_settings_section .chosen-container").css("width", "100%");
}
<?php
}
?>
</script>
<?php
/**
 * @param array $settings_fields
 * @param array $form_data
 * @param array $plugin_settings
 * @param \phs\system\core\views\PHS_View $fthis
 * @param null|\phs\libraries\PHS_Plugin $plugin_obj
 */
function phs_display_plugin_settings_all_fields( array $settings_fields, array $context_arr, PHS_View $fthis, ?PHS_Plugin $plugin_obj): void
{
    foreach ($settings_fields as $field_name => $field_details) {
        if (!PHS_Plugin::settings_field_is_group($field_details)) {
            phs_display_plugin_settings_field($field_name, $field_details, $context_arr, $fthis, $plugin_obj);
        } else {
            ?>
            <fieldset id="phs_group_<?php echo $field_name; ?>">
                <legend><?php
                    if (!empty($field_details['group_foldable'])) {
                    ?><div class="lineformgrouptrigger" onclick="phs_toggle_settings_group( '<?php echo $field_name; ?>' )"><?php
                        }

                        echo $field_details['display_name'];

                        if (!empty($field_details['group_foldable'])) {
                        ?> <i id="phs_group_arrow_icon_<?php echo $field_name; ?>" class="fa fa-arrow-circle-up"></i></div><?php
                }
                ?></legend>
                <?php
                if (!empty($field_details['display_hint'])) {
                    ?><small style="top:-15px;position:relative;"><?php echo $field_details['display_hint']; ?></small><?php
                }
                ?>
                <div id="phs_group_content_<?php echo $field_name; ?>">
                    <?php
                    phs_display_plugin_settings_all_fields($field_details['group_fields'], $context_arr, $fthis, $plugin_obj);
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
function phs_display_plugin_settings_field(string $field_name, array $field_details, array $context_arr, PHS_View $fthis, ?PHS_Plugin $plugin_obj): void
{
    if (!empty($field_details['skip_rendering'])) {
        return;
    }

    $settings_for_tenant = $context_arr['settings_for_tenant'] ?? false;

    ?>
    <div class="form-group row">
        <label for="<?php echo $field_name; ?>" class="col-sm-2 col-form-label"><?php echo $field_details['display_name']; ?>
            <?php echo empty($field_details['editable']) ? '<br/><small>'.$fthis->_pt('[Non-editable]').'</small>' : ''; ?></label>
        <div class="col-sm-10"><?php

            if(!$settings_for_tenant || !empty($field_details['ignore_field_value'])
               || $field_details['input_type'] === PHS_Has_db_settings::INPUT_TYPE_TEMPLATE ) {
                $field_value = $context_arr['form_data'][$field_name] ?? $context_arr['db_settings'][$field_name] ?? null;

                echo phs_display_plugin_settings_get_field_input($field_value, $field_name, $field_details, $context_arr, $fthis, $plugin_obj);
            } else {
                $default_value = $context_arr['default_settings'][$field_name] ?? null;
                $main_value = $context_arr['db_main_settings'][$field_name] ?? $default_value;
                $field_value = $context_arr['form_data'][$field_name] ?? null;
                $is_tenant_value = array_key_exists( $field_name, $context_arr['db_tenant_settings'] );

                $tenant_settings_section_id = 'phs_settings_'.str_replace(':', '_', $context_arr['plugin']).
                                              (!empty( $context_arr['model'] )?'_'.$context_arr['model']:'').
                                              '_'.$field_name;

                if( empty( $field_details['only_main_tenant_value'] ) ) {
                    ?>
                    <div class="row form-check">
                        <input type="checkbox" value="1" class="form-check-input"
                            <?php echo $is_tenant_value ? 'checked="checked"':'';?>
                               onchange="toggle_custom_tenant_value_section('<?php echo $tenant_settings_section_id?>');"
                               id="tenant_custom_value_<?php echo $field_name?>"
                               name="tenant_custom_fields[<?php echo $field_name?>]" />
                        <label for="tenant_custom_value_<?php echo $field_name?>"><?php echo $fthis->_pt('Use custom tenant value')?></label>
                    </div>
                    <?php
                }

                $default_value_buf = '';
                if( '' !== ($main_value_buf = phs_display_plugin_settings_get_field_value_as_string($main_value, $field_name, $field_details, $context_arr, $fthis, $plugin_obj)) ) {
                    $default_value_buf = phs_display_plugin_settings_get_field_value_as_string($default_value, $field_name, $field_details, $context_arr, $fthis, $plugin_obj);
                }
                ?>
                <p id="<?php echo $tenant_settings_section_id?>_main_value">
                    <strong><?php echo $fthis->_pt( 'Main settings value' )?></strong>:<br/>
                    <?php
                    if( $main_value_buf !== '' ) {
                        echo $main_value_buf;
                    } else {
                        echo '('.$fthis->_pt('Empty string value').')'.
                             ('' !== $default_value_buf ?', '.$fthis->_pt('Default value').': '.$default_value_buf:'');
                    }
                    ?>
                </p>
                <?php

                if( empty( $field_details['only_main_tenant_value'] ) ) {
                    ?>
                <div id="<?php echo $tenant_settings_section_id?>" class="phs_custom_tenant_settings_section" style="display: <?php echo $is_tenant_value?'block':'none'?>;">
                    <?php
                    echo phs_display_plugin_settings_get_field_input($field_value, $field_name, $field_details, $context_arr, $fthis, $plugin_obj);
                    ?></div><?php
                }
            }
        ?></div>
    </div>
    <?php
}

function phs_display_plugin_settings_get_field_input($field_value, string $field_name, array $field_details, array $context_arr, PHS_View $fthis, ?PHS_Plugin $plugin_obj): string
{
    $form_data = $context_arr['form_data'] ?? [];

    $field_placeholder = $field_details['display_placeholder'] ?? '';

    $use_custom_renderer = (!empty($field_details['custom_renderer']) && is_callable($field_details['custom_renderer']));
    $custom_renderer_get_preset_buffer = (!empty($field_details['custom_renderer_get_preset_buffer']));
    $should_obfuscate = in_array($field_name, $context_arr['settings_keys_to_obfuscate'], true);

    $callback_params = [];
    if ($use_custom_renderer) {
        $callback_params = PHS_Plugin::default_custom_renderer_params();
        $callback_params['should_obfuscate'] = $should_obfuscate;
        $callback_params['value_as_text'] = false;
        $callback_params['field_id'] = $field_name;
        $callback_params['field_name'] = $field_name;
        $callback_params['field_details'] = $field_details;
        $callback_params['field_value'] = $field_value;
        $callback_params['form_data'] = $form_data;
        $callback_params['editable'] = (!empty($field_details['editable']));
        $callback_params['plugin_obj'] = $plugin_obj;
    }

    if ($use_custom_renderer
        && !$custom_renderer_get_preset_buffer) {
        // Default settings input rendering is not required...
        if (($cell_content = @call_user_func($field_details['custom_renderer'], $callback_params)) === false
            || $cell_content === null) {
            $cell_content = '['.$fthis->_pt('Render settings field call failed.').']';
        }

        return $cell_content;
    }

    ob_start();

    if (!empty($field_details['input_type'])) {
        switch ($field_details['input_type']) {
            case PHS_Has_db_settings::INPUT_TYPE_KEY_VAL_ARRAY:

                if (!is_array($field_value)) {
                    echo $fthis->_pt('Not a key-value array...');
                } else {
                    foreach ($field_value as $field_value_key => $field_value_val) {
                        ?>
                        <div class="form-group">
                            <label for="<?php echo $field_name.'_'.$field_value_key; ?>"><?php echo $field_value_key; ?></label>
                            <input type="text" id="<?php echo $field_name.'_'.$field_value_key; ?>"
                                   name="<?php echo $field_name; ?>[<?php echo $field_value_key; ?>]"
                                   class="form-control <?php echo $field_details['extra_classes']; ?>"
                                   value="<?php echo form_str($field_value_val); ?>"
                                <?php echo empty($field_details['editable']) ? 'disabled="disabled" readonly="readonly"' : ''; ?>
                                   style="<?php echo $field_details['extra_style']; ?>" />
                        </div>
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

                        $option_field_id = $field_name.'_'.$one_more_key;
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
                    ?><div style="width:100%">
                    <select id="<?php echo $field_name; ?>" name="<?php echo $field_name; ?>[]" multiple="multiple"
                            class="chosen-select <?php echo $field_details['extra_classes']; ?>" style="width: 100%;<?php echo $field_details['extra_style']; ?>"
                        <?php echo empty($field_details['editable']) ? 'disabled="disabled" readonly="readonly"' : ''; ?>
                    >
                        <?php
                        foreach ($field_details['values_arr'] as $one_more_key => $one_more_text) {
                            $option_checked = in_array($one_more_key, $field_value, false);

                            $option_field_id = $field_name.'_'.$one_more_key;
                            ?>
                            <option value="<?php echo form_str($one_more_key); ?>" <?php echo !empty($option_checked) ? 'selected="selected"' : ''; ?>
                                    id="<?php echo $option_field_id; ?>"><?php echo $one_more_text; ?></option>
                            <?php
                        }
                        ?>
                    </select></div>
                    <?php
                }
            break;

            case PHS_Has_db_settings::INPUT_TYPE_TEXTAREA:
                if (empty($field_details['extra_style'])) {
                    $field_details['extra_style'] = 'width:100%;height:100px;';
                }
                ?>
                <textarea id="<?php echo $field_name; ?>" name="<?php echo $field_name; ?>"
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
            <select id="<?php echo $field_name; ?>" name="<?php echo $field_name; ?>"
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
                    <input type="text" id="<?php echo $field_name; ?>" name="<?php echo $field_name; ?>" class="datepicker form-control <?php echo $field_details['extra_classes']; ?>" value="<?php echo form_str($field_value); ?>" <?php echo empty($field_details['editable']) ? 'disabled="disabled" readonly="readonly"' : ''; ?> style="<?php echo $field_details['extra_style']; ?>" /><?php
                break;

                case PHS_Params::T_BOOL:
                    ?><input type="checkbox" value="1" rel="skin_checkbox"
                             id="<?php echo $field_name; ?>" name="<?php echo $field_name; ?>"
                             class="<?php echo $field_details['extra_classes']; ?>"
                    <?php echo !empty($field_value) ? 'checked="checked"' : ''; ?>
                    <?php echo empty($field_details['editable']) ? 'disabled="disabled" readonly="readonly"' : ''; ?>
                             style="<?php echo $field_details['extra_style']; ?>" /><?php
                break;

                default:
                    if($should_obfuscate) {
                        ?><div class='input-group' style='display: flex;'><?php
                    }
                    ?>
                    <input type="<?php echo $should_obfuscate?'password':'text'?>" id="<?php echo $field_name; ?>" name="<?php echo $field_name; ?>"
                           class="form-control"  style="<?php echo $field_details['extra_style']; ?> <?php echo $field_details['extra_classes']; ?>"
                    value="<?php echo form_str($field_value); ?>"
                    <?php echo !empty($field_placeholder) ? 'placeholder="'.form_str($field_placeholder).'"' : ''; ?>
                    <?php echo empty($field_details['editable']) ? 'disabled="disabled" readonly="readonly"' : ''; ?> />
                    <?php
                    if($should_obfuscate) {
                        ?>
                        <div class="input-group-append" style="color: inherit;">
                            <div class="input-group-text"><span href="javascript:void(0)" class="fa fa-eye" style='margin: 3px;cursor:pointer;vertical-align: middle'
                                                                id="settings_eye_<?php echo $field_name; ?>"
                                                                onclick="toggle_obfuscated_settings_field('<?php echo $field_name; ?>')"
                                                                onfocus="this.blur()"></span></div>
                        </div>
                    </div>
                    <?php
                    }
                break;
            }
        }
    }

    if (!empty($field_details['display_hint'])) {
        ?>
        <div><small><?php echo form_str($field_details['display_hint']); ?></small></div><?php
    }

    if( !($input_buffer = @ob_get_clean()) ) {
        $input_buffer = '';
    }

    if ($use_custom_renderer
        && $custom_renderer_get_preset_buffer) {
        $callback_params['preset_content'] = $input_buffer;
        $callback_params['callback_params'] = (!empty($field_details['custom_renderer_params']) ? $field_details['custom_renderer_params'] : false);

        if (($cell_content = @call_user_func($field_details['custom_renderer'], $callback_params)) === false
            || $cell_content === null) {
            $cell_content = '['.$fthis->_pt('Render settings field call failed.').']';
        }

        return $cell_content;
    }

    return $input_buffer;
}

function phs_display_plugin_settings_get_field_value_as_string($field_value, string $field_name, array $field_details,
    array $context_arr, PHS_View $fthis, ?PHS_Plugin $plugin_obj): string
{
    $form_data = $context_arr['form_data'] ?? [];

    $use_custom_renderer = (!empty($field_details['custom_renderer']) && is_callable($field_details['custom_renderer']));
    $custom_renderer_get_preset_buffer = (!empty($field_details['custom_renderer_get_preset_buffer']));
    $should_obfuscate = in_array($field_name, $context_arr['settings_keys_to_obfuscate'], true);

    $callback_params = [];
    if ($use_custom_renderer) {
        $callback_params = PHS_Plugin::default_custom_renderer_params();
        $callback_params['should_obfuscate'] = $should_obfuscate;
        $callback_params['value_as_text'] = true;
        $callback_params['field_id'] = $field_name;
        $callback_params['field_name'] = $field_name;
        $callback_params['field_details'] = $field_details;
        $callback_params['field_value'] = $field_value;
        $callback_params['form_data'] = $form_data;
        $callback_params['editable'] = (!empty($field_details['editable']));
        $callback_params['plugin_obj'] = $plugin_obj;
    }

    if ($use_custom_renderer
        && !$custom_renderer_get_preset_buffer) {
        // Default settings input rendering is not required...
        if (($cell_content = @call_user_func($field_details['custom_renderer'], $callback_params)) === false
            || $cell_content === null) {
            $cell_content = '['.$fthis->_pt('Render settings field call failed.').']';
        }

        return $cell_content;
    }

    ob_start();

    if (!empty($field_details['input_type'])) {
        switch ($field_details['input_type']) {
            case PHS_Has_db_settings::INPUT_TYPE_KEY_VAL_ARRAY:

                if (!is_array($field_value)) {
                    echo $fthis->_pt('Not a key-value array...');
                } else {
                    $value_buf = '';
                    foreach ($field_value as $field_value_key => $field_value_val) {
                        $value_buf .= ($value_buf!==''?', ':'').
                                      '<em>'.$field_value_key.'</em>: '.($should_obfuscate ? SETTINGS_OBFUSCATE_MASK_STR : ($field_value_val ?? $fthis->_pt('N/A')));
                    }

                    echo $value_buf ?: $fthis->_pt( 'N/A' );
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
            case PHS_Has_db_settings::INPUT_TYPE_ONE_OR_MORE_MULTISELECT:
                if (empty($field_details['values_arr'])
                    || !is_array($field_details['values_arr'])) {
                    echo $fthis->_pt('Values array should be provided');
                } else {
                    if (empty($field_value) || !is_array($field_value)) {
                        $field_value = [];
                    }

                    $values_arr = [];
                    foreach ($field_details['values_arr'] as $one_more_key => $one_more_text) {
                        if( !in_array($one_more_key, $field_value, false) ) {
                            continue;
                        }

                        $values_arr[] = $one_more_text;
                    }

                    echo ($should_obfuscate
                        ? SETTINGS_OBFUSCATE_MASK_STR
                        : (!empty( $values_arr ) ? implode( ', ', $values_arr ) : $fthis->_pt( 'N/A' )));
                }
            break;

            case PHS_Has_db_settings::INPUT_TYPE_TEXTAREA:
                echo ($should_obfuscate ? SETTINGS_OBFUSCATE_MASK_STR : $field_value);
            break;
        }
    } else {
        if (!empty($field_details['values_arr'])
            && is_array($field_details['values_arr'])) {
            echo ($should_obfuscate
                ? SETTINGS_OBFUSCATE_MASK_STR
                : ($field_details['values_arr'][$field_value] ?? $fthis->_pt( 'N/A' )));
        } else {
            switch ($field_details['type']) {
                default:
                    echo ($should_obfuscate ? SETTINGS_OBFUSCATE_MASK_STR : $field_value);
                break;

                case PHS_Params::T_BOOL:
                case PHS_Params::T_NUMERIC_BOOL:
                    echo ($should_obfuscate
                        ? SETTINGS_OBFUSCATE_MASK_STR
                        : (!empty($field_value) ? $fthis->_pt( 'Yes' ) : $fthis->_pt( 'No' )));
                break;
            }
        }
    }

    if( false === ($input_buffer = @ob_get_clean()) ) {
        $input_buffer = '';
    }

    if ($use_custom_renderer
        && $custom_renderer_get_preset_buffer) {
        $callback_params['preset_content'] = $input_buffer;
        $callback_params['callback_params'] = (!empty($field_details['custom_renderer_params']) ? $field_details['custom_renderer_params'] : false);

        if (($cell_content = @call_user_func($field_details['custom_renderer'], $callback_params)) === false
            || $cell_content === null) {
            $cell_content = '['.$fthis->_pt('Render settings field call failed.').']';
        }

        return $cell_content;
    }

    return $input_buffer;
}
