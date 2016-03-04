<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_Hooks;
?>
<!-- BEGIN: main -->
<div class="triggerAnimation animated fadeInRight" data-animate="fadeInRight" style="min-width:600px;max-width:800px;margin: 0 auto;">
    <form id="register_form" name="register_form" action="<?php echo PHS::url( array( 'p' => 'accounts', 'a' => 'register' ) )?>" method="post" class="wpcf7">
        <input type="hidden" name="foobar" value="1" />


        <div class="form_container responsive" style="width: 450px;">

            <section class="heading-bordered">
                <h3><?php echo $this::_t( 'Register an account' )?></h3>
            </section>

            <fieldset>
                <label for="nick"><?php echo $this::_t( 'Username' )?>:</label>
                <input type="text" id="nick" name="nick" class="wpcf7-text" required="required" value="<?php echo form_str( $this->context_var( 'nick' ) )?>" style="width: 260px;" />
            </fieldset>

            <fieldset>
                <label for="email"><?php echo $this::_t( 'Email' )?>:</label>
                <input type="text" id="email" name="email" class="wpcf7-text" required="required" value="<?php echo form_str( $this->context_var( 'email' ) )?>" style="width: 260px;" />
            </fieldset>

            <fieldset>
                <label for="pass1"><?php echo $this::_t( 'Password' )?>:</label>
                <input type="password" id="pass1" name="pass1" class="wpcf7-text" required="required" value="<?php echo form_str( $this->context_var( 'pass1' ) )?>" style="width: 260px;" />
            </fieldset>

            <fieldset>
                <label for="pass2"><?php echo $this::_t( 'Confirm password' )?>:</label>
                <input type="password" id="pass2" name="pass2" class="wpcf7-text" required="required" value="<?php echo form_str( $this->context_var( 'pass2' ) )?>" style="width: 260px;" />
            </fieldset>

            <?php
            $hook_params = array();
            $hook_params['extra_img_style'] = 'padding:3px;border:1px solid black;';

            if( ($captcha_buf = PHS_Hooks::trigger_captcha_display( $hook_params )) )
            {
                ?>
                <fieldset>
                    <label for="vcode"><?php echo $this::_t( 'Validation code' ) ?>*</label>
                    <?php echo $captcha_buf; ?><br/>
                    <input type="text" id="vcode" name="vcode" class="wpcf7-text" required="required" value="<?php echo form_str( $this->context_var( 'vcode' ) )?>" style="width: 160px;" />
                </fieldset>
                <?php
            }
            ?>

            <fieldset>
                <input type="submit" id="submit" name="submit" class="wpcf7-submit submit-protection" value="<?php echo $this::_te( 'Register' )?>" />
            </fieldset>

            <fieldset>
                <a href="<?php echo PHS::url( array( 'p' => 'accounts', 'a' => 'login' ) )?>"><?php echo $this::_t( 'Already have an account' )?></a>
            </fieldset>

            <fieldset>
                <a href="<?php echo PHS::url( array( 'p' => 'accounts', 'a' => 'forgot' ) )?>"><?php echo $this::_t( 'I just forgot my password' )?></a>
            </fieldset>

        </div>
    </form>
</div>

<div class="clearfix"></div>
<p>&nbsp;</p>
<!-- END: main -->
