<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;
use phs\libraries\PHS_Roles;

/** @var \phs\plugins\backup\PHS_Plugin_Backup $plugin_obj */
if (!($plugin_obj = $this->parent_plugin())) {
    return $this->_pt('Couldn\'t get parent plugin object.');
}

$cuser_arr = PHS::current_user();

$can_list_rules = PHS_Roles::user_has_role_units($cuser_arr, $plugin_obj::ROLEU_LIST_RULES);
$can_manage_rules = PHS_Roles::user_has_role_units($cuser_arr, $plugin_obj::ROLEU_MANAGE_RULES);
$can_list_backups = PHS_Roles::user_has_role_units($cuser_arr, $plugin_obj::ROLEU_LIST_BACKUPS);
$can_delete_backups = PHS_Roles::user_has_role_units($cuser_arr, $plugin_obj::ROLEU_DELETE_BACKUPS);

if (!$can_list_rules && !$can_manage_rules && !$can_list_backups && !$can_delete_backups) {
    return '';
}

?>
<li><?php echo $this::_t('System Backups'); ?>
    <ul>
        <?php
        if ($can_manage_rules || $can_list_rules) {
            if ($can_manage_rules) {
                ?>
            <li><a href="<?php echo PHS::url([
                'a' => 'rule_add', 'p' => 'backup',
            ]); ?>"><?php echo $this::_t('Add Backup Rule'); ?></a>
            </li>
            <?php
            }
            ?>
            <li><a href="<?php echo PHS::url([
            'a' => 'rules_list', 'p' => 'backup',
            ]); ?>"><?php echo $this::_t('List Backup Rules'); ?></a>
            </li>
            <?php
        }
        if ($can_list_backups || $can_delete_backups) {
            ?>
        <li><a href="<?php echo PHS::url([
            'a' => 'backups_list', 'p' => 'backup',
        ]); ?>"><?php echo $this::_t('List Backups'); ?></a></li>
        <?php
        }
?>
    </ul>
</li>
<?php
