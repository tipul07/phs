<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;
use phs\PHS_Tenants;
use phs\system\core\models\PHS_Model_Plugins;

/** @var PHS_Model_Plugins $plugins_model */
if( !($plugins_model = PHS_Model_Plugins::get_instance())) {
    return $this->_pt('Error loading required resources.');
}

$tenant_id = (int)($this->view_var('tenant_id') ?: 0);
$all_tenants_arr = $this->view_var('all_tenants_arr') ?: [];

$decoded_settings_arr = $this->view_var('decoded_settings_arr') ?: [];
$selected_plugins = $this->view_var('selected_plugins') ?: [];

$do_import = $this->view_var('do_import') ?: null;
$import_with_success = $this->view_var('import_with_success');
$result_buffer = $this->view_var('result_buffer') ?: '';

$is_multi_tenant = PHS::is_multi_tenant();

?>
<form id="import_settings_form" name="import_settings_form" method="post"
      action="<?php echo PHS::url(['p' => 'admin', 'a' => 'import', 'ad' => 'plugins']); ?>">
    <input type="hidden" name="foobar" value="1" />

    <?php
    if(empty($decoded_settings_arr)) {
        ?>
        <div class="form_container">

            <section class="heading-bordered">
                <h3><?php echo $this->_pt('Import Plugin Settings'); ?></h3>
            </section>

            <?php
            if ($is_multi_tenant) {
                ?>
                <div class="form-group row">
                    <label for="tenant_id" class="col-sm-2 col-form-label"><?php echo $this->_pt('For tenant'); ?></label>
                    <div class="col-sm-10">
                        <select name="tenant_id" id="tenant_id" class="chosen-select" style="width:100%;">
                            <option value="0"> - <?php echo $this->_pt('- Default tenant -'); ?> - </option>
                            <?php
                            foreach ($all_tenants_arr as $t_id => $t_arr) {
                                ?>
                                <option value="<?php echo $t_id; ?>"
                                    <?php echo (int)$t_id === (int)$this->view_var('tenant_id') ? 'selected="selected"' : ''; ?>
                                ><?php echo PHS_Tenants::get_tenant_details_for_display($t_arr); ?></option>
                                <?php
                            }
                            ?>
                        </select>
                    </div>
                </div>
            <?php } ?>

            <div class="form-group row">
                <label for="crypt_key" class="col-sm-2 col-form-label"><?php echo $this->_pt('Crypt Key'); ?></label>
                <div class="col-sm-10">
                    <input type="text" id="crypt_key" name="crypt_key" class="form-control"
                           value="<?php echo form_str($this->view_var('crypt_key')); ?>" />
                    <small class="text-muted"><?php echo $this->_pt('Crypt key used when settings were exported'); ?></small>
                </div>
            </div>

            <div class="form-group row">
                <label for="settings_json" class="col-sm-2 col-form-label"><?php echo $this->_pt('Crypted Settings'); ?></label>
                <div class="col-sm-10">
        <textarea id="settings_json" name="settings_json" class="form-control"
                  style="height:400px;"><?php echo textarea_str($this->view_var('settings_json')); ?></textarea>
                    <small class="text-muted"><?php echo $this->_pt('Copy and paste the JSON string from the file obtained at plugin settings export'); ?></small>
                </div>
            </div>

            <div class="form-group row">
                <input type="submit" id="do_validate" name="do_validate"
                       class="btn btn-primary submit-protection ignore_hidden_required"
                       value="<?php echo $this->_pte('Validate Settings'); ?>" />
            </div>

        </div>

        <?php
    } elseif(!$do_import) {
        ?>
        <input type="hidden" name="crypt_key" value="<?php echo form_str($this->view_var('crypt_key')); ?>" />
        <input type="hidden" name="settings_json" value="<?php echo form_str($this->view_var('settings_json')); ?>" />

        <div class="form_container">

            <section class="heading-bordered">
                <h3><?php echo $this->_pt('Import Plugin Settings'); ?></h3>
            </section>

            <div class="form-group row">
                <label class="col-sm-2 col-form-label"><?php echo $this->_pt('Source Platform'); ?></label>
                <div class="col-sm-10"><?php echo $decoded_settings_arr['source_name'] ?? $this->_pt('N/A')?></div>
            </div>

            <div class="form-group row">
                <label class="col-sm-2 col-form-label"><?php echo $this->_pt('Source URL'); ?></label>
                <div class="col-sm-10"><?php echo $decoded_settings_arr['source_url'] ?? $this->_pt('N/A')?></div>
            </div>
            <?php

            if( empty($decoded_settings_arr['settings']) || !is_array($decoded_settings_arr['settings'])) {
                ?>
                <div class="form-group row">
                    <p class="p-5 col-12 text-center"><?php echo $this->_pt('Seems linke provided import JSON doesn\'t contain any settings to be imported.')?></p>
                </div>

                <div class="form-group row">
                    <a class="btn btn-primary submit-protection"
                       href="<?php echo PHS::url(['p' => 'admin', 'a' => 'import', 'ad' => 'plugins']); ?>"
                    ><?php echo $this->_pte('Try again'); ?></a>
                </div>
                <?php
            } else {
                ?>
                <div class="form-group row">
                    <p class="col-12 text-center"><?php echo $this->_pt('Pick the settings you want to import from te table below.')?></p>
                    <p class="col-12 text-center font-weight-bold"><?php echo $this->_pt('If you don\'t select any plugin, system will import all plugin settings presented in this table.')?></p>
                </div>

                <table class="table-hover tgrid table-bordered" style="width:100%">
                    <thead>
                    <tr>
                        <th rowspan="2" class="text-center">#</th>
                        <th colspan="3" class="text-center"><?php echo $this->_pt('In import file')?></th>
                        <th colspan="2" class="text-center"><?php echo $this->_pt('In current platform')?></th>
                    </tr>
                    <tr>
                        <th class="text-center"><?php echo $this->_pt('Plugin (in file)')?></th>
                        <th class="text-center">
                            <?php echo $this->_pt('Models')?>
                            <i class="fa fa-question-circle" title="<?php echo $this->_pte('Only models with settings')?>"></i>
                        </th>
                        <th class="text-center"><?php echo $this->_pt('Version')?></th>
                        <th class="text-center"><?php echo $this->_pt('Version')?></th>
                        <th class='text-center'><?php echo $this->_pt('Status') ?></th>
                    </tr>
                    </thead>
                    <?php
                    $knti = 0;
                    foreach($decoded_settings_arr['settings'] as $plugin_name => $details_arr) {
                        $knti++;

                        $plugin_instance = null;
                        $can_import = !$plugin_name || ($plugin_instance = PHS::load_plugin($plugin_name));
                        $this_version = $plugin_name
                            ? $plugin_instance?->get_plugin_version()
                            : PHS_VERSION;
                        $this_status = $plugin_name
                            ? (int)($plugin_instance->get_plugin_info()['db_details']['status'] ?? -1)
                            : PHS_Model_Plugins::STATUS_ACTIVE;
                        ?>
                        <tr>
                            <td class='text-center'><?php
                                if (!$can_import) {
                                    echo '-';
                                } else {
                                    ?>
                                    <input type="checkbox" id="selected_plugins_<?php echo $knti ?>"
                                           name="selected_plugins[]" value="<?php echo form_str($plugin_name) ?>"
                                        <?php echo in_array($plugin_name, $selected_plugins, true) ? 'checked="checked"' : ''; ?>
                                    />
                                    <?php
                                }
                                ?>
                            </td>
                            <td class="text-left"><?php
                                echo '<strong>'.($details_arr['name'] ?? $plugin_name).'</strong>'.
                                     ($plugin_name?' - '.$plugin_name:'')
                                ?></td>
                            <td class="text-center"><?php echo implode(', ', array_column($details_arr['models'] ?? [], 'name')) ?: '-'?></td>
                            <td class="text-center"><?php echo $details_arr['version'] ?? $this->_pt('N/A')?></td>
                            <td class="text-center"><?php echo $this_version ?: $this->_pt('N/A')?></td>
                            <td class='text-center'><?php echo $plugins_model->get_status_title($this_status) ?: $this->_pt('N/A') ?></td>
                        </tr>
                        <?php
                    }
                    ?>
                </table>

                <div class="form-group row">
                    <input type="submit" id="do_import" name="do_import"
                           class="btn btn-primary submit-protection ignore_hidden_required"
                           value="<?php echo $this->_pte('Import Settings'); ?>" />
                </div>
                <?php
            }
            ?>
        </div>
        <?php
    } else {
        ?>
        <div class="form_container">

            <section class="heading-bordered">
                <h3><?php echo $this->_pt('Import Plugin Settings'); ?></h3>
            </section>

            <?php
            if($import_with_success) {
                ?>
                <div class="success-box m-4">
                    <div class="text-center"><?php echo $this->_pt('Plugin settings imported with success.')?></div>
                </div>
                <?php
            } else {
                ?>
                <div class="error-box m-4">
                    <div class="text-center"><?php echo $this->_pt('There were errors while importing plugin settings. Please try again.')?></div>
                </div>
                <?php
            }
            ?>

            <?php
            if($result_buffer) {
                ?>
                <div class="form-group row">
                    <pre style="width:100%;height:800px;background-color:black;color:lightgrey;font-size:12px;overflow:auto;"><?php echo $result_buffer?></pre>
                </div>
                <div class='form-group row'><small><?php echo $this->_pt('Import information is also available in maintenance.log file.')?></small></div>
                <?php
            }
            ?>

            <div class="form-group row">
                <a class="btn btn-primary submit-protection"
                   href="<?php echo PHS::url(['p' => 'admin', 'a' => 'import', 'ad' => 'plugins']); ?>"
                ><?php echo $this->_pte('Finish'); ?></a>
            </div>
        </div>
        <?php
    }
    ?>
</form>
<?php
