<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;
use phs\libraries\PHS_Instantiable;

/** @var \phs\system\core\models\PHS_Model_Plugins $plugins_model */
if (!($plugins_model = $this->view_var('plugins_model'))) {
    return $this->_pt('Error loading required resources.');
}

$tenant_id = $this->view_var('tenant_id') ?: 0;

$is_multi_tenant = $this->view_var('is_multi_tenant') ?? false;
$tenants_key_val_arr = $this->view_var('tenants_key_val_arr') ?: [];
$plugins_statuses = $this->view_var('plugins_statuses') ?: [];
$plugins_arr = $this->view_var('plugins_arr') ?: [];

$tenants_filter_arr = $this->view_var('tenants_filter_arr') ?: [];

$na_str = $this->_pt('N/A');

foreach ($plugins_arr as $key => $plugin_arr) {
    $plugin_name = $plugin_arr['plugin_name'] ?: PHS_Instantiable::CORE_PLUGIN;
    $plugins_arr[$key]['safe_name'] = $plugin_name;

    if ($tenant_id
     && ($tenant_status = $plugins_model->get_status_of_tenant($plugin_arr['id'], $tenant_id))) {
        $plugins_arr[$key]['tenant_status'] = $tenant_status;
    } else {
        $plugins_arr[$key]['tenant_status'] = $plugin_arr['status'];
    }
}

echo $this->sub_view('ractive/bootstrap');
?>
<form id="tenant_plugins_form" name="tenant_plugins_form" method="post"
      action="<?php echo PHS::url(['p' => 'admin', 'a' => 'plugins', 'ad' => 'tenants']); ?>">
<input type="hidden" name="foobar" value="1" />

<div class="form_container">

    <section class="heading-bordered">
        <h3><?php echo $this->_pt('Manage Tenant\'s Plugins'); ?></h3>
    </section>

    <div class="form-group row">
        <label for="tenant_id" class="col-sm-2 col-form-label"><?php echo $this->_pt('Choose tenant'); ?></label>
        <div class="col-sm-8">
            <select name="tenant_id" id="tenant_id" class="chosen-select" style="width: 250px;"
                    onchange="tenant_changed()">
                <?php
                foreach ($tenants_filter_arr as $t_id => $t_name) {
                    ?>
                    <option value="<?php echo $t_id; ?>" <?php echo $t_id === $tenant_id ? 'selected="selected"' : ''; ?>><?php echo $t_name; ?></option>
                    <?php
                }
?>
            </select>
        </div>
        <input type="button" class="btn btn-primary" value="<?php echo $this->_pt('Select'); ?>" onclick="tenant_changed()" />
    </div>

    <div id="PHS_RActive_Tenants_plugins_target"></div>

