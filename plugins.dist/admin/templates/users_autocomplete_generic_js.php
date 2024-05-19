<?php
/** @var phs\system\core\views\PHS_View $this */
?>
<script type="text/javascript">
function phs_lock_accounts_autocomplete( text_id )
{
    $("#" + text_id).prop( "readonly", "readonly" );
}

function phs_reset_accounts_autocomplete( id_id, text_id )
{
    $("#" + id_id).val( 0 );
    $("#" + text_id).val( "" ).removeProp( "readonly" );
}
</script>
