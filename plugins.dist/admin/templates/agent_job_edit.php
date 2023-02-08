<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;
use phs\libraries\PHS_Utils;

if (!($agent_routes = $this->view_var('agent_routes'))) {
    $agent_routes = [];
}

if (!($plugins_arr = array_keys($agent_routes))) {
    $plugins_arr = [];
}

$routes_structure_arr = [];
foreach ($plugins_arr as $plugin_name) {
    if (empty($agent_routes[$plugin_name]) || !is_array($agent_routes[$plugin_name])
     || empty($agent_routes[$plugin_name]['controllers']) || !is_array($agent_routes[$plugin_name]['controllers'])
     || empty($agent_routes[$plugin_name]['actions']) || !is_array($agent_routes[$plugin_name]['actions'])) {
        continue;
    }

    $routes_structure_arr[$plugin_name] = [];
    foreach ($agent_routes[$plugin_name]['controllers'] as $controller_name => $controller_obj) {
        if (empty($routes_structure_arr[$plugin_name][$controller_name])) {
            $routes_structure_arr[$plugin_name][$controller_name] = [];
        }

        $routes_structure_arr[$plugin_name][$controller_name] = array_keys($agent_routes[$plugin_name]['actions']);
    }
}

if (!($back_page = $this->view_var('back_page'))) {
    $back_page = PHS::url(['p' => 'admin', 'a' => 'agent_jobs_list']);
}
?>
<form id="edit_agent_job_form" name="edit_agent_job_form" method="post"
      action="<?php echo PHS::url(['p' => 'admin', 'a' => 'agent_job_edit'],
          ['aid' => $this->view_var('aid')]); ?>">
<input type="hidden" name="foobar" value="1" />
<?php
if (!empty($back_page)) {
    ?><input type="hidden" name="back_page" value="<?php echo form_str(safe_url($back_page)); ?>" /><?php
}
?>

<div class="form_container">

    <?php
    if (!empty($back_page)) {
        ?><i class="fa fa-chevron-left"></i> <a href="<?php echo form_str(from_safe_url($back_page)); ?>"><?php echo $this->_pt('Back'); ?></a><?php
    }
?>

    <section class="heading-bordered">
        <h3><?php echo $this->_pt('Edit Agent Job'); ?></h3>
    </section>

    <div class="form-group row">
        <label for="title" class="col-sm-2 col-form-label">
            <?php echo $this->_pt('Title'); ?>
            <i class="fa fa-question-circle" title="<?php echo $this->_pte('User friendly title describing this agent job.'); ?>"></i>
        </label>
        <div class="col-sm-10">
            <input type="text" id="title" name="title" class="form-control" required="required"
                   value="<?php echo form_str($this->view_var('title')); ?>" autocomplete="title" />
        </div>
    </div>

    <div class="form-group row">
        <small><?php echo $this::_t('Only plugins, controllers and actions which support agent scope will be shown.'); ?></small>
    </div>

    <div class="form-group row">
        <label for="plugin" class="col-sm-2 col-form-label"><?php echo $this->_pt('Plugin'); ?></label>
        <div class="col-sm-10">
        <select name="plugin" id="plugin" class="chosen-select"
                style="width:100%;" onchange="change_plugin()">
        <option value=""><?php echo $this->_pt(' - Choose - '); ?></option>
        <?php
    $selected_plugin = $this->view_var('plugin');
/** @var \phs\libraries\PHS_Plugin $plugin_instance */
foreach ($routes_structure_arr as $plugin_name => $plugin_data) {
    if (empty($plugin_data) || !is_array($plugin_data)
     || empty($agent_routes[$plugin_name])) {
        continue;
    }

    ?><option value="<?php echo $plugin_name; ?>"
            <?php echo $plugin_name === $selected_plugin ? 'selected="selected"' : ''; ?>
            ><?php echo $agent_routes[$plugin_name]['info']['name'].' ('.$agent_routes[$plugin_name]['info']['db_version'].')'; ?>
            </option><?php
}
?>
        </select>
        </div>
    </div>

    <?php
$we_have_controller = false;
if (!empty($selected_plugin)
&& !empty($routes_structure_arr[$selected_plugin])
&& is_array($routes_structure_arr[$selected_plugin])
&& ($selected_controller = $this->view_var('controller'))) {
    $we_have_controller = true;
}

