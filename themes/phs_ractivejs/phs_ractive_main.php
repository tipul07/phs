<div id="PHS_RActive_Main_app_target"></div>
<script id="PHS_RActive_main_app_template" type="text/html">
    <PHSNotifications
            disable_notifications="{{phs_notifications.disable_notifications}}"
            success="{{phs_notifications.success}}"
            errors="{{phs_notifications.errors}}"
            warnings="{{phs_notifications.warnings}}"
            positionX="{{phs_notifications.positionX}}" positionY="{{phs_notifications.positionY}}"
    ></PHSNotifications>
</script>
<script type="text/javascript">
<?php
    // We include this file so that developers will not have to care about this component
    // This component is considered "built-in"
    include_once( 'js/ractive_notifications.js.php' );
?>

var PHS_RActive_Main_app = PHS_RActive_Main_app || new PHS_RActive({

    target: "PHS_RActive_Main_app_target",
    template: "#PHS_RActive_main_app_template",

    data: function() {
        return {
            phs_notifications: {
                disable_notifications: false,
                success: [],
                errors: [],
                warnings: [],
                positionX: "left",
                positionY: "bottom"
            }
        }
    },

    phs_add_warning_message: function( msg, timeout = 6 )
    {
        var messages_arr = this.get( "phs_notifications.warnings" );
        messages_arr.push( { timeout: timeout, msg: msg } );

        this.set( "phs_notifications.warnings", messages_arr );
    },

    phs_add_error_message: function( msg, timeout = 6 )
    {
        var messages_arr = this.get( "phs_notifications.errors" );
        messages_arr.push( { timeout: timeout, msg: msg } );

        this.set( "phs_notifications.errors", messages_arr );
    },

    phs_add_success_message: function( msg, timeout = 6 )
    {
        var messages_arr = this.get( "phs_notifications.success" );
        messages_arr.push( { timeout: timeout, msg: msg } );

        this.set( "phs_notifications.success", messages_arr );
    },

    onrender: function()
    {
        //phs_refresh_input_skins();
    }

});
</script>
