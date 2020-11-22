<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_Utils;

    if( !($current_user = PHS::user_logged_in()) )
        $current_user = false;

    if( !($accounts_settings = $this->view_var( 'accounts_settings' )) )
        $accounts_settings = array();

    if( !($no_nickname_only_email = $this->view_var( 'no_nickname_only_email' )) )
        $no_nickname_only_email = false;
    if( !($url_extra_args = $this->view_var( 'url_extra_args' ))
     or !is_array( $url_extra_args ) )
        $url_extra_args = false;
?>
<div style="max-width:1000px;margin: 0 auto;">
    <form id="change_password_form" name="change_password_form" action="<?php echo PHS::url( array( 'p' => 'accounts', 'a' => 'change_password' ), $url_extra_args )?>" method="post">
        <input type="hidden" name="foobar" value="1" />

        <div class="form_container responsive">

            <section class="heading-bordered">
                <h3><?php echo $this->_pt( 'Change Password' )?></h3>
            </section>

            <?php
            if( !empty( $current_user ) )
            {
                ?>
                <fieldset class="form-group">
                    <label for="nick"><?php echo (empty( $no_nickname_only_email )?$this->_pt( 'Username' ):$this->_pt( 'Email' ))?></label>
                    <div class="lineform_line">
                    <?php echo form_str( $this->view_var( 'nick' ) )?>
                    </div>
                </fieldset>

                <fieldset class="form-group">
                    <label for="pass"><?php echo $this->_pt( 'Current Password' )?></label>
                    <div class="lineform_line">
                    <input type="password" id="pass" name="pass" class="form-control" value="<?php echo form_str( $this->view_var( 'pass' ) )?>" required="required" />
                    </div>
                </fieldset>
                <?php
            }
            ?>

            <fieldset class="form-group">
                <label for="pass1"><?php echo $this->_pt( 'New Password' )?></label>
                <div class="lineform_line">
                <input type="password" id="pass1" name="pass1" class="form-control" value="<?php echo form_str( $this->view_var( 'pass1' ) )?>" required="required" /><br/>
                <small><?php

                echo $this->_pt( 'Password should be at least %s characters.', $this->view_var( 'min_password_length' ) );

                $pass_regexp = $this->view_var( 'password_regexp' );
                if( !empty( $accounts_settings['password_regexp_explanation'] ) )
                    echo ' '.$this->_pt( $accounts_settings['password_regexp_explanation'] );

                elseif( !empty( $pass_regexp ) )
                {
                    echo '<br/>'.$this->_pt( 'Password should pass regular expresion: ' );

                    if( ($regexp_parts = explode( '/', $pass_regexp ))
                    and !empty( $regexp_parts[1] ) )
                    {
                        if( empty($regexp_parts[2]) )
                            $regexp_parts[2] = '';

                        ?><a href="https://regex101.com/?regex=<?php echo rawurlencode( $regexp_parts[1] )?>&options=<?php echo $regexp_parts[2]?>" title="Click for details" target="_blank"><?php echo $pass_regexp?></a><?php
                    } else
                        echo $this->_pt( 'Password should pass regular expresion: %s.', $pass_regexp );
                }

                ?></small>
                </div>
            </fieldset>

            <fieldset class="form-group">
                <label for="pass2"><?php echo $this->_pt( 'Confirm Password' )?></label>
                <div class="lineform_line">
                <input type="password" id="pass2" name="pass2" class="form-control" value="<?php echo form_str( $this->view_var( 'pass2' ) )?>" required="required" />
                </div>
            </fieldset>

            <fieldset>
                <input type="submit" id="do_submit" name="do_submit" class="btn btn-primary submit-protection ignore_hidden_required" value="<?php echo $this->_pte( 'Change password' )?>" />
            </fieldset>

            <?php
                if( !empty( $current_user ) )
                {
                    ?>
                    <fieldset>
                        <a href="<?php echo PHS::url( array( 'p' => 'accounts', 'a' => 'edit_profile' ) ) ?>"><?php echo $this->_pt( 'Edit Profile' ) ?></a>
                    </fieldset>
                    <?php
                }
            ?>

        </div>
    </form>
</div>
