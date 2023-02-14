<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Roles;

/** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
if (!($accounts_model = PHS::load_model('accounts', 'accounts'))) {
    $accounts_model = false;
}

$cuser_arr = PHS::user_logged_in();

?>
<div id="menu-left-pane" class="menu-pane clearfix">
    <div class="main-menu-pane-close-button clearfix" style="float: right;"><a href="javascript:void(0)" onclick="close_menu_panes()" onfocus="this.blur();" class="fa fa-times"></a></div>

    <ul>
    <?php

    if (($hook_args = PHS::trigger_hooks(PHS_Hooks::H_MAIN_TEMPLATE_BEFORE_LEFT_MENU, PHS_Hooks::default_buffer_hook_args()))
    && is_array($hook_args)
    && !empty($hook_args['buffer'])) {
        echo $hook_args['buffer'];
    }

    if (!empty($accounts_model)
    && $accounts_model->acc_is_operator($cuser_arr)) {
        ?><li><a href="<?php echo PHS::url(['p' => 'admin']); ?>"><?php echo $this::_t('Admin Menu'); ?></a></li><?php
    }
?>
    <li><a href="<?php echo PHS::url(); ?>"><?php echo $this::_t('Home'); ?></a></li>
    <?php
if (can(PHS_Roles::ROLEU_CONTACT_US)) {
    ?><li><a href="<?php echo PHS::url(['a' => 'contact_us']); ?>"><?php echo $this::_t('Contact Us'); ?></a></li><?php
}
?>
    <li><a href="<?php echo PHS::url(['a' => 'tandc']); ?>" ><?php echo $this::_t('Terms and Conditions'); ?></a></li>
    <?php

if (($hook_args = PHS::trigger_hooks(PHS_Hooks::H_MAIN_TEMPLATE_AFTER_LEFT_MENU, PHS_Hooks::default_buffer_hook_args()))
&& is_array($hook_args)
&& !empty($hook_args['buffer'])) {
    echo $hook_args['buffer'];
}

?>
    </ul>

</div>
