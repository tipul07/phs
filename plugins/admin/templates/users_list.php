<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_utils;

    if( !($accounts_plugin_settings = $this->context_var( 'accounts_plugin_settings' )) )
        $accounts_plugin_settings = array();

    if( !($user_levels = $this->context_var( 'user_levels' )) )
        $user_levels = array();

    $current_user = PHS::user_logged_in();
?>
<div class="triggerAnimation animated fadeInRight" data-animate="fadeInRight" style="margin: 0 auto; float:right;">
    <form id="users_list_filters_form" name="users_list_filters_form" action="<?php echo PHS::url( array( 'p' => 'admin', 'a' => 'users_list' ) )?>" method="post" class="wpcf7">
        <input type="hidden" name="foobar" value="1" />

        <div class="form_container responsive">

            <section class="heading-bordered">
                <h3><?php echo $this::_t( 'Filters' )?></h3>
            </section>

            <div>
                <label for="nick"><?php echo $this::_t( 'Username' )?>:</label>
                <input type="text" id="nick" name="nick" class="wpcf7-text" required="required" value="<?php echo form_str( $this->context_var( 'nick' ) )?>" style="width: 260px;" autocomplete="off" /><br/>
            </div>

            <div>
                <label for="pass"><?php echo $this::_t( 'Password' )?>:</label>
                <div class="lineform_line">
                <input type="password" id="pass" name="pass" class="wpcf7-text" required="required" value="<?php echo form_str( $this->context_var( 'pass' ) )?>" style="width: 260px;" autocomplete="off" /><br/>
                <small><?php

                    echo $this::_t( 'Password should be at least %s characters.', $this->context_var( 'min_password_length' ) );

                    $pass_regexp = $this->context_var( 'password_regexp' );
                    if( !empty( $pass_regexp ) )
                    {
                        echo '<br/>'.$this::_t( 'Password should pass regular expresion: ' );

                        if( ($regexp_parts = explode( '/', $pass_regexp ))
                            and !empty( $regexp_parts[1] ) )
                        {
                            if( empty($regexp_parts[2]) )
                                $regexp_parts[2] = '';

                            ?><a href="https://regex101.com/?regex=<?php echo rawurlencode( $regexp_parts[1] )?>&options=<?php echo $regexp_parts[2]?>" title="Click for details" target="_blank"><?php echo $pass_regexp?></a><?php
                        } else
                            echo $this::_t( 'Password should pass regular expresion: %s.', $pass_regexp );
                    }

                ?></small>
                </div>
        </div>

            <fieldset class="lineform">
                <label for="email"><?php echo $this::_t( 'Email' )?>:</label>
                <input type="text" id="email" name="email" class="wpcf7-text" <?php echo (!empty( $accounts_plugin_settings['email_mandatory'] )?'required="required"':'')?> value="<?php echo form_str( $this->context_var( 'email' ) )?>" style="width: 260px;" autocomplete="off" />
            </fieldset>

            <fieldset class="lineform">
                <label for="level"><?php echo $this::_t( 'Level' )?>:</label>
                <select name="level" id="level" class="wpcf7-select">
                    <option value="0"><?php echo $this::_t( ' - Choose - ' )?></option>
                    <?php
                    foreach( $user_levels as $key => $level_details )
                    {
                        if( $key >= $current_user['level'] )
                            break;

                        ?><option value="<?php echo $key?>" <?php echo ($this->context_var( 'level' )==$key?'selected="selected"':'')?>><?php echo $level_details['title']?></option><?php
                    }
                    ?>
                </select>
            </fieldset>

            <fieldset class="lineform">
                <label for="fstatus"><?php echo $this::_t( 'Status' )?>:</label>
                <select name="fstatus" id="fstatus" class="wpcf7-select">
                    <option value="0"><?php echo $this::_t( ' - Choose - ' )?></option>
                    <?php
                    foreach( $user_levels as $key => $level_details )
                    {
                        if( $key >= $current_user['level'] )
                            break;

                        ?><option value="<?php echo $key?>" <?php echo ($this->context_var( 'level' )==$key?'selected="selected"':'')?>><?php echo $level_details['title']?></option><?php
                    }
                    ?>
                </select>
            </fieldset>

            <fieldset>
                <input type="submit" id="submit" name="submit" class="wpcf7-submit submit-protection" value="<?php echo $this::_te( 'Create Account' )?>" />
            </fieldset>

        </div>
    </form>
</div>
