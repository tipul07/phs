<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;

/** @var \phs\plugins\messages\PHS_Plugin_Messages $plugin_obj */
if (!($plugin_obj = $this->get_plugin_instance())) {
    return $this->_pt('Couldn\'t get parent plugin object.');
}

$can_read_messages = can($plugin_obj::ROLEU_READ_MESSAGE);
$can_write_messages = can($plugin_obj::ROLEU_WRITE_MESSAGE);

if (!$can_read_messages && !$can_write_messages) {
    return '';
}

?>
<li><a href="javascript:void(0);"><?php echo $this->_pt('Messages'); ?></a>
    <ul>
    <?php
    if ($can_read_messages) {
        ?><li><a href="<?php echo PHS::url([
            'p' => 'messages',
            'a' => 'inbox',
        ]); ?>" onfocus="this.blur();"><?php echo $this->_pt('Inbox'); ?></a></li><?php
    }
    if ($can_write_messages) {
        ?><li><a href="<?php echo PHS::url([
            'p' => 'messages',
            'a' => 'compose',
        ]); ?>" onfocus="this.blur();"><?php echo $this->_pt('Compose'); ?></a></li><?php
    }
?></ul></li>