if (empty($we_have_controller)
 || empty($selected_controller)) {
    $selected_controller = '';
}
?>
    <div id="controller_container"
         style="display:<?php echo !empty($we_have_controller) ? 'block' : 'none'; ?>;">
    <div class="form-group row">
        <label for="controller" class="col-sm-2 col-form-label"><?php echo $this->_pt('Controller'); ?></label>
        <div class="col-sm-10">
        <select name="controller" id="controller" class="chosen-select"
                style="width:100%;" onchange="change_controller()">
        <option value=""><?php echo $this->_pt(' - Choose - '); ?></option>
        <?php
    if (!empty($we_have_controller)) {
        foreach ($routes_structure_arr[$selected_plugin] as $controller_name => $controller_data) {
            if (empty($controller_data) || !is_array($controller_data)
             || empty($agent_routes[$selected_plugin]['controllers'][$controller_name])) {
                continue;
            }

            ?><option value="<?php echo $controller_name; ?>"
                <?php echo $controller_name === $selected_controller ? 'selected="selected"' : ''; ?>
                ><?php echo $controller_name; ?>
                </option><?php
        }
    }
?>
        </select>
        </div>
    </div>
    </div>

    <?php
$we_have_action = false;
if (!empty($selected_plugin) && !empty($selected_controller)
 && !empty($routes_structure_arr[$selected_plugin])
 && is_array($routes_structure_arr[$selected_plugin])
 && !empty($routes_structure_arr[$selected_plugin][$selected_controller])
 && is_array($routes_structure_arr[$selected_plugin][$selected_controller])
 && ($selected_action = $this->view_var('action'))) {
    $we_have_action = true;
}

if (empty($we_have_action)
 || empty($selected_action)) {
    $selected_action = '';
}
?>
    <div id="action_container" style="display:<?php echo !empty($we_have_action) ? 'block' : 'none'; ?>;">
    <div class="form-group row">
        <label for="action" class="col-sm-2 col-form-label"><?php echo $this->_pt('Action'); ?></label>
        <div class="col-sm-10">
        <select name="action" id="action" class="chosen-select"
                style="width:100%;" onchange="change_action()">
        <option value=""><?php echo $this->_pt(' - Choose - '); ?></option>
        <?php
    if (!empty($we_have_action)) {
        foreach ($routes_structure_arr[$selected_plugin][$selected_controller] as $action_name) {
            if (empty($action_name)
             || empty($agent_routes[$selected_plugin]['actions'][$action_name])) {
                continue;
            }

            ?><option value="<?php echo $action_name; ?>"
                <?php echo $action_name === $selected_action ? 'selected="selected"' : ''; ?>
                ><?php echo $action_name; ?></option><?php
        }
    }
?>
        </select>
        </div>
    </div>
    </div>

    <div class="form-group row">
        <label for="handler" class="col-sm-2 col-form-label">
            <?php echo $this->_pt('Handler'); ?>
            <i class="fa fa-question-circle"
               title="<?php echo $this->_pte('Handler should be unique as it will identify agent job.'); ?>"></i>
        </label>
        <div class="col-sm-10">
        <input type="text" id="handler" name="handler" class="form-control" required="required"
               value="<?php echo form_str($this->view_var('handler')); ?>" autocomplete="handler" />
        </div>
    </div>

    <div class="form-group row">
        <label for="params" class="col-sm-2 col-form-label">
            <?php echo $this->_pt('Job parameters'); ?>
            <i class="fa fa-question-circle"
               title="<?php echo $this->_pte('This is a JSON string which will be decoded and passed as parameter to job execute method.'); ?>"></i>
            <br/><small>(JSON string - optional)</small>
        </label>
        <div class="col-sm-10">
            <textarea id="params" name="params" class="form-control"
                      style="height:100px;"><?php echo textarea_str($this->view_var('params')); ?></textarea>
        </div>
    </div>

    <div class="form-group row">
        <label for="timed_seconds" class="col-sm-2 col-form-label">
            <?php echo $this->_pt('Running interval'); ?>
            <i class="fa fa-question-circle"
               title="<?php echo $this->_pte('Once how many seconds should this job run. Minimum interval depends on interval set for _agent.php script to run in crontab.'); ?>"></i>
        </label>
        <div class="col-sm-10">
        <input type="text" id="timed_seconds" name="timed_seconds" class="form-control" required="required"
               value="<?php echo form_str($this->view_var('timed_seconds')); ?>"
               style="width: 150px;" autocomplete="timed_seconds" />
            <small><?php echo $this->_pt('seconds'); ?></small>
        </div>
    </div>

    <div class="form-group row">
        <label for="run_async" class="col-sm-2 col-form-label">
            <?php echo $this->_pt('Run asynchronous'); ?>
            <i class="fa fa-question-circle" title="<?php echo $this->_pte('This agent job will stop agent from advancing to next job untill it is finished.'); ?>"></i>
        </label>
        <div class="col-sm-10">
        <input type="checkbox" value="1" name="run_async" id="run_async" rel="skin_checkbox"
            <?php echo $this->view_var('run_async'); ?> />
        </div>
    </div>

    <div class="form-group row">
        <label for="stalling_minutes" class="col-sm-2 col-form-label">
            <?php echo $this->_pt('Stalling minutes'); ?>
            <i class="fa fa-question-circle"
               title="<?php echo $this->_pte('After how many minutes should this job be considered as stalling.'); ?>"></i>
        </label>
        <div class="col-sm-10">
        <input type="text" id="stalling_minutes" name="stalling_minutes" class="form-control" required="required"
               value="<?php echo form_str($this->view_var('stalling_minutes')); ?>"
               style="width: 150px;" autocomplete="stalling_minutes" />
            <small><?php echo $this->_pt('minutes'); ?></small>
        </div>
    </div>

    <div class="form-group row">
        <input type="submit" id="do_submit" name="do_submit"
               class="btn btn-primary submit-protection ignore_hidden_required"
               value="<?php echo $this->_pte('Edit job'); ?>" />
    </div>

