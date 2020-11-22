<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_Action;
    use \phs\libraries\PHS_Language;
    use \phs\libraries\PHS_Hooks;
    use \phs\libraries\PHS_Notifications;
    use \phs\libraries\PHS_Roles;

    $accounts_plugin_settings = array();
    /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
    if( !($accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
    {
        PHS_Notifications::add_error_notice( $this::_t( 'Couldn\'t load accounts model. Please contact support.' ) );
        $accounts_model = false;
    } elseif( !($accounts_plugin_settings = $accounts_model->get_plugin_settings()) )
        $accounts_plugin_settings = array();

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

    echo $this->sub_view( 'template_admin_head_meta' );

    echo $this->sub_view( 'template_admin_head_css' );

    echo $this->sub_view( 'template_admin_head_js' );

?>
<script type="text/javascript">
function phs_refresh_input_skins()
{
    $('input:checkbox[rel="skin_chck_big"]').checkbox({cls:'jqcheckbox-big', empty:'<?php echo $this->get_resource_url( 'images/empty.png' )?>'});
    $('input:checkbox[rel="skin_chck_small"]').checkbox({cls:'jqcheckbox-small', empty:'<?php echo $this->get_resource_url( 'images/empty.png' )?>'});
    $('input:checkbox[rel="skin_checkbox"]').checkbox({cls:'jqcheckbox-checkbox', empty:'<?php echo $this->get_resource_url( 'images/empty.png' )?>'});
    $('input:radio[rel="skin_radio"]').checkbox({cls:'jqcheckbox-radio', empty:'<?php echo $this->get_resource_url( 'images/empty.png' )?>'});

    $(".chosen-select").chosen( { disable_search_threshold: 7, search_contains: true } );
    $(".chosen-select-nosearch").chosen({disable_search: true});
    $(".ui-button").button();
    $("*[title]").not(".no-title-skinning").tooltip();
}

function phs_refresh_dismissible_functionality()
{
    $('.dismissible').before( '<i class="fa fa-times-circle dismissible-close" style="float:right; margin: 5px; cursor: pointer;"></i>' );
    $('.dismissible-close').on( 'click', function( event ){
        $(this).parent().slideUp();
        $(this).parent().find(".dismissible").html("");
    });
}

function ignore_hidden_required( obj )
{
    var form_obj = $(obj).parents('form:first');

    if( form_obj && form_obj[0]
     && typeof document.createElement( 'input' ).checkValidity == 'function'
     && form_obj[0].checkValidity() ) {
        return;
    }

    form_obj.find( 'input,textarea,select' ).filter('[required]:hidden').removeAttr('required');
}

$(document).ready(function(){

    phs_refresh_input_skins();

    $.datepicker.setDefaults( $.datepicker.regional["<?php echo PHS_Language::get_current_language()?>"] );

    phs_refresh_dismissible_functionality();

    $('.submit-protection').on('click', function( event ){

        var form_obj = $(this).parents('form:first');

        if( form_obj && form_obj[0]
         && typeof document.createElement( 'input' ).checkValidity == 'function'
         && !form_obj[0].checkValidity() ) {
            return;
        }

        var msg = $( this ).data( 'protectionTitle' );
        if( typeof msg == 'undefined' || !msg )
            msg = '';

        show_submit_protection( msg );
    });

    $(document).on('click', '.ignore_hidden_required', function(){

        var form_obj = $(this).parents('form:first');

        if( form_obj && form_obj[0]
         && typeof document.createElement( 'input' ).checkValidity == 'function'
         && form_obj[0].checkValidity() ) {
            return;
        }

        form_obj.find( 'input,textarea,select' ).filter('[required]:hidden').removeAttr('required');
    });
});
</script>

<script type="text/javascript">
function open_messages_summary_menu_pane()
{
    close_menu_panes();
    $('#messages-summary-container' ).fadeToggle();
}
function close_messages_summary_menu_pane()
{
    $('#messages-summary-container' ).hide();
}
function open_login_menu_pane()
{
    $('#login_popup').slideToggle();
}
function open_right_menu_pane()
{
    $('#menu-right-pane' ).fadeToggle();
    $('#menu-left-pane' ).hide();
}
function open_left_menu_pane()
{
    $('#menu-right-pane' ).hide();
    $('#menu-left-pane' ).fadeToggle();
}
function close_menu_panes()
{
    $('#menu-right-pane' ).hide();
    $('#menu-left-pane' ).hide();
}
</script>

    <title><?php echo $action_result['page_settings']['page_title']?></title>
    <?php echo $action_result['page_settings']['page_in_header']?>
<?php

if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_ADMIN_TEMPLATE_PAGE_HEAD, PHS_Hooks::default_buffer_hook_args() ))
and is_array( $hook_args )
and !empty( $hook_args['buffer'] ) )
    echo $hook_args['buffer'];
