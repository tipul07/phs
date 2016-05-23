<?php

    @header( 'Content-type: text/javascript' );

    include( '../../../main.php' );

    use \phs\PHS;
    use \phs\libraries\PHS_Language;

?>
function dialog_from_container( container, o )
{
    if( typeof container == 'undefined' )
        container = '';
    if( typeof o == 'undefined' )
        o = {};
    
    if( PHS_JSEN )
    {
        var dialog_options = { suffix: 'd_container', source_obj: container, url: null,
            width: 800, height: 600, close_outside_click: false, resizable:true, title: '...' };
            
            var options = $.extend( dialog_options, o );

        PHS_JSEN.createAjaxDialog( options );
    } else
        alert( '<?php echo PHS_Language::_te( 'Error initializing PHS JS Engine', '\'' );?>' );
}

function hide_submit_protection()
{
    var protection_container_obj = jQuery( "#main_submit_protection" );
    if( protection_container_obj )
    {
        protection_container_obj.hide();
    }
}

function show_submit_protection( msg )
{
    var protection_container_obj = jQuery("#main_submit_protection");
    if( protection_container_obj )
    {
        protection_container_obj.appendTo('body');
        protection_container_obj.show();
        protection_container_obj.css({height: document.getElementsByTagName('html')[0].scrollHeight});
    }
    var protection_message_obj = jQuery("#main_submit_protection_message");
    if( protection_message_obj )
    {
        if( typeof msg == 'undefined' || !msg )
            msg = '<?php echo PHS_Language::_te( 'Please wait...', '\'' )?>';

        protection_message_obj.html( msg );
    }
}

function close_dialog( suffix )
{
    if( PHS_JSEN )
    {
        if( typeof suffix == "undefined" )
            suffix = "";

        PHS_JSEN.closeAjaxDialog( suffix );
    }
}

function dialog_loading( options )
{
    if( PHS_JSEN )
    {
        var o = $.extend( { suffix: '', title: '<?php echo PHS_Language::_te( 'Please wait...', '\'' )?>', parent_tag: 'body',
            message: '<?php echo PHS_Language::_te( 'Loading', '\'' )?>', width: 320, height: 130 }, options );

        PHS_JSEN.createLoadingDialog( o );
    }
}

function close_loading( suffix )
{
    if( PHS_JSEN )
    {
        PHS_JSEN.closeLoadingDialog( suffix );
    }
}

function div_obj_toggle( div_name )
{
   if( div_name == '' )
       return;

   if( $("#"+div_name) )
   {
       $("#"+div_name).slideToggle("slow");
   }
}

function div_obj_show( div_name )
{
   if( div_name == '' )
       return;

   if( $("#"+div_name) )
   {
       $("#"+div_name).slideDown("slow");
   }
}

function div_obj_hide( div_name )
{
   if( div_name == '' )
       return;

   if( $("#"+div_name) )
   {
       $("#"+div_name).slideUp("slow");
   }
}

function clear_date_field( field )
{
    var field_obj = document.getElementById( field );

    if( field_obj )
    {
        field_obj.value = '';
    }
}