</div>
</form>

<script type="text/javascript">
var routes_structure = <?php echo @json_encode($routes_structure_arr); ?>;
function change_plugin()
{
    var plugin_obj = $("#plugin");
    var controller_obj = $("#controller");
    var action_obj = $("#action");
    if( !plugin_obj || !controller_obj || !action_obj )
        return;

    var plugin_val = plugin_obj.val();

    controller_obj.empty();
    action_obj.empty();

    if( typeof routes_structure[plugin_val] === "undefined" )
        return;

    var controllers_container = $("#controller_container");
    if( controllers_container )
        controllers_container.show();

    var actions_container = $("#action_container");
    if( actions_container )
        actions_container.hide();

    controller_obj.append( $("<option></option>").attr( "value", "" ).text( "<?php echo $this->_pt(' - Choose - '); ?>" ) );
    action_obj.append( $("<option></option>").attr( "value", "" ).text( "<?php echo $this->_pt(' - Choose - '); ?>" ) );

    var controller = '';
    for( controller in routes_structure[plugin_val] )
    {
        if( !routes_structure[plugin_val].hasOwnProperty( controller ) )
            continue;

        controller_obj.append( $("<option></option>").attr( "value", controller ).text( controller ) );
    }

    controller_obj.trigger("chosen:updated");
    action_obj.trigger("chosen:updated");

    update_handler();
}

function change_controller()
{
    var plugin_obj = $("#plugin");
    var controller_obj = $("#controller");
    var action_obj = $("#action");
    if( !plugin_obj || !controller_obj || !action_obj )
        return;

    var plugin_val = plugin_obj.val();
    var controller_val = controller_obj.val();

    action_obj.empty();

    if( typeof routes_structure[plugin_val] === "undefined"
     || typeof routes_structure[plugin_val][controller_val] === "undefined" )
        return;

    var actions_container = $("#action_container");
    if( actions_container )
        actions_container.show();

    action_obj.append( $("<option></option>").attr( "value", "" ).text( "<?php echo $this->_pt(' - Choose - '); ?>" ) );

    var i = 0;
    for( i = 0; i < routes_structure[plugin_val][controller_val].length; i++ )
    {
        action_obj.append( $("<option></option>").attr( "value", routes_structure[plugin_val][controller_val][i] ).
                   text( routes_structure[plugin_val][controller_val][i] ) );
    }

    action_obj.trigger("chosen:updated");

    update_handler();
}

function change_action()
{
    update_handler();
}

function update_handler()
{
    var plugin_obj = $("#plugin");
    var controller_obj = $("#controller");
    var action_obj = $("#action");
    var handler_obj = $("#handler");
    if( !plugin_obj || !controller_obj || !action_obj || !handler_obj )
        return;

    var handler_val = plugin_obj.val();
    var controller_val = controller_obj.val();
    if( controller_val && controller_val !== "index" )
        handler_val += "_" + controller_obj.val();
    if( action_obj.val() )
        handler_val += "_" + action_obj.val().replace( "/", "_" );

    handler_obj.val( handler_val );
}
<?php
if (!$this->view_var('foobar')) {
    ?>
$(document).ready(function(){
    update_handler();
});
    <?php
}
?>
</script>
