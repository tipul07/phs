<?php
/** @var phs\system\core\views\PHS_View $this */
$paginator_params = $this->view_var('paginator_params') ?: [];
$flow_params = $this->view_var('flow_params') ?: [];
?>
<div class="form_container">
<section class="heading-bordered">
    <h3><?php echo ($flow_params['listing_title'] ?? '') ?: ''; ?></h3>
</section>
<?php
echo $this->view_var('filters') ?: '';
echo $this->view_var('listing') ?: '';
?>
</div>
