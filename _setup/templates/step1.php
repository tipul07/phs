<?php
    /** @var \phs\setup\libraries\PHS_Setup_view $this */

    $this->set_context( 'page_title', 'Step 1' );

    var_dump( $_SERVER );

?>
<form id="phs_setup_step1" name="phs_setup_step1" method="post">
<fieldset class="form-group">
    <label for="phs_root_dir"><?php echo $this->_pt( 'PHS Root path' )?></label>
    <div class="lineform_line">
        <input type="text" id="phs_root_dir" name="phs_root_dir" class="form-control" value="<?php echo form_str( $this->get_context( 'phs_root_dir' ) )?>" placeholder="/path/to/phs/root/" style="width: 350px;" /><br/>
        <small>Absolute path on server to root of PHS framework.</small>
    </div>
</fieldset>

<fieldset class="form-group">
    <label for="phs_domain"><?php echo $this->_pt( 'PHS Domain' )?></label>
    <div class="lineform_line">
        <input type="text" id="phs_domain" name="phs_domain" class="form-control" value="<?php echo form_str( $this->get_context( 'phs_domain' ) )?>" placeholder="www.example.com" style="width: 350px;" /><br/>
        <small>Domain where framework is installed. Provide here only the domain, no paths or ports. (eg. www.example.com)</small>
    </div>
</fieldset>
</form>
