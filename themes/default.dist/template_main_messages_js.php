<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_Action;
    use \phs\libraries\PHS_Language;
    use \phs\libraries\PHS_Hooks;
    use \phs\libraries\PHS_Notifications;
    use \phs\libraries\PHS_Roles;
    use \phs\plugins\accounts\models\PHS_Model_Accounts;
?>
<script type="text/javascript">
function open_messages_summary_menu_pane()
{
    var isVisible = false;
    close_menu_panes();
    $('#messages-summary-container').stop().fadeToggle(function(){ if(!isVisible) isVisible = true });
    var once = true;
    if (once) {
        $(window).click(function() {
            if (isVisible && $('#messages-summary-container').is(':visible'))
                $('#messages-summary-container').stop().fadeOut();
        });

        $('#messages-summary-container, #messages_summary_toggle').click(function(event){
            event.stopPropagation();
        });
        once = false;
    }
}
function close_messages_summary_menu_pane()
{
    $('#messages-summary-container' ).fadeOut();
}
function open_login_menu_pane( trigger )
{
    $('#login_popup').stop().slideToggle();
    $(trigger).find('div').toggleClass('fa-arrow-down').toggleClass('fa-arrow-up');
}
function open_right_menu_pane()
{
    close_messages_summary_menu_pane();
    $('#menu-right-pane' ).toggle('slide', { direction: 'right' }, 300);
    $('#menu-left-pane' ).hide();
}
function open_left_menu_pane()
{
    close_messages_summary_menu_pane();
    $('#menu-right-pane' ).hide();
    $('#menu-left-pane' ).toggle('slide', { direction: 'left' }, 300);
}
function close_menu_panes()
{
    $('#menu-right-pane' ).hide('slide', { direction: 'right' }, 200);
    $('#menu-left-pane' ).hide('slide', { direction: 'left' }, 200);
}
</script>
