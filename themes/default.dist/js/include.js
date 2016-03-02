(jQuery)(function($) {

    /* ================ MAIN NAVIGATION ================ */

    (function() {
        include_nav_init();
    })();

    /* ================ CONTENT TABS ================ */
    (function() {
        include_tabs_init_tabs();
    })();

    /* ================ ACCORDION ================ */

    (function() {
        include_accordion_init();
    })();

    (function() {
        include_others_init();
    })();

    // function to check is user is on touch device
    function is_touch_device() {
        return 'ontouchstart' in window // works on most browsers 
                || 'onmsgesturechange' in window; // works on ie10
    }

});

function include_init_all()
{
    include_nav_init();
    include_tabs_init_tabs();
    include_accordion_init();
    include_others_init();
}

function include_nav_init()
{
    $(" #nav ul ").css({
        display: "none"
    }); // Opera Fix
    $(" #nav li").hover(function() {
        $(this).find('ul:first').css({
            visibility: "visible",
            display: "none"
        }).fadeIn(300);
    }, function() {
        $(this).find('ul:first').css({
            display: "none"
        });
    });
}

function include_others_init()
{
    /* ================ INFORMATION BOXES ================ */
    $('.information-boxes .close').on('click', function() {
        $(this).parent().slideUp(300);
    });

    /* ================ PLACEHOLDER PLUGIN ================ */
    $('input[placeholder], textarea[placeholder]').placeholder();
}

function include_accordion_init()
{
    'use strict';
    $( '.accordion' ).on( 'click', '.title', function( event )
    {
        event.preventDefault();
        $( this ).siblings( '.accordion .active' ).next().slideUp( 'normal' );
        $( this ).siblings( '.accordion .title' ).removeClass( "active" );

        if( $( this ).next().is( ':hidden' ) === true )
        {
            $( this ).next().slideDown( 'normal' );
            $( this ).addClass( "active" );
        }
    } );
    $( '.accordion .content' ).hide();
    $( '.accordion .active' ).next().slideDown( 'normal' );
}

function include_tabs_init_tabs()
{
    $('.tabs').each(function() {
        var $tabLis = $(this).find('li');
        var $tabContent = $(this).next('.tab-content-wrap').find('.tab-content');

        $tabContent.hide();
        $tabLis.first().addClass('active').show();
        $tabContent.first().show();
    });

    $('.tabs').on('click', 'li', function(e) {

        tabs_switch_to_tab( $(this) );

        e.preventDefault();
    });
}

function tabs_switch_to_tab( tabobj )
{
    if( !tabobj )
        return;

    var parentUL = tabobj.parent();
    var tabContent = parentUL.next('.tab-content-wrap');

    parentUL.children().removeClass('active');
    tabobj.addClass('active');

    tabContent.find('.tab-content').hide();
    var showById = $(tabobj.find('a').attr('href'));
    tabobj.find('a').blur();
    tabContent.find(showById).fadeIn();
}
