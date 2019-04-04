<?php
    /** @var \phs\setup\libraries\PHS_Setup_view $this */

    $this->set_context( 'page_title', 'Step 2' );

    if( !($db_drivers_arr = $this->get_context( 'db_drivers_arr' )) )
        $db_drivers_arr = array();
?>
<form id="phs_setup_step2" name="phs_setup_step2" method="post">
<input type="hidden" name="foobar" value="1" />
<fieldset class="form-group">
    <label><?php echo $this->_pt( 'DB Driver' )?></label>
    <div class="lineform_line">
        <?php echo $this->_pt( 'MySQLi' );?><br/>
        <small><?php echo $this->_pt( 'Default MySQLi driver connection settings. PHS uses a MySQL database structure, so it requires a MySQL server.' )?></small>
    </div>
</fieldset>

<fieldset class="form-group">
    <label for="phs_db_hostname"><?php echo $this->_pt( 'DB host' )?></label>
    <div class="lineform_line">
        <input type="text" id="phs_db_hostname" name="phs_db_hostname" class="form-control" value="<?php echo form_str( $this->get_context( 'phs_db_hostname' ) )?>" placeholder="localhost" style="width: 350px;" />
    </div>
</fieldset>

<fieldset class="form-group">
    <label for="phs_db_username"><?php echo $this->_pt( 'DB Username' )?></label>
    <div class="lineform_line">
        <input type="text" id="phs_db_username" name="phs_db_username" class="form-control" value="<?php echo form_str( $this->get_context( 'phs_db_username' ) )?>" style="width: 350px;" />
    </div>
</fieldset>

<fieldset class="form-group">
    <label for="phs_db_password"><?php echo $this->_pt( 'DB Password' )?></label>
    <div class="lineform_line">
        <input type="text" id="phs_db_password" name="phs_db_password" class="form-control" value="<?php echo form_str( $this->get_context( 'phs_db_password' ) )?>" style="width: 350px;" />
    </div>
</fieldset>

<fieldset class="form-group">
    <label for="phs_db_database"><?php echo $this->_pt( 'Database' )?></label>
    <div class="lineform_line">
        <input type="text" id="phs_db_database" name="phs_db_database" class="form-control" value="<?php echo form_str( $this->get_context( 'phs_db_database' ) )?>" style="width: 350px;" /><br/>
        <small>Database should already exist and provided user must have all rights on that database.</small>
    </div>
</fieldset>

<fieldset class="form-group">
    <label for="phs_db_prefix"><?php echo $this->_pt( 'Tables prefix' )?></label>
    <div class="lineform_line">
        <input type="text" id="phs_db_prefix" name="phs_db_prefix" class="form-control" value="<?php echo form_str( $this->get_context( 'phs_db_prefix' ) )?>" style="width: 150px;" /><br/>
        <small>What prefix should be used when naming tables in database. If you use this database only for this famework you can leave this blank.</small>
    </div>
</fieldset>

<fieldset class="form-group">
    <label for="phs_db_port"><?php echo $this->_pt( 'DB Port' )?></label>
    <div class="lineform_line">
        <input type="text" id="phs_db_port" name="phs_db_port" class="form-control" value="<?php echo form_str( $this->get_context( 'phs_db_port' ) )?>" placeholder="3306" style="width: 100px;" />
    </div>
</fieldset>

<fieldset class="form-group">
    <label for="phs_db_charset"><?php echo $this->_pt( 'DB Charset' )?></label>
    <div class="lineform_line">
        <input type="text" id="phs_db_charset" name="phs_db_charset" class="form-control" value="<?php echo form_str( $this->get_context( 'phs_db_charset' ) )?>" placeholder="UTF8" style="width: 150px;" /><br/>
        <small>What character set should be used when creating connection to database server.</small>
    </div>
</fieldset>

<fieldset class="form-group">
    <label for="phs_db_use_pconnect"><?php echo $this->_pt( 'Permanent Connections' )?></label>
    <div class="lineform_line">
        <input type="checkbox" id="phs_db_use_pconnect" name="phs_db_use_pconnect" class="form-control" value="1" <?php echo ($this->get_context( 'phs_db_use_pconnect' )?'checked="checked"':'')?> /><br/>
        <small>Use permanent connections when connecting to database server. (if driver supports this)</small>
    </div>
</fieldset>

<fieldset class="form-group">
    <label for="phs_db_driver_settings"><?php echo $this->_pt( 'Driver Settings' )?></label>
    <div class="lineform_line">
        <input type="text" id="phs_db_driver_settings" name="phs_db_driver_settings" class="form-control" value="<?php echo form_str( $this->get_context( 'phs_db_driver_settings' ) )?>" style="width: 550px;" /><br/>
        <small>This is a JSON string which holds special driver settings.</small>
    </div>
</fieldset>

<fieldset>
    <div class="lineform_line">
        <input type="submit" id="do_test_connection" name="do_test_connection" class="btn btn-primary submit-protection" value="<?php echo $this->_pte( 'Test connection' )?>" />
        <input type="submit" id="do_submit" name="do_submit" class="btn btn-primary submit-protection" value="<?php echo $this->_pte( 'Continue' )?>" />
    </div>
</fieldset>

</form>
