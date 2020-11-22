<?php

    @header( 'Content-type: text/javascript' );

    define( 'PHS_PREVENT_SESSION', true );
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

function show_submit_protection( msg, extr_msg )
{
    var $protection_message_obj = jQuery("#main_submit_protection_message");
    if( $protection_message_obj )
    {
        if( typeof msg == 'undefined' || !msg )
            msg = '<?php echo PHS_Language::_te( 'Please wait...', '\'' )?>';

        $protection_message_obj.html( msg );
    }

    if( typeof extr_msg !== 'undefined' && extr_msg )
    {
        var $protection_extra_msg_obj = jQuery("#main_submit_protection_loading_content");
        if( $protection_extra_msg_obj )
        {
            if( jQuery('#main_submit_protection_extr_msg').length === 0 )
            {
                var $extra_msg_obj = jQuery('<div id="main_submit_protection_extr_msg"><div>' + extr_msg + '</div></div>');
                $protection_extra_msg_obj.append( $extra_msg_obj );
            } else
            {
                jQuery('#main_submit_protection_extr_msg').html( '<div>' + extr_msg + '</div>');
            }
        }
    }

    var $protection_container_obj = jQuery("#main_submit_protection");
    if( $protection_container_obj )
    {
        $protection_container_obj.appendTo('body');
        $protection_container_obj.show();
        //$protection_container_obj.css({height: document.getElementsByTagName('html')[0].scrollHeight}); //this is done by CSS
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

(function ($) {
    //scroll page to an element
    $.fn.goTo = function () {
        if (this.length > 0) {
            $('html, body').animate({
                scrollTop: ($(this).offset().top - (window.outerHeight / 3)) + 'px'
            }, 'fast');
        }
        return this; // for chaining...
        };
})(jQuery);
