<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;

/** @var \phs\system\core\models\PHS_Model_Plugins $plugins_model */
if( !($plugins_model = $this->view_var('plugins_model')) ) {
    return $this->_pt('Error loading required resources.');
}

$tenant_id = $this->view_var('tenant_id') ?: 0;

$is_multi_tenant = $this->view_var('is_multi_tenant') ?? false;
$tenants_key_val_arr = $this->view_var('tenants_key_val_arr') ?: [];
$plugins_statuses = $this->view_var('plugins_statuses') ?: [];
$plugins_arr = $this->view_var('plugins_arr') ?: [];

$tenants_filter_arr = $this->view_var('tenants_filter_arr') ?: [];

$na_str = $this->_pt( 'N/A' );

$selected_plugins = [];
foreach( $plugins_arr as $plugin_arr ) {
    $plugin_name = $plugin_arr['plugin_name']?:'core';
    $selected_plugins[$plugin_name] = false;
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
                foreach( $tenants_filter_arr as $t_id => $t_name ) {
                    ?>
                    <option value="<?php echo $t_id?>" <?php echo $t_id===$tenant_id?'selected="selected"':''?>><?php echo $t_name?></option>
                    <?php
                }
                ?>
            </select>
        </div>
        <input type="button" class="btn btn-primary" value="<?php echo $this->_pt('Select')?>" onclick="tenant_changed()" />
    </div>

    <div id="PHS_RActive_Tenants_plugins_target"></div>

</div>
</form>
<script>
function tenant_changed()
{
    show_submit_protection("<?php echo $this->_pte('Please wait...')?>");
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
            <input type="checkbox" name="selected_plugins_all" value="{{selected_plugins_all}}"
                   id="selected_plugins_all" as-skin_checkbox="{}" />
        </th>
        <th scope="col"><?php echo $this->_pt( 'Plugin' )?></th>
        <th scope="col" class="text-center"><?php echo $this->_pt( 'Version' )?></th>
        <th scope="col" class="text-center"><?php echo $this->_pt( 'Status' )?></th>
        <th scope="col" class="text-center"><?php echo $this->_pt( 'Actions' )?></th>
    </tr>
    </thead>
    <tbody>
    {{#each @this.filter_all_plugins()}}
    <tr>
        <th scope="row" class="text-center">
            {{@index+1}}<br/>
            <input type="checkbox" name="selected_plugins[{{.plugin_name?.plugin_name:'core'}}]"
                   value="{{selected_plugins[(.plugin_name?.plugin_name:'core')]}}"
                   id="selected_plugins_{{.plugin_name ? .plugin_name : 'core'}}" as-skin_checkbox="{}" />
        </th>
        <td>
            <strong>{{.name}}</strong>
            {{#if .description.length }}
            <br/><small>{{.description}}</small>
            {{/if}}
        </td>
        <td class="text-center">{{.version}}</td>
        <td class-text-center
            class-font-weight-bold="@this.plugin_status_display_success(.status)"
            class-font-italic="@this.plugin_status_display_warning(.status)"
        >{{@this.plugin_status_as_string(.status)}}</td>
        <td>&nbsp;</td>
    </tr>
    {{/each}}
    </tbody>
    </table>

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
                    statuses_filter_arr: statuses_filter_arr,
                    all_tenants: <?php echo @json_encode($tenants_key_val_arr ?: null); ?>,
                    selected_plugins_all: false,
                    selected_plugins: <?php echo @json_encode($selected_plugins ?: []); ?>
                }
            },

            observe: {
                selected_plugins_all ( value ) {
                    console.log( `show changed to '${value}'` );

                    let selected_plugins = this.get("selected_plugins");
                    let plugin = null;
                    for( plugin in selected_plugins ) {
                        if( !selected_plugins.hasOwnProperty( plugin )) {
                            continue;
                        }

                        selected_plugins[plugin] = !selected_plugins[plugin];
                    }

                    this.set("selected_plugins", selected_plugins);
                }
            },

            onrender: function () {
                phs_refresh_input_skins();
            },

            plugin_status_as_string: function(status) {
                if(typeof statuses_filter_arr[status] !== "undefined") {
                    return statuses_filter_arr[status];
                }

                return "<?php echo $na_str?>";
            },

            plugin_status_display_success: function(status) {
                return parseInt(status) === parseInt('<?php echo $plugins_model::STATUS_ACTIVE?>');
            },

            plugin_status_display_warning: function(status) {
                return parseInt(status) === parseInt('<?php echo $plugins_model::STATUS_INSTALLED?>')
                    || parseInt(status) === parseInt('<?php echo $plugins_model::STATUS_INACTIVE?>');
            },

            filter_all_plugins: function() {
                let f_name = this.get("filters.plugin_name");
                let f_status = this.get("filters.plugin_status");

                let items_arr = [];
                let knti = null;
                for( knti in all_plugins ) {
                    let item = all_plugins[knti];
                    if( typeof item !== "object"
                        || !item.hasOwnProperty( "id" )
                        || !item.hasOwnProperty( "plugin_name" )
                        || !item.hasOwnProperty( "status" )
                        || (f_name.length > 0 && item.plugin_name.length > 0
                            && -1 === item.plugin_name.toLowerCase().indexOf( f_name ))
                        || (parseInt(f_status) !== 0 && parseInt(f_status) !== parseInt(item.status))) {
                        continue;
                    }

                    items_arr.push(item);
                }

                return items_arr;
            }

        });
    })
</script>
