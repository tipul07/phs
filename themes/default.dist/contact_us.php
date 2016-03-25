<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
?>
<!-- BEGIN: main -->
<div class="triggerAnimation animated fadeInRight" data-animate="fadeInRight" style="min-width:700px;max-width:800px;margin: 0 auto;">
    <form id="contact_form" name="contact_form" action="<?php echo PHS::url( array( 'a' => 'contact_us' ) )?>" method="post" class="wpcf7">
        <input type="hidden" name="foobar" value="1" />


        <div class="form_container responsive" style="width: 650px;">

            <section class="heading-bordered">
                <h3><?php echo $this::_t( 'Contact Us' )?></h3>
            </section>

            <fieldset>
                <label for="nick"><?php echo $this::_t( 'Username' )?>:</label>
                <input type="text" id="nick" name="nick" class="wpcf7-text" required="required" value="<?php echo form_str( $this->context_var( 'nick' ) )?>" style="width: 260px;" />
            </fieldset>

            <fieldset>
                <label for="pass"><?php echo $this::_t( 'Password' )?>:</label>
                <input type="password" id="pass" name="pass" class="wpcf7-text" required="required" value="<?php echo form_str( $this->context_var( 'pass' ) )?>" style="width: 260px;" />
            </fieldset>

            <fieldset class="fixskin">
                <label for="do_remember"><input type="checkbox" value="1" name="do_remember" id="do_remember" rel="skin_checkbox" <?php echo $this->context_var( 'do_remember' )?> /> <strong><?php echo $this::_t( 'Remember Me' )?></strong></label>
            </fieldset>

            <fieldset>
                <input type="submit" id="submit" name="submit" class="wpcf7-submit submit-protection" value="<?php echo $this::_te( 'Login' )?>" />
            </fieldset>

            <fieldset>
                <a href="<?php echo PHS::url( array( 'p' => 'accounts', 'a' => 'forgot' ) )?>"><?php echo $this::_t( 'Forgot password' )?></a>
            </fieldset>

            <fieldset>
                <a href="<?php echo PHS::url( array( 'p' => 'accounts', 'a' => 'register' ) )?>"><?php echo $this::_t( 'Register an account' )?></a>
            </fieldset>

        </div>
    </form>
</div>

<div class="clearfix"></div>
<p>&nbsp;</p>
<!-- END: main -->
