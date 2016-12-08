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
    } else
    {
        if( !($accounts_plugin_settings = $accounts_model->get_plugin_settings()) )
            $accounts_plugin_settings = array();
    }

    if( !($user_logged_in = PHS::user_logged_in()) )
        $user_logged_in = false;
    if( !($cuser_arr = PHS::current_user()) )
        $cuser_arr = false;

    $action_result = $this::validate_array( $this->context_var( 'action_result' ), PHS_Action::default_action_result() );

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
    <meta http-equiv="Content-Type" content="text/html; charset=<?php echo PHS_Language::get_current_language_key( 'browser_charset' )?>" />
    <meta name="HandheldFriendly"   content="true" />
    <meta name="MobileOptimized"    content="320" />
    <meta name="viewport"           content="user-scalable=no, width=device-width, initial-scale=1.0" />
    <meta name="title"              content="<?php echo $action_result['page_settings']['page_title']?>" />
    <meta name="description"        content="<?php echo $action_result['page_settings']['page_description']?>" />
    <meta name="keywords"           content="<?php echo $action_result['page_settings']['page_keywords']?>" />
    <meta name="copyright"          content="Copyright <?php echo date( 'Y' ).' - '.PHS_SITE_NAME?>. All Right Reserved." />
    <meta name="author"             content="PHS Framework" />
    <meta name="revisit-after"      content="1 days" />

    <link href="<?php echo $this->get_resource_url( 'images/favicon.png' )?>" rel="shortcut icon" />

    <link href="<?php echo $this->get_resource_url( 'jquery-ui.css' )?>" rel="stylesheet" type="text/css" />
    <link href="<?php echo $this->get_resource_url( 'jquery-ui.theme.css' )?>" rel="stylesheet" type="text/css" />
    <link href="<?php echo $this->get_resource_url( 'jquery.checkbox.css' )?>" rel="stylesheet" type="text/css" />
    <link href="<?php echo $this->get_resource_url( 'chosen.css' )?>" rel="stylesheet" type="text/css" />
    <link href="<?php echo $this->get_resource_url( 'font-awesome/css/font-awesome.min.css' )?>" rel="stylesheet" type="text/css" />
    <link href="<?php echo $this->get_resource_url( 'css/bootstrap.css' )?>" rel="stylesheet" type="text/css" />
    <link href="<?php echo $this->get_resource_url( 'css/lightbox.css' )?>" rel="stylesheet" type="text/css" />
    <link href="<?php echo $this->get_resource_url( 'css/style.css' )?>" rel="stylesheet" type="text/css" />
    <link href="<?php echo $this->get_resource_url( 'css/style-colors.css' )?>" rel="stylesheet" type="text/css" />
    <link href="<?php echo $this->get_resource_url( 'css/extra.css' )?>" rel="stylesheet" type="text/css" />

    <script type="text/javascript" src="<?php echo $this->get_resource_url( 'js/jquery.js' )?>"></script>
    <script type="text/javascript" src="<?php echo $this->get_resource_url( 'js/jquery-ui.js' )?>"></script>
    <script type="text/javascript" src="<?php echo $this->get_resource_url( 'js/jquery.validate.js' )?>"></script>
    <script type="text/javascript" src="<?php echo $this->get_resource_url( 'js/jquery.checkbox.js' )?>"></script>
    <script type="text/javascript" src="<?php echo $this->get_resource_url( 'js/chosen.jquery.js' )?>"></script>
    <script type="text/javascript" src="<?php echo $this->get_resource_url( 'js/bootstrap.js' )?>"></script>

    <script type="text/javascript" src="<?php echo $this->get_resource_url( 'js/include.js' )?>" ></script>

    <?php
    if( ($jq_datepicker_lang_url = $this->get_resource_url( 'js/jquery.ui.datepicker-'.PHS_Language::get_current_language().'.js' )) )
    {
        ?><script type="text/javascript" src="<?php echo $jq_datepicker_lang_url?>"></script><?php
    }
    ?>
    <script type="text/javascript" src="<?php echo $this->get_resource_url( 'js/jsen.js.php' )?>"></script>
    <script type="text/javascript" src="<?php echo $this->get_resource_url( 'js/base.js.php' )?>"></script>

    <script type="text/javascript">
        function phs_refresh_input_skins()
        {
            $('input:checkbox[rel="skin_chck_big"]').checkbox({cls:'jqcheckbox-big', empty:'<?php echo $this->get_resource_url( 'images/empty.png' )?>'});
            $('input:checkbox[rel="skin_chck_small"]').checkbox({cls:'jqcheckbox-small', empty:'<?php echo $this->get_resource_url( 'images/empty.png' )?>'});
            $('input:checkbox[rel="skin_checkbox"]').checkbox({cls:'jqcheckbox-checkbox', empty:'<?php echo $this->get_resource_url( 'images/empty.png' )?>'});
            $('input:radio[rel="skin_radio"]').checkbox({cls:'jqcheckbox-radio', empty:'<?php echo $this->get_resource_url( 'images/empty.png' )?>'});

            $(".chosen-select").chosen( { disable_search_threshold: 7 } );
            $(".chosen-select-nosearch").chosen( { disable_search: true } );
            $(".ui-button").button();
            $("*[title]").tooltip();
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

            $('.dismissible').before( '<i class="fa fa-times-circle dismissible-close" style="float:right; margin: 5px; cursor: pointer;"></i>' );
            $('.dismissible-close').on( 'click', function( event ){
                $(this).parent().slideUp();
                $(this).parent().find(".dismissible").html("");
            });

            $(document).on( 'click', '.submit-protection', function( event ){

                ignore_hidden_required( this );

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
            $('#messages-summary-container' ).stop().fadeToggle();
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
            close_messages_summary_menu_pane();
            $('#menu-right-pane' ).fadeToggle();
            $('#menu-left-pane' ).hide();
        }
        function open_left_menu_pane()
        {
            close_messages_summary_menu_pane();
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
</head>

<body <?php echo $action_result['page_body_extra_tags']?>>
<?php
if( empty( $action_result['page_settings']['page_only_buffer'] ) )
{
?>
<div id="main_submit_protection" style="display: none; position: absolute; top: 0px; left: 0px; width: 100%; height: 100%; z-index: 10000;">
    <div style="position: relative; width: 100%; height: 100%;">
        <div style="position: absolute; top: 0px; left: 0px; width: 100%; height: 100%; background: #333; opacity: 0.5; filter:alpha(opacity=50)"></div>
        <div style="position: absolute; top: 0px; left: 0px; width: 100%; height: 100%;">
            <div id="protection-wrapper" style="position: fixed; display: table; margin: 0px auto; margin-top: 50px; width: 100%">
                <div style="margin: 0px auto; display: table;">

                    <div id="main_submit_protection_loading_content" style="margin: 20% auto 0 auto; min-width:250px; background-color: white;border: 2px solid lightgrey; text-align: center; padding: 40px;">
                        <div class="ajax-loader" title="<?php echo $this::_te( 'Loading...' )?>"></div>
                        <p style="padding:20px;margin: 20px auto;" id="main_submit_protection_message"><?php echo $this::_t( 'Please wait...' )?></p>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
<div id="container">
    <!-- BEGIN: page_header -->
    <div id="menu-left-pane" class="menu-pane">
        <div class="main-menu-pane-close-button" style="float: right; "><a href="javascript:void(0)" onclick="close_menu_panes()" onfocus="this.blur();" class="fa fa-times"></a></div>
        <div class="clearfix"></div>

        <ul>
        <?php

        if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_MAIN_TEMPLATE_BEFORE_LEFT_MENU, PHS_Hooks::default_buffer_hook_args() ))
        and is_array( $hook_args )
        and !empty( $hook_args['buffer'] ) )
            echo $hook_args['buffer'];

        if( !empty( $accounts_model )
        and $accounts_model->acc_is_admin( $cuser_arr ) )
        {
            ?><li><a href="<?php echo PHS::url( array( 'p' => 'admin' ) ) ?>"><?php echo $this::_t( 'Admin Menu' ) ?></a></li><?php
        }
        ?>
            <li><a href="<?php echo PHS::url()?>"><?php echo $this::_t( 'Home' )?></a></li>
            <?php
            if( PHS_Roles::user_has_role_units( $cuser_arr, PHS_Roles::ROLEU_CONTACT_US ) )
            {
                ?><li><a href="<?php echo PHS::url( array( 'a' => 'contact_us' ) ) ?>"><?php echo $this::_t( 'Contact Us' ) ?></a></li><?php
            }
            ?>
            <li><a href="<?php echo PHS::url( array( 'a' => 'tandc' ) )?>" ><?php echo $this::_t( 'Terms and Conditions' )?></a></li>
            <?php

        if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_MAIN_TEMPLATE_AFTER_LEFT_MENU, PHS_Hooks::default_buffer_hook_args() ))
        and is_array( $hook_args )
        and !empty( $hook_args['buffer'] ) )
            echo $hook_args['buffer'];

        ?>
        </ul>

    </div>
    <div class="clearfix"></div>

    <div id="menu-right-pane" class="menu-pane">
        <div class="main-menu-pane-close-button" style="float: left; "><a href="javascript:void(0)" onclick="close_menu_panes()" onfocus="this.blur();" class="fa fa-times"></a></div>
        <div class="clearfix"></div>

        <ul>
        <?php

        if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_MAIN_TEMPLATE_BEFORE_RIGHT_MENU, PHS_Hooks::default_buffer_hook_args() ))
        and is_array( $hook_args )
        and !empty( $hook_args['buffer'] ) )
            echo $hook_args['buffer'];

        if( !empty( $user_logged_in ) )
        {
            ?>
            <li><?php echo $this::_t( 'Hello %s', $cuser_arr['nick'] ) ?></li>

            <?php
            if( !empty( $accounts_model )
            and $accounts_model->acc_is_admin( $cuser_arr ) )
            {
                ?><li><a href="<?php echo PHS::url( array( 'p' => 'admin' ) ) ?>"><?php echo $this::_t( 'Admin Menu' ) ?></a></li><?php
            }
            ?>
            <li><a href="<?php echo PHS::url( array(
                                                  'p' => 'accounts',
                                                  'a' => 'edit_profile'
                                              ) ) ?>"><?php echo $this::_t( 'Edit Profile' ) ?></a></li>
            <li><a href="<?php echo PHS::url( array(
                                                  'p' => 'accounts',
                                                  'a' => 'change_password'
                                              ) ) ?>"><?php echo $this::_t( 'Change Password' ) ?></a></li>
            <li><a href="<?php echo PHS::url( array(
                                                  'p' => 'accounts',
                                                  'a' => 'logout'
                                              ) ) ?>"><?php echo $this::_t( 'Logout' ) ?></a></li>
            <?php
        } else
        {
            if( PHS_Roles::user_has_role_units( $cuser_arr, PHS_Roles::ROLEU_REGISTER ) )
            {
                ?>
                <li><a href="<?php echo PHS::url( array(
                                                          'p' => 'accounts', 'a' => 'register'
                                                  ) ) ?>"><?php echo $this::_t( 'Register' ) ?></a></li>
                <?php
            }
            ?>
            <li>
                <a href="javascript:void(0);" onclick="open_login_menu_pane();this.blur();"><?php echo $this::_t( 'Login' ) ?>
                    <div style="float:right;" class="fa fa-arrow-down"></div>
                </a>
                <div id="login_popup" style="display: none; padding: 10px;width:100%;">
                    <form id="menu_pane_login_frm" name="menu_pane_login_frm" method="post" action="<?php echo PHS::url( array(
                                                                                                                             'p' => 'accounts',
                                                                                                                             'a' => 'login'
                                                                                                                         ) ) ?>">
                        <div class="menu-pane-form-line form-group">
                            <label for="mt_nick"><?php echo (empty( $accounts_plugin_settings['no_nickname_only_email'] )?$this::_t( 'Username' ):$this::_t( 'Email' ))?></label>
                            <input type="text" id="mt_nick" class="form-control" name="nick" required="required" />
                        </div>
                        <div class="menu-pane-form-line form-group">
                            <label for="mt_pass"><?php echo $this::_t( 'Password' ) ?></label>
                            <input type="password" id="mt_pass" class="form-control" name="pass" required="required" />
                        </div>
                        <div class="menu-pane-form-line fixskin">
                            <label for="mt_do_remember"><?php echo $this::_t( 'Remember Me' ) ?></label>
                            <input type="checkbox" id="mt_do_remember" name="do_remember" rel="skin_checkbox" value="1" />
                            <div class="clearfix"></div>
                        </div>
                        <div class="menu-pane-form-line form-group" style="width:100%;">
                            <div style="float: left;"><a href="<?php echo PHS::url( array(
                                                                                        'p' => 'accounts',
                                                                                        'a' => 'forgot'
                                                                                    ) ) ?>"><?php echo $this::_t( 'Forgot Password' ) ?></a>
                            </div>
                            <div style="float: right; right: 10px;">
                                <input type="submit" name="submit" class="btn btn-primary btn-medium submit-protection" value="<?php echo $this::_t( 'Login' ) ?>" /></div>
                            <div class="clearfix"></div>
                        </div>
                    </form>
                </div>
                <div class="clearfix"></div>
            </li>
            <?php
        }

        if( ($defined_languages = PHS_Language::get_defined_languages())
        and count( $defined_languages ) > 1 )
        {
            if( !($current_language = PHS_Language::get_current_language())
             or empty( $defined_languages[$current_language] ) )
                $current_language = PHS_Language::get_default_language();

            ?>
            <li><span><?php echo $this::_t( 'Choose language' )?></span>
                <ul>
                <?php
                foreach( $defined_languages as $lang => $lang_details )
                {
                    $language_flag = '';
                    if( !empty( $lang_details['flag_file'] ) and !empty( $lang_details['dir'] ) and !empty( $lang_details['www'] )
                    and @file_exists( $lang_details['dir'].$lang_details['flag_file'] ) )
                        $language_flag = '<span style="margin: 0 5px;"><img src="'.$lang_details['www'].$lang_details['flag_file'].'" /></span> ';

                    $language_link = 'javascript:alert( "In work..." )';

                    ?>
                    <li><a href="<?php echo $language_link?>"><?php echo $language_flag.$lang_details['title']?></a></li>
                    <?php
                }
                ?>
                </ul>
            </li>
            <?php
        }

        if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_MAIN_TEMPLATE_AFTER_RIGHT_MENU, PHS_Hooks::default_buffer_hook_args() ))
        and is_array( $hook_args )
        and !empty( $hook_args['buffer'] ) )
            echo $hook_args['buffer'];
        ?>
        </ul>

    </div>
    <div class="clearfix"></div>

    <header id="header">
        <div id="header_content">
            <div id="logo">
                <a href="<?php echo PHS::url()?>"><img src="<?php echo $this->get_resource_url( 'images/logo.png' )?>" alt="<?php echo PHS_SITE_NAME?>" title="<?php echo PHS_SITE_NAME?>" /></a>
                <div class="clearfix"></div>
            </div>

            <div id="menu">
                <nav>
                    <ul>
                        <li class="main-menu-placeholder"><a href="javascript:void(0)" onclick="open_left_menu_pane()" onfocus="this.blur();" class="fa fa-bars main-menu-icon"></a></li>

                        <?php
                        if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_MAIN_TEMPLATE_BEFORE_MAIN_MENU, PHS_Hooks::default_buffer_hook_args() ))
                        and is_array( $hook_args )
                        and !empty( $hook_args['buffer'] ) )
                            echo $hook_args['buffer'];
                        ?>

                        <li><a href="<?php echo PHS::url()?>" onfocus="this.blur();"><?php echo $this::_t( 'Home' )?></a></li>

                        <?php
                        if( empty( $user_logged_in ) )
                        {
                            if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_MAIN_TEMPLATE_BEFORE_MAIN_MENU_LOGGED_OUT, PHS_Hooks::default_buffer_hook_args() ))
                            and is_array( $hook_args )
                            and !empty( $hook_args['buffer'] ) )
                                echo $hook_args['buffer'];

                            if( PHS_Roles::user_has_role_units( $cuser_arr, PHS_Roles::ROLEU_REGISTER ) )
                            {
                                ?>
                                <li><a href="<?php echo PHS::url( array(
                                                                          'p' => 'accounts', 'a' => 'register'
                                                                  ) ) ?>" onfocus="this.blur();"><?php echo $this::_t( 'Register' ) ?></a>
                                </li>
                                <?php
                            }
                            ?>
                            <li><a href="<?php echo PHS::url( array(
                                                                  'p' => 'accounts',
                                                                  'a' => 'login'
                                                              ) ) ?>" onfocus="this.blur();"><?php echo $this::_t( 'Login' ) ?></a>
                            </li>
                            <?php

                            if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_MAIN_TEMPLATE_AFTER_MAIN_MENU_LOGGED_OUT, PHS_Hooks::default_buffer_hook_args() ))
                            and is_array( $hook_args )
                            and !empty( $hook_args['buffer'] ) )
                                echo $hook_args['buffer'];
                        } else
                        {
                            if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_MAIN_TEMPLATE_BEFORE_MAIN_MENU_LOGGED_IN, PHS_Hooks::default_buffer_hook_args() ))
                            and is_array( $hook_args )
                            and !empty( $hook_args['buffer'] ) )
                                echo $hook_args['buffer'];


                            if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_MAIN_TEMPLATE_AFTER_MAIN_MENU_LOGGED_IN, PHS_Hooks::default_buffer_hook_args() ))
                            and is_array( $hook_args )
                            and !empty( $hook_args['buffer'] ) )
                                echo $hook_args['buffer'];
                        }

                        if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_MAIN_TEMPLATE_AFTER_MAIN_MENU, PHS_Hooks::default_buffer_hook_args() ))
                        and is_array( $hook_args )
                        and !empty( $hook_args['buffer'] ) )
                            echo $hook_args['buffer'];
                    ?>
                    </ul>
                </nav>
                <div id="user_info">
                    <nav>
                        <ul>
                            <?php
                            if( !empty( $mail_hook_args['summary_buffer'] ) )
                            {
                            ?>
                            <li class="main-menu-placeholder"><a href="javascript:void(0)" onclick="open_messages_summary_menu_pane()" onfocus="this.blur();" class="fa fa-envelope main-menu-icon"><span id="messages-summary-new-count"><?php echo $mail_hook_args['messages_new']?></span></a>
                                <div id="messages-summary-container"><?php echo $mail_hook_args['summary_buffer']?></div>
                            </li>
                            <?php
                            }
                            ?>
                            <li class="main-menu-placeholder"><a href="javascript:void(0)" onclick="open_right_menu_pane()" onfocus="this.blur();" class="fa fa-user main-menu-icon"></a></li>

                        </ul>
                    </nav>
                </div>
            </div>

            <div class="clearfix"></div>
        </div>
    </header>
    <div class="clearfix"></div>
    <!-- END: page_header -->

    <div id="content"><?php
}

        if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_NOTIFICATIONS_DISPLAY, PHS_Hooks::default_notifications_hook_args() ))
        and is_array( $hook_args )
        and !empty( $hook_args['notifications_buffer'] ) )
            echo $hook_args['notifications_buffer'];

        echo $action_result['buffer'];

