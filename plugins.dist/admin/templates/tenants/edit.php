<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;

if (!($back_page = $this->view_var('back_page'))) {
    $back_page = PHS::url(['p' => 'admin', 'a' => 'list', 'ad' => 'tenants']);
}

if (!($phs_defined_themes = @array_keys(PHS::get_defined_themes()))) {
    $phs_defined_themes = [];
}

echo $this->sub_view('ractive/bootstrap');
?>
<form id="edit_tenant_form" name="edit_tenant_form" method="post"
      action="<?php echo PHS::url(['p' => 'admin', 'a' => 'edit', 'ad' => 'tenants'],
          ['tid' => $this->view_var('tid')]); ?>">
<input type="hidden" name="foobar" value="1" />
<?php
    if (!empty($back_page)) {
        ?><input type="hidden" name="back_page" value="<?php echo form_str(safe_url($back_page)); ?>" /><?php
    }
?>

<div class="form_container">

<?php
if (!empty($back_page)) {
    ?><i class="fa fa-chevron-left"></i>
            <a href="<?php echo form_str(from_safe_url($back_page)); ?>"><?php echo $this->_pt('Back'); ?></a><?php
}
?>

    <section class="heading-bordered">
        <h3><?php echo $this->_pt('Edit Tenant'); ?></h3>
    </section>

    <div class="form-group row">
        <label for="name" class="col-sm-2 col-form-label"><?php echo $this->_pt('Name'); ?></label>
        <div class="col-sm-10">
            <input type="text" id="name" name="name" class="form-control" autocomplete="name"
                   placeholder="<?php echo form_str($this->_pt('Friendly tenant name')); ?>"
                   value="<?php echo form_str($this->view_var('name')); ?>" />
            <div id="name_help" class="form-text"><?php echo $this::_t('A friendly name to identify the tenant. This will replace PHS_SITE_NAME constant.'); ?></div>
        </div>
    </div>

    <div class="form-group row">
        <label for="domain" class="col-sm-2 col-form-label"><?php echo $this->_pt('Domain'); ?></label>
        <div class="col-sm-10">
            <input type="text" id="domain" name="domain" class="form-control" autocomplete="name"
                   placeholder="www.exemple.com"
                   value="<?php echo form_str($this->view_var('domain')); ?>" />
            <div id="domain_help" class="form-text"><?php echo $this::_t('What domain is used for this tenant (e.g. www.example.com)'); ?></div>
        </div>
    </div>

    <div class="form-group row">
        <label for="directory" class="col-sm-2 col-form-label"><?php echo $this->_pt('Directory'); ?></label>
        <div class="col-sm-10">
            <input type="text" id="directory" name="directory" class="form-control" autocomplete="directory"
                   placeholder="appdir/"
                   value="<?php echo form_str($this->view_var('directory')); ?>" />
            <div id="directory_help" class="form-text"><?php echo $this::_t('What directory (if any) is used for this tenant (e.g. appdir/)'); ?></div>
        </div>
    </div>

    <div class="form-group row">
        <label for="identifier" class="col-sm-2 col-form-label"><?php echo $this->_pt('Identifier'); ?></label>
        <div class="col-sm-10">
            <input type="text" id="identifier" name="identifier" class="form-control" autocomplete="identifier"
                   placeholder="<?php echo form_str($this->_pt('External tenant identifier')); ?>"
                   value="<?php echo form_str($this->view_var('identifier')); ?>" />
            <div id="identifier_help" class="form-text"><?php echo $this::_t('Used to identify the tenant from requests from outside.'); ?></div>
        </div>
    </div>

    <div class="form-group row">
        <label for="is_default" class="col-sm-2 col-form-label"><?php echo $this->_pt('Default tenant?'); ?></label>
        <div class="col-sm-10">
            <input class="form-check-input" type="checkbox" id="is_default" name="is_default"
                   rel="skin_checkbox"
                <?php echo $this->view_var('is_default') ? 'checked="checked"' : ''; ?>>
            <br/>
            <small><?php echo $this->_pt('If no tenant can be selected from the request, use this tenant as default'); ?></small>
        </div>
    </div>

    <div class="form-group row">
        <label for="default_theme" class="col-sm-2 col-form-label"><?php echo $this->_pt('Default Theme'); ?></label>
        <div class="col-sm-10">
            <select id="default_theme" name="default_theme" class="chosen-select" style="width: 250px;">
                <option value=""> - <?php echo $this->_pt('Choose'); ?> - </option>
                <?php
                $default_theme = $this->view_var('default_theme');
foreach ($phs_defined_themes as $theme) {
    ?><option value="<?php echo $theme; ?>" <?php echo $default_theme === $theme ? 'selected="selected"' : ''; ?>><?php echo $theme; ?></option><?php
}
?>
            </select></div>
    </div>

    <div class="form-group row">
        <label for="current_theme" class="col-sm-2 col-form-label"><?php echo $this->_pt('Current Theme'); ?></label>
        <div class="col-sm-10">
            <select id="current_theme" name="current_theme" class="chosen-select" style="width: 250px;">
                <option value=""> - <?php echo $this->_pt('Choose'); ?> - </option>
                <?php
