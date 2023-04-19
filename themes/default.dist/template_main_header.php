<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Roles;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Language;
use phs\libraries\PHS_Notifications;
use phs\plugins\accounts\models\PHS_Model_Accounts;
use phs\system\core\events\layout\PHS_Event_Layout;

$cuser_arr = PHS::user_logged_in();

$summary_mail_hook_args = PHS_Hooks::default_messages_summary_hook_args();
$summary_mail_hook_args['summary_container_id'] = 'messages-summary-container';

if (!($mail_hook_args = PHS::trigger_hooks(PHS_Hooks::H_MSG_GET_SUMMARY, $summary_mail_hook_args))
 || !is_array($mail_hook_args)) {
    $mail_hook_args = PHS_Hooks::default_messages_summary_hook_args();
} elseif (!empty($mail_hook_args['hook_errors']) && PHS::arr_has_error($mail_hook_args['hook_errors'])) {
    $error_message = PHS::arr_get_error_message($mail_hook_args['hook_errors']);
    $mail_hook_args = PHS_Hooks::default_messages_summary_hook_args();
    $mail_hook_args['summary_buffer'] = $error_message;
}

?>
<div id="header_content">
    <div id="logo">
        <a href="<?php echo PHS::url(); ?>"><img src="<?php echo $this->get_resource_url('images/logo.png'); ?>" alt="<?php echo PHS_SITE_NAME; ?>" /></a>
        <div class="clearfix"></div>
    </div>

    <div id="menu">
        <nav>
            <ul>
                <li class="main-menu-placeholder"><a href="javascript:void(0)" onclick="open_left_menu_pane()" onfocus="this.blur();" class="fa fa-bars main-menu-icon"></a></li>

                <?php
                echo PHS_Event_Layout::get_buffer(PHS_Event_Layout::MAIN_TEMPLATE_BEFORE_MAIN_MENU);
?>

                <li><a href="<?php echo PHS::url(); ?>" onfocus="this.blur();"><?php echo $this::_t('Home'); ?></a></li>

                <?php
if (empty($cuser_arr)) {
    echo PHS_Event_Layout::get_buffer(PHS_Event_Layout::MAIN_TEMPLATE_BEFORE_MAIN_MENU_LOGGED_OUT);

    if (can(PHS_Roles::ROLEU_REGISTER)) {
        ?>
        <li><a href="<?php echo PHS::url([
            'p' => 'accounts', 'a' => 'register',
        ]); ?>" onfocus="this.blur();"><?php echo $this::_t('Register'); ?></a>
        </li>
        <?php
    }
    ?>
    <li><a href="<?php echo PHS::url([
        'p' => 'accounts',
        'a' => 'login',
    ]); ?>" onfocus="this.blur();"><?php echo $this::_t('Login'); ?></a>
    </li>
    <?php

    echo PHS_Event_Layout::get_buffer(PHS_Event_Layout::MAIN_TEMPLATE_AFTER_MAIN_MENU_LOGGED_OUT);
} else {
    echo PHS_Event_Layout::get_buffer(PHS_Event_Layout::MAIN_TEMPLATE_BEFORE_MAIN_MENU_LOGGED_IN);
    echo PHS_Event_Layout::get_buffer(PHS_Event_Layout::MAIN_TEMPLATE_AFTER_MAIN_MENU_LOGGED_IN);
}

echo PHS_Event_Layout::get_buffer(PHS_Event_Layout::MAIN_TEMPLATE_AFTER_MAIN_MENU);
?>
            </ul>
        </nav>
        <div id="user_info">
            <nav>
                <ul>
                    <?php
        if (!empty($mail_hook_args['summary_buffer'])) {
            ?>
                    <li class="main-menu-placeholder"><a href="javascript:void(0)" id="messages_summary_toggle" onclick="open_messages_summary_menu_pane()" onfocus="this.blur();" class="fa fa-envelope main-menu-icon"><span id="messages-summary-new-count"><?php echo $mail_hook_args['messages_new']; ?></span></a>
                        <div id="messages-summary-container"><?php echo $mail_hook_args['summary_buffer']; ?></div>
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
