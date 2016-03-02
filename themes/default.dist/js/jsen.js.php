<?php

// /version 1.20

    include( '../../../main.php' );

    header( "Content-type: text/javascript" );

    use \phs\PHS;
?>
if( typeof( JSEN ) != "undefined" || !JSEN )
{
    var JSEN =
    {
        version: 1.20,

        // Base URL
        baseUrl : "<?php echo PHS::get_base_url()?>",

        default_action_13 : ".def-link",

        dialogs_prefix: "JSENd",

        dialogs_options: [],

        keys : function( o )
        {
            var keys = [];
            for( var i in o )
                if( o.hasOwnProperty( i ) )
                {
                    keys.push( i );
                }

            return keys;
        },

        in_array : function( needle, haystack )
        {
            var length = haystack.length;
            for( var i = 0; i < length; i++ )
            {
                if( haystack[i] == needle )
                    return true;
            }

            return false;
        },

        dialogErrorsDivsIds : [],
        dialogErrorsDivs : 0,
        dialogErrorsClose : function ()
        {
            for( var i = 0; i < this.dialogErrorsDivsIds.length; i++ )
            {
                container_obj = $(this.dialogErrorsDivsIds[i]);
                if( container_obj )
                    container_obj.remove();
            }
        },

        removeURLParameter: function ( url, parameter )
        {
            //prefer to use l.search if you have a location/link object
            var urlparts= url.split('?');

            if( urlparts.length < 2 )
                return url;

            var prefix = encodeURIComponent( parameter ) + '=';
            var pars = urlparts[1].split( /[&;]/g );

            //reverse iteration as may be destructive
            for (var i= pars.length; i-- > 0;) {
                //idiom for string.startsWith
                if (pars[i].lastIndexOf(prefix, 0) !== -1) {
                    pars.splice(i, 1);
                }
            }

            url= urlparts[0]+'?'+pars.join('&');

            return url;
        },

        refreshPage : function( to_url )
        {
            if( typeof to_url == "undefined" || to_url == "" )
                to_url = window.location.href;

            to_url = JSEN.removeURLParameter( to_url, '_JSENr' );

            rand_no = Math.round(((new Date()).getTime()-Date.UTC(1970,0,1))/1000);
            if( to_url.search( '\\?' ) == -1 )
                to_url = to_url + "?&_JSENr=" + rand_no;
            else
                to_url = to_url + "&_JSENr=" + rand_no;

            window.location.href = to_url;
        },

        dialogErrors : function( error_arr )
        {
            if( !error_arr || typeof error_arr != "object" || !error_arr.length )
                return;

            this.dialogErrorsClose();

            for( var i = 0; i < error_arr.length; i++ )
            {
                container_obj = false;
                appendto_obj = false;

                if( !error_arr[i].highlight_classes || typeof error_arr[i].highlight_classes != "object" )
                    error_arr[i].highlight_classes = [];

                container_name_id = '';
                container_div_id = '';
                if( !error_arr[i].container || !error_arr[i].container.length )
                {
                    if( !error_arr[i].appendto || !error_arr[i].appendto.length )
                        continue;

                    appendto_obj = $(error_arr[i].appendto);
                    if( !appendto_obj )
                        continue;

                    container_div_id = "JSENDiaErr" + this.dialogErrorsDivs;
                    container_name_id = "#JSENDiaErr" + this.dialogErrorsDivs;
                }
                else
                {
                    container_obj = $(error_arr[i].container);
                    if( !container_obj )
                        continue;

                    container_name_id = error_arr[i].container;

                    this.dialogErrorsDivsIds.push( error_arr[i].container );
                }

                if( !error_arr[i].check_visible || typeof error_arr[i].check_visible != "object" )
                {
                    error_arr[i].check_visible = [];
                    error_arr[i].check_visible.push( container_name_id );
                } else if( !this.in_array( container_name_id, error_arr[i].check_visible ) )
                    error_arr[i].check_visible.push( container_name_id );

                if( error_arr[i].highlight_field )
                {
                    if( typeof error_arr[i].highlight_field == "object" && error_arr[i].highlight_field.length )
                    {
                        var len = error_arr[i].highlight_field.length;
                        for( var ki = 0; ki < len; ki++ )
                        {
                            var highlight_id = error_arr[i].highlight_field[ki];
                            if( $("#" + highlight_id) )
                                $("#" + highlight_id).addClass( "ui-highlight-error" );
                        }
                    } else if( $("#" + error_arr[i].highlight_field) )
                    {
                        $("#" + error_arr[i].highlight_field).addClass("ui-highlight-error");
                        //console.log($("#" + error_arr[i].highlight_field));
                    }
                }

                if( appendto_obj )
                {
                    appendto_obj.append( '<div id="' + container_div_id + '"></div>' );
                    this.dialogErrorsDivs++;
                    container_obj = $(container_name_id);

                    if( !container_obj )
                        continue;

                    this.dialogErrorsDivsIds.push( container_name_id );
                }

                if( error_arr[i].check_visible && typeof error_arr[i].check_visible == "object" )
                {
                    var len = error_arr[i].check_visible.length;
                    for( var ki = 0; ki < len; ki++ )
                    {
                        container_name = error_arr[i].check_visible[ki];
                        if( container_name.substr( 0, 1 ) != '#' )
                            container_name = '#' + container_name;

                        var vis_obj = $(container_name);
                        if( !vis_obj )
                            continue;

                        if( vis_obj.css( 'display' ) != 'block' )
                            vis_obj.css( 'display', 'block' );
                    }
                }

                if( error_arr[i].highlight_classes && typeof error_arr[i].highlight_classes == "object" )
                {
                    for( var knti = 0; knti < error_arr[i].highlight_classes.length; knti++ )
                    {
                        if( !container_obj.hasClass( error_arr[i].highlight_classes[knti] ) )
                            container_obj.addClass( error_arr[i].highlight_classes[knti] );
                    }
                }

                // if( !container_obj.hasClass( "ui-state-error" ) )
                //     container_obj.addClass( "ui-state-error" );
                // if( !container_obj.hasClass( "dialog-field-error" ) )
                //     container_obj.addClass( "dialog-field-error" );

                if( error_arr[i].text_message && error_arr[i].text_message.length )
                    container_obj.text( error_arr[i].text_message );
                if( error_arr[i].html_message && error_arr[i].html_message.length )
                    container_obj.html( error_arr[i].html_message );

                container_obj.show();
            }
        },

        closeAjaxDialog : function( suffix )
        {
            if( typeof suffix == "undefined" )
                suffix = "";

            if( $("#" + JSEN.dialogs_prefix + suffix) )
            {
                var obj_options = JSEN.dialogOptions( suffix );
                if( obj_options && obj_options.onclose )
                {
                    if( jQuery.isFunction( obj_options.onclose ) )
                        obj_options.onclose();
                    else if( typeof obj_options.onclose == "string" )
                        eval( obj_options.onclose );
                }

                $("#" + JSEN.dialogs_prefix + suffix).remove();
            }
        },

        // Create AJAX request
        redirectAjaxDialog : function( o )
        {
            var defaults = {
                suffix            : "",
                cache_response    : false,
                url               : null,
                method            : "GET",
                url_data          : "",
                title             : "",
                cssclass          : ["jsenOverlay"]
            };

            var options = $.extend( defaults, o );

            if( ( typeof( options.url ) != "undefined" ) && ( options.url ) && $("#" + JSEN.dialogs_prefix + options.suffix) )
            {
                if( options.title && options.title.length )
                {
                    $("#" + JSEN.dialogs_prefix + options.suffix).dialog( "option", "title",  options.title );
                    JSEN.dialogOptions( options.suffix, 'title', options.title );
                }

                if( typeof( options.cssclass ) != "undefined" && options.cssclass )
                {
                    $("#" + JSEN.dialogs_prefix + options.suffix).dialog( "option", "dialogClass", options.cssclass );
                    JSEN.dialogOptions( options.suffix, 'cssclass', options.cssclass );
                }

                ajax_parameters_obj = {
                    type: options.method,
                    url: options.url,
                    data: options.url_data,
                    cache: options.cache_response,
                    async: true,

                    success: function( data, status, ajax_obj ) {
                        $("#" + JSEN.dialogs_prefix + options.suffix).html( data );
                    },

                    error: function( ajax_obj, status, error_exception ) {
                        $("#" + JSEN.dialogs_prefix + options.suffix).html( "<?php echo PHS::_te( 'Error' )?>" );
                    }
                };

                if( typeof( options.data_type ) == "string" )
                    ajax_parameters_obj.dataType = options.data_type;

                $.ajax( ajax_parameters_obj );
            }
        },

        // Reload HTML content of an AJAX dialog
        reloadAjaxDialog : function( o )
        {
            var defaults = {
                suffix            : "",
                cache_response    : false,
                url               : null,
                method            : "GET",
                url_data          : "",

                onfailed          : null,
                onsuccess         : null
            };

            var options = $.extend( defaults, o );

            if( !$('#'+ JSEN.dialogs_prefix + options.suffix) )
                return false;

            if( ( typeof( options.url ) != "undefined" ) && ( options.url ) )
            {
                $.ajax({
                    type: options.method,
                    url: options.url,
                    data: options.url_data,
                    cache: options.cache_response,
                    async: true,

                    success: function( html ) {
                        $("#" + JSEN.dialogs_prefix + options.suffix).html( html );

                        if( options.onsuccess )
                        {
                            if( jQuery.isFunction( options.onsuccess ) )
                                options.onsuccess();
                            else if( typeof options.onsuccess == "string" )
                                eval( options.onsuccess );
                        }
                    },

                    error: function( err ) {

                        if( options.onfailed )
                        {
                            if( jQuery.isFunction( options.onfailed ) )
                                options.onfailed();
                            else if( typeof options.onfailed == "string" )
                                eval( options.onfailed );
                        }
                    }
                });

                JSEN.dialogOptions( options.suffix, 'url', options.url );
            }

            JSEN.modifyAjaxDialog( o );

            return true;
        },

        // Alter an AJAX dialog
        modifyAjaxDialog : function( o )
        {
            var defaults = {
                width             : 0,
                height            : 0,
                suffix            : '',
                opacity           : 0.9,
                title             : "",
                draggable         : true,
                resizable         : false,
            };

            var options = [];
            var options_to_change = false;

            for( key in o )
            {
                if( defaults.hasOwnProperty( key ) )
                {
                    options[key] = o[key];
                    options_to_change = true;
                }
            }

            if( !options.hasOwnProperty( 'suffix' ) )
                options['suffix'] = '';

            if( options_to_change && $("#" + JSEN.dialogs_prefix + options.suffix) )
            {
                for( key in options )
                {
                    if( key == 'suffix' )
                        continue;

                    $('#' + JSEN.dialogs_prefix + options.suffix).dialog( 'option', key, options[key] );
                    JSEN.dialogOptions( options.suffix, key, options[key] );
                }
            }
        },

        // Create AJAX request
        createAjaxDialog : function( o )
        {
            var defaults = {
                width             : 600,
                height            : 400,
                suffix            : "",
                cache_response    : false,
                stack             : true,

                // Source of dialog content
                url               : null,
                method            : "GET",
                url_data          : "",
                // Content from where html() will be used
                source_obj        : null,

                draggable         : true,
                resizable         : false,
                title             : "",
                parent_tag        : "body",
                autoshow          : false,
                opacity           : 0.9,
                cssclass          : ["jsenOverlay"],
                close_outside_click : true,
                dialog_show         : "",

                onclose           : null,
                onbeforeclose     : null,
                onsuccess         : null
            };

            var options = $.extend( defaults, o );

            // Remove Dialog ( if previously created )
            if( $("#" + JSEN.dialogs_prefix + options.suffix) )
            {
                $("#" + JSEN.dialogs_prefix + options.suffix).remove();
            }

            // Create Dialog
            if( typeof $(options.parent_tag) == "undefined" )
                options.parent_tag = "body";

            $(options.parent_tag).append( '<div id="' + JSEN.dialogs_prefix + options.suffix + '"></div>' );

            if( $("#" + JSEN.dialogs_prefix + options.suffix) )
            {
                $("#" + JSEN.dialogs_prefix + options.suffix).dialog( {
                        width: options.width,
                        height: options.height,
                        draggable: options.draggable,
                        dialogClass: options.cssclass,
                        stack: options.stack,
                        title: options.title,
                        overlay: { opacity: 0.9, background: "#000" },

                        modal: true,
                        minHeight: 300,
                        autoOpen: false,
                        position: { my: "center", at: "center", of: window },
                        resizable: options.resizable,

                        beforeClose: function(event,ui) {
                            if( options.onbeforeclose )
                            {
                                if( jQuery.isFunction( options.onbeforeclose ) )
                                    options.onbeforeclose();
                                else if( typeof options.onbeforeclose == "string" )
                                    eval( options.onbeforeclose );
                            }
                        },

                        open: function(event, ui) {
                            $('.ui-widget-overlay').css('opacity', options.opacity);
                        }
                });

                // Check if we should call an url
                if( typeof( options.url ) != "undefined" && options.url )
                {
                    $.ajax( {
                        type: options.method,
                        url: options.url,
                        data: options.url_data,
                        cache: options.cache_response,
                        // async: true,

                        success: function( data, textStatus, jqXHR )
                        {
                            var diag_container = $( "#" + JSEN.dialogs_prefix + options.suffix );
                            if( diag_container )
                            {
                                diag_container.html( data );
                                diag_container.dialog( "open" );
                            }

                            if( options.onsuccess )
                            {
                                if( jQuery.isFunction( options.onsuccess ) )
                                    options.onsuccess();
                                else if( typeof options.onsuccess == "string" )
                                    eval( options.onsuccess );
                            }
                        },

                        error: function( err )
                        {
                            $( "#" + JSEN.dialogs_prefix + options.suffix ).html( "<?php echo PHS::_te( 'Error' )?>" );
                            $( "#" + JSEN.dialogs_prefix + options.suffix ).dialog( "open" );
                        }
                    } );
                } else

                // Check if we have an object to extract html() from
                if( typeof( options.source_obj ) != "undefined" && options.source_obj )
                {
                    var source_container = null;
                    if( typeof( options.source_obj ) == "string" )
                        source_container = $('#'+options.source_obj);
                    else
                        source_container = options.source_obj;

                    $( "#" + JSEN.dialogs_prefix + options.suffix ).html( source_container.html() );
                    $( "#" + JSEN.dialogs_prefix + options.suffix ).dialog( "open" );

                    if( options.onsuccess )
                    {
                        if( jQuery.isFunction( options.onsuccess ) )
                            options.onsuccess();
                        else if( typeof options.onsuccess == "string" )
                            eval( options.onsuccess );
                    }
                }

                if( options.close_outside_click )
                {
                    $(document).on('click', '.ui-widget-overlay', function() {
                        if( $("#" + JSEN.dialogs_prefix + options.suffix) )
                            $("#" + JSEN.dialogs_prefix + options.suffix).dialog("close");
                    });

                }

                $("#" + JSEN.dialogs_prefix + options.suffix).bind('dialogclose', function(event) {
                    if( options.onclose )
                    {
                        if( jQuery.isFunction( options.onclose ) )
                            options.onclose();
                        else if( typeof options.onclose == "string" )
                            eval( options.onclose );
                    }
                });

                JSEN.dialogOptions( options.suffix, options );
            }

            if( window.innerHeight < options.height )
            {
                setTimeout(function() {
                    $( "#" + JSEN.dialogs_prefix + options.suffix ).parent().css( "top", "30px" );
                }, 500 );
            }
        },

        // Close Loading div...
        dialogOptions : function( suffix, key, val )
        {
            if( typeof key == 'undefined' && typeof val == 'undefined' )
                return JSEN.dialogs_options[suffix];

            if( typeof key == 'string' && typeof val == 'undefined' )
            {
                if( typeof JSEN.dialogs_options[suffix] != 'undefined' && typeof JSEN.dialogs_options[suffix][key] != 'undefined' )
                    return JSEN.dialogs_options[suffix][key];
                else
                    return null;
            }

            if( typeof key == 'object' )
            {
                if( typeof JSEN.dialogs_options[suffix] != 'undefined' )
                    JSEN.dialogs_options[suffix] = $.extend( JSEN.dialogs_options[suffix], key );
                else
                    JSEN.dialogs_options[suffix] = key;

                return JSEN.dialogs_options[suffix];
            }

            if( typeof key == 'string' && typeof val != 'undefined'
             && typeof JSEN.dialogs_options[suffix] != 'undefined' )
            {
                JSEN.dialogs_options[suffix][key] = val;
                return true;
            }

            return false;
        },

        // Close Loading div...
        closeLoadingDialog : function( suffix )
        {
            // Remove Dialog ( if previously created )
            if( $("#jsen_loading" + suffix) ) { $("#jsen_loading" + suffix).remove(); };
        },

        // Create Loading div...
        createLoadingDialog : function( o )
        {
            var options = $.extend( {
                width       : 320,
                height      : 100,
                suffix      : "",
                message     : "",
                stack       : true,
                draggable   : true,
                close_on_escape : false,
                title       : "<?php echo PHS::_te( 'Please wait...' )?>",
                parent_tag  : "body",
                cssclass    : "ui-dialog-no-close ui-dialog-loading"
            }, o );

            // Remove Dialog ( if previously created )
            if( $("#jsen_loading" + options.suffix) ) { $("#jsen_loading" + options.suffix).remove(); };

            // Create Dialog
            if( typeof $(options.parent_tag) == "undefined" )
                options.parent_tag = "body";

            $(options.parent_tag).append( '<div id="jsen_loading' + options.suffix + '"></div>' );

            if( $("#jsen_loading" + options.suffix) )
            {
                $("#jsen_loading" + options.suffix).dialog( {
                        width: options.width,
                        height: options.height,
                        draggable: options.draggable,
                        dialogClass: options.cssclass,
                        stack: options.stack,
                        title: options.title,
                        closeOnEscape: options.close_on_escape,
                        appendTo: options.parent_tag,

                        modal: true,
                        minHeight: 300,
                        overlay: { opacity: 0.4, background: "#000" },
                        show: "",
                        autoOpen: false,
                        position: { my: "center", at: "center", of: $(options.parent_tag) },
                        resizable: false
                       } );

                $("#jsen_loading" + options.suffix).html( options.message + '<div id="loading-animation-pb' + options.suffix + '"></div>' );

                $( "#loading-animation-pb" + options.suffix ).progressbar({ value: false });

                $("#jsen_loading" + options.suffix).dialog( "open" );
            };
        },

        keyPressHandler : function()
        {
            var r = $(JSEN.default_action_13);
            if( !r || !r.length )
                return;

            r.each( function()
            {
                var a = $(this);
                while( a && a.length && a.attr( "tagName" ) != "FORM" )
                {
                    a = a.parent()
                };

                if( a && a.length )
                    a.find("input").keypress( function(e){ if( ( e.which && e.which == 13 ) || (e.keyCode && e.keyCode == 13) ) { a.find(JSEN.default_action_13).click(); return false } else { return true } } );

            });
        },

        logf : function( str )
        {
            if( console )
                console.log( str );
        }

    };
};

