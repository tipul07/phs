<?php

if (!defined('PHS_PATH')) {
    exit;
}

const PHS_GRAPHQL_LIBRARIES_DIR = PHS_GRAPHQL_DIR.'libraries/';

include_once PHS_GRAPHQL_LIBRARIES_DIR.'webonyx/vendor/autoload.php';

include_once PHS_LIBRARIES_DIR.'phs_graphql_type.php';
include_once PHS_GRAPHQL_DIR.'phs_graphql.php';
include_once PHS_CORE_DIR.'phs_api_graphql.php';
