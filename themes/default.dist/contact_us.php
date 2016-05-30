<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_Hooks;
?>
<div style="min-width:100%;max-width:800px;margin: 0 auto;">
    <form id="contact_form" name="contact_form" action="<?php echo PHS::url( array( 'a' => 'contact_us' ) )?>" method="post">
        <input type="hidden" name="foobar" value="1" />


        <div class="form_container" style="width: 650px;">

            <section class="heading-bordered">
                <h3><?php echo $this::_t( 'Contact Us' )?></h3>
            </section>

            <fieldset class="form-group">
                <label for="email"><?php echo $this::_t( 'Email' )?></label>
                <div class="lineform_line">
                <input type="text" id="email" name="email" class="form-control" required="required" value="<?php echo form_str( $this->context_var( 'email' ) )?>" style="width: 350px;" />
                </div>
            </fieldset>

            <fieldset class="form-group">
                <label for="subject"><?php echo $this::_t( 'Subject' )?></label>
                <div class="lineform_line">
                <input type="text" id="subject" name="subject" class="form-control" required="required" value="<?php echo form_str( $this->context_var( 'subject' ) )?>" style="width: 350px;" />
                </div>
            </fieldset>

            <fieldset class="form-group">
                <label for="body"><?php echo $this::_t( 'Message' )?></label>
                <div class="lineform_line">
                <textarea id="body" name="body" class="form-control" required="required" style="width: 400px; height: 300px;"><?php echo $this->context_var( 'body' )?></textarea>
                </div>
            </fieldset>

            <?php
                $hook_params = array();
                $hook_params['extra_img_style'] = 'padding:3px;border:1px solid black;';

                if( !PHS::user_logged_in()
                and ($captcha_buf = PHS_Hooks::trigger_captcha_display( $hook_params )) )
                {
                    ?>
                    <fieldset class="form-group">
                        <label for="vcode"><?php echo $this::_t( 'Validation code' ) ?></label>
                        <div class="lineform_line">
                        <?php echo $captcha_buf; ?><br/>
                        <input type="text" id="vcode" name="vcode" class="form-control" required="required" value="<?php echo form_str( $this->context_var( 'vcode' ) )?>" style="width: 160px;" />
                        </div>
                    </fieldset>
                    <?php
                }
            ?>

            <fieldset>
                <input type="submit" id="submit" name="submit" class="btn btn-primary submit-protection" value="<?php echo $this::_te( 'Send Message' )?>" />
            </fieldset>

        </div>
    </form>
</div>

<div class="clearfix"></div>
<p>&nbsp;</p>
