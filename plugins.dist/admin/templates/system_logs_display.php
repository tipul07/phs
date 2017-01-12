<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_Hooks;

    if( !($log_file = $this->context_var( 'log_file' ))
     or !($log_full_file = $this->context_var( 'log_full_file' ))
     or !@file_exists( $log_full_file ) )
        return $this->_pt( 'Log file not provided or invalid.' );

    if( !($file_size = @filesize( $log_full_file )) )
        $file_size = 0;

    if( !($HOOK_LOG_ACTIONS = $this->context_var( 'HOOK_LOG_ACTIONS' )) )
        $HOOK_LOG_ACTIONS = false;

    if( !($log_lines = $this->context_var( 'log_lines' )) )
        $log_lines = 20;

    if( !($log_file_buffer = $this->context_var( 'log_file_buffer' )) )
        $log_file_buffer = '';
?>
<fieldset class="form-group">
</fieldset>
<fieldset class="form-group">
    <strong><?php echo $this->_pt( 'File name' )?></strong>: <?php echo $log_file?><br/>
        <strong><?php echo $this->_pt( 'File size' )?></strong>: <?php echo format_filesize( $file_size ).' ('.number_format( $file_size, '0', '.', ',' ).' bytes)'?>
</fieldset>
<fieldset class="form-group">
    <input type="button" id="do_download_log" name="do_download_log" class="btn btn-primary" value="<?php echo $this->_pte( 'Download file' )?>" onclick="do_download_log_file()" />
<?php

    $hook_args = PHS_Hooks::default_buffer_hook_args();
    $hook_args['log_file'] = $log_file;
    $hook_args['log_full_file'] = $log_full_file;

    if( !empty( $HOOK_LOG_ACTIONS )
    and ($hook_args = PHS::trigger_hooks( $HOOK_LOG_ACTIONS, $hook_args ))
    and is_array( $hook_args )
    and !empty( $hook_args['buffer'] ) )
        echo $hook_args['buffer'];

?>
</fieldset>
<small><?php echo $this->_pt( 'Displaying last %s lines from %s file.', $log_lines, $log_file )?></small>
<pre style="height:800px;background-color:black;color:lightgrey;font-size:12px;overflow:auto;"><?php echo (!empty( $log_file_buffer )?$log_file_buffer:'['.$this->_pt( 'Log file returned empty buffer.' ).']')?></pre>
