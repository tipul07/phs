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

