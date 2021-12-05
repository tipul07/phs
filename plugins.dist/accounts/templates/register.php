<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_Hooks;

    $trd_party_register_buffer = '';
    /** @var \phs\plugins\accounts_3rd\PHS_Plugin_Accounts_3rd $trd_party_plugin */
    if( ($trd_party_plugin = PHS::load_plugin( 'accounts_3rd' ))
     && $trd_party_plugin->plugin_active()
     && ($hook_args = PHS::trigger_hooks( $trd_party_plugin::H_ACCOUNTS_3RD_REGISTER_BUFFER, PHS_Hooks::default_buffer_hook_args() ))
     && is_array( $hook_args )
     && !empty( $hook_args['buffer'] ) )
    {
        $trd_party_register_buffer = $hook_args['buffer'];
    }

    if( !($no_nickname_only_email = $this->view_var( 'no_nickname_only_email' )) )
        $no_nickname_only_email = false;
?>
<div>
    <form id="register_form" name="register_form" action="<?php echo PHS::url( array( 'p' => 'accounts', 'a' => 'register' ) )?>" method="post">
        <input type="hidden" name="foobar" value="1" />

        <div class="form_container">

            <section class="heading-bordered">
                <h3><?php echo $this->_pt( 'Register an account' )?></h3>
            </section>

            <?php
            if( empty( $no_nickname_only_email ) )
            {
                ?>
                <fieldset class="form-group">
                    <label for="nick"><?php echo $this->_pt( 'Username' ) ?></label>
                    <div class="lineform_line">
                    <input type="text" id="nick" name="nick" class="form-control" required="required" value="<?php echo form_str( $this->view_var( 'nick' ) ) ?>" />
                    </div>
                </fieldset>
                <?php
            }
            ?>

            <fieldset class="form-group">
                <label for="email"><?php echo $this->_pt( 'Email' )?></label>
                <div class="lineform_line">
                <input type="text" id="email" name="email" class="form-control" required="required" value="<?php echo form_str( $this->view_var( 'email' ) )?>" />
                </div>
            </fieldset>

            <fieldset class="form-group">
                <label for="pass1"><?php echo $this->_pt( 'Password' )?></label>
                <div class="lineform_line">
                <input type="password" id="pass1" name="pass1" class="form-control" required="required" value="<?php echo form_str( $this->view_var( 'pass1' ) )?>" /><br/>
                <small><?php

                echo $this->_pt( 'Password should be at least %s characters.', $this->view_var( 'min_password_length' ) );

                $pass_regexp = $this->view_var( 'password_regexp' );
                if( !empty( $pass_regexp ) )
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
                <label for="pass2"><?php echo $this->_pt( 'Confirm password' )?></label>
                <div class="lineform_line">
                <input type="password" id="pass2" name="pass2" class="form-control" required="required" value="<?php echo form_str( $this->view_var( 'pass2' ) )?>" />
                </div>
            </fieldset>

            <?php
            $hook_params = array();
            $hook_params['extra_img_style'] = 'padding:3px;border:1px solid black;margin-bottom:3px;';

            if( ($captcha_buf = PHS_Hooks::trigger_captcha_display( $hook_params )) )
            {
                ?>
                <fieldset class="form-group">
                    <label for="vcode"><?php echo $this->_pt( 'Validation code' ) ?></label>
                    <div class="lineform_line">
                    <?php echo $captcha_buf; ?><br/>
                    <input type="text" id="vcode" name="vcode" class="form-control" required="required" value="<?php echo form_str( $this->view_var( 'vcode' ) )?>" style="max-width: 160px;" />
                    </div>
                </fieldset>
                <?php
            }
            ?>

            <fieldset>
                <input type="submit" id="do_submit" name="do_submit" class="btn btn-primary submit-protection ignore_hidden_required" value="<?php echo $this->_pte( 'Register' )?>" />
            </fieldset>

            <fieldset>
                <a href="<?php echo PHS::url( array( 'p' => 'accounts', 'a' => 'login' ) )?>"><?php echo $this->_pt( 'Already have an account' )?></a>
            </fieldset>

            <fieldset>
                <a href="<?php echo PHS::url( array( 'p' => 'accounts', 'a' => 'forgot' ) )?>"><?php echo $this->_pt( 'I just forgot my password' )?></a>
            </fieldset>

            <?php
            if( !empty( $trd_party_register_buffer ) )
            {
                echo $trd_party_register_buffer;
            }
            ?>

        </div>
    </form>
</div>
