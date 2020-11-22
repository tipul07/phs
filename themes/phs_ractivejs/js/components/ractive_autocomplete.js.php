<?php

    @header( 'Content-type: text/javascript' );

    $check_main_dir = dirname( __DIR__, 4 );
    if( !@file_exists( $check_main_dir.'/main.php' ) )
    {
        $check_main_dir = dirname( $_SERVER['SCRIPT_FILENAME'], 5 );
        if( !@file_exists( $check_main_dir.'/main.php' ) )
        {
            ?>
            alert( "Failed initializing autocomplete for Ractive.js library. Please contact support." );
            <?php
            exit;
        }
    }

    include( $check_main_dir.'/main.php' );
?>
var PHS_RActive_autocomplete = PHS_RActive_autocomplete || PHS_RActive.extend({

    template: '#PHS_RActive_autocomplete_inputs',

    data: function() {
        return {
            // Inputs settings
            id_input_id: 'PHS_RActive_autocomplete_id',
            id_input_name: 'PHS_RActive_autocomplete_id',
            id_input_value: 0,
            text_input_id: 'PHS_RActive_autocomplete_text',
            text_input_name: 'PHS_RActive_autocomplete_text',
            text_input_value: '',
            text_input_css_classes: ['form-control'],
            text_input_style: 'width:90%;float:left;',
            text_input_twoway: true,

            min_text_length: 1,
            hide_component: false,
            text_is_readonly: false,
            display_show_all: false,
            showing_all_records: false,
            input_lazyness: 500,

            // Data source
            // Where to send AJAX call for a list of items
            // AJAX action should normalize it's output using \phs\system\core\contracts\PHS_Contract_Ractive_autocomplete contract
            ajax_phs_route: '',
            ajax_term_param: 'q',
            // How many items should autocomplete return
            ajax_items_limit: 20,
            ajax_limit_param: 'limit',
            // Object with key-val pairs which will be passed to autocomplete AJAX call
            ajax_extra_params: {},

            // Provided array of items
            // An item should be an array with id, listing_title, listing_title_html and input_title keys
            // id - field will be passed in hidden field as value
            // listing_title - Plain text to be presented as option to be selected in items list
            // listing_title_html - HTML text to be presented as option to be selected in items list
            // input_title - This text will be put in autocomplete input after user selected an item
            source_data: null,
            // END Data source

            // Items to be presented as result of search
            filtered_items: [],
            // Display or hide results
            show_filtered_items: false,
            total_items_count: 0
        }
    },

    observe: {
        'text_input_value': {
            handler( newval, oldval ) {
                if( this.get( "text_is_readonly" )
                 && this.get( "id_input_value" ) === 0 )
                    return;

                if( newval === "" ) {
                    this.do_reset_inputs();
                    return;
                }

                this.start_search( newval );
            },
            defer: true
        }
    },

    on: {
        // Default event when clicking show all icon. Handle "PHSAutocomplete.event_show_all_results_custom" event for custom functionality
        // with a function which returns false to stop default behaviour when clicking "Show all" icon.
        "event_show_all_results": function( context ) {

            var showing_all_records = this.get( "showing_all_records" );
            if( showing_all_records )
                return;

            var show_filtered_items = this.get( "show_filtered_items" );
            if( show_filtered_items )
                this.hide_filtered_items();

            this.set( "showing_all_records", true )

            this.get_items_by_term( "" );
        }
    },

    hide_filtered_items: function() {
        this.set( { "show_filtered_items": false, "showing_all_records": false } );
    },

    select_item: function( id, input_value, item_obj ) {
        this.set({
            "text_is_readonly": true,
            "id_input_value": id,
            "text_input_value": input_value
        });
        this.hide_filtered_items();

        // Trigger select item event
        this.fire( "event_select_item", {}, item_obj );
    },

    start_search: function( term ) {

        this.fire( "event_start_search", term );

        this.start_loading_animation();

        this.set( "filtered_items", this.get_items_by_term( term ) );
    },

    stop_search: function() {

        this.fire( "event_stop_search", term );

        this.stop_loading_animation();
    },

    get_items_by_term: function( term ) {
        var items_arr = [];
        var ajax_phs_route = this.get( "ajax_phs_route" );
        if( ajax_phs_route !== "" ) {
            // Source is an AJAX query
            this.query_for_items_by_term( term );
        } else {
            // Source is a provided array
            var source_arr = this.get( "source_data" );

            if( $.isArray( source_arr )
             && source_arr.length > 0 ) {
                items_arr = $.grep( source_arr, function( value ) {
                    if( typeof value !== "object"
                     || !value.hasOwnProperty( "id" )
                     || !value.hasOwnProperty( "listing_title" )
                     || !value.hasOwnProperty( "input_title" ) )
                        return false;

                    if( !value.hasOwnProperty( "listing_title_html" ) )
                        value["listing_title_html"] = value["listing_title"];

                    if( term.length === 0 )
                        return true;

                    return (-1 !== value["listing_title"].toLowerCase().indexOf( term ));
                });
            }

            this.set({
                "filtered_items": items_arr,
                "total_items_count": source_arr.length,
                "show_filtered_items": true
            });
        }

        return items_arr;
    },

    query_for_items_by_term: function( term ) {
        var ajax_phs_route = this.get( "ajax_phs_route" );
        if( ajax_phs_route === "" )
            return;

        var q_param = this.get( "ajax_term_param" );
        var limit_param = this.get( "ajax_limit_param" );
        var limit_value = this.get( "ajax_items_limit" );

        var query_data = this.get( "ajax_extra_params" );
        query_data[q_param] = term;
        query_data[limit_param] = limit_value;

        var inner_this = this;
        this.read_data(
            ajax_phs_route,
            query_data,
            function( data, status, ajax_obj ) {

                inner_this.stop_loading_animation();

                if( typeof data !== "object"
                 || typeof data.response !== "object"
                 || typeof data.response.items !== "object" )
                    return;

                if( typeof data.response.total_items === "undefined" )
                    data.response.total_items = $(data.response.items).length;

                inner_this.set( "total_items_count", data.response.total_items );
                inner_this.set( "filtered_items", data.response.items );
                inner_this.set( "show_filtered_items", true );
            },
            function() {
                inner_this.stop_loading_animation();
            }, {
                queue_request: true,
                queue_response_cache: true,
                queue_response_cache_timeout: 10,
                stack_request: true
            }
        );
    },

    start_loading_animation: function() {
        var new_classes = this.get( "text_input_css_classes" );
        if( -1 === $.inArray( "phs_ractive_autocomplete_loading", new_classes ) )
        {
            new_classes.push( "phs_ractive_autocomplete_loading" );
            this.set( "text_input_css_classes", new_classes );
        }
    },

    stop_loading_animation: function() {
        var new_classes = this.get( "text_input_css_classes" );

        var value_found = false;
        new_classes = $.grep( new_classes, function(value){
            var ret_val = (value !== "phs_ractive_autocomplete_loading");
            if( !ret_val )
                value_found = true;
            return ret_val;
        });

        if( value_found )
            this.set( "text_input_css_classes", new_classes );
    },

    hide_me: function() {
        this.set( "hide_component", true );
    },

    show_me: function() {
        this.set( "hide_component", false );
    },

    do_reset_inputs: function() {
        this.stop_loading_animation();
        this.set({
            id_input_value: 0,
            text_input_value: "",
            text_is_readonly: false,
            show_filtered_items: false
        });

        this.fire( "event_reset_inputs" );
    }
});

Ractive.components.PHSAutocomplete = PHS_RActive_autocomplete;
