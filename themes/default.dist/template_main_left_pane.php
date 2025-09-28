<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;
use phs\libraries\PHS_Roles;
use phs\plugins\accounts\models\PHS_Model_Accounts;
use phs\system\core\events\layout\PHS_Event_Layout;

?>
<div id="menu-left-pane" class="menu-pane clearfix">
    <div class="main-menu-pane-close-button clearfix" style="float: right;"><a href="javascript:void(0)" onclick="close_menu_panes()" onfocus="this.blur();" class="fa fa-times"></a></div>
    <ul>
    <?php

    echo PHS_Event_Layout::get_buffer(PHS_Event_Layout::MAIN_TEMPLATE_BEFORE_LEFT_MENU);

if (PHS_Model_Accounts::get_instance()?->acc_is_operator()) {
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
    echo PHS_Event_Layout::get_buffer(PHS_Event_Layout::MAIN_TEMPLATE_AFTER_LEFT_MENU);
?>
    </ul>
</div>
