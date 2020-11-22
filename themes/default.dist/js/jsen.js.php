<?php

    define( 'PHS_PREVENT_SESSION', true );
    include( '../../../main.php' );

    @header( 'Content-type: text/javascript' );

    use \phs\PHS;
    use \phs\PHS_Ajax;
    use \phs\libraries\PHS_Language;
?>
if( typeof( PHS_JSEN ) != "undefined" || !PHS_JSEN )
{
    if( typeof $ === "undefined" )
    {
        if( typeof jQuery !== "undefined" )
            $ = jQuery;
    }

    if( typeof $ === "undefined" )
    {
        if( console )
            console.log( "Seems like we don't have jQuery..." );
    }

    var PHS_JSEN = {
        debugging_mode: <?php echo (PHS::st_debugging_mode()?'true':'false')?>,

        version: 1.4,

        // Base URL
        baseUrl : "<?php echo PHS::get_base_url()?>",

        default_action_13 : ".def-link",

        dialogs_prefix: "PHS_JSENd",

        dialogs_options: [],

        // Hash AJAX requests by route and parameters and allow a single request per hash
        // Having a response from first request, it will be served to all other callbacks in the queue
        ajax_queue: {},
        // Allow only a number of simultaneous AJAX requests
        // the rest will be added to this stack and next AJAX request in this stack will be executed once current AJAX requests are completed
        requests_stack: [],
        simultaneous_requests_running: 0,
        max_simultaneous_requests_allowed: 3,

        max_simultaneous_requests: function( req_no ) {
            if( typeof req_no === "undefined" )
                return PHS_JSEN.max_simultaneous_requests_allowed;

            PHS_JSEN.max_simultaneous_requests_allowed = parseInt( req_no );
        },

        keys : function( o ) {
            var keys = [];
            for( var i in o ) {
                if( o.hasOwnProperty( i ) ) {
                    keys.push( i );
                }
            }

            return keys;
        },

        objects_are_equal: function ( object1, object2 ) {
            var obj1_empty = true, obj2_empty = true;
            if( (obj1_empty = (typeof object1 === "undefined" || object1 == null))
             || (obj2_empty = (typeof object2 === "undefined" || object2 == null)) ) {
                return (obj1_empty && obj2_empty);
            }

            const keys1 = Object.keys( object1 );
            const keys2 = Object.keys( object2 );

            if( keys1.length !== keys2.length )
                return false;

            for( const key in keys1 ) {
                const val1 = object1[key];
                const val2 = object2[key];
                const areObjects = (val1 != null && typeof val1 === "object"
                                 && val2 != null && typeof val2 === "object");

                if( (areObjects && !PHS_JSEN.objects_are_equal( val1, val2 ))
                 || (!areObjects && val1 !== val2) )
                    return false;
            }

            return true;
        },

        object_has_keys : function( o ) {
            for( var i in o ) {
                if( o.hasOwnProperty( i ) ) {
                    return true;
                }
            }

            return false;
        },

        object_length : function( o ) {
            if( typeof o !== "object" )
                return 0;

            var length = 0;
            for( var i in o ) {
                if( o.hasOwnProperty( i ) ) {
                    length++;
                }
            }

            return length;
        },

        in_array : function( needle, haystack ) {
            var length = haystack.length;
            for( var i = 0; i < length; i++ ) {
                if( haystack[i] == needle )
                    return true;
            }

            return false;
        },

        random_string: function( length ) {
            var result = '';
            var characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            var charactersLength = characters.length;
            for ( var i = 0; i < length; i++ ) {
                result += characters.charAt( Math.floor( Math.random() * charactersLength ) );
            }

            return result;
        },

        hash_string: function( str ) {
            var hash = 0, str_len = str.length;
            if( str_len === 0 )
                return hash;

            for( var i = 0; i < str_len; i++ ) {
                var char = str.charCodeAt( i );
                hash = ((hash << 5) - hash) + char;
                //hash = hash & hash; // Convert to 32bit integer
            }

            return hash;
        },

        bool_value_to_numeric: function( val ) {
            if( val )
                return 1;

            return 0;
        },

        js_messages_hide_all: function( message_box_container ) {
            PHS_JSEN.js_messages_hide( "success", message_box_container );
            PHS_JSEN.js_messages_hide( "warning", message_box_container );
            PHS_JSEN.js_messages_hide( "error", message_box_container );
        },

        js_messages_hide: function( type, message_box_container ) {
            var message_box = false;
            if( typeof message_box_container === "undefined"
             || message_box_container.length === 0 )
                message_box = $("#phs_ajax_" + type + "_box");

            else {
                if( typeof message_box_container === "string" )
                    message_box = $("#"+message_box_container + "_" + type + "_box");
                else if( typeof message_box_container === "object" )
                    message_box = message_box_container;
            }

            if( message_box && message_box.length ) {
                message_box.find( ".dismissible" ).html( "" );
                message_box.hide();
            }
        },

        /**
         * Display JS errors
         * @param messages_arr Array of messages to be displayed
         * @param type String: "error", "warning" or "success"
         * @param message_box_container String with container ID, container JQuery object
         */
        js_messages: function( messages_arr, type, message_box_container ) {
            if( typeof messages_arr === "undefined" || !messages_arr
             || typeof messages_arr.length === "undefined" || !messages_arr.length )
                return;

            var message_box = false;
            if( typeof message_box_container === "undefined"
             || message_box_container.length === 0 )
                message_box = $("#phs_ajax_" + type + "_box");
            else {
                if( typeof message_box_container === "string" )
                    message_box = $("#"+message_box_container + "_" + type + "_box");
                else if( typeof message_box_container === "object" )
                    message_box = message_box_container;
            }

            if( message_box && message_box.length ) {
                var find_result = message_box.find( ".dismissible" );
                for( var i = 0; i < messages_arr.length; i++ ) {
                    if( find_result.length )
                        find_result.append( "<p>" + messages_arr[i] + "</p>" );
                    else
                        message_box.append( "<p>" + messages_arr[i] + "</p>" );
                }
                message_box.show();
            }
        },

        do_autocomplete: function( container, o ) {
            var defaults = {
                url : '',
                autocomplete_obj : {
                    minLength: 1,
                    select: false
                },
                ajax_options : {}
            };

            var options = $.extend( {}, defaults, o );

            if( typeof o.autocomplete_obj != "undefined" )
                options.autocomplete_obj = $.extend( {}, defaults.autocomplete_obj, o.autocomplete_obj );
            if( typeof o.ajax_options != "undefined" )
                options.ajax_options = $.extend( {}, defaults.ajax_options, o.ajax_options );

            var container_obj = false;
            if( typeof container == "string" )
                container_obj = $(container);
            else if( typeof container == "object" )
                container_obj = container;

            if( !container_obj ) {
                alert( "<?php echo PHS_Language::_te( 'Couldn\'t obtain jQuery object for autocomplete field.' )?>" );
                return false;
            }

            if( !options.url ) {
                PHS_JSEN.js_messages( [ "<?php echo PHS_Language::_te( 'URL not provided for autocomplete field.' )?>" ], "error" );
                return container_obj.autocomplete();
            }

            return container_obj.autocomplete( $.extend( {}, {
                source: function( request, response ) {
                    PHS_JSEN.do_ajax( options.url, $.extend( {}, {
                        url_data: {
                            term: request.term
                        },
                        data_type: "json",
                        onsuccess: function( data, status, ajax_obj ) {
                            response( data );
                        }
                    }, options.ajax_options ) );
                }
            }, options.autocomplete_obj ) );
        },

        // Add this request in the queue
        // private
        remove_ajax_request_from_queue: function ( request_hash ) {
            if( typeof PHS_JSEN.ajax_queue[request_hash] === "undefined" )
                return;

            delete PHS_JSEN.ajax_queue[request_hash];
        },

        // Add this request in the queue
        // private
        add_ajax_request_to_queue: function ( request_hash, onsuccess, onfailed, queue_options ) {
            var defaults = {
                cache: false,
                cache_timeout: 10
            };
            var options = $.extend( {}, defaults, queue_options );

            var queue_item = {};
            if( typeof PHS_JSEN.ajax_queue[request_hash] !== "undefined" ) {
                queue_item = PHS_JSEN.ajax_queue[request_hash];
            } else {
                queue_item = {
                    cache: options.cache,
                    cache_timeout: options.cache_timeout,
                    response_received: false,
                    response_success: false,
                    response_data: false,
                    remove_from_queue_handler: false,
                    success_callbacks: [],
                    failed_callbacks: []
                };
            }

            if( queue_item.remove_from_queue_handler )
                clearTimeout( queue_item.remove_from_queue_handler );

            queue_item.success_callbacks.push( onsuccess );
            queue_item.failed_callbacks.push( onfailed );

            queue_item.remove_from_queue_handler = setTimeout( function(){ PHS_JSEN.remove_ajax_request_from_queue( request_hash ); }, 1000 * options.cache_timeout );

            PHS_JSEN.ajax_queue[request_hash] = queue_item;
        },

        // If we have a request like this in the queue, add success and failed callbacks in the queue
        // If we also have a response (failed or success), just call the callback function with cached response
        // return true if request is already queued, false if request is not yet queued
        // private
        check_ajax_queue_for_request: function ( request_hash, onsuccess, onfailed, queue_options ) {
            if( typeof PHS_JSEN.ajax_queue[request_hash] === "undefined" )
                return false;

            var queue_item = PHS_JSEN.ajax_queue[request_hash];

            if( typeof queue_item.response_received !== "undefined"
             && !queue_item.response_received ) {
                PHS_JSEN.add_ajax_request_to_queue( request_hash, onsuccess, onfailed, queue_options );
                return true;
            }

            // We already have a response...
            var func_list = [];
            if( queue_item.response_success ) {
                func_list = queue_item.success_callbacks;
            } else {
                func_list = queue_item.failed_callbacks;
            }

            var response = null, status = null, ajax_obj = null, data = null, error_exception = null;
            if( queue_item.response_success ) {
                response = queue_item.response_data.result_response;
                status = queue_item.response_data.status;
                ajax_obj = queue_item.response_data.ajax_obj;
                data = queue_item.response_data.data;
            } else {
                ajax_obj = queue_item.response_data.ajax_obj;
                status = queue_item.response_data.status;
                error_exception = queue_item.response_data.error_exception;
            }

            if( typeof func_list !== "undefined"
             && typeof func_list.length !== "undefined"
             && func_list.length > 0 )
            {
                for( var func in func_list )
                {
                    if( !func_list.hasOwnProperty( func ) )
                        continue;

                    var func_callback = func_list[func];

                    if( $.isFunction( func_callback ) )
                    {
                        if( queue_item.response_success ) {
                            func_callback( response, status, ajax_obj, data );
                        } else {
                            func_callback( ajax_obj, status, error_exception );
                        }
                    } else if( typeof func_callback === "string" )
                        eval( func_callback );
                }
            }

        },

        // We received a success response for a queued request, iterate all callbacks and call them
        // private
        onsuccess_ajax_queue_for_request: function ( request_hash, response, status, ajax_obj, data ) {
            if( typeof PHS_JSEN.ajax_queue[request_hash] === "undefined" )
                return false;

            var queue_item = PHS_JSEN.ajax_queue[request_hash];

            var cached_response = { result_response: response, status: status, ajax_obj: ajax_obj, data: data };

            queue_item.response_received = true;
            queue_item.response_success = true;
            queue_item.response_data = cached_response;

            var onsuccess_result = null;
            if( typeof queue_item.success_callbacks !== "undefined"
             && typeof queue_item.success_callbacks.length !== "undefined"
             && queue_item.success_callbacks.length > 0 )
            {
                for( var func in queue_item.success_callbacks )
                {
                    if( !queue_item.success_callbacks.hasOwnProperty( func ) )
                        continue;

                    var func_callback = queue_item.success_callbacks[func];

                    if( $.isFunction( func_callback ) )
                        onsuccess_result = func_callback( response, status, ajax_obj, data );
                    else if( typeof func_callback === "string" )
                        onsuccess_result = eval( func_callback );
                }
            }

            queue_item.success_callbacks = [];
            queue_item.failed_callbacks = [];

            PHS_JSEN.ajax_queue[request_hash] = queue_item;

            return onsuccess_result;
        },

        // We received a failed response for a queued request, iterate all callbacks and call them
        // private
        onfailed_ajax_queue_for_request: function ( request_hash, ajax_obj, status, error_exception ) {
            if( typeof PHS_JSEN.ajax_queue[request_hash] === "undefined" )
                return false;

            var queue_item = PHS_JSEN.ajax_queue[request_hash];

            var cached_response = { ajax_obj: ajax_obj, status: status, error_exception: error_exception };

            queue_item.response_received = true;
            queue_item.response_success = false;
            queue_item.response_data = cached_response;

            if( typeof queue_item.failed_callbacks !== "undefined"
             && typeof queue_item.failed_callbacks.length !== "undefined"
             && queue_item.failed_callbacks.length > 0 )
            {
                for( var func in queue_item.failed_callbacks )
                {
                    if( !queue_item.failed_callbacks.hasOwnProperty( func ) )
                        continue;

                    var func_callback = queue_item.failed_callbacks[func];

                    if( $.isFunction( func_callback ) )
                        func_callback( ajax_obj, status, error_exception );
                    else if( typeof func_callback === "string" )
                        eval( func_callback );
                }
            }

            queue_item.success_callbacks = [];
            queue_item.failed_callbacks = [];

            PHS_JSEN.ajax_queue[request_hash] = queue_item;

            return true;
        },

        // private
        check_ajax_requests_stack: function() {

            if( PHS_JSEN.requests_stack.length > 0 ) {
                var stack_item = PHS_JSEN.requests_stack.shift();

                if( typeof stack_item === "undefined"
                 || !stack_item )
                {
                    if( PHS_JSEN.requests_stack.length > 0 )
                        return PHS_JSEN.check_ajax_requests_stack();

                    return false;
                }

                var request_hash = stack_item.request_hash, ajax_url = stack_item.ajax_url,
                    options = stack_item.options, ajax_parameters_obj = stack_item.ajax_parameters_obj;

                return $.ajax( ajax_parameters_obj );
            }

            PHS_JSEN.simultaneous_requests_running--;
        },

        // private
        stack_ajax_request: function( request_hash, ajax_url, options, ajax_parameters_obj ) {
            var stack_item = {
                request_hash: request_hash,
                ajax_url: ajax_url,
                options: options,
                ajax_parameters_obj: ajax_parameters_obj
            };

            PHS_JSEN.requests_stack.push( stack_item );
        },

        // public
        do_ajax: function( url, o ) {
            var defaults = {
                // QUEUE SETTINGS
                // Use custom queue?
                queue_request: false,
                // Queue response caching
                queue_response_cache: false,
                queue_response_cache_timeout: 10,

                // STACK SETTINGS
                stack_request: false,

                // HTTP cache
                cache_response: false,
                method: "GET",
                url_data: "",
                data_type: "html",
                message_box_prefix: "",
                async: true,
                full_buffer: false,

                onfailed: null,
                onsuccess: null
            };

            var options = $.extend( {}, defaults, o );

            var ajax_url = PHS_JSEN.addURLParameter( url, "<?php echo PHS_Ajax::PARAM_FB_KEY?>", (options.full_buffer?1:0) );

            var request_hash = PHS_JSEN.hash_string( ajax_url.toLowerCase() + ":" + options.method.toLowerCase() + ":" + JSON.stringify( options.url_data ).toLowerCase() );

            if( options.queue_request ) {
                var queue_options = { cache: options.queue_response_cache, cache_timeout: options.queue_response_cache_timeout };

                // If we already have a similar request, just queue this one or call success or failed callback depending on response received (if any)
                if( PHS_JSEN.check_ajax_queue_for_request( request_hash, options.onsuccess, options.onfailed, queue_options ) ) {
                    return true;
                }

                PHS_JSEN.add_ajax_request_to_queue( request_hash, options.onsuccess, options.onfailed, queue_options );
            }

            var ajax_parameters_obj = {
                type: options.method,
                url: ajax_url,
                data: options.url_data,
                dataType: options.data_type,
                cache: options.cache_response,
                async: options.async,

                success: function( data, status, ajax_obj ) {

                    // check next AJAX requests in the stack before managing current response
                    if( options.stack_request )
                        PHS_JSEN.check_ajax_requests_stack();

                    var result_response = false;
                    if( !options.full_buffer ) {
                        if( typeof data.response == 'undefined' || !data.response )
                            data.response = false;

                        result_response = data.response;
                    } else
                        result_response = data;

                    var onsuccess_result = null;
                    if( options.queue_request ) {
                        onsuccess_result = PHS_JSEN.onsuccess_ajax_queue_for_request( request_hash, result_response, status, ajax_obj, data );
                    } else if( options.onsuccess ) {
                        if( $.isFunction( options.onsuccess ) )
                            onsuccess_result = options.onsuccess( result_response, status, ajax_obj, data );
                        else if( typeof options.onsuccess == "string" )
                            onsuccess_result = eval( options.onsuccess );
                    }

                    if( typeof onsuccess_result == "object"
                     && onsuccess_result )
                        data = onsuccess_result;

                    if( data && typeof data.redirect_to_url != 'undefined' && data.redirect_to_url.length ) {
                        document.location = data.redirect_to_url;
                        return;
                    }

                    if( data && typeof data.status != 'undefined' && data.status ) {
                        if( typeof data.status.success_messages != 'undefined' && data.status.success_messages.length )
                            PHS_JSEN.js_messages( data.status.success_messages, "success", options.message_box_prefix );
                        if( typeof data.status.warning_messages != 'undefined' && data.status.warning_messages.length )
                            PHS_JSEN.js_messages( data.status.warning_messages, "warning", options.message_box_prefix );
                        if( typeof data.status.error_messages != 'undefined' && data.status.error_messages.length )
                            PHS_JSEN.js_messages( data.status.error_messages, "error", options.message_box_prefix );
                    }
                },

                error: function( ajax_obj, status, error_exception ) {

                    // check next AJAX requests in the stack before managing current response
                    if( options.stack_request )
                        PHS_JSEN.check_ajax_requests_stack();

                    if( options.queue_request ) {
                        PHS_JSEN.onfailed_ajax_queue_for_request( request_hash, ajax_obj, status, error_exception );
                    } else if( options.onfailed ) {
                        if( $.isFunction( options.onfailed ) )
                            options.onfailed( ajax_obj, status, error_exception );
                        else if( typeof options.onfailed == "string" )
                            eval( options.onfailed );
                    }
                }
            };

            if( options.stack_request )
            {
                if( PHS_JSEN.simultaneous_requests_running >= PHS_JSEN.max_simultaneous_requests_allowed ){
                    return PHS_JSEN.stack_ajax_request( request_hash, ajax_url, options, ajax_parameters_obj );
                }

                PHS_JSEN.simultaneous_requests_running++;
            }

            return $.ajax( ajax_parameters_obj );
        },

        dialogErrorsDivsIds : [],
        dialogErrorsDivs : 0,
        dialogErrorsClose : function () {
            var container_obj = null;
            for( var i = 0; i < this.dialogErrorsDivsIds.length; i++ ) {
                container_obj = $(this.dialogErrorsDivsIds[i]);
                if( container_obj )
                    container_obj.remove();
            }
        },

        removeURLParameter: function ( url, parameter ) {
            //prefer to use l.search if you have a location/link object
            var urlparts= url.split( "?" );

            if( urlparts.length < 2 )
                return url;

            var prefix = encodeURIComponent( parameter ) + "=";
            var parts = urlparts[1].split( /[&;]/g );

            // reverse iteration as may be destructive
            for ( var i = parts.length; i-- > 0; ) {
                // idiom for string.startsWith
                if ( parts[i].lastIndexOf( prefix, 0 ) !== -1 ) {
                    parts.splice(i, 1);
                }
            }

            url = urlparts[0] + "?" + parts.join( "&" );

            return url;
        },

        addURLParameter: function ( url, key, value ) {
            var urlparts= url.split( "?" );
            key = encodeURI( key );
            value = encodeURI( value );

            if( urlparts.length < 2 )
                return url + "?" + key + "=" + value;

            var parts = urlparts[1].split( "&" );

            var i = parts.length;
            var x;

            while( i-- ) {
                x = parts[i].split( "=" );

                if( x[0] === key ) {
                    x[1] = value;
                    parts[i] = x.join( "=" );
                    break;
                }
            }

            if( i < 0 ) {
                parts[parts.length] = [key,value].join( "=" );
            }

            url = urlparts[0] + "?" + parts.join( "&" );

            return url;
        },

        refreshPage : function( to_url ) {
            if( typeof to_url === "undefined" || to_url === "" )
                to_url = window.location.href;

            to_url = PHS_JSEN.removeURLParameter( to_url, "_PHS_JSENr" );

            var rand_no = Math.round(((new Date()).getTime()-Date.UTC(1970,0,1))/1000);
            if( to_url.search( "\\?" ) === -1 )
                to_url = to_url + "?&_PHS_JSENr=" + rand_no;
            else
                to_url = to_url + "&_PHS_JSENr=" + rand_no;

            window.location.href = to_url;
        },

        change_language: function( language ) {
            show_submit_protection( "<?php echo PHS_Language::_t( 'Changing language... Please wait.' )?>" );

            var ajax_params = {
                cache_response: false,
                method: 'post',
                url_data: { "<?php echo PHS_Language::LANG_URL_PARAMETER?>": language },
                data_type: 'json',

                onsuccess: function( response, status, ajax_obj, response_data ) {
                    hide_submit_protection();

                    if( response
                     && typeof response.language_changed !== "undefined" && response.language_changed )
                        PHS_JSEN.refreshPage();
                },

                onfailed: function( ajax_obj, status, error_exception ) {
                    hide_submit_protection();

                    PHS_JSEN.js_messages( [ "<?php echo PHS_Language::_t( 'Error changing language. Please retry.' )?>" ], "error" );
                }
            };

            var ajax_obj = PHS_JSEN.do_ajax( "<?php echo PHS_Ajax::url( [ 'a' => 'change_language_ajax' ] )?>", ajax_params );
        },

        dialogErrors : function( error_arr ) {
            if( !error_arr || typeof error_arr !== "object" || !error_arr.length )
                return;

            this.dialogErrorsClose();

            for( var i = 0; i < error_arr.length; i++ ) {
                var container_obj = false;
                var appendto_obj = false;

                if( typeof error_arr[i].highlight_classes === "undefined"
                 || !error_arr[i].highlight_classes
                 || typeof error_arr[i].highlight_classes != "object" )
                    error_arr[i].highlight_classes = [];

                var container_name_id = '';
                var container_div_id = '';
                if( !error_arr[i].container || !error_arr[i].container.length ) {
                    if( typeof error_arr[i].appendto === "undefined"
                     || !error_arr[i].appendto
                     || error_arr[i].appendto.length === 0 )
                        continue;

                    appendto_obj = $(error_arr[i].appendto);
                    if( appendto_obj.length === 0 )
                        continue;

                    container_div_id = "PHS_JSENDiaErr" + this.dialogErrorsDivs;
                    container_name_id = "#PHS_JSENDiaErr" + this.dialogErrorsDivs;
                } else {
                    container_obj = $(error_arr[i].container);
                    if( container_obj.length === 0 )
                        continue;

                    container_name_id = error_arr[i].container;

                    this.dialogErrorsDivsIds.push( error_arr[i].container );
                }

                if( !error_arr[i].check_visible || typeof error_arr[i].check_visible !== "object" ) {
                    error_arr[i].check_visible = [];
                    error_arr[i].check_visible.push( container_name_id );
                } else if( !this.in_array( container_name_id, error_arr[i].check_visible ) )
                    error_arr[i].check_visible.push( container_name_id );

                var len = 0;
                var ki = 0;

                if( typeof error_arr[i].highlight_field !== "undefined" ) {
                    if( typeof error_arr[i].highlight_field === "object" && error_arr[i].highlight_field.length ) {
                        len = error_arr[i].highlight_field.length;
                        for( ki = 0; ki < len; ki++ ) {
                            var highlight_id = error_arr[i].highlight_field[ki];
                            if( $("#" + highlight_id) )
                                $("#" + highlight_id).addClass( "ui-highlight-error" );
                        }
                    } else if( $("#" + error_arr[i].highlight_field).length > 0 ) {
                        $("#" + error_arr[i].highlight_field).addClass("ui-highlight-error");
                    }
                }

                if( appendto_obj ) {
                    appendto_obj.append( '<div id="' + container_div_id + '"></div>' );
                    this.dialogErrorsDivs++;
                    container_obj = $(container_name_id);

                    if( !container_obj )
                        continue;

                    this.dialogErrorsDivsIds.push( container_name_id );
                }

                if( error_arr[i].check_visible && typeof error_arr[i].check_visible === "object" ) {
                    len = error_arr[i].check_visible.length;
                    for( ki = 0; ki < len; ki++ ) {
                        container_name = error_arr[i].check_visible[ki];
                        if( container_name.substr( 0, 1 ) !== '#' )
                            container_name = '#' + container_name;

                        var vis_obj = $(container_name);
                        if( !vis_obj )
                            continue;

                        if( vis_obj.css( 'display' ) !== 'block' )
                            vis_obj.css( 'display', 'block' );
                    }
                }

                if( error_arr[i].highlight_classes && typeof error_arr[i].highlight_classes === "object" ) {
                    for( ki = 0; ki < error_arr[i].highlight_classes.length; ki++ ) {
                        if( !container_obj.hasClass( error_arr[i].highlight_classes[ki] ) )
                            container_obj.addClass( error_arr[i].highlight_classes[ki] );
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

        closeAjaxDialog : function( suffix ) {
            if( typeof suffix === "undefined" )
                suffix = "";

            var dialog_obj = $("#" + PHS_JSEN.dialogs_prefix + suffix);
            if( dialog_obj ) {
                //var obj_options = PHS_JSEN.dialogOptions( suffix );
                //if( obj_options && obj_options.onclose )
                //{
                //    if( $.isFunction( obj_options.onclose ) )
                //        obj_options.onclose();
                //    else if( typeof obj_options.onclose == "string" )
                //        eval( obj_options.onclose );
                //}
                //
                //dialog_obj.remove();
                dialog_obj.dialog( "close" );
            }
        },

        // Create AJAX request
        redirectAjaxDialog : function( o ) {
            var defaults = {
                suffix            : "",
                cache_response    : false,
                url               : null,
                method            : "GET",
                url_data          : "",
                title             : "",
                cssclass          : ["phs_jsenOverlay"]
            };

            var options = $.extend( {}, defaults, o );

            var ajax_obj = false;
            var dialog_obj = $("#" + PHS_JSEN.dialogs_prefix + options.suffix);
            if( typeof options.url !== "undefined" && options.url && dialog_obj ) {
                if( options.title && options.title.length ) {
                    dialog_obj.dialog( "option", "title",  options.title );
                    PHS_JSEN.dialogOptions( options.suffix, 'title', options.title );
                }

                if( typeof( options.cssclass ) !== "undefined" && options.cssclass ) {
                    dialog_obj.dialog( "option", "dialogClass", options.cssclass );
                    PHS_JSEN.dialogOptions( options.suffix, 'cssclass', options.cssclass );
                }

                var ajax_params = {
                    cache_response: options.cache_response,
                    method: options.method,
                    url_data: options.url_data,
                    full_buffer: true,

                    onsuccess: function( response, status, ajax_obj ) {
                        dialog_obj.html( response );
                    },

                    onfailed: function( ajax_obj, status, error_exception ) {
                        dialog_obj.html( "<?php echo PHS_Language::_te( 'Error' )?>" );
                    }
                };

                if( typeof options.data_type === "string" )
                    ajax_params.data_type = options.data_type;

                ajax_obj = PHS_JSEN.do_ajax( options.url, ajax_params );
            }

            return ajax_obj;
        },

        // Reload HTML content of an AJAX dialog
        reloadAjaxDialog : function( o ) {
            var defaults = {
                suffix            : "",
                cache_response    : false,
                url               : null,
                method            : "GET",
                url_data          : "",

                onfailed          : null,
                onsuccess         : null
            };

            var options = $.extend( {}, defaults, o );

            var dialog_obj = $('#'+ PHS_JSEN.dialogs_prefix + options.suffix);
            if( !dialog_obj )
                return false;

            if( typeof options.url !== "undefined" && options.url ) {
                var ajax_params = {
                    cache_response: options.cache_response,
                    method: options.method,
                    url_data: options.url_data,
                    full_buffer: true,

                    onsuccess: function( response, status, ajax_obj ) {
                        dialog_obj.html( response );

                        if( options.onsuccess ) {
                            if( $.isFunction( options.onsuccess ) )
                                options.onsuccess();
                            else if( typeof options.onsuccess == "string" )
                                eval( options.onsuccess );
                        }
                    },

                    onfailed: function( ajax_obj, status, error_exception ) {
                        dialog_obj.html( "<?php echo PHS_Language::_te( 'Error' )?>" );
                    }
                };

                if( typeof options.data_type === "string" )
                    ajax_params.data_type = options.data_type;

                PHS_JSEN.do_ajax( options.url, ajax_params );

                PHS_JSEN.dialogOptions( options.suffix, 'url', options.url );
            }

            PHS_JSEN.modifyAjaxDialog( o );

            return true;
        },

        // Alter an AJAX dialog
        modifyAjaxDialog : function( o ) {
            var defaults = {
                width             : 0,
                height            : 0,
                suffix            : '',
                opacity           : 0.9,
                title             : "",
                draggable         : true,
                resizable         : false
            };

            var options = [];
            var options_to_change = false;

            var key = false;
            for( key in o ) {
                if( !o.hasOwnProperty( key )
                 || !defaults.hasOwnProperty( key ) )
                    continue;

                options[key] = o[key];
                options_to_change = true;
            }

            if( !options.hasOwnProperty( "suffix" ) )
                options["suffix"] = "";

            var dialog_obj = $("#" + PHS_JSEN.dialogs_prefix + options.suffix);
            if( options_to_change && dialog_obj ) {
                for( key in options ) {
                    if( key === 'suffix'
                     || !options.hasOwnProperty( key ) )
                        continue;

                    dialog_obj.dialog( "option", key, options[key] );
                    PHS_JSEN.dialogOptions( options.suffix, key, options[key] );
                }
            }
        },

        // Create AJAX request
        createAjaxDialog : function( o ) {
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
                // If true source_obj will be appended (preserving DOM objects)
                source_not_cloned : false,
                // Tells where to put source_obj DOM object once dialog will get destroyed (if source_not_cloned is true)
                source_parent     : null,

                draggable         : true,
                resizable         : false,
                title             : "",
                parent_tag        : "body",
                autoshow          : false,
                opacity           : 0.9,
                cssclass          : ["phs_jsenOverlay"],
                close_outside_click : true,
                dialog_show         : "",

                onclose           : null,
                onbeforeclose     : null,
                onsuccess         : null,
                onfailed          : null,

                dialog_full_options : null
            };

            var options = $.extend( {}, defaults, o );

            // Remove Dialog ( if previously created )
            var dialog_obj = $("#" + PHS_JSEN.dialogs_prefix + options.suffix);
            if( dialog_obj ) {
                dialog_obj.dialog( "destroy" ).remove();
            }

            // Create Dialog
            if( $(options.parent_tag).length === 0 )
                options.parent_tag = "body";

            $(options.parent_tag).append( '<div id="' + PHS_JSEN.dialogs_prefix + options.suffix + '"></div>' );

            dialog_obj = $("#" + PHS_JSEN.dialogs_prefix + options.suffix);
            if( dialog_obj ) {
                var dialog_default_options_obj = {
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

                    beforeClose: function( event, ui ) {
                        if( options.onbeforeclose ) {
                            if( $.isFunction( options.onbeforeclose ) )
                                options.onbeforeclose();
                            else if( typeof options.onbeforeclose == "string" )
                                eval( options.onbeforeclose );
                        }
                    },

                    open: function( event, ui ) {
                        $('.ui-widget-overlay').css('opacity', options.opacity);
                    }
                };

                var dialog_options_obj = {};
                if( options.dialog_full_options )
                    dialog_options_obj = $.extend( {}, dialog_default_options_obj, options.dialog_full_options );
                else
                    dialog_options_obj = dialog_default_options_obj;

                dialog_obj.dialog( dialog_options_obj );

                // Check if we should call an url
                if( typeof options.url !== "undefined" && options.url ) {
                    var ajax_params = {
                        cache_response: options.cache_response,
                        method: options.method,
                        url_data: options.url_data,
                        full_buffer: true,

                        onsuccess: function( response, status, ajax_obj ) {
                            //var diag_container = $( "#" + PHS_JSEN.dialogs_prefix + options.suffix );

                            if( dialog_obj ) {
                                dialog_obj.html( response );
                                dialog_obj.dialog( "open" );
                            }

                            if( options.onsuccess ) {
                                if( $.isFunction( options.onsuccess ) )
                                    options.onsuccess();
                                else if( typeof options.onsuccess == "string" )
                                    eval( options.onsuccess );
                            }
                        },

                        onfailed: function( ajax_obj, status, error_exception ) {
                            //var diag_container = $( "#" + PHS_JSEN.dialogs_prefix + options.suffix );
                            if( dialog_obj ) {
                                dialog_obj.html( "<?php echo PHS_Language::_te( 'Error ontaining dialogue body. Please try again.' )?>" );
                                dialog_obj.dialog( "open" );
                            }

                            if( options.onfailed ) {
                                if( $.isFunction( options.onfailed ) )
                                    options.onfailed();
                                else if( typeof options.onfailed == "string" )
                                    eval( options.onfailed );
                            }
                        }
                    };

                    PHS_JSEN.do_ajax( options.url, ajax_params );
                } else

                // Check if we have an object to extract html() from
                if( typeof( options.source_obj ) !== "undefined" && options.source_obj ) {
                    var source_container = null;
                    if( typeof( options.source_obj ) === "string" ) {
                        source_container = $( '#' + options.source_obj );
                        options.source_obj = source_container;
                    } else
                        source_container = options.source_obj;

                    if( !options.source_parent )
                        options.source_parent = source_container.parent();

                    if( options.source_not_cloned )
                        dialog_obj.append( source_container );
                    else
                        dialog_obj.html( source_container.html() );

                    dialog_obj.dialog( "open" );

                    if( options.onsuccess ) {
                        if( $.isFunction( options.onsuccess ) )
                            options.onsuccess();
                        else if( typeof options.onsuccess === "string" )
                            eval( options.onsuccess );
                    }
                }

                if( options.close_outside_click ) {
                    $(document).on( "click", ".ui-widget-overlay", function() {
                        if( dialog_obj )
                            dialog_obj.dialog( "close" );
                    });
                }

                dialog_obj.bind( 'dialogclose', function( event ) {
                    if( options.onclose ) {
                        if( $.isFunction( options.onclose ) )
                            options.onclose();
                        else if( typeof options.onclose === "string" )
                            eval( options.onclose );
                    }

                    if( options.source_not_cloned && options.source_obj && options.source_parent )
                        options.source_parent.append( options.source_obj );

                    dialog_obj.dialog('destroy').remove();
                    dialog_obj = false;
                });

                PHS_JSEN.dialogOptions( options.suffix, options );
            }

            if( window.innerHeight < options.height ) {
                setTimeout(function() {
                    dialog_obj.parent().css( "top", "30px" );
                }, 500 );
            }
        },

        // Close Loading div...
        dialogOptions : function( suffix, key, val ) {
            if( typeof key === "undefined" && typeof val === "undefined" )
                return PHS_JSEN.dialogs_options[suffix];

            if( typeof key === "string" && typeof val === "undefined" ) {
                if( typeof PHS_JSEN.dialogs_options[suffix] !== "undefined" && typeof PHS_JSEN.dialogs_options[suffix][key] !== "undefined" )
                    return PHS_JSEN.dialogs_options[suffix][key];
                else
                    return null;
            }

            if( typeof key === "object" ) {
                if( typeof PHS_JSEN.dialogs_options[suffix] !== "undefined" )
                    PHS_JSEN.dialogs_options[suffix] = $.extend( {}, PHS_JSEN.dialogs_options[suffix], key );
                else
                    PHS_JSEN.dialogs_options[suffix] = key;

                return PHS_JSEN.dialogs_options[suffix];
            }

            if( typeof key === "string" && typeof val !== "undefined"
             && typeof PHS_JSEN.dialogs_options[suffix] !== "undefined" ) {
                PHS_JSEN.dialogs_options[suffix][key] = val;
                return true;
            }

            return false;
        },

        // Close Loading div...
        closeLoadingDialog : function( suffix ) {
            // Remove Dialog ( if previously created )
            var loading_dialog_obj = $("#phs_jsen_loading" + suffix);
            if( loading_dialog_obj ) {
                loading_dialog_obj.remove();
            }
        },

        // Create Loading div...
        createLoadingDialog : function( o ) {
            var options = $.extend( {}, {
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
            var loading_dialog_obj = $("#phs_jsen_loading" + options.suffix);
            if( loading_dialog_obj )
                loading_dialog_obj.remove();

            // Create Dialog
            if( $(options.parent_tag).length === 0 )
                options.parent_tag = "body";

            $(options.parent_tag).append( '<div id="phs_jsen_loading' + options.suffix + '"></div>' );

            if( loading_dialog_obj ) {
                loading_dialog_obj.dialog( {
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

                loading_dialog_obj.html( options.message + '<div id="loading-animation-pb' + options.suffix + '"></div>' );

                $( "#loading-animation-pb" + options.suffix ).progressbar( { value: false } );

                loading_dialog_obj.dialog( "open" );
            }
        },

        keyPressHandler : function() {
            var r = $(PHS_JSEN.default_action_13);
            if( !r || !r.length )
                return;

            r.each( function() {
                var a = $(this);
                while( a && a.length && a.attr( "tagName" ) !== "FORM" ) {
                    a = a.parent()
                }

                if( a && a.length ) {
                    a.find("input").keypress( function( e ) {
                        if( ( e.which && e.which === 13 ) || (e.keyCode && e.keyCode === 13) ) {
                            a.find( PHS_JSEN.default_action_13 ).click();
                            return false;
                        }

                        return true;
                    });
                }
            });
        },

        logf : function( str ) {
            if( console && PHS_JSEN.debugging_mode )
                console.log( str );
        }
    };
}

