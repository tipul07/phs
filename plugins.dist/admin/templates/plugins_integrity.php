<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\PHS_Ajax;

    if( !($plugin_names_arr = $this->view_var( 'plugin_names_arr' )) )
        $plugin_names_arr = array();
    if( !($check_plugin = $this->view_var( 'check_plugin' )) )
        $check_plugin = '';
    if( !($PLUGIN_NAME_ALL = $this->view_var( 'PLUGIN_NAME_ALL' )) )
        $PLUGIN_NAME_ALL = '_all_';
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
            <option value="<?php echo $PLUGIN_NAME_ALL?>"><?php echo $this->_pt( ' - All plugins - ' )?></option>
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
            <input type="button" id="do_submit" name="do_submit" class="btn btn-primary" value="<?php echo $this->_pte( 'Integrity check' )?>" onclick="check_integrity()" />
            </div>
        </fieldset>

        <div id="integrity_progressbar" style="display:none;"><div class="progress-label" id="integrity_progrss_label"><?php echo $this->_pt( 'Please wait...' )?></div></div>

        <div id="check_container"></div>

    </div>
    </form>
</div>
<div id="plugin_integrity_header" style="display:none;">
    <section class="heading-bordered">
        <h3 id="integrity_title"><?php echo $this->_pt( 'Checking plugin integrity...' )?></h3>
    </section>
</div>
<script type="text/javascript">
var all_plugin_names = <?php echo @json_encode( $plugin_names_arr )?>;
var running_all_plugins = false;
var plugin_index = -1;
function check_integrity()
{
    var check_plugin_obj = $("#check_plugin");
    var check_container_obj = $("#check_container");
    var container_header_obj = $("#plugin_integrity_header");
    var integrity_title_obj = $("#integrity_title");
    var do_submit_obj = $("#do_submit");
    if( !check_plugin_obj || !check_container_obj || !container_header_obj
     || !integrity_title_obj || !do_submit_obj )
        return;

    check_container_obj.html( "" );
    do_submit_obj.prop( "disabled", true );

    //show_submit_protection( "<?php echo $this->_pte( 'Sending request to server... Please wait.' )?>" );

    var plugin_name = check_plugin_obj.val();
    if( plugin_name != "<?php echo $PLUGIN_NAME_ALL?>" )
    {
        plugin_index = -1;
        running_all_plugins = false;

        integrity_title_obj.html( "<?php echo str_replace( '\\" + plugin_name + \\"', '" + plugin_name + "', $this::_e( $this->_pt( 'Checking integrity for plugin %s...', '" + plugin_name + "' ) ) )?>" );

        check_container_obj.html( container_header_obj.html() );

        hide_progress_bar();
        send_server_request( { command: "integrity_check", check_plugin: plugin_name } );
    } else
    {
        integrity_title_obj.html( "<?php echo $this->_pte( 'Checking integrity for ALL plugins...' )?>" );

        plugin_index = 0;
        running_all_plugins = true;

        if( typeof all_plugin_names[plugin_index] == "undefined" )
            check_container_obj.append( "<p><?php echo $this->_pt( 'Didn\'t find a valid value in plugins list to check integrity.' )?></p>" );

        else
        {
            init_progress_bar();
            send_server_request( { command: "integrity_check", check_plugin: all_plugin_names[plugin_index] } );
        }
    }
}
function send_server_request( data )
{
    var defaults = {
        command           : 'integrity_check',
        check_plugin      : ""
    };

    var request_data = $.extend( {}, defaults, data );

    PHS_JSEN.js_messages_hide_all();

    var ajax_params = {
        cache_response: false,
        method: 'post',
        url_data: request_data,
        data_type: 'html',
        full_buffer: true,

        onsuccess: function( response, status, ajax_obj, response_data ) {

            var check_container_obj = $("#check_container");
            if( !check_container_obj )
                return;

            if( request_data.command == "integrity_check" )
            {
                check_container_obj.append( response );

                if( running_all_plugins && plugin_index != -1 )
                {
                    setTimeout( continue_integrity_check, 100 );
                } else
                    finish_single_request();
            }

            //PHS_JSEN.js_messages( [ "<?php echo $this->_pt( 'Message deleted with success.' )?>" ], "success" );
        },

        onfailed: function( ajax_obj, status, error_exception ) {
            hide_submit_protection();

            PHS_JSEN.js_messages( [ "<?php echo $this->_pt( 'Error sending request to server. Please retry.' )?>" ], "error" );
        }
    };

    var ajax_obj = PHS_JSEN.do_ajax( "<?php echo PHS_Ajax::url( array( 'p' => 'admin', 'a' => 'plugins_integrity' ), false, array( 'raw_params' => array( '_r' => '" + Math.round(((new Date()).getTime()-Date.UTC(1970,0,1))/1000) + "' ) ) )?>", ajax_params );
}
function finish_single_request()
{
    hide_submit_protection();

    var check_container_obj = $("#check_container");
    var do_submit_obj = $("#do_submit");
    if( !check_container_obj || !do_submit_obj )
        return;

    check_container_obj.append( "<p><strong><?php echo $this->_pt( 'DONE' )?></strong></p>" );

    do_submit_obj.prop( "disabled", false );
}
function continue_integrity_check()
{
    if( !running_all_plugins )
        return;

    plugin_index++;
    if( typeof all_plugin_names[plugin_index] != "undefined" )
    {
        update_progress_bar( plugin_index+1 );
        send_server_request( { command: "integrity_check", check_plugin: all_plugin_names[plugin_index] } );
    } else
    {
        hide_submit_protection();

        plugin_index = -1;
        running_all_plugins = false;

        var check_container_obj = $("#check_container");
        var do_submit_obj = $("#do_submit");
        if( !check_container_obj || !do_submit_obj )
            return;

        check_container_obj.append( "<hr/><p><strong><?php echo $this->_pt( 'DONE' )?></strong></p>" );

        do_submit_obj.prop( "disabled", false );

    }
}
function init_progress_bar()
{
    var progressbar_obj = $("#integrity_progressbar"),
        progresslabel_obj = $("#integrity_progrss_label");

    if( !progressbar_obj || !progresslabel_obj )
        return;

    progressbar_obj.progressbar({
        value: 0,
        max: <?php echo count( $plugin_names_arr )?>,
        change: function() {
            var cur_val = progressbar_obj.progressbar( "value" );
            var max_val = <?php echo count( $plugin_names_arr )?>;

            if( max_val == 0 )
                progresslabel_obj.text( "100%" );
            else
                progresslabel_obj.text( (cur_val / max_val * 100).toFixed(2) + "%" );
        },
        complete: function() {
            progresslabel_obj.text( "<?php echo $this->_pt( 'DONE' )?>!" );
        }
    });

    update_progress_bar( 0 );
    progressbar_obj.show();
}
function update_progress_bar( val )
{
    var progressbar_obj = $("#integrity_progressbar");

    if( !progressbar_obj )
        return;

    progressbar_obj.progressbar( "value", val );
}
function hide_progress_bar()
{
    var progressbar_obj = $("#integrity_progressbar");

    if( !progressbar_obj )
        return;

    progressbar_obj.hide();
}
<?php
if( !empty( $plugin_found ) )
{
    ?>
    check_integrity();
    <?php
}
?>
</script>
<style>
  .ui-progressbar {
    position: relative;
  }
  .progress-label {
    position: absolute;
    left: 50%;
    top: 4px;
    font-weight: bold;
    text-shadow: 1px 1px 0 #fff;
  }
</style>
