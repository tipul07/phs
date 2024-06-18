<?php

namespace phs\system\core\models;

use phs\libraries\PHS_Model;
use phs\traits\PHS_Model_Trait_statuses;

class PHS_Model_Agent_jobs_monitor extends PHS_Model
{
    use PHS_Model_Trait_statuses;

    public const STATUS_STARTED = 1, STATUS_SUCCESS = 2, STATUS_ERROR = 3;

    protected static array $STATUSES_ARR = [
        self::STATUS_STARTED => ['title' => 'Started'],
        self::STATUS_SUCCESS => ['title' => 'Success'],
        self::STATUS_ERROR   => ['title' => 'Error'],
    ];

    public function get_model_version() : string
    {
        return '1.0.0';
    }

    public function get_table_names() : array
    {
        return ['bg_agent_monitor'];
    }

    public function get_main_table_name() : string
    {
        return 'bg_agent_monitor';
    }

    public function job_started($job_data) : ?array
    {
        return $this->_add_job_monitor_record($job_data, self::STATUS_STARTED);
    }

    public function job_success($job_data) : ?array
    {
        return $this->_add_job_monitor_record($job_data, self::STATUS_SUCCESS);
    }

    public function job_error($job_data, string $error_msg, int $error_code) : ?array
    {
        return $this->_add_job_monitor_record($job_data, self::STATUS_ERROR, $error_msg, $error_code);
    }

    /**
     * @inheritdoc
     */
    final public function fields_definition($params = false) : ?array
    {
        if (empty($params['table_name'])) {
            return null;
        }

        $return_arr = [];

        switch ($params['table_name']) {
            case 'bg_agent_monitor':
                $return_arr = [
                    'id' => [
                        'type'           => self::FTYPE_INT,
                        'primary'        => true,
                        'auto_increment' => true,
                    ],
                    'job_id' => [
                        'type'    => self::FTYPE_INT,
                        'default' => 0,
                        'comment' => 'Agent job id',
                    ],
                    'job_title' => [
                        'type'    => self::FTYPE_VARCHAR,
                        'length'  => 255,
                        'default' => null,
                    ],
                    'job_handle' => [
                        'type'    => self::FTYPE_VARCHAR,
                        'length'  => 255,
                        'default' => null,
                        'index'   => true,
                    ],
                    'plugin' => [
                        'type'    => self::FTYPE_VARCHAR,
                        'length'  => 255,
                        'default' => null,
                    ],
                    'error_message' => [
                        'type'    => self::FTYPE_VARCHAR,
                        'length'  => 255,
                        'default' => null,
                    ],
                    'error_code' => [
                        'type'    => self::FTYPE_INT,
                        'default' => 0,
                    ],
                    'status' => [
                        'type'    => self::FTYPE_TINYINT,
                        'length'  => 2,
                        'default' => 0,
                        'index'   => true,
                    ],
                    'cdate' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                ];
                break;
        }

        return $return_arr;
    }

    protected function get_insert_prepare_params_bg_agent_monitor($params)
    {
        if (empty($params) || !is_array($params)) {
            return false;
        }

        if (empty($params['fields']['job_handle'])) {
            $this->set_error(self::ERR_INSERT, $this->_pt('Please provide a job handle for this agent job monitor record.'));

            return false;
        }

        if (empty($params['fields']['status'])
            || !$this->valid_status($params['fields']['status'])) {
            $this->set_error(self::ERR_INSERT, $this->_pt('Please provide a status for this agent job monitor record.'));

            return false;
        }

        if (!empty($params['fields']['job_title'])) {
            $params['fields']['job_title'] = substr($params['fields']['job_title'], 0, 255);
        }
        if (!empty($params['fields']['job_handle'])) {
            $params['fields']['job_handle'] = substr($params['fields']['job_handle'], 0, 255);
        }
        if (!empty($params['fields']['plugin'])) {
            $params['fields']['plugin'] = substr($params['fields']['plugin'], 0, 255);
        }
        if (!empty($params['fields']['error_message'])) {
            $params['fields']['error_message'] = substr($params['fields']['error_message'], 0, 255);
        }

        if (empty($params['fields']['cdate'])) {
            $params['fields']['cdate'] = date(self::DATETIME_DB);
        }

        return $params;
    }

    protected function get_edit_prepare_params_bg_agent_monitor($existing_data, $params)
    {
        if (empty($params) || !is_array($params)) {
            return false;
        }

        if (isset($params['fields']['job_handle']) && empty($params['fields']['job_handle'])) {
            $this->set_error(self::ERR_EDIT, $this->_pt('Please provide a job handle for this agent job monitor record.'));

            return false;
        }

        if (isset($params['fields']['status'])
            && (empty($params['fields']['status'])
                || !$this->valid_status($params['fields']['status']))
        ) {
            $this->set_error(self::ERR_EDIT, $this->_pt('Please provide a status for this agent job monitor record.'));

            return false;
        }

        if (!empty($params['fields']['job_title'])) {
            $params['fields']['job_title'] = substr($params['fields']['job_title'], 0, 255);
        }
        if (!empty($params['fields']['job_handle'])) {
            $params['fields']['job_handle'] = substr($params['fields']['job_handle'], 0, 255);
        }
        if (!empty($params['fields']['plugin'])) {
            $params['fields']['plugin'] = substr($params['fields']['plugin'], 0, 255);
        }
        if (!empty($params['fields']['error_message'])) {
            $params['fields']['error_message'] = substr($params['fields']['error_message'], 0, 255);
        }

        return $params;
    }

    private function _add_job_monitor_record($job_data, int $status, ?string $error_msg = null, int $error_code = 0) : ?array
    {
        $this->reset_error();

        /** @var PHS_Model_Agent_jobs $agent_jobs_model */
        if (!($agent_jobs_model = PHS_Model_Agent_jobs::get_instance())) {
            $this->set_error(self::ERR_RESOURCES, self::_t('Error loading required resources.'));

            return null;
        }

        if (empty($job_data)
            || !($job_arr = $agent_jobs_model->data_to_array($job_data))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Couldn\'t load agent jobs details from database.'));

            return null;
        }

        $insert_arr = $this->fetch_default_flow_params(['table_name' => 'bg_agent_monitor']);
        $insert_arr['fields'] = [];
        $insert_arr['fields']['job_id'] = $job_arr['id'];
        $insert_arr['fields']['job_title'] = $job_arr['title'] ?? 'N/A';
        $insert_arr['fields']['job_handle'] = $job_arr['handler'] ?? 'N/A';
        $insert_arr['fields']['plugin'] = $job_arr['plugin'] ?? 'N/A';
        $insert_arr['fields']['status'] = $status;
        $insert_arr['fields']['error_message'] = $error_msg;
        $insert_arr['fields']['error_code'] = $error_code;

        if (!($record_arr = $this->insert($insert_arr))) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_INSERT, $this->_pt('Error adding agent job monitor error record.'));
            }

            return null;
        }

        return $record_arr;
    }
}
