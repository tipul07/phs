<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_Utils;

    /** @var \phs\system\core\models\PHS_Model_Roles $roles_model */
    /** @var \phs\system\core\models\PHS_Model_Plugins $plugins_model */
    if( !($roles_model = $this->view_var( 'roles_model' ))
     || !($plugins_model = $this->view_var( 'plugins_model' )) )
        return $this->_pt( 'Error loading required resources.' );

    if( !($accounts_plugin_settings = $this->view_var( 'accounts_plugin_settings' )) )
        $accounts_plugin_settings = [];

    if( !($user_levels = $this->view_var( 'user_levels' )) )
        $user_levels = [];

    if( !($roles_by_slug = $this->view_var( 'roles_by_slug' )) )
        $roles_by_slug = [];

    $current_user = PHS::user_logged_in();
?>
<form id="add_user_form" name="add_user_form" method="post" enctype="multipart/form-data"
      action="<?php echo PHS::url( ['p' => 'admin', 'a' => 'import', 'ad' => 'users' ] )?>">
<input type="hidden" name="foobar" value="1" />

<div class="form_container">

    <section class="heading-bordered">
        <h3><?php echo $this->_pt( 'Import User Accounts' )?></h3>
    </section>

    <div class="form-group row">
        <label for="prod_picture" class="col-sm-2 col-form-label"><?php echo $this->_pt( 'Import file' )?></label>
        <div class="col-sm-10">
            <div class="custom-file">
                <input type="file" class="custom-file-input" id="import_file" name="import_file"
                       value="<?php echo form_str( $this->view_var( 'import_file' ) )?>" />
                <label class="custom-file-label" for="import_file"><?php echo $this->_pt( 'Choose file' )?></label>
            </div>
            <div id="import_file_help" class="form-text">
                <?php echo $this->_pt( 'This should be a JSON file exported using Export options from accounts management page.' )?>
                <?php
                if( !($max_size = ini_get( 'upload_max_filesize' )) )
                    $max_size = '20M';

                echo $this->_pt( 'Current maximum upload file size %s.', $max_size )?>
            </div>
        </div>
    </div>

    <div class="form-group row">
        <label for="prod_picture" class="col-sm-2 col-form-label"><?php echo $this->_pt( 'Import options' )?></label>
        <div class="col-sm-10">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="insert_not_found" name="insert_not_found"
                <?php echo ($this->view_var( 'insert_not_found' )?'checked="checked"':'')?> value="1">
                <label class="form-check-label"
                       for="insert_not_found"><?php echo $this->_pt( 'Create non-existing accounts (if email is not found in database, create a new account)' )?></label>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="override_level" name="override_level"
                <?php echo ($this->view_var( 'override_level' )?'checked="checked"':'')?> value="1">
                <label class="form-override_level-label"
                       for="override_level"><?php echo $this->_pt( 'Override account level from database with the level found in import file' )?></label>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="update_roles" name="update_roles"
                <?php echo ($this->view_var( 'update_roles' )?'checked="checked"':'')?> value="1">
                <label class="form-check-label"
                       for="update_roles"><?php echo $this->_pt( 'Update roles (if account is found). Append roles found in import file.' )?></label>
            </div>
            <div class="pl-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="reset_roles" name="reset_roles"
                    <?php echo ($this->view_var( 'reset_roles' )?'checked="checked"':'')?> value="1">
                    <label class="form-check-label"
                           for="reset_roles"><?php echo $this->_pt( 'Remove existing roles and use the ones found in import file' )?></label>
                </div>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="update_details" name="update_details"
                <?php echo ($this->view_var( 'update_details' )?'checked="checked"':'')?> value="1">
                <label class="form-check-label"
                       for="update_details"><?php echo $this->_pt( 'Update user account details with data from import file' )?></label>
            </div>
        </div>
    </div>

    <div class="form-group row">
        <label for="import_level" class="col-sm-2 col-form-label"><?php echo $this->_pt( 'Import only level' )?></label>
        <div class="col-sm-10">
        <select name="import_level" id="import_level" class="chosen-select-nosearch" style="min-width:260px;">
            <option value="0"><?php echo $this->_pt( ' - Choose - ' )?></option>
            <?php
            $current_level = (int)$this->view_var( 'import_level' );
            foreach( $user_levels as $key => $level_details )
            {
                ?><option value="<?php echo $key?>"
                <?php echo ($current_level===$key?'selected="selected"':'')?>><?php echo $level_details['title']?></option><?php
            }
            ?>
        </select>
        <div id="import_level_help" class="form-text"><?php echo $this->_pt( 'Import only specific levels from import file.' )?></div>
        </div>
    </div>

    <div class="form-group row">
        <input type="submit" id="do_submit" name="do_submit"
               class="btn btn-primary submit-protection ignore_hidden_required"
               value="<?php echo $this->_pt( 'Import Accounts' )?>" />
    </div>

</div>
</form>