$current_theme = $this->view_var('current_theme');
foreach ($phs_defined_themes as $theme) {
    ?><option value="<?php echo $theme; ?>" <?php echo $current_theme === $theme ? 'selected="selected"' : ''; ?>><?php echo $theme; ?></option><?php
}
?>
            </select></div>
    </div>

    <div id="PHS_RActive_Tenants_themes_target"></div>

    <fieldset>
        <input type="submit" id="do_submit" name="do_submit"
               class="btn btn-primary submit-protection ignore_hidden_required"
               value="<?php echo $this->_pte('Save changes'); ?>" />
    </fieldset>

</div>
</form>
<script id="PHS_RActive_Tenants_themes_template" type="text/html">
    <div class="form-group row">
        <label for="identifier" class="col-sm-2 col-form-label"><?php echo $this->_pt('Themes Cascade'); ?></label>
        <div class="col-sm-10">
            {{#if cascading_themes.length}}
            <table class="table-bordered">
                <thead>
                <tr>
                    <th>&nbsp;</th>
                    <th>Theme</th>
                    <th>&nbsp;</th>
                </tr>
                </thead>
                <tbody>
                {{#each cascading_themes}}
                <tr>
                    <td>{{@index+1}}</td>
                    <td><select value="{{this}}" name="cascading_themes[]" style="width: 250px;"
                                id="cascading_themes_{{@index}}"
                                as-chosen_select_nosearch="{}">
                            <option value=""> - <?php echo $this->_pt('Choose'); ?> - </option>
                            {{#each all_themes_arr}}
                            <option value="{{this}}">{{this}}</option>
                            {{/each}}
                        </select></td>
                    <td>
                        <i class="fa fa-times action-icons" style="cursor: pointer;"
                           on-click="@this.remove_theme(@index)"
                           title="<?php echo $this->_pte('Remove'); ?>"></i>
                        {{#if 0 !== @index }}
                        <i class="fa fa-arrow-up action-icons" style="cursor: pointer;"
                           on-click="@this.move_up_theme(@index)"
                           title="<?php echo $this->_pte('Move up'); ?>"></i>
                        {{/if}}
                        {{#if @last !== @index }}
                        <i class="fa fa-arrow-down action-icons" style="cursor: pointer;"
                           on-click="@this.move_down_theme(@index)"
                           title="<?php echo $this->_pte('Move down'); ?>"></i>
                        {{/if}}
                    </td>
                </tr>
                {{/each}}
                </tbody>
            </table>
            {{else}}
            <p class="text-center"><?php echo $this->_pt('No theme selected yet.'); ?></p>
            {{/if}}

            <input type="button" id="do_add_theme"
                   class="btn btn-primary"
                   on-click="@this.add_theme()"
                   value="<?php echo $this->_pte('Add theme'); ?>" />

        </div>
    </div>
</script>
<script type="text/javascript">
    let PHS_RActive_Tenants_themes_app = null;
    $(document).ready(function() {
        PHS_RActive_Tenants_themes_app = PHS_RActive_Tenants_themes_app || new PHS_RActive({

            target: "PHS_RActive_Tenants_themes_target",
            template: "#PHS_RActive_Tenants_themes_template",

            data: function () {
                return {
                    all_themes_arr: <?php echo @json_encode($phs_defined_themes); ?>,
                    cascading_themes: <?php echo @json_encode($this->view_var('cascading_themes') ?: []); ?>
                }
            },

            onrender: function () {
                phs_refresh_input_skins();
            },

            add_theme: function() {
                let cascading_themes = this.get("cascading_themes");
                cascading_themes.push('');
                this.set("cascading_themes", cascading_themes);
            },

            remove_theme: function(index) {
                let cascading_themes = this.get("cascading_themes");
                cascading_themes.splice(index,1);
                this.set("cascading_themes", cascading_themes);

                for( let knti = index; knti < cascading_themes.length; knti++ ) {
                    $("#cascading_themes_" + knti).trigger("chosen:updated");
                }
            },

            move_up_theme: function(index) {
                if(index === 0 ) {
                    return;
                }
                let cascading_themes = this.get("cascading_themes");
                let temp = cascading_themes[index-1];
                cascading_themes[index-1] = cascading_themes[index];
                cascading_themes[index] = temp;
                this.set("cascading_themes", cascading_themes);

                $("#cascading_themes_"+(index-1)).trigger( "chosen:updated" );
                $("#cascading_themes_"+index).trigger( "chosen:updated" );
            },

            move_down_theme: function(index) {
                let cascading_themes = this.get("cascading_themes");
                if(index === cascading_themes.length-1 ) {
                    return;
                }
                let temp = cascading_themes[index+1];
                cascading_themes[index+1] = cascading_themes[index];
                cascading_themes[index] = temp;
                this.set("cascading_themes", cascading_themes);

                $("#cascading_themes_"+(index+1)).trigger( "chosen:updated" );
                $("#cascading_themes_"+index).trigger( "chosen:updated" );
            }

        });
    })
</script>
