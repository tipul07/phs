<?php
namespace phs\plugins\phs_inmail\events;

use phs\libraries\PHS_Event;

class PHS_Event_Inmail_new extends PHS_Event
{
    protected function _input_parameters() : array
    {
        return [
            'to_list'          => [],
            'cc_list'          => [],
            'bcc_list'         => [],
            'subject'          => '',
            'attachment_files' => [],
            'mime_obj'         => null,
        ];
    }

    protected function _output_parameters() : array
    {
        return [];
    }

    protected function _finally(bool $are_we_in_background) : void
    {
        if (!($attachments = $this->get_input('attachment_files'))) {
            return;
        }

        @clearstatcache();
        foreach ($attachments as $attachment) {
            if (empty($attachment['file'])
               || !@is_file($attachment['file'])) {
                continue;
            }

            // TODO: Make sure attachment file is from attachments dir
            @unlink($attachment['file']);
        }
    }
}
