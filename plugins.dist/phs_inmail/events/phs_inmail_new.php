<?php
namespace phs\plugins\phs_inmail\events;

use phs\libraries\PHS_Event;
use phs\libraries\PHS_Utils;
use phs\system\core\attributes\PHS_Dependency;
use phs\plugins\phs_inmail\PHS_Plugin_Phs_inmail;

class PHS_Event_Inmail_new extends PHS_Event
{
    #[PHS_Dependency]
    private ?PHS_Plugin_Phs_inmail $_inmail_plugin = null;

    /**
     * @inheritdoc
     */
    public function supports_background_listeners() : bool
    {
        return false;
    }

    protected function _input_parameters() : array
    {
        return [
            'to_list'          => [],
            'cc_list'          => [],
            'bcc_list'         => [],
            'subject'          => '',
            'text_body'        => '',
            'html_body'        => '',
            'attachment_files' => [],
            'attachments_dir'  => '',
            'mime_obj'         => null,
        ];
    }

    protected function _output_parameters() : array
    {
        return [];
    }

    protected function _finally(bool $are_we_in_background) : void
    {
        if (!($attachments_dir = $this->get_input('attachments_dir'))
            || !($inmail_dir = $this->_inmail_plugin->get_inmail_dir())
            || !str_starts_with($attachments_dir, $inmail_dir)
            || !($rest_of_dir = str_replace($inmail_dir, '', $attachments_dir))
            || str_contains($rest_of_dir, '..')
            || str_contains($rest_of_dir, '~')) {
            return;
        }

        @clearstatcache();
        PHS_Utils::rmdir_tree($inmail_dir, ['recursive' => true]);
    }
}
