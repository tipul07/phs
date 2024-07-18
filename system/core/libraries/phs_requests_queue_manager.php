<?php

namespace phs\system\core\libraries;

use phs\PHS_Bg_jobs;
use phs\libraries\PHS_Library;
use phs\system\core\models\PHS_Model_Request_queue;

class PHS_Requests_queue_manager extends PHS_Library
{
    private ?PHS_Model_Request_queue $_requests_model = null;

    public function queue_request(
        string $url,
        string $method = 'get',
        ?string $payload = null,
        ?array $settings = null,
        array $params = [],
    ) : ?array {
        if ( !$this->_load_dependencies()) {
            return null;
        }

        $params['max_retries'] = (int)($params['max_retries'] ?? 1);
        $params['handle'] ??= null;
        $params['run_now'] = !isset($params['run_now']) || !empty($params['run_now']);
        $params['sync_run'] = !isset($params['sync_run']) || !empty($params['sync_run']);
        $params['same_thread_if_bg'] = !isset($params['same_thread_if_bg']) || !empty($params['same_thread_if_bg']);
        $params['run_after'] ??= $params['run_after'];

        if ( !empty($params['run_after'])
            && ($run_after = parse_db_date($params['run_after']))) {
            $params['run_after'] = date($this->_requests_model::DATETIME_DB, $run_after);
        } else {
            $params['run_after'] = null;
        }

        if ( !($request_arr = $this->_requests_model->create_request($url, $method, $payload, $settings, $params['max_retries'], $params['handle'], $params['run_after'])) ) {
            $this->copy_or_set_error($this->_requests_model,
                self::ERR_FUNCTIONALITY, self::_t('Error adding the request to the queue.'));

            return null;
        }

        if ($params['run_now']) {
            if ( $params['sync_run'] ) {
                if ( !($request_arr = $this->run_request($request_arr)) ) {
                    return null;
                }
            } else {
                if ( !($request_arr = $this->run_request_bg($request_arr, $params['same_thread_if_bg'])) ) {
                    return null;
                }
            }
        }

        return $request_arr;
    }

    public function run_request_bg(int | array $request_data, bool $forced_run = false) : ?array
    {
        if ( !$this->_load_dependencies()) {
            return null;
        }

        if ( !($request_arr = $this->_requests_model->data_to_array($request_data, ['table_name' => 'phs_request_queue'])) ) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Request details not found in database.'));

            return null;
        }

        if (!$forced_run
           && !$this->_requests_model->can_run_request($request_arr, $forced_run)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Provided request cannot be run.'));

            return null;
        }

        if (!PHS_Bg_jobs::run(
            ['c' => 'index_bg', 'a' => 'run_request_bg'],
            ['request_id' => $request_arr['id'], 'force_run' => $forced_run])
        ) {
            $this->set_error(self::ERR_FUNCTIONALITY,
                self::_t('Error launching background job for the provided request.'));

            return null;
        }

        return $request_arr;
    }

    public function run_request(int | array $request_data, bool $forced_run = false) : ?array
    {
        if ( !$this->_load_dependencies()) {
            return null;
        }

        if ( !($request_arr = $this->_requests_model->data_to_array($request_data, ['table_name' => 'phs_request_queue'])) ) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Request details not found in database.'));

            return null;
        }

        if (!$forced_run
           && $this->_requests_model->can_run_request($request_arr)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Provided request cannot be run.'));

            return null;
        }

        $this->_requests_model->start_request($request_arr);

        return $request_arr;
    }

    protected function _do_api_call(array $request_arr) : ?array
    {
        if ( !$this->_load_dependencies()) {
            return null;
        }

        $this->reset_error();

        $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Method not implemented.'));

        return null;
    }

    protected function _do_api_call_to_url(string $url, ?string $payload = null, string $method = 'get') : ?array
    {
        if ( !$this->_load_dependencies()) {
            return null;
        }

        $this->reset_error();

        $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Method not implemented.'));

        return null;
    }

    private function _load_dependencies() : bool
    {
        $this->reset_error();

        if (
            ($this->_requests_model === null && !($this->_requests_model = PHS_Model_Request_queue::get_instance()))
        ) {
            $this->set_error(self::ERR_DEPENDENCIES, self::_t('Error loading required resources.'));

            return false;
        }

        return true;
    }
}
