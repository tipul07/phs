<?php
/** @var phs\system\core\views\PHS_View $this */
$headers_definition = $this->view_var('headers_definition') ?: [];
$current_settings = $this->view_var('current_settings') ?: [];

?>
<table style="width:100%" class="table">
<thead class="thead-light">
<tr>
    <th scope="col" class="text-center font-weight-bold"><?php echo $this->_pt('Enabled?'); ?></th>
    <th scope="col" class="text-center font-weight-bold"><?php echo $this->_pt('Security Header'); ?></th>
    <th scope="col" class="text-center font-weight-bold"><?php echo $this->_pt('Value'); ?></th>
</tr>
</thead>
<tbody>
<?php
foreach ($headers_definition as $h_id => $h_arr) {
    if (empty($h_arr['header'])) {
        continue;
    }

    $h_value = $current_settings['headers_values'][$h_id] ?? '';
    $default_value = $h_arr['default'] ?? null;

    ?>
    <tr>
        <td class="text-center" <?php echo $default_value !== null ? 'rowspan="2"' : ''; ?>><input type="checkbox" value="1" rel="skin_checkbox"
                   id="headers_selected_<?php echo $h_id; ?>"
                   name="headers_selected[<?php echo $h_id; ?>]"
                   <?php echo !empty($current_settings['headers_selected'][$h_id]) ? 'checked="checked"' : ''; ?>
            /></td>
        <td class="font-weight-bold">
            <label for="headers_values_<?php echo $h_id; ?>"><?php echo $h_arr['header']; ?></label>
            <?php
            if (!empty($h_arr['details_url'])) {
                ?>
                <a href="<?php echo $h_arr['details_url']; ?>" onclick="this.blur()"
                   target="_blank"><span class="action-icons fa fa-question-circle"></span></a>
                <?php
            }
    ?>
        </td>
        <td><?php
        if ( !empty($h_arr['values_arr']) && is_array($h_arr['values_arr'])) {
            ?>
            <select class="form-control"
                    id="headers_values_<?php echo $h_id; ?>" name="headers_values[<?php echo $h_id; ?>]"><?php
                foreach ($h_arr['values_arr'] as $value) {
                    ?><option value="<?php echo form_str($value); ?>"
                    <?php echo $h_value === $value ? ' selected="selected"' : ''; ?>><?php echo $value; ?></option><?php
                }
            ?></select>
            <?php
        } else {
            ?><input type="text" class="form-control"
                       id="headers_values_<?php echo $h_id; ?>" name="headers_values[<?php echo $h_id; ?>]"
                       value="<?php echo form_str($h_value); ?>"
                /><?php
        }

    if (!empty($h_arr['hint'])) {
        ?><small class="form-text text-muted"><?php echo $h_arr['hint']; ?></small><?php
    }
    ?></td>
    </tr>
    <?php
    if ($default_value !== null) {
        ?>
        <tr>
            <td class="font-italic" colspan="2"><?php echo $this->_pt('Default value:').' '.($default_value ?: '['.$this->_pt('No default value').']'); ?></td>
        </tr>
        <?php
    }
}
?>
</tbody>
</table>
