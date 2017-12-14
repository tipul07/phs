<?php
    /** @var \phs\system\core\views\PHS_View $this */

    if( !($filters_buffer = $this->view_var( 'filters' )) )
        $filters_buffer = '';
    if( !($listing_buffer = $this->view_var( 'listing' )) )
        $listing_buffer = '';
    if( !($paginator_params = $this->view_var( 'paginator_params' )) )
        $paginator_params = array();
	if( !($flow_params = $this->view_var( 'flow_params' )) )
		$flow_params = array();		
?>
<div class="form_container">
<?php	
    if( !($paginator_params = $this->view_var( 'paginator_params' )) )
        $paginator_params = array();
    if( !($flow_params = $this->view_var( 'flow_params' )) )
        $flow_params = array();
?>
<section class="heading-bordered">
    <h3><?php echo (!empty( $flow_params['listing_title'] )?$flow_params['listing_title']:'')?></h3>
</section>
<?php
    echo $filters_buffer;
    echo $listing_buffer;
?>
</div>
