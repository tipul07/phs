<?php
namespace phs\plugins\admin\actions;

use phs\PHS;
use phs\PHS_Scope;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Record_data;
use phs\libraries\PHS_Notifications;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\system\core\attributes\PHS_Dependency;
use phs\plugins\accounts\models\PHS_Model_Accounts;
use phs\plugins\accounts\models\PHS_Model_Accounts_details;

class PHS_Action_Users_autocomplete extends PHS_Action
{
    public const FORMAT_NICK_EMAIL_ID = 1, FORMAT_NICK_NAME_ID = 2;

    public const K_ACCOUNT_DATA = 'account_data', K_ACCOUNT_DETAILS_DATA = 'account_details_data',
        K_SEARCH_TERM = 'search_term', K_TEXT_FROMAT = 'text_format';

    #[PHS_Dependency]
    private ?PHS_Plugin_Admin $_admin_plugin = null;

    #[PHS_Dependency]
    private ?PHS_Model_Accounts $_accounts_model = null;

    #[PHS_Dependency]
    private ?PHS_Model_Accounts_details $_account_details_model = null;

    private array $autocomplete_params = [
        self::K_ACCOUNT_DATA         => null,
        self::K_ACCOUNT_DETAILS_DATA => [],

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

        self::K_TEXT_FROMAT => self::FORMAT_NICK_NAME_ID,

        self::K_SEARCH_TERM => '',
    ];

    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_AJAX];
    }

    public function execute()
    {
        if (!PHS::user_logged_in()) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        if (!$this->_admin_plugin->can_admin_list_accounts()) {
            PHS_Notifications::add_error_notice($this->_pt('You don\'t have rights to access this section.'));

            return self::default_action_result();
        }

        $term = PHS_Params::_g('term', PHS_Params::T_REMSQL_CHARS);
        if (($_f = PHS_Params::_g('_f', PHS_Params::T_INT))) {
            $this->autocomplete_params(self::K_TEXT_FROMAT, $_f);
        }

        if ($term) {
            $this->autocomplete_params(self::K_SEARCH_TERM, $term);
        }

        if (!$term
            || !($user_details_table = $this->_account_details_model->get_flow_table_name())) {
            $guessed_accounts = [];
        } else {
            $list_arr = [];
            $list_arr['fields']['{linkage_func}'] = 'AND';
            $list_arr['fields']['status'] = ['check' => '!=', 'value' => $this->_accounts_model::STATUS_DELETED];
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

            $guessed_accounts = $this->_accounts_model->get_list($list_arr) ?: [];
        }

        $ajax_result = [];
        foreach ($guessed_accounts as $account_id => $account_arr) {
            // Simulate account details so we spare queries in database...
            $account_details_arr = $this->_account_details_model->get_empty_data();
            $account_details_arr['id'] = $account_arr['users_details_id'] ?? 0;
            $account_details_arr['title'] = $account_arr['users_details_title'];
            $account_details_arr['fname'] = $account_arr['users_details_fname'];
            $account_details_arr['lname'] = $account_arr['users_details_lname'];
            $account_details_arr['phone'] = $account_arr['users_details_phone'];
            $account_details_arr['company'] = $account_arr['users_details_company'];

            $this->set_account_data($account_arr, $account_details_arr);

            $ajax_result[] = [
                'id'    => $account_id,
                'value' => $this->format_data(false),
                'label' => $this->format_data(),
                'nick'  => $account_arr['nick'],
                'email' => $account_arr['email'] ?? '',
                'title' => $account_arr['users_details_title'] ?? '',
                'fname' => $account_arr['users_details_fname'] ?? '',
                'lname' => $account_arr['users_details_lname'] ?? '',
            ];
        }

        return $this->send_ajax_response($ajax_result);
    }

    public function set_account_data(
        int | array | PHS_Record_data $account_data,
        null | int | array | PHS_Record_data $account_details_data = null
    ) : bool {
        $this->reset_error();

        $this->autocomplete_params([
            self::K_ACCOUNT_DATA         => null,
            self::K_ACCOUNT_DETAILS_DATA => null,
        ]);

        if (!$account_data
           || !($account_arr = $this->_accounts_model->data_to_array($account_data))
           || $this->_accounts_model->is_deleted($account_arr)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Account not found in database.'));

            return false;
        }

        if (($existing_id = $this->autocomplete_params(self::K_ACCOUNT_DATA)['id'] ?? 0)
            && (int)$existing_id === (int)$account_arr['id']) {
            return true;
        }

        if ($account_details_data === null) {
            $account_details_arr = $this->_accounts_model->get_account_details($account_arr);
        } elseif (!($account_details_arr = $this->_account_details_model->data_to_array($account_details_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Account details not found in database.'));

            return false;
        }

        if (!$account_details_arr) {
            $account_details_arr = $this->_account_details_model->get_empty_data();
        }

        $this->autocomplete_params([
            self::K_ACCOUNT_DATA         => $account_arr,
            self::K_ACCOUNT_DETAILS_DATA => $account_details_arr,
        ]);

        return true;
    }

    public function format_data(bool $as_html = true, int $format = 0) : string
    {
        if (!($account_arr = $this->autocomplete_params(self::K_ACCOUNT_DATA) ?: null)) {
            return '';
        }

        $account_details = $this->autocomplete_params(self::K_ACCOUNT_DETAILS_DATA) ?: null;

        if (!$format) {
            $format = $this->autocomplete_params(self::K_TEXT_FROMAT);
        }

        $search_term = $this->autocomplete_params(self::K_SEARCH_TERM) ?: '';

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

    public function js_all_functionality(array $data) : string
    {
        return $this->js_generic_functionality($data)
               .$this->js_autocomplete_functionality($data);
    }

    public function js_generic_functionality(array $data) : string
    {
        if (($params_arr = $this->autocomplete_params())
            && is_array($params_arr)) {
            foreach ($params_arr as $key => $val) {
                $data[$key] = $val;
            }
        }

        if (!($action_result = $this->quick_render_template('users_autocomplete_generic_js', $data))) {
            return '<!-- Couldn\'t obtain autocomplete users generic JS functionality: '.$this->get_error_message().' -->';
        }

        return $action_result['buffer'] ?? '';
    }

    public function js_autocomplete_functionality(array $data) : string
    {
        if (($params_arr = $this->autocomplete_params())
            && is_array($params_arr)) {
            foreach ($params_arr as $key => $val) {
                $data[$key] = $val;
            }
        }

        if (!($action_result = $this->quick_render_template('users_autocomplete_js', $data))) {
            return '<!-- Couldn\'t obtain autocomplete users JS functionality: '.$this->get_error_message().' -->';
        }

        return $action_result['buffer'] ?? '';
    }

    public function autocomplete_inputs(array $data) : string
    {
        if (($params_arr = $this->autocomplete_params())
            && is_array($params_arr)) {
            foreach ($params_arr as $key => $val) {
                $data[$key] = $val;
            }
        }

        if (!($action_result = $this->quick_render_template('users_autocomplete_input', $data))) {
            return '<!-- Couldn\'t obtain autocomplete users inputs: '.$this->get_error_message().' -->';
        }

        return $action_result['buffer'] ?? '';
    }

    public function autocomplete_params(null | string | array $key = null, mixed $val = null) : mixed
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

    private function _highlight_data(?string $str, string $term) : string
    {
        if (empty($term)) {
            return $str;
        }

        return preg_replace('/('.preg_quote($term, '/').')/i', '<strong>\1</strong>', $str);
    }
}
