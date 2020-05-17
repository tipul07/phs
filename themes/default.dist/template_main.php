<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_Action;
    use \phs\libraries\PHS_Language;
    use \phs\libraries\PHS_Hooks;
    use \phs\libraries\PHS_Notifications;
    use \phs\libraries\PHS_Roles;
    use \phs\plugins\accounts\models\PHS_Model_Accounts;

    $accounts_plugin_settings = array();
    /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
    if( !($accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
    {
        PHS_Notifications::add_error_notice( $this::_t( 'Couldn\'t load accounts model. Please contact support.' ) );
        $accounts_model = false;
    }

    $cuser_arr = PHS::user_logged_in();

    // $action_result = $this::validate_array( $this->view_var( 'action_result' ), PHS_Action::default_action_result() );
    $action_result = $this->get_action_result();

    $summary_mail_hook_args = PHS_Hooks::default_messages_summary_hook_args();
    $summary_mail_hook_args['summary_container_id'] = 'messages-summary-container';

    if( !($mail_hook_args = PHS::trigger_hooks( PHS_Hooks::H_MSG_GET_SUMMARY, $summary_mail_hook_args ))
     or !is_array( $mail_hook_args ) )
        $mail_hook_args = PHS_Hooks::default_messages_summary_hook_args();

    elseif( !empty( $mail_hook_args['hook_errors'] ) and PHS::arr_has_error( $mail_hook_args['hook_errors'] ) )
    {
        $error_message = PHS::arr_get_error_message( $mail_hook_args['hook_errors'] );
        $mail_hook_args = PHS_Hooks::default_messages_summary_hook_args();
        $mail_hook_args['summary_buffer'] = $error_message;
    }

?><!doctype html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="<?php echo PHS_Language::get_current_language_key( 'browser_lang' )?>" lang="<?php echo PHS_Language::get_current_language_key( 'browser_lang' )?>">
<head>
<?php

    echo $this->sub_view( 'template_main_head_meta' );

    echo $this->sub_view( 'template_main_head_css' );

    echo $this->sub_view( 'template_main_head_js' );

    echo $this->sub_view( 'template_main_skinning' );

    echo $this->sub_view( 'template_main_messages_js' );
?>
    <title><?php echo $action_result['page_settings']['page_title']?></title>
    <?php echo $action_result['page_settings']['page_in_header']?>
<?php

if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_MAIN_TEMPLATE_PAGE_HEAD, PHS_Hooks::default_buffer_hook_args() ))
and is_array( $hook_args )
and !empty( $hook_args['buffer'] ) )
    echo $hook_args['buffer'];
?>
</head>

<body<?php echo (($page_body_class = PHS::page_settings( 'page_body_class' ))?' class="'.$page_body_class.'" ':'').$action_result['page_body_extra_tags']?>>
<?php

if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_MAIN_TEMPLATE_PAGE_START, PHS_Hooks::default_buffer_hook_args() ))
and is_array( $hook_args )
and !empty( $hook_args['buffer'] ) )
    echo $hook_args['buffer'];

if( empty( $action_result['page_settings']['page_only_buffer'] ) )
{
?>
<div id="main_submit_protection">
    <div class="mask"></div>
    <div class="loader_container">
        <div id="main_submit_protection_loading_content">
            <div class="ajax-loader" title="<?php echo $this::_te( 'Loading...' )?>"></div>
            <div class="loader-3_container">
                <div class="loader-3"></div>
            </div>
            <div id="main_submit_protection_message">
                <?php echo $this::_t( 'Please wait...' )?>
            </div>
        </div>
    </div>
</div>
<div id="container">
<?php

    echo $this->sub_view( 'template_main_left_pane' );

    echo $this->sub_view( 'template_main_right_pane' );

    if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_COOKIE_NOTICE_DISPLAY, PHS_Hooks::default_buffer_hook_args() ))
    and is_array( $hook_args )
    and !empty( $hook_args['buffer'] ) )
        echo $hook_args['buffer'];
?>
<header id="header"><?php echo $this->sub_view( 'template_main_header' ); ?></header>

<div id="content">
    <div id="main_content"><?php
}

        if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_NOTIFICATIONS_DISPLAY, PHS_Hooks::default_notifications_hook_args() ))
        and is_array( $hook_args )
        and !empty( $hook_args['notifications_buffer'] ) )
            echo $hook_args['notifications_buffer'];

        echo $action_result['buffer'];

if( empty( $action_result['page_settings']['page_only_buffer'] ) )
{
    ?></div>

<footer id="footer" class="clearfix"><?php echo $this->sub_view( 'template_main_footer' ); ?></footer>

</div>

</div>
<?php
if( false )
{
    ?><script type="text/javascript" src="<?php echo $this->get_resource_url( 'js/lightbox.js' ) ?>"></script><?php
}
}

if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_MAIN_TEMPLATE_PAGE_END, PHS_Hooks::default_buffer_hook_args() ))
and is_array( $hook_args )
and !empty( $hook_args['buffer'] ) )
    echo $hook_args['buffer'];
?>
</body>
</html>

