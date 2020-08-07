<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_Action;
    use \phs\libraries\PHS_Language;
    use \phs\libraries\PHS_Hooks;
    use \phs\libraries\PHS_Notifications;
    use \phs\libraries\PHS_Roles;
    use \phs\plugins\accounts\models\PHS_Model_Accounts;

    $cuser_arr = PHS::user_logged_in();

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

?>
<div id="header_content">
    <div id="logo">
        <a href="<?php echo PHS::url()?>"><img src="<?php echo $this->get_resource_url( 'images/logo.png' )?>" alt="<?php echo PHS_SITE_NAME?>" /></a>
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
                if( empty( $cuser_arr ) )
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
                    <li class="main-menu-placeholder"><a href="javascript:void(0)" id="messages_summary_toggle" onclick="open_messages_summary_menu_pane()" onfocus="this.blur();" class="fa fa-envelope main-menu-icon"><span id="messages-summary-new-count"><?php echo $mail_hook_args['messages_new']?></span></a>
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

</div>
