<?php
    /** @var \phs\system\core\views\PHS_View $this */

    if( !($filters_buffer = $this->context_var( 'filters' )) )
        $filters_buffer = '';
    if( !($listing_buffer = $this->context_var( 'listing' )) )
        $listing_buffer = '';

    echo $filters_buffer;
    echo $listing_buffer;