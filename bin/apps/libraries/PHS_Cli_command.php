<?php

namespace phs\cli\apps\libraries;

use phs\PHS_Cli;
use phs\libraries\PHS_Registry;

abstract class PHS_Cli_command extends PHS_Registry
{
    protected string $_descr = 'No description yet.';

    // Return the command line command to be executed (e.g. plugins, update, etc)
    abstract public function get_cli_command() : string;

    abstract public function run(PHS_Cli $app, array $params = []) : int;

    public function get_command_options() : array
    {
    }

    public function get_description() : string
    {
        return $this->_descr;
    }
}