?>
</head>

<body<?php echo (($page_body_class = PHS::page_settings( 'page_body_class' ))?' class="'.$page_body_class.'" ':'').$action_result['page_body_extra_tags']?>>
<?php

if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_ADMIN_TEMPLATE_PAGE_START, PHS_Hooks::default_buffer_hook_args() ))
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
    <div id="menu-left-pane" class="menu-pane">
        <div class="main-menu-pane-close-button" style="float: right; ">
            <a href="javascript:void()" onclick="close_menu_panes()" onfocus="this.blur();" class="fa fa-times"></a>
        </div>
        <div class="clearfix"></div>

        <ul>
            <?php
                if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_ADMIN_TEMPLATE_BEFORE_LEFT_MENU,
                                                      PHS_Hooks::default_buffer_hook_args() )) and is_array( $hook_args ) and !empty($hook_args['buffer'])
                )
                    echo $hook_args['buffer'];


                if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_ADMIN_TEMPLATE_AFTER_LEFT_MENU,
                                                      PHS_Hooks::default_buffer_hook_args() )) and is_array( $hook_args ) and !empty($hook_args['buffer'])
                )
                    echo $hook_args['buffer'];

            ?>
        </ul>

    </div>
    <div class="clearfix"></div>

    <div id="menu-right-pane" class="menu-pane">
        <div class="main-menu-pane-close-button" style="float: left; ">
            <a href="javascript:void()" onclick="close_menu_panes()" onfocus="this.blur();" class="fa fa-times"></a>
        </div>
        <div class="clearfix"></div>

        <ul>
            <?php

                if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_ADMIN_TEMPLATE_BEFORE_RIGHT_MENU,
                                                      PHS_Hooks::default_buffer_hook_args() )) and is_array( $hook_args ) and !empty($hook_args['buffer'])
                )
                    echo $hook_args['buffer'];

                if( !empty($cuser_arr) )
                {
                    ?>
                    <li><p><?php echo $this::_t( 'Hello %s', $cuser_arr['nick'] ) ?></p></li>

                    <li><a href="<?php echo PHS::url( array(
                                                              'p' => 'accounts', 'a' => 'edit_profile'
                                                      ) ) ?>"><?php echo $this::_t( 'Edit Profile' ) ?></a></li>
                    <li><a href="<?php echo PHS::url( array(
                                                              'p' => 'accounts', 'a' => 'change_password'
                                                      ) ) ?>"><?php echo $this::_t( 'Change Password' ) ?></a></li>
                    <li><a href="<?php echo PHS::url( array(
                                                              'p' => 'accounts', 'a' => 'logout'
                                                      ) ) ?>"><?php echo $this::_t( 'Logout' ) ?></a></li>
                    <?php
                }
                else
                {
                    ?>
                    <li><a href="<?php echo PHS::url( array(
                                                              'p' => 'accounts', 'a' => 'register'
                                                      ) ) ?>"><?php echo $this::_t( 'Register' ) ?></a></li>
                    <li>
                        <a href="javascript:void(0);" onclick="open_login_menu_pane();this.blur();"><?php echo $this::_t( 'Login' ) ?>
                            <div style="float:right;" class="fa fa-arrow-down"></div>
                        </a>
                        <div id="login_popup" style="display: none; padding: 10px;">
                            <form id="menu_pane_login_frm" name="menu_pane_login_frm" method="post" action="<?php echo PHS::url( array(
                                                                                                                                         'p' => 'accounts',
                                                                                                                                         'a' => 'login'
                                                                                                                                 ) ) ?>">
                                <div class="menu-pane-form-line">
                                    <label for="mt_nick"><?php echo(empty($accounts_plugin_settings['no_nickname_only_email']) ? $this::_t( 'Username' ) : $this::_t( 'Email' )) ?></label>
                                    <input type="text" id="mt_nick" class="form-control" name="nick" required/>
                                </div>
                                <div class="menu-pane-form-line">
                                    <label for="mt_nick"><?php echo $this::_t( 'Password' ) ?></label>
                                    <input type="password" id="mt_nick" class="form-control" name="pass" required/>
                                </div>
                                <div class="menu-pane-form-line fixskin">
                                    <label for="mt_do_remember"><?php echo $this::_t( 'Remember Me' ) ?></label>
                                    <input type="checkbox" id="mt_do_remember" name="do_remember" rel="skin_checkbox" value="1" required/>
                                    <div class="clearfix"></div>
                                </div>
                                <div class="menu-pane-form-line">
                                    <div style="float: left;"><a href="<?php echo PHS::url( array(
                                                                                                    'p' => 'accounts',
                                                                                                    'a' => 'forgot'
                                                                                            ) ) ?>"><?php echo $this::_t( 'Forgot Password' ) ?></a>
                                    </div>
                                    <div style="float: right; right: 10px;">
                                        <input type="submit" name="submit" value="<?php echo $this::_t( 'Login' ) ?>"/>
                                    </div>
                                    <div class="clearfix"></div>
                                </div>
                            </form>
                        </div>
                        <div class="clearfix"></div>
                    </li>
                    <?php
                }

                if( ($defined_languages = PHS_Language::get_defined_languages()) and count( $defined_languages ) > 1 )
                {
                    if( !($current_language = PHS_Language::get_current_language()) or empty($defined_languages[$current_language]) )
                        $current_language = PHS_Language::get_default_language();

                    ?>
                    <li><span><?php echo $this::_t( 'Choose language' ) ?></span>
                        <ul>
                        <?php
                        foreach( $defined_languages as $lang => $lang_details )
                        {
                            $language_flag = '';
                            if( !empty( $lang_details['flag_file'] ) )
                                $language_flag = '<span style="margin: 0 5px;"><img src="'.$lang_details['www'].$lang_details['flag_file'].'" /></span> ';

                            $language_link = 'javascript:PHS_JSEN.change_language( \''.$lang.'\' )';

                            ?>
                            <li><a href="<?php echo $language_link?>"><?php echo $language_flag.$lang_details['title']?></a></li>
                            <?php
                        }
                        ?>
                        </ul>
                    </li>
                    <?php
                }

                if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_ADMIN_TEMPLATE_AFTER_RIGHT_MENU,
                                                      PHS_Hooks::default_buffer_hook_args() )) and is_array( $hook_args ) and !empty($hook_args['buffer'])
                )
                    echo $hook_args['buffer'];
            ?>
        </ul>

    </div>
    <div class="clearfix"></div>

    <header id="header">
        <div id="header_content">
            <div id="logo">
                <a href="<?php echo PHS::url() ?>"><img src="<?php echo $this->get_resource_url( 'images/logo.png' ) ?>" alt="<?php echo PHS_SITE_NAME ?>" /></a>
                <div class="clearfix"></div>
            </div>

            <div id="menu">
                <nav>
                    <ul>
                        <li class="main-menu-placeholder">
                            <a href="javascript:void(0)" onclick="open_left_menu_pane()" onfocus="this.blur();" class="fa fa-bars main-menu-icon"></a>
                        </li>

                        <?php
                            if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_ADMIN_TEMPLATE_BEFORE_MAIN_MENU,
                                                                  PHS_Hooks::default_buffer_hook_args() )) and is_array( $hook_args ) and !empty($hook_args['buffer'])
                            )
                                echo $hook_args['buffer'];
                        ?>

                        <li>
                            <a href="<?php echo PHS::url() ?>" onfocus="this.blur();"><?php echo $this::_t( 'Site Index' ) ?></a>
                        </li>

                        <?php
                            if( empty($cuser_arr) )
                            {
                                ?>
                                <li><a href="<?php echo PHS::url( array(
                                                                          'p' => 'accounts', 'a' => 'register'
                                                                  ) ) ?>" onfocus="this.blur();"><?php echo $this::_t( 'Register' ) ?></a>
                                </li>
                                <li><a href="<?php echo PHS::url( array(
                                                                          'p' => 'accounts', 'a' => 'login'
                                                                  ) ) ?>" onfocus="this.blur();"><?php echo $this::_t( 'Login' ) ?></a>
                                </li>
                                <?php
                            }

                            if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_ADMIN_TEMPLATE_AFTER_MAIN_MENU,
                                                                  PHS_Hooks::default_buffer_hook_args() )) and is_array( $hook_args ) and !empty($hook_args['buffer'])
                            )
                                echo $hook_args['buffer'];
                        ?>
                    </ul>
                </nav>
                <div id="user_info">
                    <nav>
                        <ul>
                            <?php
                                if( !empty($mail_hook_args['summary_buffer']) )
                                {
                                    ?>
                                    <li class="main-menu-placeholder">
                                        <a href="javascript:void(0)" onclick="open_messages_summary_menu_pane()" onfocus="this.blur();" class="fa fa-envelope main-menu-icon"><span id="messages-summary-new-count"><?php echo $mail_hook_args['messages_new'] ?></span></a>
                                        <div id="messages-summary-container"><?php echo $mail_hook_args['summary_buffer'] ?></div>
                                    </li>
                                    <?php
                                }
                            ?>
                            <li class="main-menu-placeholder">
                                <a href="javascript:void(0)" onclick="open_right_menu_pane()" onfocus="this.blur();" class="fa fa-user main-menu-icon"></a>
                            </li>

                        </ul>
                    </nav>
                </div>
            </div>

            <div class="clearfix"></div>
        </div>
    </header>
    <div class="clearfix"></div>

    <div id="content"><?php
