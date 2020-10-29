<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\libraries\PHS_Language;
?>
<script type="text/javascript">
function phs_refresh_input_skins()
{
    $('input:checkbox[rel="skin_chck_big"]').checkbox({cls:'jqcheckbox-big', empty:'<?php echo $this->get_resource_url( 'images/empty.png' )?>'});
    $('input:checkbox[rel="skin_chck_small"]').checkbox({cls:'jqcheckbox-small', empty:'<?php echo $this->get_resource_url( 'images/empty.png' )?>'});
    $('input:checkbox[rel="skin_checkbox"]').checkbox({cls:'jqcheckbox-checkbox', empty:'<?php echo $this->get_resource_url( 'images/empty.png' )?>'});
    $('input:radio[rel="skin_radio"]').checkbox({cls:'jqcheckbox-radio', empty:'<?php echo $this->get_resource_url( 'images/empty.png' )?>'});

    $(".chosen-select").chosen( { disable_search_threshold: 7, search_contains: true } );
    $(".chosen-select-nosearch").chosen( { disable_search: true } );
    $(".ui-button").button();
    $("*[title]").not(".no-title-skinning").tooltip();
}

function phs_refresh_dismissible_functionality()
{
    $('.dismissible').before( '<i class="fa fa-times-circle dismissible-close"></i>' );
    $('.dismissible-close').on( 'click', function( event ){
        $(this).parent().slideUp();
        $(this).parent().find(".dismissible").html("");
    });
}

function ignore_hidden_required( obj )
{
    var form_obj = $(obj).parents('form:first');

    if( form_obj && form_obj[0]
     && typeof document.createElement( 'input' ).checkValidity == 'function'
     && form_obj[0].checkValidity() ) {
        return;
    }

    form_obj.find( 'input,textarea,select' ).filter('[required]:hidden').removeAttr('required');
}

$(document).ready(function(){

    phs_refresh_input_skins();

    $.datepicker.setDefaults( $.datepicker.regional["<?php echo PHS_Language::get_current_language()?>"] );

    phs_refresh_dismissible_functionality();

    $(document).on( 'click', '.submit-protection', function( event ){

        var form_obj = $(this).parents('form:first');

        if( form_obj && form_obj[0]
         && typeof document.createElement( 'input' ).checkValidity == 'function'
         && !form_obj[0].checkValidity() ) {
            return;
        }

        var msg = $( this ).data( 'protectionTitle' );
        if( typeof msg == 'undefined' || !msg )
            msg = '';

        show_submit_protection( msg );
    });

    $(document).on('click', '.ignore_hidden_required', function(){

        var form_obj = $(this).parents('form:first');

        if( form_obj && form_obj[0]
         && typeof document.createElement( 'input' ).checkValidity == 'function'
         && form_obj[0].checkValidity() ) {
            return;
        }

        form_obj.find( 'input,textarea,select' ).filter('[required]:hidden').removeAttr('required');
    });
});
</script>
