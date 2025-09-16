<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;
use phs\PHS_Tenants;
use phs\libraries\PHS_Plugin;

/** @var null|PHS_Plugin $plugin_obj */
/** @var null|\phs\system\core\models\PHS_Model_Tenants $tenants_model */
/** @var null|\phs\system\core\models\PHS_Model_Plugins $plugins_model */
if (!($tenants_model = $this->view_var('tenants_model'))
    || !($plugins_model = $this->view_var('plugins_model'))
) {
    return $this->_pt('Error loading required resources.');
}

$plugin_obj = $this->view_var('plugin_obj') ?: null;
$tenant_id = $this->view_var('tenant_id') ?: 0;
$back_page = $this->view_var('back_page') ?: PHS::url(['p' => 'admin', 'a' => 'list', 'ad' => 'plugins']);
$tenants_arr = $this->view_var('tenants_arr') ?: [];
$registry_arr = $this->view_var('registry_arr') ?: [];
$registry_fields_settings = $this->view_var('registry_fields_settings') ?: [];

$pname = $this->view_var('pname') ?? '';
$is_multi_tenant = (bool)$this->view_var('is_multi_tenant');
$plugin_is_multi_tenant = (bool)$this->view_var('plugin_is_multi_tenant');

if( !$plugin_obj ) {
    $plugin_info = PHS_Plugin::core_plugin_details_fields();
} elseif( !($plugin_info = $plugin_obj->get_plugin_info()) ) {
    $plugin_info = [];
}

