<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\PHS_ajax;

    if( !($plugin_names_arr = $this->context_var( 'plugin_names_arr' )) )
        $plugin_names_arr = array();
    if( !($check_plugin = $this->context_var( 'check_plugin' )) )
        $check_plugin = '';
?>
<div style="margin: 0 auto;">
    <form id="plugins_integrity_form" name="plugins_integrity_form" action="<?php echo PHS::url( array( 'p' => 'admin', 'a' => 'plugins_integrity' ) )?>" method="post">
    <input type="hidden" name="foobar" value="1" />

    <div class="form_container responsive" style="width: 100%;">

        <section class="heading-bordered">
            <h3><?php echo $this->_pt( 'Plugins\' Integrity' )?></h3>
        </section>

        <fieldset class="form-group">
            <label for="check_plugin"><?php echo $this->_pt( 'Plugin to check' )?></label>
            <div class="lineform_line">
            <select name="check_plugin" id="check_plugin" class="chosen-select" style="width:350px;">
            <option value=""><?php echo $this->_pt( ' - All plugins - ' )?></option>
            <?php
            $plugin_found = false;
            foreach( $plugin_names_arr as $plugin_name )
            {
                if( empty( $plugin_name ) )
                    continue;

                if( $check_plugin == $plugin_name )
                    $plugin_found = true;

                ?><option value="<?php echo $plugin_name?>" <?php echo ($check_plugin==$plugin_name?'selected="selected"':'')?>><?php echo $plugin_name?></option><?php
            }

            if( empty( $plugin_found ) )
                $check_plugin = '';
            ?>
            </select>
            <input type="button" id="do_submit" name="do_submit" class="btn btn-primary" value="<?php echo $this->_pte( 'Integrity check' )?>" onclick="refresh_log_view()" />
            </div>
        </fieldset>

        <div id="check_container"></div>

    </div>
    </form>
</div>
<script type="text/javascript">
function refresh_log_view()
{
    var check_plugin_obj = $("#check_plugin");
    if( !check_plugin_obj )
        return;

    send_server_request( { command: "integrity_check", check_plugin: check_plugin_obj.val() } );
}
function send_server_request( data )
{
    var defaults = {
        command           : 'integrity_check',
        log_file          : "",
        log_lines         : <?php echo (!empty( $log_lines )?intval( $log_lines ):20)?>,
        search_term       : ""
    };

    var request_data = $.extend( {}, defaults, data );

    PHS_JSEN.js_messages_hide_all();

    show_submit_protection( "<?php echo $this->_pte( 'Sending request to server... Please wait.' )?>" );

    var ajax_params = {
        cache_response: false,
        method: 'post',
        url_data: request_data,
        data_type: 'html',
        full_buffer: true,

        onsuccess: function( response, status, ajax_obj, response_data ) {
            hide_submit_protection();

            var log_container_obj = $("#log_container");
            if( !log_container_obj )
                return;

            if( request_data.command == "display_file" )
                log_container_obj.html( response );

            //PHS_JSEN.js_messages( [ "<?php echo $this->_pt( 'Message deleted with success.' )?>" ], "success" );
        },

        onfailed: function( ajax_obj, status, error_exception ) {
            hide_submit_protection();

            PHS_JSEN.js_messages( [ "<?php echo $this->_pt( 'Error sending request to server. Please retry.' )?>" ], "error" );
        }
    };

    var ajax_obj = PHS_JSEN.do_ajax( "<?php echo PHS_ajax::url( array( 'p' => 'admin', 'a' => 'system_logs' ) )?>", ajax_params );
}
<?php
if( $we_have_logfile )
{
    ?>
    send_server_request( { command: "display_file", log_file: "<?php echo $selected_log_file?>", log_lines: <?php echo $log_lines?> } );
    <?php
}
?>
</script>
