<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_Language;
    use \phs\libraries\PHS_Hooks;
    use \phs\libraries\PHS_Roles;

    $cuser_arr = PHS::user_logged_in();

    /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
    if( !($accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
        $accounts_model = false;

    else
    {
        if( !($accounts_plugin_settings = $accounts_model->get_plugin_settings()) )
            $accounts_plugin_settings = array();
    }

?>
<div id="menu-right-pane" class="menu-pane clearfix">
    <div class="main-menu-pane-close-button clearfix" style="float: left;"><a href="javascript:void(0)" onclick="close_menu_panes()" onfocus="this.blur();" class="fa fa-times"></a></div>

    <ul>
    <?php

    if( !empty( $cuser_arr ) )
    {
        ?>
        <li class="welcome_msg"><?php echo $this::_t( 'Hello %s', $cuser_arr['nick'] ) ?></li>
        <?php

        if( !empty( $accounts_model )
        and $accounts_model->acc_is_operator( $cuser_arr ) )
        {
            ?><li><a href="<?php echo PHS::url( array( 'p' => 'admin' ) ) ?>"><?php echo $this::_t( 'Admin Menu' ) ?></a></li><?php
        }
    }

    if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_MAIN_TEMPLATE_BEFORE_RIGHT_MENU, PHS_Hooks::default_buffer_hook_args() ))
    and is_array( $hook_args )
    and !empty( $hook_args['buffer'] ) )
        echo $hook_args['buffer'];

    if( !empty( $cuser_arr ) )
    {
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
            <a href="javascript:void(0);" onclick="open_login_menu_pane(this);this.blur();"><?php echo $this::_t( 'Login' ) ?>
                <div class="fa fa-arrow-up trigger_embedded_login"></div>
            </a>
            <div id="login_popup" class="login_popup">
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
                            <input type="submit" name="do_submit" class="btn btn-primary btn-medium submit-protection ignore_hidden_required" value="<?php echo $this::_t( 'Login' ) ?>" /></div>
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
        <li class="phs_lang_container">
            <div class="switch_lang_title">
                <i class="fa fa-globe" style=""></i>
                <span><?php echo $this::_t( 'Change language' )?></span>
            </div>
            <ul>
            <?php
            foreach( $defined_languages as $lang => $lang_details )
            {
                $language_flag = '';
                if( !empty( $lang_details['flag_file'] ) )
                    $language_flag = '<span style="margin: 0 5px;"><img src="'.$lang_details['www'].$lang_details['flag_file'].'" /></span> ';

                $language_link = 'javascript:PHS_JSEN.change_language( \''.$lang.'\' )';

                ?>
                <li class="phs_language_<?php echo $lang?><?php echo ($current_language==$lang?' phs_language_selected':'')?>"><a href="<?php echo $language_link?>"><?php echo $language_flag.$lang_details['title']?></a></li>
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
