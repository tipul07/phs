<?php
    /** @var \phs\setup\libraries\PHS_Setup_view $this */

    $this->set_context( 'page_title', 'Step 2' );
?>
<form id="phs_setup_step2" name="phs_setup_step2" method="post">
<fieldset class="form-group">
    <label for="phs_path"><?php echo $this->_pt( 'PHS Root path' )?></label>
    <div class="lineform_line">
        <input type="text" id="phs_path" name="phs_path" class="form-control" value="<?php echo form_str( $this->get_context( 'phs_path' ) )?>" placeholder="/path/to/phs/root/" style="width: 350px;" /><br/>
        <small>Absolute path on server to root of PHS framework.</small>
    </div>
</fieldset>

<fieldset class="form-group">
    <label for="phs_domain"><?php echo $this->_pt( 'PHS Domain' )?></label>
    <div class="lineform_line">
        <input type="text" id="phs_domain" name="phs_domain" class="form-control" value="<?php echo form_str( $this->get_context( 'phs_domain' ) )?>" placeholder="www.domain.com" style="width: 350px;" /><br/>
        <small>Domain where framework is installed. Provide here only the domain, no paths or ports. (eg. www.domain.com)</small>
    </div>
</fieldset>

<fieldset class="form-group">
    <label for="phs_ssl_domain"><?php echo $this->_pt( 'PHS SSL Domain' )?><br/><small><?php echo $this->_pt( 'for HTTPS requests' )?></small></label>
    <div class="lineform_line">
        <input type="text" id="phs_ssl_domain" name="phs_ssl_domain" class="form-control" value="<?php echo form_str( $this->get_context( 'phs_ssl_domain' ) )?>" placeholder="www.domain.com" style="width: 350px;" /><br/>
        <small>If secure requests (HTTPS) are made on other domain than "normal" one and that subdomain points to this framework. (eg. secure.domain.com).
               If not provided, system will use value provided at <em>PHS Domain</em>.</small>
    </div>
</fieldset>

<fieldset class="form-group">
    <label for="phs_cookie_domain"><?php echo $this->_pt( 'PHS Cookie Domain' )?></label>
    <div class="lineform_line">
        <input type="text" id="phs_cookie_domain" name="phs_cookie_domain" class="form-control" value="<?php echo form_str( $this->get_context( 'phs_cookie_domain' ) )?>" placeholder=".domain.com" style="width: 350px;" /><br/>
        <small>If you are using subdomains, you might want to setup the cookie for all subdomains (eg. ".domain.com" will make cookie available for all subdomains)
               If not provided, system will use value provided at <em>PHS Domain</em>.</small>
    </div>
</fieldset>

<fieldset class="form-group">
    <label for="phs_port"><?php echo $this->_pt( 'PHS Port' )?><br/><small><?php echo $this->_pt( 'for "normal" requests (HTTP)' )?></small></label>
    <div class="lineform_line">
        <input type="text" id="phs_port" name="phs_port" class="form-control" value="<?php echo form_str( $this->get_context( 'phs_port' ) )?>" placeholder="<?php echo $this->_pt( 'If 80, leave blank' )?>" style="width: 120px;" /><br/>
        <small>Port for "normal" requests (default 80). Leave blank if using default port 80.</small>
    </div>
</fieldset>

<fieldset class="form-group">
    <label for="phs_ssl_port"><?php echo $this->_pt( 'PHS SSL Port' )?><br/><small><?php echo $this->_pt( 'for HTTPS requests' )?></small></label>
    <div class="lineform_line">
        <input type="text" id="phs_ssl_port" name="phs_ssl_port" class="form-control" value="<?php echo form_str( $this->get_context( 'phs_ssl_port' ) )?>" placeholder="<?php echo $this->_pt( 'If 443, leave blank' )?>" style="width: 120px;" /><br/>
        <small>If you use a special port when accessing framework in a HTTPS request, please provide port. leave blank if using default port 443.</small>
    </div>
</fieldset>

<fieldset class="form-group">
    <label for="phs_domain_path"><?php echo $this->_pt( 'PHS Domain path' )?></label>
    <div class="lineform_line">
        <input type="text" id="phs_domain_path" name="phs_domain_path" class="form-control" value="<?php echo form_str( $this->get_context( 'phs_domain_path' ) )?>" placeholder="/url/path/to/root" style="width: 350px;" /><br/>
        <small>In case framework runs at a specific path in domain. (eg. full URL to root http://www.domain.com/phs_dir/framwork/ input here /phs_dir/framework).
            Leave blank if domain is dedicated to this framework.</small>
    </div>
</fieldset>

<fieldset>
    <div class="lineform_line">
        <input type="submit" id="do_test_connection" name="do_test_connection" class="btn btn-primary submit-protection" value="<?php echo $this->_pte( 'Test connection' )?>" />
        <input type="submit" id="do_submit" name="do_submit" class="btn btn-primary submit-protection" value="<?php echo $this->_pte( 'Continue' )?>" />
    </div>
</fieldset>

</form>
