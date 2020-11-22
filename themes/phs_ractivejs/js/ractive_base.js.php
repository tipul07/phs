<?php

    @header( 'Content-type: text/javascript' );

    $check_main_dir = dirname( __DIR__, 3 );
    if( !@file_exists( $check_main_dir.'/main.php' ) )
    {
        $check_main_dir = dirname( $_SERVER['SCRIPT_FILENAME'], 4 );
        if( !@file_exists( $check_main_dir.'/main.php' ) )
        {
            ?>
            alert( "Failed initializing Ractive.js library. Please contact support." );
            <?php
            exit;
        }
    }

    define( 'PHS_PREVENT_SESSION', true );
    include( $check_main_dir.'/main.php' );

    use \phs\PHS;
    use \phs\PHS_Ajax;

    if( !($view_obj = PHS::spawn_view_in_context(
        [ 'a' => 'index' ], 'index' )) )
    {
        ?>
        alert( "Failed initializing Ractive.js library. Error obtaining view instance. Please contact support." );
        <?php
        exit;
    }

    if( !($empty_img_url = $view_obj->get_resource_url( 'images/empty.png' )) )
        $empty_img_url = '';
?>
//var PHS_RActive_AJAX_requests_count = 0;
//var PHS_RActive_AJAX_max_requests = 30;
//var PHS_RActive_AJAX_calls_handler = false;
var PHS_RActive = PHS_RActive || Ractive.extend({

    debugging_mode: <?php echo (PHS::st_debugging_mode()?'true':'false')?>,
    submit_protections_count: 0,

    //onconstruct: function() {
    //    var self = this;
    //    PHS_RActive_AJAX_calls_handler = setTimeout( function(){ self.disable_ajax_timer( self ); }, 1000 );
    //},
    //
    //ondestruct: function() {
    //    if( PHS_RActive_AJAX_calls_handler )
    //        clearTimeout( PHS_RActive_AJAX_calls_handler );
    //
    //    PHS_RActive_AJAX_calls_handler = false;
    //},
    //
    //disable_ajax_timer: function( self ) {
    //    PHS_RActive_AJAX_calls_handler = false;
    //    PHS_RActive_AJAX_requests_count = PHS_RActive_AJAX_max_requests;
    //},

    // These decorators help adding skinning on inputs (as in classic PHS default theme)
    // Chosen selects update chosen skinning when:
    // - user selects something in dropdown
    // - also when using RActive_obj.set( "property", "new_value" );
    // Respecting twoway binding rules of RActive
    //region Decorators
    decorators: {
        chosen_select: function( node ) {
            var self = this;

            if( typeof node._ractive !== "undefined"
             && typeof node._ractive.binding !== "undefined"
             && typeof node._ractive.binding.model !== "undefined"
             && typeof node._ractive.binding.model.key !== "undefined" ) {
                this.observe( node._ractive.binding.model.key, function( newval, oldval ) {
                    window.setTimeout( function(){
                        $(node).trigger( "chosen:updated" );
                    }, 100 );
                });
            }

            $(node)
                .chosen( { disable_search_threshold: 7, search_contains: true } )
                .on("change", function( evt, params ) {
                       self.updateModel();
                });

            return {
                teardown: function() {
                    $(node).chosen("destroy");
                }
            };
        },
        chosen_select_nosearch: function( node ) {
            var self = this;

            if( typeof node._ractive !== "undefined"
             && typeof node._ractive.binding !== "undefined"
             && typeof node._ractive.binding.model !== "undefined"
             && typeof node._ractive.binding.model.key !== "undefined" ) {
                this.observe( node._ractive.binding.model.key, function( newval, oldval ) {
                    window.setTimeout( function() {
                        $(node).trigger( "chosen:updated" );
                    }, 100 );
                });
            }

            $(node)
                .chosen( { disable_search: true, search_contains: true } )
                .on("change", function( evt, params ){
                    self.updateModel();
                });

            return {
                teardown: function() {
                    $(node).chosen("destroy");
                }
            };
        },
        skin_checkbox: function( node ) {
            $(node).checkbox({cls:'jqcheckbox-checkbox', empty: '<?php echo $empty_img_url?>'});
            return {
                teardown: function() {
                    // Nothing to do on teardown
                }
            };
        },
        skin_radio: function( node ) {
            $(node).checkbox({cls:'jqcheckbox-radio', empty: '<?php echo $empty_img_url?>'});
            return {
                teardown: function() {
                    // Nothing to do on teardown
                }
            };
        },
        datepicker_month: function( node, args ) {
            var self = this;
            $(node).datepicker({
                dateFormat: 'yy-mm',
                changeMonth: true,
                changeYear: true,
                showButtonPanel: true,
                onSelect: function(dateValue) {
                    self.updateModel();
                },
                onClose: function(dateText, inst) {
                    $(this).datepicker('setDate', new Date(inst.selectedYear, inst.selectedMonth, 1));
                }
            });

            return {
                teardown: function() {
                    $(node).datepicker("destroy");
                }
            };
        },
        datepicker: function( node, args ) {
            var self = this;
            $(node).datepicker({
                dateFormat: 'yy-mm-dd',
                changeMonth: true,
                changeYear: true,
                showButtonPanel: true,
                onSelect: function(dateValue) {
                    self.updateModel();
                }
            });

            return {
                teardown: function() {
                    $(node).datepicker("destroy");
                }
            };
        }
    },
    //endregion Decorators

    object_has_keys: function( o ) {
        for( var i in o ) {
            if( o.hasOwnProperty( i ) ) {
                return true;
            }
        }

        return false;
    },

    is_showing_submit_protection: function() {
        return (this.submit_protections_count > 0);
    },

    show_submit_protection: function( msg, extra_msg ) {
        this.submit_protections_count++;
        show_submit_protection( msg, extra_msg );
    },

    hide_submit_protection: function() {
        if( this.submit_protections_count <= 0 )
            return;

        this.submit_protections_count--;

        if( this.submit_protections_count <= 0 )
            hide_submit_protection();
    },

    hide_all_submit_protections: function() {
        if( this.submit_protections_count <= 0 )
            return;

        while( this.submit_protections_count > 0 ) {
            this.hide_submit_protection();
        }
    },

    phs_add_warning_message: function( msg, timeout = 6 ) {
        if( !PHS_RActive_Main_app )
            return;

        PHS_RActive_Main_app.phs_add_warning_message( msg, timeout );
    },

    phs_add_error_message: function( msg, timeout = 6 ) {
        if( !PHS_RActive_Main_app )
            return;

        PHS_RActive_Main_app.phs_add_error_message( msg, timeout );
    },

    phs_add_success_message: function( msg, timeout = 6 ) {
        if( !PHS_RActive_Main_app )
            return;

        PHS_RActive_Main_app.phs_add_success_message( msg, timeout );
    },

    valid_default_response_from_read_data: function( response ) {
        return (typeof response !== "undefined"
            && response !== null
            && typeof response.response !== "undefined"
            && response.response !== null
            && typeof response.error !== "undefined"
            && typeof response.error.code !== "undefined"
            && parseInt( response.error.code ) === 0 );
    },

    get_error_message_for_default_read_data: function( response ) {
        if( typeof response === "undefined"
         || response === null
         || typeof response.error === "undefined"
         || typeof response.error.message === "undefined"
         || response.error.message.length === 0 )
            return false;

        var error_msg = response.error.message;
        if( typeof response.error.code !== "undefined"
         && parseInt( response.error.code ) !== 0 )
            error_msg = error_msg + " (error code: " + response.error.code + ")";

        return error_msg;
    },

    read_data: function ( route, data, success, failure, ajax_opts ) {
        if( typeof data === "undefined" )
            data = false;
        if( typeof success === "undefined" )
            success = false;
        if( typeof failure === "undefined" )
            failure = false;

        var default_ajax_params = {
            cache_response: false,
            method: "post",
            url_data: data,
            data_type: "json",

            onsuccess: success,
            onfailed: failure
        };

        var ajax_params = $.extend( {}, default_ajax_params, ajax_opts );

        return PHS_JSEN.do_ajax( "<?php echo PHS_Ajax::url( false, false, [ 'raw_route' => '" + route + "' ] )?>", ajax_params );
    },

    read_html: function ( route, data, success, failure, ajax_opts ) {
        if( typeof data === "undefined" )
            data = false;
        if( typeof success === "undefined" )
            success = false;
        if( typeof failure === "undefined" )
            failure = false;

        var default_ajax_params = {
            cache_response: false,
            method: "post",
            url_data: data,
            data_type: "html",

            onsuccess: success,
            onfailed: failure
        };

        var ajax_params = $.extend( {}, default_ajax_params, ajax_opts );

        return PHS_JSEN.do_ajax( "<?php echo PHS_Ajax::url( false, false, [ 'raw_route' => '" + route + "' ] )?>", ajax_params );
    }
});

