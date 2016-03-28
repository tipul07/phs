<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_Hooks;
?>
<div class="triggerAnimation animated fadeInRight" data-animate="fadeInRight" style="min-width:700px;max-width:800px;margin: 0 auto;">
    <form id="contact_form" name="contact_form" action="<?php echo PHS::url( array( 'a' => 'contact_us' ) )?>" method="post" class="wpcf7">
        <input type="hidden" name="foobar" value="1" />


        <div class="form_container responsive" style="width: 650px;">

            <section class="heading-bordered">
                <h3><?php echo $this::_t( 'Contact Us' )?></h3>
            </section>

            <fieldset>
                <label for="email"><?php echo $this::_t( 'Email' )?>:</label>
                <input type="text" id="email" name="email" class="wpcf7-text" required="required" value="<?php echo form_str( $this->context_var( 'email' ) )?>" style="width: 350px;" />
            </fieldset>

            <fieldset>
                <label for="subject"><?php echo $this::_t( 'Subject' )?>:</label>
                <input type="text" id="subject" name="subject" class="wpcf7-text" required="required" value="<?php echo form_str( $this->context_var( 'subject' ) )?>" style="width: 350px;" />
            </fieldset>

            <fieldset>
                <label for="body"><?php echo $this::_t( 'Message' )?>:</label>
                <textarea id="body" name="body" class="wpcf7-text" required="required" style="width: 400px; height: 300px;"><?php echo $this->context_var( 'body' )?></textarea>
            </fieldset>

            <?php
                $hook_params = array();
                $hook_params['extra_img_style'] = 'padding:3px;border:1px solid black;';

                if( !PHS::user_logged_in()
                and ($captcha_buf = PHS_Hooks::trigger_captcha_display( $hook_params )) )
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
                <input type="submit" id="submit" name="submit" class="wpcf7-submit submit-protection" value="<?php echo $this::_te( 'Send Message' )?>" />
            </fieldset>

        </div>
    </form>
</div>

<div class="clearfix"></div>
<p>&nbsp;</p>
