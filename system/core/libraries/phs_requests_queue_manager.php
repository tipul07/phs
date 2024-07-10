<?php

namespace phs\system\core\libraries;

use phs\libraries\PHS_Library;
use phs\system\core\models\PHS_Model_Request_queue;

class PHS_Requests_queue_manager extends PHS_Library
{
    private ?PHS_Model_Request_queue $_requests_model = null;

    public function queue_request(
        string $url,
        string $method = 'get',
        ?string $payload = null,
        int $max_retries = 1,
        ?string $handle = null,
        ?array $settings = null,
        bool $run_now = true,
    ) : ?array {
        if ( !$this->_load_dependencies()) {
            return null;
        }

        if ( !($request_arr = $this->_requests_model->create_request($url, $method, $payload, $max_retries, $handle, $settings)) ) {
            $this->copy_or_set_error($this->_requests_model,
                self::ERR_FUNCTIONALITY, self::_t('Error adding the request to the queue.'));

            return null;
        }

        return $request_arr;
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
