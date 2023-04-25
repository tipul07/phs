<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;
use phs\libraries\PHS_Utils;

if (!($accounts_settings = $this->view_var('accounts_settings'))) {
    $accounts_settings = [];
}

if (!($no_nickname_only_email = $this->view_var('no_nickname_only_email'))) {
    $no_nickname_only_email = false;
}
if (!($url_extra_args = $this->view_var('url_extra_args'))
 || !is_array($url_extra_args)) {
    $url_extra_args = [];
}
?>
<form id="setup_password_form" name="setup_password_form" method="post"
      action="<?php echo PHS::url(['p' => 'accounts', 'a' => 'setup_password'], $url_extra_args); ?>">
<input type="hidden" name="foobar" value="1" />

<div class="form_container">

    <section class="heading-bordered">
        <h3><?php echo $this->_pt('Password Setup'); ?></h3>
    </section>

    <div class="form-group row">
        <div class="col-sm-12 text-center">
        <?php echo $this->_pt('You are setting up a password for account %s.', $this->view_var('nick')); ?>
        </div>
    </div>

    <div class="form-group row">
        <label for="pass1" class="col-sm-2 col-form-label"><?php echo $this->_pt('Password'); ?></label>
        <div class="col-sm-10">
        <input type="password" id="pass1" name="pass1" class="form-control" required="required"
               value="<?php echo form_str($this->view_var('pass1')); ?>" /><br/>
        <small><?php

echo $this->_pt('Password should be at least %s characters.', $this->view_var('min_password_length'));

$pass_regexp = $this->view_var('password_regexp');
if (!empty($accounts_settings['password_regexp_explanation'])) {
    echo ' '.$this->_pt($accounts_settings['password_regexp_explanation']);
} elseif (!empty($pass_regexp)) {
    echo '<br/>'.$this->_pt('Password should pass regular expresion: ');

    if (($regexp_parts = explode('/', $pass_regexp))
    && !empty($regexp_parts[1])) {
        if (empty($regexp_parts[2])) {
            $regexp_parts[2] = '';
        }

        ?><a href="https://regex101.com/?regex=<?php echo rawurlencode($regexp_parts[1]); ?>&options=<?php echo $regexp_parts[2]; ?>" title="Click for details" target="_blank"><?php echo $pass_regexp; ?></a><?php
    } else {
        echo $this->_pt('Password should pass regular expresion: %s.', $pass_regexp);
    }
}

?></small>
        </div>
    </div>

    <div class="form-group row">
        <label for="pass2" class="col-sm-2 col-form-label"><?php echo $this->_pt('Confirm Password'); ?></label>
        <div class="col-sm-10">
        <input type="password" id="pass2" name="pass2" class="form-control" required="required"
               value="<?php echo form_str($this->view_var('pass2')); ?>" />
        </div>
    </div>

    <div class="form-group row">
        <input type="submit" id="do_submit" name="do_submit" class="btn btn-primary submit-protection ignore_hidden_required"
               value="<?php echo $this->_pte('Set password'); ?>" />
    </div>

</div>
</form>
