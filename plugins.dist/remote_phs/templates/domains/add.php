<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;

    $current_user = PHS::user_logged_in();

    /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
    /** @var \phs\system\core\models\PHS_Model_Api_keys $apikeys_model */
    /** @var \phs\plugins\remote_phs\models\PHS_Model_Phs_Remote_domains $domains_model */
    if( !($accounts_model = $this->view_var( 'accounts_model' ))
     || !($apikeys_model = $this->view_var( 'accounts_model' ))
     || !($domains_model = $this->view_var( 'domains_model' )) )
        return $this->_pt( 'Error loading required resources.' );

    if( !($apikeys_arr = $this->view_var( 'apikeys_arr' )) )
        $apikeys_arr = [];
?>
<form id="add_remote_domain_form" name="add_remote_domain_form" method="post"
      action="<?php echo PHS::url( [ 'p' => 'remote_phs', 'c' => 'admin', 'a' => 'add', 'ad' => 'domains' ] )?>">
    <input type="hidden" name="foobar" value="1" />

    <div class="form_container">

        <section class="heading-bordered">
            <h3><?php echo $this->_pt( 'Add PHS Remote Domain' )?></h3>
        </section>

        <fieldset class="form-group">
            <label for="title"><?php echo $this->_pt( 'Title' )?></label>
            <div class="lineform_line">
                <input type="text" id="title" name="title" class="form-control"
                       value="<?php echo form_str( $this->view_var( 'title' ) )?>" /><br/>
                <small><?php echo $this->_pt( 'Short description to identify this remote domain' )?></small>
            </div>
        </fieldset>

        <fieldset class="form-group">
            <label for="handle"><?php echo $this->_pt( 'Handle' )?></label>
            <div class="lineform_line">
                <input type="text" id="handle" name="handle" class="form-control"
                       value="<?php echo form_str( $this->view_var( 'handle' ) )?>" /><br/>
                <small><?php echo $this->_pt( 'Identifier used to programatically identify this remote domain' )?></small>
            </div>
        </fieldset>

        <fieldset class="form-group">
            <label for="domain"><?php echo $this->_pt( 'Remote Domain' )?></label>
            <div class="lineform_line">
                <input type="text" id="domain" name="domain" class="form-control"
                       value="<?php echo form_str( $this->view_var( 'domain' ) )?>" /><br/>
                <small><?php echo $this->_pt( 'Only domain name of PHS remote domain (eg. www.example.com)' )?></small>
            </div>
        </fieldset>

        <fieldset class="form-group">
            <label for="apikey_id"><?php echo $this->_pt( 'Incomming API Key' )?>
                <i class="fa fa-question-circle" title="<?php echo $this->_pt( 'What API Key will remote PHS domain use to send requests to this platform.' )?>"></i>
            </label>
            <div class="lineform_line">
            <select name="apikey_id" id="apikey_id" class="chosen-select-nosearch w-100p">
                <option value=""><?php echo $this->_pt( ' - Choose - ' )?></option>
                <?php
                    $apikey_id = (int)$this->view_var( 'apikey_id' );
                    foreach( $apikeys_arr as $key => $text )
                    {
                        ?><option value="<?php echo $key?>" <?php echo ($apikey_id===$key?'selected="selected"':'')?>><?php echo $text?></option><?php
                    }
                ?>
            </select>
            </div>
        </fieldset>

        <fieldset class="form-group">
            <label for="out_apikey"><?php echo $this->_pt( 'Outgoing API Key' )?></label>
            <div class="lineform_line">
                <input type="text" id="out_apikey" name="out_apikey" class="form-control"
                       value="<?php echo form_str( $this->view_var( 'out_apikey' ) )?>" />
            </div>
        </fieldset>

        <fieldset class="form-group">
            <label for="out_apisecret"><?php echo $this->_pt( 'Outgoing API Secret' )?></label>
            <div class="lineform_line">
                <input type="text" id="out_apisecret" name="out_apisecret" class="form-control"
                       value="<?php echo form_str( $this->view_var( 'out_apisecret' ) )?>" /><br/>
                <small><?php echo $this->_pt( 'What API Key and API Secret should be used when sending requests to this domain?' )?></small>
            </div>
        </fieldset>

        <fieldset class="form-group">
            <label for="ips_whihtelist"><?php echo $this->_pt( 'Incomming IPs Whitelist' )?></label>
            <div class="lineform_line">
                <input type="text" id="ips_whihtelist" name="ips_whihtelist" class="form-control"
                       value="<?php echo form_str( $this->view_var( 'ips_whihtelist' ) )?>" /><br/>
                <small><?php echo $this->_pt( 'Comma separated IPs from where we will allow incomming requests. Empty = No restriction' )?></small>
            </div>
        </fieldset>

        <fieldset class="form-group">
            <label for="allow_incoming">
                <?php echo $this->_pt( 'Allow incomming calls' )?>
                <i class="fa fa-question-circle" title="<?php echo $this->_pte( 'Allow running actions with requests comming from this domain.' )?>"></i>
            </label>
            <div class="lineform_line">
                <input type="checkbox" value="1" name="allow_incoming" id="allow_incoming" rel="skin_checkbox"
                    <?php echo ($this->view_var( 'allow_incoming' )?'checked="checked"':'')?> />
            </div>
        </fieldset>

        <fieldset class="form-group">
            <label for="log_requests">
                <?php echo $this->_pt( 'Log Requests' )?>
                <i class="fa fa-question-circle" title="<?php echo $this->_pte( 'Log requests to and from this domain.' )?>"></i>
            </label>
            <div class="lineform_line">
                <input type="checkbox" value="1" name="log_requests" id="log_requests" rel="skin_checkbox"
                    <?php echo ($this->view_var( 'log_requests' )?'checked="checked"':'')?> />
            </div>
        </fieldset>

        <fieldset>
            <input type="submit" id="do_submit" name="do_submit" class="btn btn-primary submit-protection ignore_hidden_required"
                   value="<?php echo $this->_pte( 'Add PHS remote domain' )?>" />
        </fieldset>

    </div>
</form>
