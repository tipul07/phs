<?php
    /** @var \phs\setup\libraries\PHS_Setup_view $this */

    // Check to see if we run included from root/index.php or this script is called directly
    if( @file_exists( 'guide.php' ) )
        $setup_location = 'index.php';

    elseif( @file_exists( '_setup/guide.php' ) )
        $setup_location = '_setup/index.php';

    else
    {
        echo 'Something is wrong...';
        exit;
    }

    $this->set_context( 'page_title', 'PHS Guided Setup' );
?>
<h1>Welcome...</h1>

<p>Seems like this is first time you run PHS platform.</p>

<p>This script will help you setup the framework in current environment.</p>

<p>In order to start the setup, please click the link below.</p>

<p><a href="<?php echo $setup_location?>">Start guided setup</a></p>
