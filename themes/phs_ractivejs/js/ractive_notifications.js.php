<?php

    // This is included after ractive_base.js.php file (where PHS_RActive var is defined)
    // This is not intended to be included as separate resource
    if( !defined( 'PHS_PATH' ) )
        exit;
?>

// Append template and target used by notifications class to document DOM body
var PHS_RActive_notifications_template = `<?php
echo @file_get_contents( __DIR__.'/../phs_ractive_notifications.php' );
?>`;

var PHS_RActive_notifications = PHS_RActive_notifications || PHS_RActive.extend({

    template: PHS_RActive_notifications_template,

    data: function() {
        return {
            messages: {},
            // left, center, right
            positionX: "left",
            // bottom, center, top
            positionY: "bottom",
            disable_notifications: false,
            success: [],
            errors: [],
            warnings: []
        }
    },

    observe: {
        'success':
        {
            handler( newval )
            {
                this.add_notifications_to_queue( "success" );
            }
        },
        'errors':
        {
            handler( newval )
            {
                this.add_notifications_to_queue( "errors" );
            }
        },
        'warnings':
        {
            handler( newval )
            {
                this.add_notifications_to_queue( "warnings" );
            }
        }
    },

    add_notifications_to_queue: function( type )
    {
        if( type !== "success"
         && type !== "errors"
         && type !== "warnings" )
            return;

        var messages_arr = this.get( type );
        if( typeof messages_arr === "undefined"
         || messages_arr.length <= 0 )
            return;

        for( i = 0; i < messages_arr.length; i++ )
        {
            var msg_item = messages_arr[i];
            if( typeof msg_item === "undefined"
             || typeof msg_item["msg"] === "undefined" )
                continue;

            if( typeof msg_item["timeout"] === "undefined" )
                msg_item["timeout"] = 5;

            if( type === "success" )
                this.phs_add_success_message( msg_item["msg"], msg_item["timeout"] );
            else if( type === "errors" )
                this.phs_add_error_message( msg_item["msg"], msg_item["timeout"] );
            else if( type === "warnings" )
                this.phs_add_warning_message( msg_item["msg"], msg_item["timeout"] );
        }

        this.set( type, [] );
    },

    positional_class: function()
    {
        var positionX = this.get( "positionX" );
        var positionY = this.get( "positionY" );

        var class_name = "";
        if( positionX === "left"
         || positionX === "center"
         || positionX === "right" )
            class_name += positionX;

        if( class_name.length > 0 )
            class_name += "_";

        if( positionY === "bottom"
         || positionY === "center"
         || positionY === "top" )
            class_name += positionY;

        if( class_name.length > 0 )
            class_name = "phs_ractive_notifications_container_" + class_name;

        return class_name;
    },

    phs_add_warning_message: function( msg, timeout = 3 )
    {
        this.phs_add_message_in_queue( msg, "warnings", timeout );
    },

    phs_add_error_message: function( msg, timeout = 3 )
    {
        this.phs_add_message_in_queue( msg, "errors", timeout );
    },

    phs_add_success_message: function( msg, timeout = 3 )
    {
        this.phs_add_message_in_queue( msg, "success", timeout );
    },

    phs_add_message_in_queue: function( msg, type, timeout = 3 )
    {
        var messages = this.get( "messages." + type );
        if( typeof messages === "undefined" )
            messages = {};

        var msg_id = Math.random();
        messages[msg_id] = msg;

        var inner_this = this;

        this.set( "messages." + type, messages );

        if( timeout > 0 )
            setTimeout( function() { inner_this.phs_remove_message_from_queue( msg_id, type ) }, timeout * 1000 );
    },

    phs_remove_warning_message: function( id )
    {
        this.phs_remove_message_from_queue( id, "warnings" );
    },

    phs_remove_error_message: function( id )
    {
        this.phs_remove_message_from_queue( id, "errors" );
    },

    phs_remove_success_message: function( id )
    {
        this.phs_remove_message_from_queue( id, "success" );
    },

    phs_remove_message_from_queue: function( id, type )
    {
        var messages = this.get( "messages" );
        if( typeof messages[type] === "undefined"
         || typeof messages[type][id] === "undefined" )
            return;

        delete messages[type][id];

        if( !this.object_has_keys( messages[type] ) )
            delete messages[type];
        if( !this.object_has_keys( messages ) )
            messages = {};

        this.set( "messages", messages );
    },

    phs_remove_all_types_from_queue: function( type )
    {
        var messages = this.get( "messages" );
        if( typeof messages[type] === "undefined" )
            return;

        delete messages[type];

        if( !this.object_has_keys( messages ) )
            messages = {};

        this.set( "messages", messages );
    },

    phs_remove_all_from_queue: function()
    {
        this.set( "messages", {} );
    }
});

Ractive.components.PHSNotifications = PHS_RActive_notifications;
