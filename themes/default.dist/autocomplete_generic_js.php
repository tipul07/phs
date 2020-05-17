<?php
    /** @var \phs\system\core\views\PHS_View $this */

    if( !($include_js_script_tags = $this->view_var( 'include_js_script_tags' )) )
        $include_js_script_tags = false;

if( $include_js_script_tags )
{
?><script type="text/javascript">
<?php
}
?>
function phs_autocomplete_input_lock( text_id )
{
    $("#" + text_id).prop( "readonly", "readonly" );
}

function phs_autocomplete_input_reset( id_id, text_id )
{
    $("#" + id_id).val( "" );
    $("#" + text_id).val( "" ).removeProp( "readonly" );
}
<?php
if( $include_js_script_tags )
{
    ?></script>
<?php
}

