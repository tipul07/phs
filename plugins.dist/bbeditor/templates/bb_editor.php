<?php

use \phs\PHS;
use \phs\PHS_Ajax;

    /** @var \phs\system\core\views\PHS_View $this */

    /** @var \phs\plugins\bbeditor\libraries\Bbcode $bb_code_obj */
    if( !($bb_code_obj = $this->view_var( 'bb_code_obj' ))
     or !($theme_arr = $bb_code_obj->get_current_theme()) )
        return $this->_pt( 'Couldn\'t initialize BB code editor.' );

    if( !($bb_editor_attributes = $this->view_var( 'bb_editor_attributes' )) )
        $bb_editor_attributes = array();
?>
<style>
.phs_bb_editor { width: 100%; }
.phs_bb_editor_controls { width: 100%; min-height: 38px; border-top: 1px dotted #ccc; border-left: 1px dotted #ccc; border-right: 1px dotted #ccc; padding: 3px; }
.phs_bb_editor textarea { width: 100% !important; border-radius: 0; }
.phs_editor_control_separator, .phs_editor_control_command { vertical-align: middle; text-align: center; float: left; height: 32px; line-height: 32px; margin: 0 5px; }
.phs_editor_control_separator { width: 5px; border-right: 1px dotted gray; }
.phs_editor_control_command { cursor: pointer; border: 1px solid black; border-radius: 4px; width: 32px; }
.phs_editor_control_err { color: red; }
</style>
<div class="phs_bb_editor">
<?php
    $phs_bb_editor_templates = array();
    if( !empty( $theme_arr ) and is_array( $theme_arr ) )
    {
        ?><div class="phs_bb_editor_controls"><?php
        // render buttons
        foreach( $theme_arr as $shortcode_code )
        {
            if( $shortcode_code == $bb_code_obj::THEME_CONTROL_SEP )
            {
                ?><div class="phs_editor_control_separator"></div><?php
                continue;
            }

            if( !($shortcode_arr = $bb_code_obj->valid_shortcode( $shortcode_code )) )
            {
                ?><div class="phs_editor_control_command phs_editor_control_err" title="<?php echo $this->_pt( 'Shortcode [%s] not defined', $shortcode_code )?>"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i></div><?php
                continue;
            }

            if( !empty( $shortcode_arr['editor_button_callback'] ) )
            {
                if( empty( $shortcode_arr['editor_button_callback_params'] ) )
                    $shortcode_arr['editor_button_callback_params'] = array();

                $callback_params = $shortcode_arr['editor_button_callback_params'];

                $callback_params['shortcode_definition'] = $shortcode_arr;
                $callback_params['editor_obj'] = $bb_code_obj;

                if( !is_callable( $shortcode_arr['editor_button_callback'] )
                 or !($shortcode_details = @call_user_func( $shortcode_arr['editor_button_callback'], $callback_params ))
                 or !is_array( $shortcode_details ) )
                {
                    ?><div class="phs_editor_control_command phs_editor_control_err" title="<?php echo $this->_pt( 'Invalid callback for shortcode [%s]', $shortcode_code )?>"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i></div><?php
                    continue;
                }

                if( !empty( $shortcode_details['editor_button'] ) )
                    echo $shortcode_details['editor_button'];

                if( !empty( $shortcode_details['templates'] ) and is_array( $shortcode_details['templates'] ) )
                {
                    foreach( $shortcode_details['templates'] as $template_code => $template_str )
                        $phs_bb_editor_templates[$template_code] = $template_str;
                }

                continue;
            }

            if( !empty( $shortcode_arr['template'] ) )
                $phs_bb_editor_templates[$shortcode_code] = $shortcode_arr['template'];

            if( !empty( $shortcode_arr['js_click_function'] ) )
                $js_click_function = $bb_code_obj->bb_editor_replace_vars( $shortcode_arr['js_click_function'] );
            else
                $js_click_function = 'phs_bb_editor.insert_text( \''.$bb_editor_attributes['id'].'\', \''.$shortcode_code.'\' )';

            ?><div class="phs_editor_control_command" title="<?php echo $shortcode_arr['title']?>" onclick="<?php echo $js_click_function?>"><?php echo $shortcode_arr['editor_button']?></div><?php
        }
        ?></div><?php
    }
?>
<textarea id="<?php echo $bb_editor_attributes['id']?>" name="<?php echo $bb_editor_attributes['name']?>" class="<?php echo $bb_editor_attributes['class']?>" placeholder="<?php echo form_str( $bb_editor_attributes['placeholder'] )?>" style="<?php echo $bb_editor_attributes['style']?>"><?php echo textarea_str( $this->view_var( 'bb_text' ) )?></textarea>
</div>
<div class="clearfix"></div>
<script type="text/javascript">
var phs_bb_editor_settings = phs_bb_editor_settings || {};

$(document).ready(function(){
    phs_bb_editor_settings['<?php echo $bb_editor_attributes['id']?>'] = {
        input_obj: $('#<?php echo $bb_editor_attributes['id']?>'),
        templates: <?php echo @json_encode( $phs_bb_editor_templates )?>
    };
});

var phs_bb_editor = phs_bb_editor || {

    insert_text: function( editor_id, code )
    {
        var editor_obj = document.getElementById( editor_id );
        if( !editor_obj
         || typeof phs_bb_editor_settings[editor_id] == "undefined"
         || typeof phs_bb_editor_settings[editor_id]['templates'][code] == "undefined" )
            return;

        var template_str = phs_bb_editor_settings[editor_id]['templates'][code];
        var prefix_str = template_str;
        var suffix_str = '';

        var pointer_str = "{CONTENT}";
        var pointer_len = pointer_str.length;
        var pointer_pos = template_str.indexOf( pointer_str );

        if( pointer_pos != -1 )
        {
            prefix_str = template_str.substring( 0, pointer_pos );
            suffix_str = template_str.substring( pointer_pos + pointer_len, template_str.length );
        }

        if( !editor_obj.setSelectionRange )
        {
            var selected = document.selection.createRange().text;
            if( selected.length <= 0 )
            {
                // no text was selected
                editor_obj.value += prefix_str + suffix_str;
            } else
            {
                // put the code around the selected text
                document.selection.createRange().text = prefix_str + selected + suffix_str;
            }

        } else
        {
            // the text before the selection
            var pretext = editor_obj.value.substring( 0, editor_obj.selectionStart );

            // the selected text with tags before and after
            var codetext = prefix_str + editor_obj.value.substring( editor_obj.selectionStart, editor_obj.selectionEnd ) + suffix_str;

            // the text after the selection
            var posttext = editor_obj.value.substring( editor_obj.selectionEnd, editor_obj.value.length );

            // update the text field
            editor_obj.value = pretext + codetext + posttext;
        }
    },

    do_preview: function( editor_id )
    {
        PHS_JSEN.createAjaxDialog( {
            width: 1000,
            height: 800,
            suffix: "phs_bbeditor_document",
            resizable: true,

            title: "<?php echo self::_e( $this->_pt( 'Document preview' ) )?>",
            method: "POST",
            url: "<?php echo PHS_Ajax::url( array( 'p' => 'bbeditor', 'a' => 'bb_editor_preview' ) )?>",
            url_data: { body: document.getElementById( editor_id ).value }
        });
    }
};
</script>
