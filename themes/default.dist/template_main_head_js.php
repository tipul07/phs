<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\libraries\PHS_Language;

?>
<script type="text/javascript" src="<?php echo $this->get_resource_url('js/jquery.js'); ?>"></script>
<script type="text/javascript" src="<?php echo $this->get_resource_url('js/jquery-ui.js'); ?>"></script>
<script type="text/javascript" src="<?php echo $this->get_resource_url('js/jquery.validate.js'); ?>"></script>
<script type="text/javascript" src="<?php echo $this->get_resource_url('js/jquery.checkbox.js'); ?>"></script>
<script type="text/javascript" src="<?php echo $this->get_resource_url('js/chosen.jquery.js'); ?>"></script>
<script type="text/javascript" src="<?php echo $this->get_resource_url('js/bootstrap.js'); ?>"></script>
<?php
if (($jq_datepicker_lang_url = $this->get_resource_url('js/jquery.ui.datepicker-'.PHS_Language::get_current_language().'.js'))
&& ($datepicker_lang_file = $this->get_resource_path('js/jquery.ui.datepicker-'.PHS_Language::get_current_language().'.js'))
&& @file_exists($datepicker_lang_file)) {
    ?><script type="text/javascript" src="<?php echo $jq_datepicker_lang_url; ?>"></script><?php
}
?>
<script type="text/javascript" src="<?php echo $this->get_resource_url('js/jsen.js.php'); ?>"></script>
<script type="text/javascript" src="<?php echo $this->get_resource_url('js/base.js.php'); ?>"></script>
