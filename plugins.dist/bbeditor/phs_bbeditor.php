<?php
namespace phs\plugins\bbeditor;

use phs\libraries\PHS_Plugin;
use phs\plugins\bbeditor\libraries\Bbcode;

class PHS_Plugin_Bbeditor extends PHS_Plugin
{
    // Keep this  for backwards compatibility
    public function get_bbcode_instance() : Bbcode
    {
        return Bbcode::get_instance();
    }
}
