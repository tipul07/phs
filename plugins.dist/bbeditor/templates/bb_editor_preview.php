<?php

use \phs\PHS;

    /** @var \phs\system\core\views\PHS_View $this */

    /** @var \phs\plugins\bbeditor\libraries\Bbcode $bb_code_obj */
    if( !($bb_code_obj = $this->view_var( 'bb_code_obj' )) )
        return $this->_pt( 'Couldn\'t initialize BB code editor.' );

    if( !($bb_text = $this->view_var( 'bb_text' )) )
        $bb_text = '';
?>
<style>
.phs_bb_preview { width: 100%; font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; font-size: 14px; }
.phs_bb_preview table { width: 100%; font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; font-size: 14px; }
.ui-dialog .ui-dialog-content { padding: 0 !important; overflow: hidden; }
</style>
<div style="height:100%; width:100%;overflow: scroll;"><?php echo $bb_text?></div>
