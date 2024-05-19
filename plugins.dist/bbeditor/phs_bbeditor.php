<?php
namespace phs\plugins\bbeditor;

use phs\libraries\PHS_Plugin;

class PHS_Plugin_Bbeditor extends PHS_Plugin
{
    /**
     * Returns an instance of Bbcode class
     *
     * @return bool|libraries\Bbcode
     */
    public function get_bbcode_instance()
    {
        static $bbcode_library = null;

        if ($bbcode_library !== null) {
            return $bbcode_library;
        }

        $library_params = [];
        $library_params['full_class_name'] = '\\phs\\plugins\\bbeditor\\libraries\\Bbcode';
        $library_params['as_singleton'] = true;

        /** @var libraries\Bbcode $loaded_library */
        if (!($loaded_library = $this->load_library('phs_bbcode', $library_params))) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_LIBRARY, $this->_pt('Error loading BB code library.'));
            }

            return false;
        }

        if ($loaded_library->has_error()) {
            $this->copy_error($loaded_library, self::ERR_LIBRARY);

            return false;
        }

        $bbcode_library = $loaded_library;

        return $bbcode_library;
    }
}