if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_ADMIN_TEMPLATE_PAGE_FIRST_CONTENT, PHS_Hooks::default_buffer_hook_args() ))
 && is_array( $hook_args )
 && !empty( $hook_args['buffer'] ) )
    echo $hook_args['buffer'];
?>
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



    <footer id="footer">
        <div id="footer_content">
            <div class="footerlinks">
                <a href="<?php echo PHS::url( array( 'a' => 'contact_us' ) )?>"><?php echo $this::_t( 'Contact Us' )?></a> |
                <a href="<?php echo PHS::url( array( 'a' => 'tandc' ) )?>" ><?php echo $this::_t( 'Terms and Conditions' )?></a>
            </div>
            <div class="clearfix"></div>
            <?php
            $debug_str = '';
            if( PHS::st_debugging_mode()
            and ($debug_data = PHS::platform_debug_data()) )
            {
                $debug_str = ' </br><span class="debug_str">'.$debug_data['db_queries_count'].' queries, '.
                             ' bootstrap: '.number_format( $debug_data['bootstrap_time'], 6, '.', '' ).'s, '.
                             ' running: '.number_format( $debug_data['running_time'], 6, '.', '' ).'s, '.
                             ' peak mem: '.format_filesize( $debug_data['memory_peak'] ).
                             '</span>';
            }
            ?>
            <div><?php echo PHS_SITE_NAME.' (v'.PHS_SITEBUILD_VERSION.')'?> &copy; <?php echo date( 'Y' ).' '.$this::_t( 'All rights reserved.' ).$debug_str?> &nbsp;</div>
        </div>
    </footer>
    <div class="clearfix"></div>

</div>
<?php
if( false )
{
    ?><script type="text/javascript" src="<?php echo $this->get_resource_url( 'js/lightbox.js' ) ?>"></script><?php
}
}

if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_ADMIN_TEMPLATE_PAGE_END, PHS_Hooks::default_buffer_hook_args() ))
and is_array( $hook_args )
and !empty( $hook_args['buffer'] ) )
    echo $hook_args['buffer'];

?>
</body>
</html>