</div>
</form>
<script>
function tenant_changed()
{
    show_submit_protection("<?php echo $this->_pte('Please wait...'); ?>");
    document.getElementById("tenant_plugins_form").submit();
}
</script>
<script id="PHS_RActive_Tenants_plugins_template" type="text/html">

    <div class="row">
        <div class="form-group col-xs-12 col-md-6 col-lg-3">
            <label for="plugin_name"><?php echo $this->_pte('Plugin name'); ?></label>
            <input type="text" id="plugin_name" name="plugin_name" class="form-control"
                   value="{{filters.plugin_name}}" placeholder="" />
        </div>
        <div class="form-group col-xs-12 col-md-6 col-lg-3">
            <label for="plugin_status"><?php echo $this->_pte('Plugin status'); ?></label>
            <div><select id="plugin_status" name="plugin_status" style="min-width: 250px;"
                    value="{{filters.plugin_status}}" as-chosen_select_nosearch="{}">
                {{#each statuses_filter_arr}}
                <option value="{{@key}}">{{this}}</option>
                {{/each}}
            </select></div>
        </div>
    </div>

    <table class="table table-bordered table-striped table-hover">
    <thead>
    <tr>
        <th scope="col" class="text-center">
            #<br/>
            <input type="checkbox" id="selected_plugins_all" name="selected_plugins_all" value="1"
                   checked="{{selected_plugins_all}}" />
        </th>
        <th scope="col"><?php echo $this->_pt('Plugin'); ?></th>
        <th scope="col" class="text-center"><?php echo $this->_pt('Version'); ?></th>
        <th scope="col" class="text-center"><?php echo $this->_pt('Status'); ?></th>
        <th scope="col" class="text-center"><?php echo $this->_pt('Actions'); ?></th>
    </tr>
    </thead>
    <tbody>
    {{#each filtered_plugins}}
    <tr>
        <th scope="row" class="text-center">
            {{@index+1}}
            {{#if !.is_always_active }}
            <br/>
            <input type="checkbox" name="{{selected_plugins}}" value="{{.safe_name}}"
                   id="selected_plugins_{{.safe_name}}" />
            {{/if}}
        </th>
        <td>
            <strong>{{.name}}</strong>
            {{#if .description.length }}
            <br/><small>{{.description}}</small>
            {{/if}}
        </td>
        <td class="text-center">{{.version}}</td>
        <td class-text-center
            class-font-weight-bold="@this.plugin_status_display_success(.tenant_status)"
            class-font-italic="@this.plugin_status_display_warning(.tenant_status)"
        >{{@this.plugin_status_as_string(.tenant_status)}}
            {{#if tenant_id }}
            <br/>[{{@this.plugin_status_as_string(.status)}}]
            {{/if}}
        </td>
        <td>&nbsp;</td>
    </tr>
    {{else}}
    <tr>
        <td colspan="5" class="p-5 text-center"><?php echo $this->_pt('No plugins selected with current filters.'); ?></td>
    </tr>
    {{/each}}
    </tbody>
    </table>
    <div>
        <div><small>Selected {{selected_plugins_length}} plugins...</small></div>
        {{#if !all_selected_visible }}
        <div class="alert alert-danger" role="alert">There are selected plugins which are not visible!</div>
        {{/if}}
    </div>
    <div>
        <input type="button" id="do_activate_selected" name="do_activate_selected" class="btn btn-primary"
               on-click="@this.activate_selected_plugins()"
               value="<?php echo $this->_pte('ACTIVATE Selected'); ?> ({{selected_plugins_length}})" />

        <input type="button" id="do_inactivate_selected" name="do_inactivate_selected" class="btn btn-danger"
               on-click="@this.inactivate_selected_plugins()"
               value="<?php echo $this->_pte('INACTIVATE Selected'); ?> ({{selected_plugins_length}})" />
    </div>

</script>
<script type="text/javascript">
    let statuses_filter_arr = <?php echo @json_encode($this->view_var('statuses_filter_arr') ?: []); ?>;
    let all_plugins = <?php echo @json_encode($plugins_arr ?: []); ?>;
    let PHS_RActive_Tenants_plugins_app = null;
    $(document).ready(function() {
        PHS_RActive_Tenants_plugins_app = PHS_RActive_Tenants_plugins_app || new PHS_RActive({

            target: "PHS_RActive_Tenants_plugins_target",
            template: "#PHS_RActive_Tenants_plugins_template",

            data: function () {
                return {
                    filters: {
                        plugin_name: "",
                        plugin_status: 0
                    },
                    tenant_id: <?php echo $tenant_id; ?>,
                    statuses_filter_arr: statuses_filter_arr,
                    all_tenants: <?php echo @json_encode($tenants_key_val_arr ?: null); ?>,
                    selected_plugins_all: false,
                    selected_plugins: null
                }
            },

            observe: {
                "selected_plugins_all": {
                    handler( newval, oldval ) {
                        let selected_plugins = this.get("selected_plugins");
                        if(selected_plugins===null) {
                            this.set("selected_plugins", []);
                            return;
                        }

                        let selected_plugins_empty = this.get("selected_plugins_length") === 0;
                        let new_selected_plugins = [];
                        let knti = 0;
                        for( ; knti < all_plugins.length; knti++ ) {
                            if( !all_plugins[knti].is_always_active
                                && (selected_plugins_empty
                                    || -1 === selected_plugins.indexOf(all_plugins[knti].safe_name))
                            ) {
                                new_selected_plugins.push(all_plugins[knti].safe_name);
                            }
                        }

                        this.set("selected_plugins", new_selected_plugins);
                    }
                }
            },

            computed: {
                filtered_plugins () {
                    let f_name = this.get("filters.plugin_name");
                    let f_status = this.get("filters.plugin_status");

                    return this.filter_all_plugins(f_name, f_status);
                },

                filtered_plugins_length () {
                    return this.get("filtered_plugins").length;
                },

                selected_plugins_length () {
                    let selected_plugins = this.get("selected_plugins");
                    return selected_plugins ? selected_plugins.length : 0;
                },

                all_selected_visible () {
                    let selected_plugins = this.get("selected_plugins");
                    let total_selected = this.get("selected_plugins_length");
                    if( total_selected === 0 ) {
                        return true;
                    }

                    let filtered_plugins = this.get("filtered_plugins");
                    if( filtered_plugins.length === 0 ) {
                        return false;
                    }

                    let knti = 0;
                    let selected_from_filtered = 0;
                    for( ; knti < filtered_plugins.length; knti++ ) {
                        if( -1 === selected_plugins.indexOf(filtered_plugins[knti].safe_name)) {
                            continue;
                        }

                        selected_from_filtered++;
                    }

                    return total_selected === selected_from_filtered;
                },
            },

            onrender: function () {
                //phs_refresh_input_skins();
            },

            plugin_status_as_string: function(status) {
                if(status
                    && typeof statuses_filter_arr[status] !== "undefined") {
                    return statuses_filter_arr[status];
                }

                return "<?php echo $na_str; ?>";
            },

            plugin_status_display_success: function(status) {
                return parseInt(status) === parseInt('<?php echo $plugins_model::STATUS_ACTIVE; ?>');
            },

            plugin_status_display_warning: function(status) {
                return parseInt(status) === parseInt('<?php echo $plugins_model::STATUS_INSTALLED; ?>')
                    || parseInt(status) === parseInt('<?php echo $plugins_model::STATUS_INACTIVE; ?>');
            },

            filter_all_plugins: function(f_name, f_status) {
                let items_arr = [];
                let knti = null;
                for( knti in all_plugins ) {
                    let item = all_plugins[knti];
                    if( typeof item !== "object"
                        || !item.hasOwnProperty( "id" )
                        || !item.hasOwnProperty( "safe_name" )
                        || !item.hasOwnProperty( "name" )
                        || !item.hasOwnProperty( "tenant_status" )
                        || (f_name.length > 0
                            && (item.safe_name.length === 0 || -1 === item.safe_name.toLowerCase().indexOf( f_name ))
                            && (item.name.length === 0 || -1 === item.name.toLowerCase().indexOf( f_name ))
                        )
                        || (parseInt(f_status) !== 0 && parseInt(f_status) !== parseInt(item.tenant_status))) {
                        continue;
                    }

                    items_arr.push(item);
                }

                return items_arr;
            },

            activate_selected_plugins: function() {
                let selected_plugins_length = this.get("selected_plugins_length");
                if( selected_plugins_length === 0 ) {
                    this.phs_add_warning_message("<?php echo $this->_pte('First, select pluins from the list.'); ?>");
                    return;
                } else if( !confirm( "<?php echo $this->_pt('Are you sure you want to ACTIVATE %s plugins for selected tenant?', '" + selected_plugins_length +"'); ?>" ) ) {
                    return;
                }

                let form_data = this.get_form_data_for_submit();
                if( !form_data ) {
                    return;
                }

                form_data.do_activate_selected = 1;

                let self = this;
                this._send_action_request(form_data,
                    function(response){

                        let timeout = 3000;
                        if( typeof response !== "undefined"
                            && response
                            && typeof response.action_success !== "undefined"
                            && response.action_success ) {
                            self.phs_add_success_message("<?php echo $this->_pt('Selected plugins activated with success.'); ?>", 10);
                            timeout = 10;
                        }

                        show_submit_protection("<?php echo $this->_pte('Refreshing the page...'); ?>");
                        setTimeout(function(){
                            document.location = "<?php echo PHS::url(['p' => 'admin', 'a' => 'plugins', 'ad' => 'tenants'], ['tenant_id' => $tenant_id]); ?>";
                        }, timeout);
                    });
            },

            inactivate_selected_plugins: function() {
                let selected_plugins_length = this.get("selected_plugins_length");
                if( selected_plugins_length === 0 ) {
                    this.phs_add_warning_message("<?php echo $this->_pte('First, select pluins from the list.'); ?>");
                    return;
                } else if( !confirm( "<?php echo $this->_pt('Are you sure you want to INACTIVATE %s plugins for selected tenant?', '" + selected_plugins_length +"'); ?>" ) ) {
                    return;
                }

                let form_data = this.get_form_data_for_submit();
                if( !form_data ) {
                    return;
                }

                form_data.do_inactivate_selected = 1;

                let self = this;
                this._send_action_request(form_data,
                    function(response){

                        let timeout = 3000;
                        if( typeof response !== "undefined"
                            && response
                            && typeof response.action_success !== "undefined"
                            && response.action_success ) {
                            self.phs_add_success_message("<?php echo $this->_pt('Selected plugins inactivated with success.'); ?>", 10);
                            timeout = 10;
                        }

                        show_submit_protection("<?php echo $this->_pte('Refreshing the page...'); ?>");
                        setTimeout(function(){
                            document.location = "<?php echo PHS::url(['p' => 'admin', 'a' => 'plugins', 'ad' => 'tenants'], ['tenant_id' => $tenant_id]); ?>";
                        }, timeout);
                    });
            },

            get_form_data_for_submit: function() {
                if( this.get("selected_plugins_length") === 0 ) {
                    this.phs_add_warning_message("<?php echo $this->_pte('First, select plugins from the list.'); ?>");
                    return null;
                }

                let form_obj = PHS_JSEN.get_form_as_json_object("tenant_plugins_form");
                if( !form_obj ) {
                    this.phs_add_warning_message("<?php echo $this->_pte('Error obtaining form data. Please try again.'); ?>");
                    return null;
                }

                if( typeof form_obj["{{selected_plugins}}"] !== "undefined" ) {
                    form_obj["selected_plugins"] = form_obj["{{selected_plugins}}"];
                    delete form_obj["{{selected_plugins}}"];
                }

                return form_obj;
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
                    "<?php echo PHS::route_from_parts(['a' => 'plugins', 'ad' => 'tenants', 'p' => 'admin']); ?>",
                    form_data,
                    //result_response, status, ajax_obj, data
                    function( response, status, ajax_obj ) {

                        console.log( "Response", response, "Status", status, "Ajax obj", ajax_obj );

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

                        const error_msg = "<?php echo $this->_pte('Error saving changes. Please try again.'); ?>";

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
    })
</script>
