<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;
use phs\PHS_Ajax;

/** @var \phs\plugins\messages\models\PHS_Model_Messages $messages_model */
/** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
/** @var \phs\system\core\models\PHS_Model_Roles $roles_model */
/** @var \phs\plugins\messages\PHS_Plugin_Messages $messages_plugin */
if (!($messages_model = $this->view_var('messages_model'))
 || !($accounts_model = $this->view_var('accounts_model'))
 || !($roles_model = $this->view_var('roles_model'))
 || !($messages_plugin = $this->view_var('messages_plugin'))
 || !($message_arr = $this->view_var('message_arr'))
 || !$messages_model::is_full_message_data($message_arr)
 || !($thread_arr = $this->view_var('thread_arr'))
 || !$messages_model::is_full_message_data($thread_arr)
 || !($current_user = PHS::user_logged_in())) {
    return $this->_pt('Couldn\'t initialize required parameters for current view.');
}

$thread_messages_arr = $this->view_var('thread_messages_arr') ?: [];
$dest_types = $this->view_var('dest_types') ?: [];
$user_levels = $this->view_var('user_levels') ?: [];
$roles_arr = $this->view_var('roles_arr') ?: [];
$roles_units_arr = $this->view_var('roles_units_arr') ?: [];

if (!empty($message_arr['message_user']['id'])) {
    $muid = $message_arr['message_user']['id'];
} elseif (!($muid = $this->view_var('muid'))) {
    $muid = 0;
}

$messages_before = 0;
$messages_before_new = 0;
$messages_after = 0;
$messages_after_new = 0;
if (!empty($thread_messages_arr) && is_array($thread_messages_arr)) {
    $we_are_before = true;
    foreach ($thread_messages_arr as $um_id => $um_arr) {
        if ((int)($message_arr['message']['id'] ?? 0) === (int)($um_arr['message_id'] ?? 0)) {
            $we_are_before = false;
            continue;
        }

        if ($we_are_before) {
            $messages_before++;
            if ((int)$current_user['id'] === (int)($um_arr['user_id'] ?? 0)
                && !empty($um_arr['is_new'])) {
                $messages_before_new++;
            }
        } else {
            $messages_after++;
            if ((int)$current_user['id'] === (int)($um_arr['user_id'] ?? 0)
                && !empty($um_arr['is_new'])) {
                $messages_after_new++;
            }
        }
    }
}

?>
<form id="view_message_form" name="view_message_form" action="<?php echo PHS::url(['p' => 'messages', 'a' => 'view_message']); ?>" method="post">
    <input type="hidden" name="foobar" value="1" />

    <div class="form_container" style="min-width: 800px;max-width:850px;">

        <section class="heading-bordered">
            <h3><?php echo $thread_arr['message']['subject']; ?></h3>
        </section>
        <div class="clearfix"></div>

        <?php
        if (!empty($messages_before)) {
            ?>
            <div id="messages_before">
            <fieldset class="form-group more_messages_before">
                <a href="javascript:void(0);" title="<?php echo $this->_pt('Load previous messages'); ?>"
                   onclick="load_previous_messages()"> ... <?php
                    if ($messages_before > 1) {
                        echo $this->_pt('%s messages', $messages_before);
                    } else {
                        echo $this->_pt('1 message');
                    }

            echo ', ';

            if ($messages_before_new !== 1) {
                echo $this->_pt('%s new messages', $messages_before_new);
            } else {
                echo $this->_pt('1 new message');
            }
            ?> ... </a>
            </fieldset>
            </div>
            <?php
        }

echo $this->sub_view('view_single_message');

if (!empty($messages_after)) {
    ?>
            <div id="messages_after">
            <fieldset class="form-group more_messages_after">
                <a href="javascript:void(0);" title="<?php echo $this->_pt('Load next messages'); ?>"
                   onclick="load_next_messages()"> ... <?php
            if ($messages_after > 1) {
                echo $this->_pt('%s messages', $messages_after);
            } else {
                echo $this->_pt('1 message');
            }

    echo ', ';

    if ($messages_after_new !== 1) {
        echo $this->_pt('%s new messages', $messages_after_new);
    } else {
        echo $this->_pt('1 new message');
    }
    ?> ... </a>
            </fieldset>
            </div>
            <?php
}
?>

    </div>
</form>
<script type="text/javascript">
let before_offset = 0;
let after_offset = 0;

    function load_previous_messages()
{
    const html_obj = $("#messages_before");
    if( !html_obj )
        return;

    show_submit_protection( "<?php echo $this->_pte('Loading previous messages...'); ?>" );

    _load_messages( html_obj, 'before', before_offset );
}
function load_next_messages()
{
    const html_obj = $("#messages_after");
    if( !html_obj )
        return;

    show_submit_protection( "<?php echo $this->_pte('Loading next messages...'); ?>" );

    _load_messages( html_obj, 'after', after_offset );
}
function _load_messages( html_container, location, offset )
{
    const max_messages = 5;
    const ajax_params = {
        cache_response: false,
        method: 'post',
        url_data: {muid: '<?php echo $muid; ?>', location: location, max_messages: max_messages, offset: offset},
        data_type: 'json',

        onsuccess: function (response, status, ajax_obj) {
            hide_submit_protection();

            if (response) {
                if (location === "before") {
                    before_offset += max_messages;
                } else if (location === "after") {
                    after_offset += max_messages;
                }

                html_container.html(response);
                phs_refresh_input_skins();
            }
        },

        onfailed: function (ajax_obj, status, error_exception) {
            hide_submit_protection();

            PHS_JSEN.js_message_error(["<?php echo $this->_pt('Error loading messages. Please retry.'); ?>"]);
        }
    };

    const ajax_obj = PHS_JSEN.do_ajax("<?php echo PHS_Ajax::url(['p' => 'messages', 'a' => 'append_messages', ]); ?>", ajax_params);
}
</script>
