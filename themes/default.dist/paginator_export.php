<?php

use phs\libraries\PHS_Paginator;
use phs\system\core\views\PHS_View;

/** @var PHS_View $this */
/** @var PHS_Paginator $paginator_obj */
if (!($paginator_obj = $this->view_var('paginator'))) {
    return '';
}

$export_selected_action = $paginator_obj->get_export_selection_bulk_action() ?: [];
$export_all_action = $paginator_obj->get_export_all_bulk_action() ?: [];
$flow_params_arr = $paginator_obj->flow_params() ?: $paginator_obj->default_flow_params();
$listing_form_name = $paginator_obj->get_listing_form_name() ?: $flow_params_arr['form_prefix'].'paginator_list_form';
?>
<div id="PHS_RActive_Paginator_export_action_target" style="display:none"></div>
<script id="PHS_RActive_Paginator_export_action_template" type="text/html">
<p><?php
$all_str = $this->_pt('ALL');
echo $this->_pt('Exporting %s records...', '{{ export_count === -1 ? \''.$all_str.'\' : export_count }}'); ?></p>

<div class="row form-group">
    <label for="export_format" class="col-sm-4 col-form-label"><?php echo $this->_pt('Export format'); ?></label>
    <div class="col-sm-8"><select name="export_format" id="export_format" value="{{ export_format }}" class="form-control">
            {{#each @this.get_valid_formats()}}<option value="{{.format}}">{{.title}}</option>{{/each}}
        </select></div>
</div>

{{#if export_format === 'csv'}}
<div class="row form-group">
    <label for="column_delimiter" class="col-sm-4 col-form-label"><?php echo $this->_pt('Column delimiter'); ?></label>
    <div class="col-sm-8"><select name="column_delimiter" id="column_delimiter" value="{{ column_delimiter }}" class="form-control">
            <option value=",">Comma (,)</option>
            <option value=";">Semicolon  (;)</option>
        </select></div>
</div>
{{/if}}

<div class="row form-group">
    <div class="col-sm-1"><input id="export_in_background" name="export_in_background" type="checkbox" checked="{{ export_in_background }}" /></div>
    <label for="export_in_background" class="col-sm-11 col-form-label"><?php echo $this->_pt('Run export action in background script.'); ?></label>
</div>

<div class="row form-group">
    <input type="button" id="do_submit_export" name="do_submit_export" class="btn btn-primary ignore_hidden_required"
           on-click="@this.do_submit_export()"
           value="<?php echo $this->_pte('Start export'); ?>" />
    <input type="button" id="do_cancel" name="do_cancel" class="btn btn-danger ignore_hidden_required"
           on-click="@this.cancel_action()"
           value="<?php echo $this->_pte('Cancel'); ?>" />
</div>

</script>
<script type="text/javascript">
let PHS_RActive_Paginator_export_action_app = null;
$(document).ready(function() {
    PHS_RActive_Paginator_export_action_app = PHS_RActive_Paginator_export_action_app || new PHS_RActive({

        target: "PHS_RActive_Paginator_export_action_target",
        template: "#PHS_RActive_Paginator_export_action_template",

        data: function () {
            return {
                active: false,
                export_count: 0,
                export_action: '',
                export_format: 'csv',
                column_delimiter: ',',
                export_in_background: false,
            }
        },

        get_valid_formats: function () {
            return [
                { format: 'csv', title: 'Comma Separated Values (CSV)' },
            ];
        },

        reset_export_data: function () {
            this.set({
                export_count: 0,
                export_action: '',
                export_in_background: false,
            });
        },

        do_submit_export: function() {
            <?php
        $submit_url = $paginator_obj->get_full_url([
            'action' => '" + this.get(\'export_action\') + "',
        ]);
?>let submit_url = "<?php echo $submit_url; ?>";

            let form_obj = $("#<?php echo $listing_form_name; ?>");
            if(!form_obj) {
                return;
            }

            const in_bg = this.get('export_in_background') ? 1 : 0;

            if(in_bg) {
                show_submit_protection("<?php echo $this->_pte('Please wait...'); ?>");
            } else {
                this.phs_add_success_message("<?php echo $this->_pte('Sending request to server. Please wait...'); ?>");
            }

            form_obj[0].action = PHS_JSEN.addURLParametersFromObject( submit_url, {
                export_format: this.get('export_format'),
                column_delimiter: this.get('column_delimiter'),
                in_bg: in_bg,
            });

            form_obj.submit()

            phs_paginator_export_cancel_modal();

            form_obj[0].action = submit_url;
        },

        cancel_action: function() {
            phs_paginator_export_cancel_modal();
        }
    });
});
function phs_paginator_export_selected_callback() {
    <?php
    if (!empty($export_selected_action['action'])
       && !empty($export_selected_action['checkbox_column'])) {
        ?>let export_count = phs_paginator_get_checkboxes_checked_count('<?php echo $export_selected_action['checkbox_column']; ?>');
    if(!export_count) {
        alert( "<?php echo $this->_pte('Please select recods you want to export first.', '"'); ?>" );
        return false;
    }

    phs_paginator_export_create_modal();

    PHS_RActive_Paginator_export_action_app.set({
        'active': true,
        'export_count': export_count,
        'export_action': '<?php echo $export_selected_action['action']; ?>',
    });
    <?php
    }
?>
    return false;
}
function phs_paginator_export_all_callback() {
    <?php
if (!empty($export_all_action['action'])) {
    ?>phs_paginator_export_create_modal();

    PHS_RActive_Paginator_export_action_app.set({
        'active': true,
        'export_count': -1,
        'export_action': '<?php echo $export_all_action['action']; ?>',
    });
    <?php
}
?>
    return false;
}
function phs_paginator_export_create_modal() {
    PHS_JSEN.createAjaxDialog( {
        suffix: "phs_paginator_export_modal_",
        width: 600,
        height: 300,
        title: "<?php echo $this->_pte('Export options'); ?>",
        resizable: false,
        close_outside_click: false,
        source_obj: "PHS_RActive_Paginator_export_action_target",
        source_not_cloned: true,
        onsuccess: () => {
            $('#PHS_RActive_Paginator_export_action_target').show();
        },
        onclose: () => {
            phs_paginator_export_cancel_modal();
        }
    });
}
function phs_paginator_export_cancel_modal() {
    PHS_JSEN.closeAjaxDialog( 'phs_paginator_export_modal_' );
    $('#PHS_RActive_Paginator_export_action_target').hide();
    PHS_RActive_Paginator_export_action_app.set({'active': false});
}
</script>
