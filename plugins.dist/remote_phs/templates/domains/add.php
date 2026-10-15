<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;

$apikeys_arr = $this->view_var('apikeys_arr') ?: [];
?>
<form id="add_remote_domain_form" name="add_remote_domain_form" method="post"
      action="<?php echo PHS::url(['p' => 'remote_phs', 'c' => 'admin', 'a' => 'add', 'ad' => 'domains']); ?>">
    <input type="hidden" name="foobar" value="1" />

    <div class="form_container">

        <section class="heading-bordered">
            <h3><?php echo $this->_pt('Add PHS Remote Domain'); ?></h3>
        </section>

        <div class="mb-2 row">
            <label class="col-2 col-form-label" for="title"><?php echo $this->_pt('Title'); ?></label>
            <div class="col-10">
                <input type="text" id="title" name="title" class="form-control"
                       value="<?php echo form_str($this->view_var('title')); ?>" />
                <small><?php echo $this->_pt('Short description to identify this remote domain'); ?></small>
            </div>
        </div>

        <div class="mb-2 row">
            <label class="col-2 col-form-label" for="handle"><?php echo $this->_pt('Handle'); ?></label>
            <div class="col-10">
                <input type="text" id="handle" name="handle" class="form-control"
                       value="<?php echo form_str($this->view_var('handle')); ?>" />
                <small><?php echo $this->_pt('Identifier used to programatically identify this remote domain'); ?></small>
            </div>
        </div>

        <div class="mb-2 row">
            <label class="col-2 col-form-label" for="remote_www"><?php echo $this->_pt('Full Remote Domain URL'); ?></label>
            <div class="col-10">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text">https://</span>
                    </div>
                    <input type="text" id="remote_www" name="remote_www" class="form-control"
                       value="<?php echo form_str($this->view_var('remote_www')); ?>" />
                </div>
                <small><?php echo $this->_pt('Full URL to root of PHS remote domain including domain path - if required - (eg. www.example.com/phs/)'); ?></small>
            </div>
        </div>

        <div class="mb-2 row">
            <label class="col-2 col-form-label" for="apikey_id"><?php echo $this->_pt('Incoming API Key'); ?>
                <i class="fa fa-question-circle" title="<?php echo $this->_pt('What API Key will remote PHS domain use to send requests to this platform.'); ?>"></i>
            </label>
            <div class="col-10">
            <select name="apikey_id" id="apikey_id" class="chosen-select-nosearch w-100p">
                <option value=""><?php echo $this->_pt(' - Choose - '); ?></option>
                <?php
                    $apikey_id = (int)$this->view_var('apikey_id');
foreach ($apikeys_arr as $key => $text) {
    ?><option value="<?php echo $key; ?>" <?php echo $apikey_id === $key ? 'selected="selected"' : ''; ?>><?php echo $text; ?></option><?php
}
?>
            </select>
            </div>
        </div>

        <div class="mb-2 row">
            <label class="col-2 col-form-label" for="out_apikey"><?php echo $this->_pt('Outgoing API Key'); ?></label>
            <div class="col-10">
                <input type="text" id="out_apikey" name="out_apikey" class="form-control"
                       value="<?php echo form_str($this->view_var('out_apikey')); ?>" />
            </div>
        </div>

        <div class="mb-2 row">
            <label class="col-2 col-form-label" for="out_apisecret"><?php echo $this->_pt('Outgoing API Secret'); ?></label>
            <div class="col-10">
                <input type="text" id="out_apisecret" name="out_apisecret" class="form-control"
                       value="<?php echo form_str($this->view_var('out_apisecret')); ?>" />
                <small><?php echo $this->_pt('What API Key and API Secret should be used when sending requests to this domain?'); ?></small>
            </div>
        </div>

        <div class="mb-2 row">
            <label class="col-2 col-form-label" for="out_timeout"><?php echo $this->_pt('Outgoing Timeout'); ?></label>
            <div class="col-10">
                <input type="text" id="out_timeout" name="out_timeout" class="form-control"
                       value="<?php echo form_str($this->view_var('out_timeout')); ?>" />
                <small><?php echo $this->_pt('After how many seconds should a request sent to this remote domain timeout? 0 - no timeout'); ?></small>
            </div>
        </div>

        <div class="mb-2 row">
            <label class="col-2 col-form-label" for="ips_whihtelist"><?php echo $this->_pt('Incoming IPs Whitelist'); ?></label>
            <div class="col-10">
                <input type="text" id="ips_whihtelist" name="ips_whihtelist" class="form-control"
                       value="<?php echo form_str($this->view_var('ips_whihtelist')); ?>" />
                <small><?php echo $this->_pt('Comma separated IPs from where we will allow incoming requests. Empty = No restriction'); ?></small>
            </div>
        </div>

        <div class="mb-2 row">
            <label class="col-2 col-form-label" for="allow_incoming">
                <?php echo $this->_pt('Allow incoming calls'); ?>
                <i class="fa fa-question-circle" title="<?php echo $this->_pte('Allow running actions with requests comming from this domain.'); ?>"></i>
            </label>
            <div class="col-10">
                <input type="checkbox" value="1" name="allow_incoming" id="allow_incoming" rel="skin_checkbox"
                    <?php echo $this->view_var('allow_incoming') ? 'checked="checked"' : ''; ?> />
            </div>
        </div>

        <div class="mb-2 row">
            <label class="col-2 col-form-label" for="log_requests">
                <?php echo $this->_pt('Log Requests'); ?>
                <i class="fa fa-question-circle" title="<?php echo $this->_pte('Log requests to and from this domain.'); ?>"></i>
            </label>
            <div class="col-10">
                <input type="checkbox" value="1" name="log_requests" id="log_requests" rel="skin_checkbox"
                    <?php echo $this->view_var('log_requests') ? 'checked="checked"' : ''; ?> />
            </div>
        </div>

        <div class="mb-2 row">
            <label class="col-2 col-form-label" for="log_body">
                <?php echo $this->_pt('Log Requests Body'); ?>
                <i class="fa fa-question-circle" title="<?php echo $this->_pte('If logging requests, should requests body be also logged?'); ?>"></i>
            </label>
            <div class="col-10">
                <input type="checkbox" value="1" name="log_body" id="log_body" rel="skin_checkbox"
                    <?php echo $this->view_var('log_body') ? 'checked="checked"' : ''; ?> />
            </div>
        </div>

        <div class="mb-2 row">
            <div>
            <input type="submit" id="do_submit" name="do_submit"
                   class="btn btn-primary submit-protection ignore_hidden_required"
                   value="<?php echo $this->_pte('Add PHS remote domain'); ?>" />
            </div>
        </div>

    </div>
</form>
