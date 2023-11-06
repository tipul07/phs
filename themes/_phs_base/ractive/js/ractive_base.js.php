<?php

@header('Content-type: text/javascript');

$check_main_dir = dirname(__DIR__, 4);

if (!@file_exists($check_main_dir.'/main.php')) {
    $check_main_dir = dirname($_SERVER['SCRIPT_FILENAME'], 5);
    if (!@file_exists($check_main_dir.'/main.php')) {
        ?>
            alert( "Failed initializing Ractive.js library. Please contact support." );
            <?php
        exit;
    }
}

define('PHS_PREVENT_SESSION', true);

include $check_main_dir.'/main.php';

use phs\PHS;
use phs\PHS_Ajax;

if (!($view_obj = PHS::spawn_view_in_context(
    ['a' => 'index'], 'index'))) {
    ?>
        alert( "Failed initializing Ractive.js library. Error obtaining view instance. Please contact support." );
        <?php
    exit;
}

if (!($empty_img_url = $view_obj->get_resource_url('images/empty.png'))) {
    $empty_img_url = '';
}
?>
PHS_JSEN.max_simultaneous_requests( 1 );

var PHS_RActive = PHS_RActive || Ractive.extend({

    debugging_mode: <?php echo PHS::st_debugging_mode() ? 'true' : 'false'; ?>,
    submit_protections_count: 0,

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
            var self = this;
            $(node)
                .checkbox({cls:'jqcheckbox-checkbox', empty: '<?php echo $empty_img_url; ?>'})
                .on("click", function( evt, params ){
                    self.updateModel();
                });
            return {
                teardown: function() {
                    // Nothing to do on teardown
                }
            };
        },
        skin_radio: function( node ) {
            var self = this;
            $(node)
                .checkbox({cls:'jqcheckbox-radio', empty: '<?php echo $empty_img_url; ?>'})
                .on("click", function( evt, params ){
                    self.updateModel();
                });
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

    is_showing_submit_protection: function() {
        return this.submit_protections_count > 0;
    },

    show_submit_protection: function( msg, extra_msg ) {
        this.submit_protections_count++;
        show_submit_protection( msg, extra_msg );
    },

    hide_submit_protection: function() {
        if( this.submit_protections_count <= 0 ) {
            return;
        }

        this.submit_protections_count--;

        if( this.submit_protections_count <= 0 ) {
            hide_submit_protection();
        }
    },

    hide_all_submit_protections: function() {
        if( this.submit_protections_count <= 0 ) {
            return;
        }

        while( this.submit_protections_count > 0 ) {
            this.hide_submit_protection();
        }
    },

    phs_add_warning_message: function( msg, timeout = 5 ) {
        if( !PHS_RActive_Main_app ) {
            return;
        }

        PHS_RActive_Main_app.phs_add_warning_message( msg, timeout );
    },

    phs_add_error_message: function( msg, timeout = 5 ) {
        if( !PHS_RActive_Main_app ) {
            return;
        }

        PHS_RActive_Main_app.phs_add_error_message( msg, timeout );
    },

    phs_add_success_message: function( msg, timeout = 5 ) {
        if( !PHS_RActive_Main_app ) {
            return;
        }

        PHS_RActive_Main_app.phs_add_success_message( msg, timeout );
    },

    valid_default_response_from_read_data: function( response ) {
        return typeof response !== "undefined"
            && response !== null
            && typeof response.response !== "undefined"
            && response.response !== null
            && typeof response.error !== "undefined"
            && typeof response.error.code !== "undefined"
            && parseInt( response.error.code ) === 0 ;
    },

    get_error_message_for_default_read_data: function( response ) {
        if( typeof response === "undefined"
            || response === null
            || typeof response.error === "undefined"
            || typeof response.error.message === "undefined"
            || response.error.message.length === 0 ) {
            return null;
        }

        var error_msg = response.error.message;
        if( typeof response.error.code !== "undefined"
            && parseInt( response.error.code ) !== 0 ) {
            error_msg = error_msg + " (error code: " + response.error.code + ")";
        }

        return error_msg;
    },

    read_data: function ( route, data, success, failure, ajax_opts ) {
        if( typeof ajax_opts === "undefined"
            || !ajax_opts ) {
            ajax_opts = {};
        }

        var default_ajax_params = {
            data_type: "json"
        };

        return this._server_request(route, data, success, failure, $.extend( {}, default_ajax_params, ajax_opts ));
    },

    read_html: function ( route, data, success, failure, ajax_opts ) {
        if( typeof ajax_opts === "undefined"
            || !ajax_opts ) {
            ajax_opts = {};
        }

        var default_ajax_params = {
            data_type: "html"
        };

        return this._server_request(route, data, success, failure, $.extend( {}, default_ajax_params, ajax_opts ));
    },

    _server_request: function ( route, data, success, failure, ajax_opts ) {
        let self = this;

        if( typeof ajax_opts === "undefined"
            || !ajax_opts ) {
            ajax_opts = {};
        }

        if( typeof ajax_opts.extract_logical_error_from_response === "undefined" ) {
            ajax_opts.extract_logical_error_from_response = true;
        }
        if( typeof ajax_opts.extract_status_messages_from_response === "undefined" ) {
            ajax_opts.extract_status_messages_from_response = true;
        }

        var default_ajax_params = {
            cache_response: false,
            method: "post",
            url_data: data,
            extract_response_messages: false,

            onsuccess: success,
            onfailed: failure
        };

        let ajax_params = $.extend( {}, default_ajax_params, ajax_opts );

        if( typeof data === "undefined" ) {
            data = null;
        }
        if( typeof success === "undefined" ) {
            success = null;
        }
        if( typeof failure === "undefined" ) {
            failure = null;
        }

        if( ajax_opts.extract_logical_error_from_response
            || ajax_opts.extract_status_messages_from_response ) {
            ajax_params.onsuccess = function( result_response, status, ajax_obj, data ) {

                if(result_response
                    && ajax_opts.extract_logical_error_from_response) {
                    self._extract_error_message_from_response(result_response);
                }
                if(result_response
                    && ajax_opts.extract_status_messages_from_response) {
                    self._extract_messages_from_response(data);
                }

                if(success) {
                    if ($.isFunction(success)) {
                        success(result_response, status, ajax_obj, data);
                    } else if (typeof failure === "string") {
                        eval(success + "( result_response, status, ajax_obj, data )");
                    }
                }
            }

            ajax_params.onfailed = function( ajax_obj, status, error_exception ) {

                if(ajax_opts.extract_logical_error_from_response
                    && ajax_obj
                    && typeof ajax_obj.responseJSON !== "undefined"
                    && ajax_obj.responseJSON
                    && typeof ajax_obj.responseJSON.response !== "undefined"
                    && ajax_obj.responseJSON.response ) {
                    self._extract_error_message_from_response(ajax_obj.responseJSON.response);
                }

                if( ajax_opts.extract_status_messages_from_response
                    && ajax_obj
                    && typeof ajax_obj.responseJSON !== "undefined"
                    && ajax_obj.responseJSON
                    && typeof ajax_obj.responseJSON.status !== "undefined"
                    && ajax_obj.responseJSON.status ) {
                    self._extract_messages_from_response(ajax_obj.responseJSON);
                }

                if(failure) {
                    if ($.isFunction(failure)) {
                        failure(ajax_obj, status, error_exception);
                    } else if (typeof failure === "string") {
                        eval(failure + "( ajax_obj, status, error_exception )");
                    }
                }
            }
        }

        return PHS_JSEN.do_ajax( "<?php echo PHS_Ajax::url(null, null, ['raw_route' => '" + route + "']); ?>", ajax_params );
    },

    _extract_error_message_from_response: function( response ) {
        if( typeof response !== "undefined"
            && response
            && typeof response.error !== "undefined"
            && response.error
            && typeof response.error.message !== "undefined"
            && response.error.message.length > 0
        ) {
            this.phs_add_error_message( response.error.message, 10 );
        }
    },

    _extract_messages_from_response: function( data ) {
        if( typeof data.status === "undefined"
            || !data.status ) {
            return;
        }

        this._extract_messages_from_ajax_response( data.status.success_messages, this.phs_add_success_message );
        this._extract_messages_from_ajax_response( data.status.warning_messages, this.phs_add_warning_message );
        this._extract_messages_from_ajax_response( data.status.error_messages, this.phs_add_error_message );
    },

    _extract_messages_from_ajax_response: function( messages, message_callback ) {
        if( typeof messages === "undefined"
         || !messages || messages.length === 0 ) {
            return;
        }

        let i = 0;
        for(;i < messages.length; i++ ) {
            message_callback( messages[i], 10 );
        }
    }
});

