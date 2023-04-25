<?php
namespace phs\plugins\admin\actions;

use phs\PHS;
use phs\PHS_Scope;
use phs\libraries\PHS_Roles;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;

class PHS_Action_Users_autocomplete extends PHS_Action
{
    public const FORMAT_NICK_EMAIL_ID = 1, FORMAT_NICK_NAME_ID = 2;

    /** @var bool|\phs\plugins\admin\PHS_Plugin_Admin */
    private $_admin_plugin = false;

    /** @var bool|\phs\plugins\accounts\models\PHS_Model_Accounts */
    private $_accounts_model = false;

    /** @var bool|\phs\plugins\accounts\models\PHS_Model_Accounts_details */
    private $_account_details_model = false;

    private $autocomplete_params = [
        'account_data'         => false,
        'account_details_data' => [],

        'id_id'     => 'ac_account_id',
        'text_id'   => 'ac_account_text',
        'id_name'   => 'ac_account_id',
        'text_name' => 'ac_account_text',

        // styling
        'text_css_classes' => 'form-control',
        'text_css_style'   => '',

        'id_value'   => 0,
        'text_value' => '',

        'min_text_length' => 1,

        'text_format' => self::FORMAT_NICK_EMAIL_ID,

        'search_term' => '',
    ];

    /**
     * @inheritdoc
     */
    public function allowed_scopes()
    {
        return [PHS_Scope::SCOPE_AJAX];
    }

    /**
     * @return array|bool
     */
    public function execute()
    {
        if (!PHS::user_logged_in()) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        if (!$this->_load_dependencies()) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error loading required resources.'));
            }

            PHS_Notifications::add_error_notice($this->get_error_message());

