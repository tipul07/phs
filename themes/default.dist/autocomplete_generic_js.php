<?php
/** @var \phs\system\core\views\PHS_View $this */
if (!($include_js_script_tags = $this->view_var('include_js_script_tags'))) {
    $include_js_script_tags = false;
}

if ($include_js_script_tags) {
    ?><script type="text/javascript">
<?php
}
?>
function phs_autocomplete_input_lock( text_id )
{
    let show_all_input = $("#" + text_id + "_prepend_icons");
    let reset_input = $("#" + text_id + "_append_icons");

    $("#" + text_id).prop( "readonly", true );

    if( show_all_input.length > 0 )
        show_all_input.hide();
    if( reset_input.length > 0 )
        reset_input.show();
}

function phs_autocomplete_trigger_search( text_id, term ) {
    let search_all = false;

    if( typeof term === "boolean"
     && term === true ) {
        term = "";
        search_all = true;
    }

    let input_obj = $("#" + text_id);
    if( input_obj.length > 0 ) {
        if( input_obj.val().length > 0 )
            term = input_obj.val();

        input_obj.autocomplete( "search", (search_all?"":term) );
    }
}

function phs_autocomplete_input_reset( id_id, text_id, default_id_val = "" )
{
    let show_all_input = $("#" + text_id + "_prepend_icons");
    let reset_input = $("#" + text_id + "_append_icons");

    $("#" + id_id).val( default_id_val );
    $("#" + text_id).val( "" ).removeProp( "readonly" );

    if( show_all_input.length > 0 )
        show_all_input.show();
    if( reset_input.length > 0 )
        reset_input.hide();
}
<?php
if ($include_js_script_tags) {
    ?></script>
<?php
}
