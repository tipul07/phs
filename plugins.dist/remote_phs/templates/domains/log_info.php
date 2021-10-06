<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\PHS_Scope;
    use \phs\PHS_ajax;

    $current_user = PHS::user_logged_in();

    /** @var \phs\plugins\remote_phs\models\PHS_Model_Phs_remote_domains $domains_model */
    if( !($log_arr = $this->view_var( 'log_arr' ))
     || !($domains_model = $this->view_var( 'domains_model' )) )
        return $this->_pt( 'Could\'t loaded required resources for this view.' );

    if( !($domain_arr = $this->view_var( 'domain_arr' )) )
        $domain_arr = false;

    $current_scope = PHS_Scope::current_scope();
?>

<div class="form_container clearfix" style="width: 750px;padding:10px;">

    <section class="heading-bordered">
        <h3><?php echo $this->_pt( 'Domain log details' )?></h3>
    </section>

    <fieldset class="form-group">
        <label><?php echo $this->_pt( 'Remote Domain' )?></label>
        <div class="lineform_line">
        <?php
        if( empty( $domain_arr ) )
            echo $this->_pt( 'N/A' );

        else
        {
            echo '<strong>'.$domain_arr['title'].'</strong> (#'.$domain_arr['id'].')<br/>'.
                 $this->_pt( 'Created' ).': '.date( 'Y-m-d H:i', parse_db_date( $domain_arr['cdate'] ) );

            echo '<br/>'.$this->_pt( 'Last incoming' ).': ';
            if( empty( $domain_arr['last_incoming'] ) || empty_db_date( $domain_arr['last_incoming'] ) )
                echo $this->_pt( 'N/A' );
            else
                echo date( 'Y-m-d H:i', parse_db_date( $domain_arr['last_incoming'] ) );

            echo '<br/>'.$this->_pt( 'Last outgoing' ).': ';
            if( empty( $domain_arr['last_outgoing'] ) || empty_db_date( $domain_arr['last_outgoing'] ) )
                echo $this->_pt( 'N/A' );
            else
                echo date( 'Y-m-d H:i', parse_db_date( $domain_arr['last_outgoing'] ) );

            if( ($status_title = $domains_model->valid_status( $domain_arr['status'] )) )
                echo '<br/>'.$this->_pt( 'Status' ).' - '.$status_title['title']. ': '.date( 'Y-m-d H:i', parse_db_date( $domain_arr['status_date'] ) );
        }
        ?>
        </div>
    </fieldset>

    <fieldset class="form-group">
        <label><?php echo $this->_pt( 'Log Type' )?></label>
        <div class="lineform_line"><?php
            if( ($type_arr = $domains_model->valid_log_type( $log_arr['type'] )) )
                echo (!empty( $type_arr['title'] )?$type_arr['title']:$this->_pt( 'N/A' ));
            else
                echo $this->_pt( 'N/A' );
        ?></div>
    </fieldset>

    <fieldset class="form-group">
        <label><?php echo $this->_pt( 'Route' )?></label>
        <div class="lineform_line"><?php
            echo (!empty( $log_arr['route'] )?$log_arr['route']:$this->_pt( 'N/A' ));
        ?></div>
    </fieldset>

    <fieldset class="form-group">
        <label><?php echo $this->_pt( 'Body' )?></label>
        <div class="lineform_line">
            <?php
            if( empty( $log_arr['body'] ) )
                echo $this->_pt( 'N/A' );

            else
            {
                ?>
                <div> &laquo; <a href="javascript:void(0)" onclick="$('#phs_domain_log_body<?php echo $log_arr['id']?>').toggle()"><?php echo $this->_pt( 'Toggle' )?></a> &raquo; </div>
                <div id="phs_domain_log_body<?php echo $log_arr['id']?>" style="display:none"><pre><?php
                    echo @json_encode( @json_decode( $log_arr['body'], true ), JSON_PRETTY_PRINT );
                ?></pre></div>
                <?php
            }
            ?>
        </div>
    </fieldset>

    <fieldset class="form-group">
        <label><?php echo $this->_pt( 'Error' )?></label>
        <div class="lineform_line"><?php
            echo (!empty( $log_arr['error_log'] )?$log_arr['error_log']:$this->_pt( 'N/A' ));
        ?></div>
    </fieldset>

    <fieldset class="form-group">
        <label><?php echo $this->_pt( 'Status' )?></label>
        <div class="lineform_line"><?php
            if( ($status_arr = $domains_model->valid_log_status( $log_arr['status'] )) )
                echo (!empty( $status_arr['title'] )?$status_arr['title']:$this->_pt( 'N/A' ));
            else
                echo $this->_pt( 'N/A' );

            echo ' - ';

            if( empty( $log_arr['status_date'] ) || empty_db_date( $log_arr['status_date'] ) )
                echo $this->_pt( 'N/A' );
            else
                echo date( 'Y-m-d H:i', parse_db_date( $log_arr['status_date'] ) );
        ?></div>
    </fieldset>

</div>

<script type="text/javascript">
$(document).ready(function(){
    phs_refresh_input_skins();
});
</script>
