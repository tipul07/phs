<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;

/** @var \phs\plugins\remote_phs\PHS_Plugin_Remote_phs $plugin_obj */
if (!($plugin_obj = $this->get_plugin_instance())) {
    return $this->_pt('Couldn\'t get parent plugin object.');
}

$can_list_domains = $plugin_obj->can_admin_list_domains();
$can_manage_domains = $plugin_obj->can_admin_manage_domains();
$can_list_logs = $plugin_obj->can_admin_list_logs();
$can_manage_logs = $plugin_obj->can_admin_manage_logs();

?>
<li><?php echo $this->_pt('PHS Remote'); ?>
    <ul>
    <?php
    if ($can_list_domains || $can_manage_domains) {
        if ($can_manage_domains) {
            ?>
            <li><a href="<?php echo PHS::url([
                'a' => 'add', 'ad' => 'domains', 'c' => 'admin', 'p' => 'remote_phs',
            ]); ?>"><?php echo $this->_pt('Add Remote Domain'); ?></a>
            </li>
            <?php
        }
        ?>
        <li><a href="<?php echo PHS::url([
            'a' => 'list', 'ad' => 'domains', 'c' => 'admin', 'p' => 'remote_phs',
        ]); ?>"><?php echo $this->_pt('List Remote Domains'); ?></a>
        </li>
        <?php
    }
if ($can_list_logs || $can_manage_logs) {
    ?>
        <li><a href="<?php echo PHS::url([
            'a' => 'logs_list', 'ad' => 'domains', 'c' => 'admin', 'p' => 'remote_phs',
        ]); ?>"><?php echo $this->_pt('List Logs'); ?></a>
        </li>
        <?php
}
?>
    </ul>
    </li>
<?php