if( empty( $action_result['page_settings']['page_only_buffer'] ) )
{
    ?><div class="clearfix"></div></div>
    <div class="clearfix" style="margin-bottom: 10px;"></div>

    <footer id="footer">
        <!-- BEGIN: page_footer -->
        <div id="footer_content">
            <div class="footerlinks">
                <?php
                if( PHS_Roles::user_has_role_units( $cuser_arr, PHS_Roles::ROLEU_CONTACT_US ) )
                {
                    ?><a href="<?php echo PHS::url( array( 'a' => 'contact_us' ) )?>" ><?php echo $this::_t( 'Contact Us' )?></a> |<?php
                }
                ?>
                <a href="<?php echo PHS::url( array( 'a' => 'tandc' ) )?>" ><?php echo $this::_t( 'Terms and Conditions' )?></a>
            </div>
            <div class="clearfix"></div>
            <?php
            $debug_str = '';
            if( PHS::st_debugging_mode()
            and ($debug_data = PHS::platform_debug_data()) )
            {
                $debug_str = ' | '.$debug_data['db_queries_count'].' queries, '.
                             ' bootstrap: '.number_format( $debug_data['bootstrap_time'], 6, '.', '' ).'s, '.
                             ' running: '.number_format( $debug_data['running_time'], 6, '.', '' ).'s';
            }
            ?>
            <div style="float: right"><?php echo PHS_SITE_NAME.' (v'.PHS_SITEBUILD_VERSION.')'?> &copy; <?php echo date( 'Y' ).' '.$this::_t( 'All rights reserved.' ).$debug_str?> &nbsp;</div>
        </div>
        <!-- END: page_footer -->
    </footer>
    <div class="clearfix"></div>

</div>
<script type="text/javascript" src="<?php echo $this->get_resource_url( 'js/lightbox.js' )?>"></script>
<?php
}
?>
</body>
</html>

