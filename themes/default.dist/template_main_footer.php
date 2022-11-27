<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_Roles;

    $cuser_arr = PHS::user_logged_in();

?>
<div id="footer_content">
    <div class="footerlinks clearfix">
        <?php
        if( PHS_Roles::user_has_role_units( $cuser_arr, PHS_Roles::ROLEU_CONTACT_US ) )
        {
            ?><a href="<?php echo PHS::url( ['a' => 'contact_us'])?>" ><?php echo $this::_t( 'Contact Us' )?></a> |<?php
        }
        ?>
        <a href="<?php echo PHS::url( ['a' => 'tandc'])?>" ><?php echo $this::_t( 'Terms and Conditions' )?></a>
    </div>
    <?php
    $debug_str = '';
    if( PHS::st_debugging_mode()
     && ($debug_data = PHS::platform_debug_data()) )
    {
        $debug_str = ' </br><span class="debug_str"> '.$debug_data['db_queries_count'].' queries, '.
                     ' bootstrap: '.number_format( $debug_data['bootstrap_time'], 6, '.', '' ).'s, '.
                     ' running: '.number_format( $debug_data['running_time'], 6, '.', '' ).'s, '.
                     ' peak mem: '.format_filesize( $debug_data['memory_peak'] ).
                     '</span>';
    }
    ?>
    <div><?php echo PHS_SITE_NAME.' (v'.PHS_SITEBUILD_VERSION.')'?> &copy; <?php echo date( 'Y' ).' '.$this::_t( 'All rights reserved.' ).$debug_str?> &nbsp;</div>
</div>
