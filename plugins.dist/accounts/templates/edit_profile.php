<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_utils;
?>
<div class="triggerAnimation animated fadeInRight" data-animate="fadeInRight" style="min-width:650px;max-width:1000px;margin: 0 auto;">
    <form id="edit_profile_form" name="edit_profile_form" action="<?php echo PHS::url( array( 'p' => 'accounts', 'a' => 'edit_profile' ) )?>" method="post" class="wpcf7">
        <input type="hidden" name="foobar" value="1" />

        <div class="form_container responsive" style="width: 650px;">

            <section class="heading-bordered">
                <h3><?php echo $this->_pt( 'Edit Profile' )?></h3>
            </section>

            <fieldset class="lineform">
                <label for="nick"><?php echo $this->_pt( 'Username' )?>:</label>
                <?php echo form_str( $this->context_var( 'nick' ) )?>
            </fieldset>

            <fieldset class="lineform">
                <label for="email"><?php echo $this->_pt( 'Email' )?>:</label>
                <div class="lineform_line">
                <input type="text" id="email" name="email" class="wpcf7-text" required="required" value="<?php echo form_str( $this->context_var( 'email' ) )?>" style="width: 260px;" /><br/>
                <?php
                if( !$this->context_var( 'email_verified' ) )
                    echo $this->_pt( 'Email is %s', '<span style="color: red;">'.$this->_pt( 'NOT VERIFIED' ).'</span>. <a href="'.$this->context_var( 'verify_email_link' ).'">'.$this->_pt( 'Send verification email' ).'</a>' );
                else
                    echo $this->_pt( 'Email is %s', '<span style="color: green;">'.$this->_pt( 'VERIFIED' ).'</span>.' );
                ?>
                </div>
            </fieldset>

            <fieldset class="lineform">
                <label for="title"><?php echo $this->_pt( 'Title' )?>:</label>
                <div class="lineform_line">
                <input type="text" id="title" name="title" class="wpcf7-text" value="<?php echo form_str( $this->context_var( 'title' ) )?>" style="width: 60px;" /><br/>
                <small><?php echo $this->_pt( 'eg. Mr., Ms., Mss., etc' )?></small>
                </div>
            </fieldset>

            <fieldset class="lineform">
                <label for="fname"><?php echo $this->_pt( 'First Name' )?>:</label>
                <input type="text" id="fname" name="fname" class="wpcf7-text" value="<?php echo form_str( $this->context_var( 'fname' ) )?>" style="width: 260px;" />
            </fieldset>

            <fieldset class="lineform">
                <label for="lname"><?php echo $this->_pt( 'Last Name' )?>:</label>
                <input type="text" id="lname" name="lname" class="wpcf7-text" value="<?php echo form_str( $this->context_var( 'lname' ) )?>" style="width: 260px;" />
            </fieldset>

            <fieldset class="lineform">
                <label for="phone"><?php echo $this->_pt( 'Phone Number' )?>:</label>
                <input type="text" id="phone" name="phone" class="wpcf7-text" value="<?php echo form_str( $this->context_var( 'phone' ) )?>" style="width: 260px;" />
            </fieldset>

            <fieldset class="lineform">
                <label for="company"><?php echo $this->_pt( 'Company' )?>:</label>
                <input type="text" id="company" name="company" class="wpcf7-text" value="<?php echo form_str( $this->context_var( 'company' ) )?>" style="width: 260px;" />
            </fieldset>

            <fieldset>
                <input type="submit" id="submit" name="submit" class="wpcf7-submit submit-protection" value="<?php echo $this->_pte( 'Save Changes' )?>" />
            </fieldset>

            <fieldset>
                <a href="<?php echo PHS::url( array( 'p' => 'accounts', 'a' => 'change_password' ) )?>"><?php echo $this->_pt( 'Change password' )?></a>
            </fieldset>

        </div>
    </form>
</div>
