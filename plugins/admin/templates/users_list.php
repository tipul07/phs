<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_utils;

    if( !($accounts_plugin_settings = $this->context_var( 'accounts_plugin_settings' )) )
        $accounts_plugin_settings = array();

    if( !($filters_buffer = $this->context_var( 'filters' )) )
        $filters_buffer = '';
    if( !($listing_buffer = $this->context_var( 'listing' )) )
        $listing_buffer = '';

    echo $filters_buffer;
    echo $listing_buffer;
