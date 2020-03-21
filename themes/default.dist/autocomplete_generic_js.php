<?php
    /** @var \phs\system\core\views\PHS_View $this */

?>
<script type="text/javascript">
function phs_autocomplete_input_lock( text_id )
{
    $("#" + text_id).prop( "readonly", "readonly" );
}

function phs_autocomplete_input_reset( id_id, text_id )
{
    $("#" + id_id).val( "" );
    $("#" + text_id).val( "" ).removeProp( "readonly" );
}
</script>
