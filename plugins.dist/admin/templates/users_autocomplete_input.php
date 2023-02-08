<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;
use phs\PHS_Ajax;

if (!($id_id = $this->view_var('id_id'))
 || !($id_name = $this->view_var('id_name'))
 || !($text_id = $this->view_var('text_id'))
 || !($text_name = $this->view_var('text_name'))) {
    return '<!-- Autocomplete not setup correctly -->';
}

if (!($css_style = $this->view_var('text_css_style'))) {
    $css_style = '';
}

$css_style .= 'width:90%;';
?>
<input type="hidden" id="<?php echo $id_id; ?>" name="<?php echo $id_name; ?>" value="<?php echo form_str($this->view_var('id_value')); ?>" />
<input type="text" id="<?php echo $text_id; ?>" name="<?php echo $text_name; ?>" class="<?php echo $this->view_var('text_css_classes'); ?>" value="<?php echo form_str($this->view_var('text_value')); ?>" <?php echo !empty($css_style) ? 'style="'.$css_style.'"' : ''; ?> />
<a href="javascript:void(0)" onclick="phs_reset_accounts_autocomplete( '<?php echo $id_id; ?>', '<?php echo $text_id; ?>' )" class="action-icons fa fa-refresh" onfocus="this.blur()"></a>
