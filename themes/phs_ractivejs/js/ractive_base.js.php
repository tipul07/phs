<?php

    @header( 'Content-type: text/javascript' );

    $check_main_dir = dirname( __DIR__, 3 );
    if( !@file_exists( $check_main_dir.'/main.php' ) )
    {
        $check_main_dir = dirname( $_SERVER['SCRIPT_FILENAME'], 4 );
        if( !@file_exists( $check_main_dir.'/main.php' ) )
        {
            ?>
            alert( "Failed initializing Ractive.js library. Please contact suppot." );
            <?php
            exit;
        }
    }

    include( $check_main_dir.'/main.php' );

    use \phs\PHS;
    use \phs\PHS_ajax;
?>

var PHS_RActive = PHS_RActive || Ractive.extend({

    debugging_mode: <?php echo (PHS::st_debugging_mode()?'true':'false')?>,
    submit_protections_count: 0,

    object_has_keys: function( o )
    {
        for( var i in o )
        {
            if( o.hasOwnProperty( i ) )
            {
                return true;
            }
        }

        return false;
    },

    show_submit_protection: function( msg, extra_msg )
    {
        this.submit_protections_count++;
        show_submit_protection( msg, extra_msg );
    },

    hide_submit_protection: function( msg, extra_msg )
    {
        if( this.submit_protections_count <= 0 )
            return;

        this.submit_protections_count--;

        if( this.submit_protections_count <= 0 )
            hide_submit_protection();
    },

    phs_add_warning_message: function( msg, timeout = 6 )
    {
        if( !PHS_RActive_Main_app )
            return;

        PHS_RActive_Main_app.phs_add_warning_message( msg, timeout );
    },

    phs_add_error_message: function( msg, timeout = 6 )
    {
        if( !PHS_RActive_Main_app )
            return;

        PHS_RActive_Main_app.phs_add_error_message( msg, timeout );
    },

    phs_add_success_message: function( msg, timeout = 6 )
    {
        if( !PHS_RActive_Main_app )
            return;

        PHS_RActive_Main_app.phs_add_success_message( msg, timeout );
    },

    read_data: function ( route, data, success, failure )
    {
        if( typeof data === "undefined" )
            data = false;
        if( typeof success === "undefined" )
            success = false;
        if( typeof failure === "undefined" )
            failure = false;

        var ajax_params = {
            cache_response: false,
            method: "post",
            url_data: data,
            data_type: "json",

            onsuccess: success,
            onfailed: failure
        };

        return PHS_JSEN.do_ajax( "<?php echo PHS_ajax::url( false, false, [ 'raw_route' => '" + route + "' ] )?>", ajax_params );
    },

    valid_default_response_from_read_data: function( response )
    {
        return (typeof response === "undefined"
            || typeof response.error === "undefined"
            || typeof response.error.code === "undefined"
            || response.error.code !== 0
            || typeof response.response === "undefined");
    },

    get_error_message_for_default_read_data: function( response )
    {
        if( typeof response === "undefined"
         || typeof response.error === "undefined"
         || typeof response.error.message === "undefined"
         || response.error.message.length === 0 )
            return false;

        return response.error.message;
    },

    read_html: function ( route, data, success, failure )
    {
        if( typeof data === "undefined" )
            data = false;
        if( typeof success === "undefined" )
            success = false;
        if( typeof failure === "undefined" )
            failure = false;

        var ajax_params = {
            cache_response: false,
            method: "post",
            url_data: data,
            data_type: "html",

            onsuccess: success,
            onfailed: failure
        };

        return PHS_JSEN.do_ajax( "<?php echo PHS_ajax::url( false, false, [ 'raw_route' => '" + route + "' ] )?>", ajax_params );
    }
});