            return self::default_action_result();
        }

        if (!$this->_admin_plugin->can_admin_list_accounts()) {
            PHS_Notifications::add_error_notice($this->_pt('You don\'t have rights to list accounts.'));

            return self::default_action_result();
        }

        $term = PHS_Params::_g('term', PHS_Params::T_REMSQL_CHARS);
        if (($_f = PHS_Params::_g('_f', PHS_Params::T_INT))) {
            $this->autocomplete_params('text_format', $_f);
        }

        if (!empty($term)) {
            $this->autocomplete_params('search_term', $term);
        }

        $accounts_model = $this->_accounts_model;

        if (empty($term)
         || !($user_details_table = $this->_account_details_model->get_flow_table_name())) {
            $guessed_accounts = [];
        } else {
            $list_arr = [];
            $list_arr['fields']['{linkage_func}'] = 'AND';
            $list_arr['fields']['status'] = ['check' => '!=', 'value' => $accounts_model::STATUS_DELETED];
            $list_arr['fields']['{linkage}'] = [
                'fields' => [
                    '{linkage_func}'                  => 'OR',
                    'nick'                            => ['check' => 'LIKE', 'value' => '%'.prepare_data($term).'%'],
                    'email'                           => ['check' => 'LIKE', 'value' => '%'.prepare_data($term).'%'],
                    '`'.$user_details_table.'`.fname' => ['check' => 'LIKE', 'value' => '%'.prepare_data($term).'%'],
                    '`'.$user_details_table.'`.lname' => ['check' => 'LIKE', 'value' => '%'.prepare_data($term).'%'],
                ],
            ];
            $list_arr['flags'] = ['include_account_details'];
            $list_arr['enregs_no'] = 30;

            if (!($guessed_accounts = $accounts_model->get_list($list_arr))) {
                $guessed_accounts = [];
            }
        }

        $ajax_result = [];
        foreach ($guessed_accounts as $account_id => $account_arr) {
            // Simulate account details so we spare queries in database...
            $account_details_arr = $this->_account_details_model->get_empty_data();
            $account_details_arr['title'] = $account_arr['users_details_title'];
            $account_details_arr['fname'] = $account_arr['users_details_fname'];
            $account_details_arr['lname'] = $account_arr['users_details_lname'];
            $account_details_arr['phone'] = $account_arr['users_details_phone'];
            $account_details_arr['company'] = $account_arr['users_details_company'];

            $this->autocomplete_params([
                'account_data'         => $account_arr,
                'account_details_data' => $account_details_arr,
            ]);

            $ajax_result[] = [
                'id'    => $account_id,
                'value' => $this->format_data(false, false),
                'label' => $this->format_data(),
                'nick'  => $account_arr['nick'],
                'email' => (empty($account_arr['email']) ? '' : $account_arr['email']),
                'title' => (empty($account_arr['users_details_title']) ? '' : $account_arr['users_details_title']),
                'fname' => (empty($account_arr['users_details_fname']) ? '' : $account_arr['users_details_fname']),
                'lname' => (empty($account_arr['users_details_lname']) ? '' : $account_arr['users_details_lname']),
            ];
        }

        $action_result = self::default_action_result();

        $action_result['ajax_result'] = $ajax_result;

        return $action_result;
    }

    public function autocomplete_params($key = null, $val = null)
    {
        if ($key === null) {
            return $this->autocomplete_params;
        }

        if ($val === null) {
            if (!is_array($key)) {
                if (is_scalar($key)
                 && array_key_exists($key, $this->autocomplete_params)) {
                    return $this->autocomplete_params[$key];
                }

                return null;
            }

            foreach ($key as $kkey => $kval) {
                if (!is_scalar($kkey)
                 || !array_key_exists($kkey, $this->autocomplete_params)) {
                    continue;
                }

                $this->autocomplete_params[$kkey] = $kval;
            }

            return true;
        }

        if (!is_scalar($key)
         || !array_key_exists($key, $this->autocomplete_params)) {
            return null;
        }

        $this->autocomplete_params[$key] = $val;

        return true;
    }

    /**
     * @param false|int|array $account_data
     *
     * @return array|false
     */
    public function account_data($account_data = false)
    {
        if ($account_data === false) {
            if (!($account_arr = $this->autocomplete_params('account_data'))) {
                return false;
            }

            return [
                'account_data'         => $account_arr,
                'account_details_data' => $this->autocomplete_params('account_details_data'),
            ];
        }

        if (($existing_arr = $this->autocomplete_params('account_data'))
         && is_array($existing_arr)
         && (
             (is_numeric($account_data) && (int)$existing_arr['id'] === (int)$account_data)
             || (is_array($account_data) && !empty($account_data['id']) && (int)$existing_arr['id'] === (int)$account_data['id'])
         )) {
            return [
                'account_data'         => $account_data,
                'account_details_data' => $this->autocomplete_params('account_details_data'),
            ];
        }

        if (!$this->_load_dependencies()
         || empty($account_data)
         || !($account_arr = $this->_accounts_model->data_to_array($account_data))) {
            return false;
        }

        if (!($account_details = $this->_accounts_model->get_account_details($account_arr))) {
            $account_details = [];
        }

        $return_arr = [
            'account_data'         => $account_arr,
            'account_details_data' => $account_details,
        ];

        $this->autocomplete_params($return_arr);

        return $return_arr;
    }

    public function format_data($account_data = false, $as_html = true, $format = false)
    {
        if (!$this->_load_dependencies()
         || !($account_details = $this->account_data($account_data))
         || !is_array($account_details)) {
            return '';
        }

        $account_arr = $account_details['account_data'];
        $account_details = (empty($account_details['account_details_data']) ? [] : $account_details['account_details_data']);

        if (empty($format)) {
            $format = $this->autocomplete_params('text_format');
        }

        if (!($search_term = $this->autocomplete_params('search_term'))) {
            $search_term = '';
        }

        // $return_str = '';
        switch ($format) {
            default:
            case self::FORMAT_NICK_EMAIL_ID:
                if ($as_html) {
                    $return_str = '#'.$account_arr['id'].' '.$this->_highlight_data($account_arr['nick'], $search_term)
                                  .'<br/>'.$this->_highlight_data($account_arr['email'], $search_term);
                } else {
                    $return_str = '#'.$account_arr['id'].' '.$account_arr['nick'].' '.$account_arr['email'];
                }
                break;

            case self::FORMAT_NICK_NAME_ID:
                $full_name = '';
                if (!empty($account_details['title'])) {
                    $full_name .= trim($account_details['title']);
                }
                if (!empty($account_details['fname'])) {
                    $fname = trim($account_details['fname']);
                    $full_name .= ($full_name !== '' ? ' ' : '').($as_html ? $this->_highlight_data($fname, $search_term) : $fname);
                }

                if (!empty($account_details['lname'])) {
                    $lname = trim($account_details['lname']);
                    $full_name .= ($full_name !== '' ? ' ' : '').($as_html ? $this->_highlight_data($lname, $search_term) : $lname);
                }

                if ($as_html) {
                    $return_str = '#'.$account_arr['id'].' '.$this->_highlight_data($account_arr['nick'], $search_term)
                                  .'<br/>'.$full_name;
                } else {
                    $return_str = '#'.$account_arr['id'].' '.$account_arr['nick'].' '.$full_name;
                }
                break;
        }

        return $return_str;
    }

    public function js_all_functionality($data)
    {
        return $this->js_generic_functionality($data).$this->js_autocomplete_functionality($data);
    }

    public function js_generic_functionality($data)
    {
        if (empty($data) || !is_array($data)) {
            $data = [];
        }

        if (($params_arr = $this->autocomplete_params())
         && is_array($params_arr)) {
            foreach ($params_arr as $key => $val) {
                $data[$key] = $val;
            }
        }

        if (!($action_result = $this->quick_render_template('users_autocomplete_generic_js', $data))) {
            return '<!-- Couldn\'t obtain autocomplete users generic JS functionality: '.$this->get_error_message().' -->';
        }

        return !empty($action_result['buffer']) ? $action_result['buffer'] : '';
    }

    public function js_autocomplete_functionality($data)
    {
        if (empty($data) || !is_array($data)) {
            $data = [];
        }

        if (($params_arr = $this->autocomplete_params())
         && is_array($params_arr)) {
            foreach ($params_arr as $key => $val) {
                $data[$key] = $val;
            }
        }

        if (!($action_result = $this->quick_render_template('users_autocomplete_js', $data))) {
            return '<!-- Couldn\'t obtain autocomplete users JS functionality: '.$this->get_error_message().' -->';
        }

        return !empty($action_result['buffer']) ? $action_result['buffer'] : '';
    }

    public function autocomplete_inputs($data)
    {
        if (empty($data) || !is_array($data)) {
            $data = [];
        }

        if (($params_arr = $this->autocomplete_params())
         && is_array($params_arr)) {
            foreach ($params_arr as $key => $val) {
                $data[$key] = $val;
            }
        }

        if (!($action_result = $this->quick_render_template('users_autocomplete_input', $data))) {
            return '<!-- Couldn\'t obtain autocomplete users inputs: '.$this->get_error_message().' -->';
        }

        return !empty($action_result['buffer']) ? $action_result['buffer'] : '';
    }

    private function _load_dependencies()
    {
        $this->reset_error();

        if ((empty($this->_admin_plugin)
             && !($this->_admin_plugin = PHS::load_plugin('admin')))
         || (empty($this->_accounts_model)
             && !($this->_accounts_model = PHS::load_model('accounts', 'accounts')))
         || (empty($this->_account_details_model)
             && !($this->_account_details_model = PHS::load_model('accounts_details', 'accounts')))
        ) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error loading required resources.'));

            return false;
        }

        return true;
    }

    /**
     * @param string $str
     * @param string $term
     *
     * @return string
     */
    private function _highlight_data($str, $term)
    {
        if (empty($term)) {
            return $str;
        }

        return str_replace($term, '<strong>'.$term.'</strong>', $str);
    }
}
