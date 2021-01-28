<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_Utils;
    use \phs\libraries\PHS_Roles;

    if( !($remember_me_session_minutes = $this->view_var( 'remember_me_session_minutes' )) )
        $remember_me_session_minutes = 0;

    if( !($no_nickname_only_email = $this->view_var( 'no_nickname_only_email' )) )
        $no_nickname_only_email = false;

    if( !($back_page = $this->view_var( 'back_page' )) )
        $back_page = '';
?>
<div class="login_container">
    <form id="login_form" name="login_form" method="post"
          action="<?php echo PHS::url( [ 'p' => 'accounts', 'a' => 'login' ] )?>">
        <input type="hidden" name="foobar" value="1" />
        <?php
        if( !empty( $back_page ) )
        {
            ?><input type="hidden" name="back_page" value="<?php echo form_str( safe_url( $back_page ) )?>" /><?php
        }
        ?>

        <div class="form_container">

            <section class="heading-bordered">
                <h3><?php echo $this->_pt( 'Login' )?></h3>
            </section>

            <fieldset class="form-group">
                <label for="nick"><?php echo (empty( $no_nickname_only_email )?$this->_pt( 'Username' ):$this->_pt( 'Email' ))?></label>
                <div class="lineform_line">
                <input type="text" id="nick" name="nick" class="form-control" required="required" value="<?php echo form_str( $this->view_var( 'nick' ) )?>" />
                </div>
            </fieldset>

            <fieldset class="form-group">
                <label for="pass"><?php echo $this->_pt( 'Password' )?></label>
                <div class="lineform_line">
                <input type="password" id="pass" name="pass" class="form-control" required="required" value="<?php echo form_str( $this->view_var( 'pass' ) )?>" />
                </div>
            </fieldset>

            <fieldset class="fixskin">
                <label for="do_remember"><input type="checkbox" value="1" name="do_remember" id="do_remember" rel="skin_checkbox" <?php echo $this->view_var( 'do_remember' )?> />
                    <strong><?php echo $this->_pt( 'Remember Me' ).(!empty( $remember_me_session_minutes )?$this->_pt( ' (for %s)', PHS_Utils::parse_period( $remember_me_session_minutes * 60, array( 'only_big_part' => true ) ) ):'')?></strong></label>
                <div class="clearfix"></div>
                <small><?php echo $this->_pt( 'Normal sessions will expire in %s.', PHS_Utils::parse_period( $this->view_var( 'normal_session_minutes' ) * 60, array( 'only_big_part' => true ) ) )?></small>
            </fieldset>

            <fieldset class="login_button">
                <input type="submit" id="do_submit" name="do_submit" class="btn btn-success btn-medium submit-protection ignore_hidden_required" value="<?php echo $this->_pte( 'Login' )?>" />
            </fieldset>

        </div>

        <div class="login_form_actions">
            <a class="forgot_pass" href="<?php echo PHS::url( array( 'p' => 'accounts', 'a' => 'forgot' ) )?>"><?php echo $this->_pt( 'Forgot password' )?></a>
            <?php
            $cuser_arr = PHS::account_structure( PHS::user_logged_in() );

            if( PHS_Roles::user_has_role_units( $cuser_arr, PHS_Roles::ROLEU_REGISTER ) )
            {
                ?>
                <span class="separator">|</span>
                <a class="register_acc" href="<?php echo PHS::url( array( 'p' => 'accounts', 'a' => 'register' ) )?>"><?php echo $this->_pt( 'Register an account' )?></a>
                <?php
            }
            ?>
        </div>
    </form>
</div>
