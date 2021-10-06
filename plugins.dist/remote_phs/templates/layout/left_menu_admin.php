<?php
    /** @var \phs\system\core\views\PHS_View $this */

use \phs\PHS;

    /** @var \phs\plugins\remote_phs\PHS_Plugin_Remote_phs $plugin_obj */
    if( !($plugin_obj = $this->parent_plugin()) )
        return $this->_pt( 'Couldn\'t get parent plugin object.' );

    $cuser_arr = PHS::user_logged_in();

    if( !($view_rights = $plugin_obj->get_user_platform_rights( $cuser_arr )) )
        $view_rights = [];

    if( empty( $view_rights['has_any_admin_rights'] ) )
        return '';

    $can_list_domains = (!empty( $view_rights['admin']['list_domains'] ));
    $can_manage_domains = (!empty( $view_rights['admin']['manage_domains'] ));
    $can_list_logs = (!empty( $view_rights['admin']['list_logs'] ));
    $can_manage_logs = (!empty( $view_rights['admin']['manage_logs'] ));

?>
<li><?php echo $this->_pt( 'PHS Remote' ) ?>
    <ul>
    <?php
    if( $can_list_domains || $can_manage_domains )
    {
        if( $can_manage_domains )
        {
            ?>
            <li><a href="<?php echo PHS::url( [
                  'a' => 'add', 'ad' => 'domains', 'c' => 'admin', 'p' => 'remote_phs'
                ]) ?>"><?php echo $this->_pt( 'Add Remote Domain' ) ?></a>
            </li>
            <?php
        }
        ?>
        <li><a href="<?php echo PHS::url( [
                  'a' => 'list', 'ad' => 'domains', 'c' => 'admin', 'p' => 'remote_phs'
            ]) ?>"><?php echo $this->_pt( 'List Remote Domains' ) ?></a>
        </li>
        <?php
    }
    if( $can_list_logs || $can_manage_logs )
    {
        ?>
        <li><a href="<?php echo PHS::url( [
                  'a' => 'logs_list', 'ad' => 'domains', 'c' => 'admin', 'p' => 'remote_phs'
            ]) ?>"><?php echo $this->_pt( 'List Logs' ) ?></a>
        </li>
        <?php
    }
?>
    </ul>
    </li>
<?php
