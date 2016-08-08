<?php
    /** @var \phs\system\core\views\PHS_View $this */

use \phs\PHS;
use \phs\libraries\PHS_Roles;

    /** @var \phs\plugins\messages\PHS_Plugin_Messages $plugin_obj */
    if( !($plugin_obj = $this->parent_plugin()) )
        return $this->_pt( 'Couldn\'t get parent plugin object.' );

    $cuser_arr = PHS::current_user();

    $can_read_messages = PHS_Roles::user_has_role_units( $cuser_arr, $plugin_obj::ROLEU_READ_MESSAGE );
    $can_write_messages = PHS_Roles::user_has_role_units( $cuser_arr, $plugin_obj::ROLEU_WRITE_MESSAGE );

    if( !$can_read_messages and !$can_write_messages )
        return '';

?>
<li><a href="javascript:void(0);"><?php echo $this->_pt( 'Messages' )?></a>
    <ul>
    <?php
    if( $can_read_messages )
    {
        ?><li><a href="<?php echo PHS::url( array(
                                                'p' => 'messages',
                                                'c' => 'admin',
                                                'a' => 'inbox'
                                            ) ) ?>" onfocus="this.blur();"><?php echo $this->_pt( 'Inbox' )?></a></li><?php
    }
    if( $can_write_messages )
    {
        ?><li><a href="<?php echo PHS::url( array(
                                                'p' => 'messages',
                                                'c' => 'admin',
                                                'a' => 'compose'
                                            ) ) ?>" onfocus="this.blur();"><?php echo $this->_pt( 'Compose' )?></a></li><?php
    }
?></ul></li>