echo $this->sub_view('ractive/bootstrap');
?>
<form id="plugin_registry_form" name="plugin_registry_form" method="post"
      action="<?php echo PHS::url(['p' => 'admin', 'a' => 'registry', 'ad' => 'plugins'], ['pname' => $pname]); ?>">
    <input type="hidden" name="foobar" value="1" />
    <?php
    if (!empty($back_page)) {
        ?><input type="hidden" name="back_page" value="<?php echo form_str(safe_url($back_page)); ?>" /><?php
    }
    ?>

    <div class="form_container">

        <?php
        if (!empty($back_page)) {
            ?><i class="fa fa-chevron-left"></i> <a href="<?php echo form_str(from_safe_url($back_page)); ?>"><?php echo $this->_pt('Back'); ?></a><?php
        }
        ?>

        <section class="heading-bordered">
            <h3>
                <?php echo $plugin_info['name'] ?? 'N/A'; ?>
                <small><?php echo 'Db v'.$plugin_info['db_version'].' / S v'.$plugin_info['script_version'];?></small>
            </h3>
        </section>

        <?php
        if (!empty($plugin_info['description'])) {
            ?>
            <div><small style="top:-15px;position:relative;"><?php echo $plugin_info['description']; ?></small></div>
            <?php
        }

        if(!empty($plugin_obj) && $is_multi_tenant && !$plugin_is_multi_tenant) {
            ?>
            <p class="text-center"><small><?php echo $this->_pt('This plugin is not a multi-tenant plugin! Registry data will be same for all tenants.')?></small></p>
            <?php
        }

        $selected_tenant = null;
        if ($is_multi_tenant
            && $plugin_is_multi_tenant) {
            ?>
            <div class="row form-group">
                <label for="tenant_id" class="col-sm-3 col-form-label"><?php echo $this->_pt('Select tenant'); ?></label>
                <div class="col-sm-2" style="min-width:250px;max-width:360px;">
                    <select name="tenant_id" id="tenant_id" class="chosen-select"
                            onchange="document.plugin_registry_form.submit()" style="min-width:250px;max-width:360px;">
                        <option value="0"><?php echo $this->_pt( '- Default tenant -' )?></option>
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

        ?><p><small><?php
                echo $this->_pt('Database version').': '.$plugin_info['db_version'].', ';
                echo $this->_pt('Script version').': '.$plugin_info['script_version'];

                if( $plugin_obj ) {
                    echo ', Instance Id: '.$plugin_obj->instance_id();
                }

                if (version_compare($plugin_info['db_version'], $plugin_info['script_version'], '!=')) {
                    echo ' - <span style="color:red;">'.$this->_pt('Please upgrade the plugin').'</span>';
                }

                ?></small></p><?php

        if($is_multi_tenant && $tenant_id && $plugin_obj
           && ($tenant_status = $plugins_model->get_status_of_tenant($plugin_obj->instance_id(), $tenant_id))
           && ($tenant_status_arr = $plugins_model->valid_status($tenant_status))) {
            ?><p class="text-center"><?php
            echo $this->_pt('For selected tenant, current plugin status is %s.',
                '<strong>'.($tenant_status_arr['title'] ?? 'N/A').'</strong>');
            ?></p><?php
        }

        ?>
        <div id="PHS_RActive_Plugins_registry_target"></div>
    </div>
</form>

<script type="text/javascript">
    function phs_plugin_registry_confirm_cancel_action()
    {
        if( !confirm("<?php echo $this->_pte('Are you sure you want to cancel changes in plugin registry data?')?>") ) {
            return false;
        }

        show_submit_protection();
        return true;
    }
</script>
<script id="PHS_RActive_Plugins_registry_template" type="text/html">
    {{#if plugin_registry.length === 0 }}
    <p class="p-5 text-center"><?php echo $this->_pt('No registry data yet...'); ?></p>
    {{ else }}
    <table class="table table-hover">
        <thead>
        <tr>
            <th style="width: 1%;">#</th>
            <th class="text-center"><?php echo $this->_pt( 'Registry key' )?></th>
            <th class="text-center"><?php echo $this->_pt( 'Registry value' )?></th>
            <th>&nbsp;</th>
        </tr>
        </thead>
        <tbody>
        {{#each plugin_registry}}
        <tr>
            <td>{{@index+1}}</td>
            <td>
                {{#if .is_initial_data || @this.field_is_readonly(.key) }}
                <code>{{.key}}</code>
                {{ else }}
                <input type="text" name="key[]" value="{{plugin_registry[@index].key}}" class="form-control" />
                {{/if}}
            </td>
            <td>
                {{#if @this.field_is_readonly(.key) }}
                <code>{{ JSON.stringify(.value) }}</code>
                {{ else }}
                <input type="text" name="values[]" value="{{plugin_registry[@index].value}}" class="form-control" />
                {{/if}}
            </td>
            <td class="text-center">
                {{#if @this.field_can_be_deleted(.key) }}
                <a href="javascript:void(0)" onclick="this.blur()"
                   on-click="@this.delete_registry_value(@index)"><i class="fa fa-times-circle-o action-icons"
                                                                     title="<?php echo $this->_pte('Delete record data')?>"></i></a>
                {{ else }}
                -
                {{/if}}
            </td>
        </tr>
        {{/each}}
        </tbody>
    </table>
    {{/if}}

    <div class="form-group">
        <a href="javascript:void(0)" class="btn btn-primary"
           on-click="@this.add_registry_value()"><?php echo $this->_pt('Add registry value')?></a>
    </div>

    <div class="form-group">
        <input type="button" id="do_submit" name="do_submit" class="btn btn-primary submit-protection ignore_hidden_required"
               on-click="@this.save_registry_data()"
               value="<?php echo $this->_pte('Save registry data'); ?>" />
        <input type="submit" id="do_cancel" name="do_cancel" class="btn btn-danger ignore_hidden_required"
               onclick="return phs_plugin_registry_confirm_cancel_action();"
               value="<?php echo $this->_pte('Cancel'); ?>" />
    </div>
</script>
<script type="text/javascript">
    let PHS_RActive_Plugins_registry_app = null;
    let init_registry_data = <?php echo @json_encode($registry_arr ?: []); ?>;
    let registry_fields_settings = <?php echo @json_encode($registry_fields_settings ?: []); ?>;
    $(document).ready(function() {
        PHS_RActive_Plugins_registry_app = PHS_RActive_Plugins_registry_app || new PHS_RActive({

            target: "PHS_RActive_Plugins_registry_target",
            template: "#PHS_RActive_Plugins_registry_template",

            data: function () {
                return {
                    plugin_registry: [],
                }
            },

            oninit: function() {
                this._set_registry_data();
            },

            _reset_registry_data: function() {
                this.set("plugin_registry", []);
            },
            _set_registry_data: function() {
                let new_registry_data = [];
                let el = null;
                for( el in init_registry_data ) {
                    if( !init_registry_data.hasOwnProperty(el) ) {
                        continue;
                    }

                    new_registry_data.push({
                        key: el,
                        value: init_registry_data[el],
                        is_initial_data: true,
                        dupicate: false
                    });
                }

                this.set("plugin_registry", new_registry_data);
            },

            field_is_readonly: function(key) {
                return !!registry_fields_settings[key]?.readonly;
            },

            field_can_be_deleted: function(key) {
                return !!registry_fields_settings[key]?.can_be_deleted;
            },

            add_registry_value: function() {
                this.push("plugin_registry", {
                    key: "",
                    value: "",
                    is_initial_data: false,
                    dupicate: false
                });
            },

            delete_registry_value: function(index) {
                let registry_data = this.get("plugin_registry");
                if( !registry_data.length
                    || typeof registry_data[index] === "undefined"
                    || typeof registry_data[index].key === "undefined"
                    || typeof registry_data[index].value === "undefined" ) {
                    return;
                }

                if( (registry_data[index].key.length !== 0
                        || registry_data[index].value.length !== 0)
                    && !confirm("<?php echo $this->_pte('Are you sure you want to remove this registry data?')?>" ) ) {
                    return;
                }

                registry_data.splice(index, 1);
                this.set("plugin_registry", registry_data);
            },

            get_registry_data_for_submit: function() {
                let registry_data = this.get("plugin_registry");
                if( registry_data.length === 0 ) {
                    return [];
                }

                let i = 0;
                let form_obj = {};
                for( ; i < registry_data.length; i++ ) {
                    if( typeof registry_data[i].key === "undefined"
                        || typeof registry_data[i].value === "undefined" ) {
                        continue;
                    }

                    if( registry_data[i].key.length === 0 ) {
                        this.phs_add_error_message("<?php echo $this->_pte('Please provide registry keys for all lines.')?>");
                        return null;
                    }

                    if( typeof form_obj[registry_data[i].key] !== "undefined" ) {
                        this.phs_add_error_message("<?php echo $this->_pte('There are duplicates in registry keys.')?>");
                        return null;
                    }

                    form_obj[registry_data[i].key] = registry_data[i].value;
                }

                return form_obj;
            },

            save_registry_data: function() {
                let registry_data = this.get_registry_data_for_submit();
                if( registry_data === null ) {
                    return;
                }

                if( !confirm( "<?php echo $this->_pte('Are you sure you want to save plugin registry data?'); ?>" ) ) {
                    return;
                }

                let form_data = {};
                form_data.save_registry_arr = registry_data;
                form_data.pname = '<?php echo $pname?>';
                form_data.tenant_id = '<?php echo $tenant_id?>';
                form_data.do_save_registry_data = 1;

                let self = this;
                this._send_action_request(form_data,
                    function(response){

                        if( typeof response === "undefined"
                            || !response
                            || typeof response.action_success === "undefined"
                            || !response.action_success ) {
                            self.phs_add_error_message("<?php echo $this->_pt('Error saving registry data.'); ?>", 30);
                            return;
                        }

                        show_submit_protection("<?php echo $this->_pte('Refreshing the page...'); ?>");
                        document.location = "<?php echo PHS::url(
                            ['p' => 'admin', 'a' => 'registry', 'ad' => 'plugins'],
                            ['tenant_id' => $tenant_id, 'pname' => $pname, 'changes_saved' => 1, 'back_page' => $back_page]); ?>";
                    });

            },

            _send_action_request: function(form_data, success_callback, failure_callback) {
                const self = this;

                if( typeof success_callback === "undefined" ) {
                    success_callback = null;
                }
                if( typeof failure_callback === "undefined" ) {
                    failure_callback = null;
                }

                show_submit_protection( "<?php echo $this->_pte('Please wait...'); ?>" );

                this.read_data(
                    "<?php echo PHS::route_from_parts(['a' => 'registry', 'ad' => 'plugins', 'p' => 'admin']); ?>",
                    form_data,
                    //result_response, status, ajax_obj, data
                    function( response, status, ajax_obj ) {

                        hide_submit_protection();

                        if( !self.valid_default_response_from_read_data( response ) ) {
                            let error_msg = "<?php echo $this->_pte('Error sending request to server.'); ?>";
                            const extra_error = self.get_error_message_for_default_read_data(response);
                            if( extra_error ) {
                                error_msg += " " + extra_error;
                            }

                            if( $.isFunction( failure_callback ) ) {
                                failure_callback(self, error_msg);
                            } else if( typeof failure_callback == "string" ) {
                                eval(failure_callback + "( self, error_msg )");
                            } else {
                                self.phs_add_error_message(error_msg, 10);
                            }

                            return;
                        }

                        if( success_callback ) {
                            success_callback( response.response );
                        }
                    },
                    function( ajax_obj, status, error_exception ) {
                        hide_submit_protection();

                        const error_msg = "<?php echo $this->_pte('Error sending request to the server. Please try again.'); ?>";

                        if( $.isFunction( failure_callback ) ) {
                            failure_callback(self, error_msg);
                        } else if( typeof failure_callback == "string" ) {
                            eval(failure_callback + "( self, error_msg )");
                        } else {
                            self.phs_add_error_message(error_msg, 10);
                        }
                    }, {
                        queue_request: false,
                        queue_response_cache: false,
                        queue_response_cache_timeout: 5,
                        stack_request: true
                    }
                );
            }

        });
    });
</script>
<?php
