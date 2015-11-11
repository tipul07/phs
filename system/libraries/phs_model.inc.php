<?php

include_once( PHS_LIBRARIES_DIR.'phs_model_base.inc.php' );
if( version_compare( PHP_VERSION, '5.5.0', '<' ) )
    include_once( PHS_LIBRARIES_DIR.'phs_model_generator.inc.php' );
else
    include_once( PHS_LIBRARIES_DIR.'phs_model_simple.inc.php' );

abstract class PHS_Model extends PHS_Model_Core_Generator
{
}
