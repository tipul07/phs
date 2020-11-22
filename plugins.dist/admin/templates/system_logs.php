<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\PHS_Ajax;

    if( !($logging_files_arr = $this->view_var( 'logging_files_arr' )) )
        $logging_files_arr = array();
    if( !($log_lines = $this->view_var( 'log_lines' )) )
        $log_lines = 20;
?>
<div style="margin: 0 auto;">
    <form id="system_logs_form" name="system_logs_form" action="<?php echo PHS::url( array( 'p' => 'admin', 'a' => 'system_logs' ) )?>" method="post">
    <input type="hidden" name="foobar" value="1" />

    <div class="form_container responsive" style="width: 100%;">

        <section class="heading-bordered">
            <h3><?php echo $this->_pt( 'System Logs' )?></h3>
        </section>
        <div class="clearfix"></div>

            <fieldset class="form-group">
                <label for="log_file"><?php echo $this->_pt( 'Log file' )?></label>
                <div class="lineform_line">
                <select name="log_file" id="log_file" class="chosen-select" style="width:350px;">
                <option value=""><?php echo $this->_pt( ' - Choose - ' )?></option>
                <?php
                $selected_log_file = $this->view_var( 'log_file' );
                $full_filename = '';
                /** @var \phs\libraries\PHS_Plugin $plugin_instance */
                foreach( $logging_files_arr as $file )
                {
                    $file_name = basename( $file );
                    if( empty( $file_name ) )
                        continue;

                    $extra_str = '';
                    if( $file_name == $selected_log_file )
                    {
                        $extra_str = 'selected="selected"';
                        $full_filename = $file;
                    }


                    ?><option value="<?php echo $file_name?>" <?php echo $extra_str?>><?php echo $file_name?></option><?php
                }
                ?>
                </select>
                <?php echo $this->_pt( 'lines' )?>
                <input type="text" name="log_lines" id="log_lines" value="<?php echo form_str( $this->view_var( 'log_lines' ) )?>" class="form-control" style="width:100px;" />
                <input type="button" id="do_submit" name="do_submit" class="btn btn-primary" value="<?php echo $this->_pte( 'View file' )?>" onclick="refresh_log_view()" />
                </div>
            </fieldset>

        <?php
        $we_have_logfile = false;
        if( !empty( $full_filename )
        and @file_exists( $full_filename ) )
            $we_have_logfile = true;
        ?>
        <div id="log_container"></div>

    </div>
    </form>
</div>
<script type="text/javascript">
function refresh_log_view()
{
    var log_file_obj = $("#log_file");
    var log_lines_obj = $("#log_lines");
    if( !log_file_obj || !log_lines_obj )
        return;

    send_server_request( { command: "display_file", log_file: log_file_obj.val(), log_lines: log_lines_obj.val() } );
}

function do_download_log_file()
{
    var log_file_obj = $("#log_file");
    if( !log_file_obj )
        return;

    document.location = "<?php echo PHS::url( array( 'p' => 'admin', 'a' => 'system_logs' ), array( 'command' => 'download_file' ), array( 'raw_params' => array( 'log_file' => '" + log_file_obj.val() + "' ) ) )?>";
    // send_server_request( { command: "download_file", log_file: log_file_obj.val() } );
}

function send_server_request( data )
{
    var defaults = {
        command           : 'display_file',
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

    var ajax_obj = PHS_JSEN.do_ajax( "<?php echo PHS_Ajax::url( array( 'p' => 'admin', 'a' => 'system_logs' ) )?>", ajax_params );
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
