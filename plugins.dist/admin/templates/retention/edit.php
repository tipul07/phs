<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;
use phs\libraries\PHS_Plugin;

$plugins_arr = $this->view_var('plugins_arr') ?: [];
$models_arr = $this->view_var('models_arr') ?: [];
$tables_arr = $this->view_var('tables_arr') ?: [];
$fields_arr = $this->view_var('fields_arr') ?: [];
$types_arr = $this->view_var('types_arr') ?: [];
$intervals_arr = $this->view_var('intervals_arr') ?: [];

$plugin_obj = $this->view_var('plugin_obj') ?: null;
$model_obj = $this->view_var('model_obj') ?: null;

$plugin = $this->view_var('plugin') ?: null;
$model = $this->view_var('model') ?: null;
$table = $this->view_var('table') ?: null;
$date_field = $this->view_var('date_field') ?: null;
$type = (int)($this->view_var('type') ?: 0);
$retention_interval = $this->view_var('retention_interval') ?: null;
$retention_count = (int)($this->view_var('retention_count') ?: 0);

if (!($back_page = $this->view_var('back_page'))) {
    $back_page = PHS::url(['p' => 'admin', 'a' => 'list', 'ad' => 'retention']);
}

?>
<form id="edit_data_retention_policy" name="edit_data_retention_policy" method="post"
      action="<?php echo PHS::url(['p' => 'admin', 'a' => 'edit', 'ad' => 'retention'],
          ['drid' => $this->view_var('drid')]); ?>">
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
            <h3><?php echo $this->_pt('Edit Data Retention Policy'); ?></h3>
        </section>

        <div class="form-group row">
            <label for="plugin" class="col-sm-2 col-form-label"><?php echo $this->_pt('Plugin'); ?></label>
            <div class="col-sm-10">
                <select name="plugin" id="plugin" class="chosen-select" style="width:400px;"
                        onchange="document.edit_data_retention_policy.submit()">
                <option value=''><?php echo $this::_t(' - Choose - '); ?></option>
                <?php
        /**
         * @var string $plugin_name
         * @var PHS_Plugin $plugin_obj
         */
        foreach ($plugins_arr as $plugin_name => $plugin_obj) {
            ?>
                    <option value="<?php echo form_str($plugin_name); ?>"
                        <?php echo $plugin === $plugin_name ? 'selected="selected"' : ''; ?>
                    ><?php echo $plugin_obj ? $plugin_obj->get_plugin_display_name() : 'Core'; ?></option>
                    <?php
        }
?>
                </select>
            </div>
        </div>

        <div class="form-group row">
            <label for="model" class="col-sm-2 col-form-label"><?php echo $this->_pt('Model'); ?></label>
            <div class="col-sm-10">
                <?php
if (empty($plugin)) {
    echo $this::_t('Select a plugin first.');
} else {
    ?>
                    <select name="model" id="model" class="chosen-select" style="width:400px;"
                            onchange="document.edit_data_retention_policy.submit()">
                    <option value=""><?php echo $this::_t(' - Choose - '); ?></option>
                    <?php
    foreach ($models_arr as $model_name) {
        ?>
                        <option value="<?php echo form_str($model_name); ?>"
                            <?php echo $model === $model_name ? 'selected="selected"' : ''; ?>
                        ><?php echo $model_name; ?></option>
                        <?php
    }
    ?>
                    </select>
                    <?php
}
?>
            </div>
        </div>

        <div class="form-group row">
            <label for="table" class="col-sm-2 col-form-label"><?php echo $this->_pt('Table'); ?></label>
            <div class="col-sm-10">
                <?php
if (empty($model)) {
    echo $this::_t('Select a model first.');
} else {
    ?>
                    <select name="table" id="table" class="chosen-select" style="width:400px;"
                            onchange="document.edit_data_retention_policy.submit()">
                    <option value=""><?php echo $this::_t(' - Choose - '); ?></option>
                    <?php
    foreach ($tables_arr as $table_name) {
        ?>
                        <option value="<?php echo form_str($table_name); ?>"
                            <?php echo $table === $table_name ? 'selected="selected"' : ''; ?>
                        ><?php echo $table_name; ?></option>
                        <?php
    }
    ?>
                    </select>
                    <?php
}
?>
            </div>
        </div>

        <div class="form-group row">
            <label for="date_field" class="col-sm-2 col-form-label"><?php echo $this->_pt('Date field'); ?></label>
            <div class="col-sm-10">
                <?php
if (empty($table)) {
    echo $this::_t('Select a table first.');
} else {
    ?>
                    <select name="date_field" id="date_field" class="chosen-select" style="width:400px;">
                    <option value=""><?php echo $this::_t(' - Choose - '); ?></option>
                    <?php
    foreach ($fields_arr as $field_name) {
        ?>
                        <option value="<?php echo form_str($field_name); ?>"
                            <?php echo $date_field === $field_name ? 'selected="selected"' : ''; ?>
                        ><?php echo $field_name; ?></option>
                        <?php
    }
    ?>
                    </select>
                    <br/><small><?php echo $this::_t('This field will be checked agains interval settings.'); ?></small>
                    <?php
}
?>
            </div>
        </div>

        <div class="form-group row">
            <label for="type" class="col-sm-2 col-form-label"><?php echo $this->_pt('Retention Action'); ?></label>
            <div class="col-sm-10">
                <select name="type" id="type" class="chosen-select" style="width:400px;">
                <option value=""><?php echo $this::_t(' - Choose - '); ?></option>
                <?php
foreach ($types_arr as $type_id => $type_title) {
    ?>
                    <option value="<?php echo $type_id; ?>"
                        <?php echo $type === $type_id ? 'selected="selected"' : ''; ?>
                    ><?php echo $type_title; ?></option>
                    <?php
}
?>
                </select>
            </div>
        </div>

        <div class="form-group row">
            <label for="retention_count" class="col-sm-2 col-form-label"><?php echo $this->_pt('Retention Interval'); ?></label>
            <div class="col-sm-10">
                <input type="text" name="retention_count" id="retention_count"
                       class="form-control" style="width:150px;display: inline;"
                       placeholder="<?php echo form_str($this::_t('Interval length')); ?>"
                       value="<?php echo form_str($retention_count); ?>" />
                <select name="retention_interval" id="retention_interval" class="chosen-select">
                <option value=""><?php echo $this::_t(' - Choose - '); ?></option>
                <?php
foreach ($intervals_arr as $interval_id => $interval_title) {
    ?>
                    <option value="<?php echo $interval_id; ?>"
                        <?php echo $retention_interval === $interval_id ? 'selected="selected"' : ''; ?>
                    ><?php echo $interval_title; ?></option>
                    <?php
}
?>
                </select>
            </div>
        </div>

        <div class="form-group row">
            <input type="submit" id="do_submit" name="do_submit"
                   class="btn btn-primary submit-protection ignore_hidden_required"
                   value="<?php echo $this->_pte('Save changes'); ?>" />
        </div>

    </div>
</form>
