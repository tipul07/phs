<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_utils;

    if( !($remember_me_session_minutes = $this->context_var( 'remember_me_session_minutes' )) )
        $remember_me_session_minutes = 0;

    if( !($no_nickname_only_email = $this->context_var( 'no_nickname_only_email' )) )
        $no_nickname_only_email = false;

    if( !($back_page = $this->context_var( 'back_page' )) )
        $back_page = '';
?>
<!-- BEGIN: main -->
<div class="triggerAnimation animated fadeInRight" data-animate="fadeInRight" style="min-width:600px;max-width:800px;margin: 0 auto;">
    <form id="login_form" name="login_form" action="<?php echo PHS::url( array( 'p' => 'accounts', 'a' => 'login' ) )?>" method="post" class="wpcf7">
        <input type="hidden" name="foobar" value="1" />
        <?php
        if( !empty( $back_page ) )
        {
            ?><input type="hidden" name="back_page" value="<?php echo form_str( safe_url( $back_page ) )?>" /><?php
        }
        ?>

        <div class="form_container responsive" style="width: 350px;">

            <section class="heading-bordered">
                <h3><?php echo $this->_pt( 'Login' )?></h3>
            </section>

            <fieldset>
                <label for="nick"><?php echo (empty( $no_nickname_only_email )?$this->_pt( 'Username' ):$this->_pt( 'Email' ))?></label>
                <input type="text" id="nick" name="nick" class="wpcf7-text" required="required" value="<?php echo form_str( $this->context_var( 'nick' ) )?>" style="width: 260px;" />
            </fieldset>

            <fieldset>
                <label for="pass"><?php echo $this->_pt( 'Password' )?></label>
                <input type="password" id="pass" name="pass" class="wpcf7-text" required="required" value="<?php echo form_str( $this->context_var( 'pass' ) )?>" style="width: 260px;" />
            </fieldset>

            <fieldset class="fixskin">
                <label for="do_remember"><input type="checkbox" value="1" name="do_remember" id="do_remember" class="wpcf7-text" rel="skin_checkbox" <?php echo $this->context_var( 'do_remember' )?> />
                    <strong><?php echo $this->_pt( 'Remember Me' ).(!empty( $remember_me_session_minutes )?$this->_pt( ' (for %s)', PHS_utils::parse_period( $remember_me_session_minutes * 60, array( 'only_big_part' => true ) ) ):'')?></strong></label>
                <div class="clearfix"></div>
                <small><?php echo $this->_pt( 'Normal sessions will expire in %s.', PHS_utils::parse_period( $this->context_var( 'normal_session_minutes' ) * 60, array( 'only_big_part' => true ) ) )?></small>
            </fieldset>

            <fieldset>
                <input type="submit" id="submit" name="submit" class="wpcf7-submit submit-protection" value="<?php echo $this->_pte( 'Login' )?>" />
            </fieldset>

            <fieldset>
                <a href="<?php echo PHS::url( array( 'p' => 'accounts', 'a' => 'forgot' ) )?>"><?php echo $this->_pt( 'Forgot password' )?></a>
            </fieldset>

            <fieldset>
                <a href="<?php echo PHS::url( array( 'p' => 'accounts', 'a' => 'register' ) )?>"><?php echo $this->_pt( 'Register an account' )?></a>
            </fieldset>

        </div>
    </form>
</div>

<div class="clearfix"></div>
<p>&nbsp;</p>
<!-- END: main -->
