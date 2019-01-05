<?php
    /** @var \phs\setup\libraries\PHS_Setup_view $this */

    $this->set_context( 'page_title', $this->_pt( 'Step 4' ) );

    if( !($phs_crypt_key = $this->get_context( 'phs_crypt_key' )) )
        $phs_crypt_key = '';
    if( !($phs_crypt_internal_keys_arr = $this->get_context( 'phs_crypt_internal_keys_arr' )) )
        $phs_crypt_internal_keys_arr = array();
?>
<form id="phs_setup_step4" name="phs_setup_step4" method="post">
<input type="hidden" name="foobar" value="1" />
<fieldset class="form-group">
    <label for="phs_crypt_key"><?php echo $this->_pt( 'Crypting Key' )?></label>
    <div class="lineform_line">
        <input type="text" id="phs_crypt_key" name="phs_crypt_key" class="form-control" value="<?php echo form_str( $phs_crypt_key )?>" style="width: 350px;" /><br/>
        <small><?php echo $this->_pt( 'This is used as crypting key for everything that will be crypted. Once you use a crypting key make sure you dion\'t change it.' )?></small>
    </div>
</fieldset>

<fieldset class="form-group">
    <label for="phs_contact_email"><?php echo $this->_pt( 'Crypto Internal Keys Array' )?></label>
    <div class="lineform_line">
        <?php
        for( $i = 0; $i < 34; $i++ )
        {
            ?>
            <div style="width:40px;float;left;"><?php echo ($i+1).'. '?></div>
            <div style="float:left;"><input type="text" class="form-control" style="width: 350px;"
                   id="phs_crypt_internal_keys_arr<?php echo $i?>" name="phs_crypt_internal_keys_arr[]"
                   value="<?php echo (!empty( $phs_crypt_internal_keys_arr[$i] )?form_str( $phs_crypt_internal_keys_arr[$i] ):'')?>" />
            <small>(<?php echo $this->_pt( 'hexa, 32 chars string' )?>)</small>
            </div>
            <div style="clear:both;"></div>
            <?php
        }
        ?>
    </div>
</fieldset>

<fieldset>
    <div class="lineform_line">
        <input type="submit" id="do_submit" name="do_submit" class="btn btn-primary submit-protection" value="<?php echo $this->_pte( 'Continue' )?>" />
    </div>
</fieldset>

</form>
