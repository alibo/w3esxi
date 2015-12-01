<?php
    
    if (!defined("WHMCS")){
        die("This file cannot be accessed directly");
    }
    error_reporting(E_ALL);
    
    define('W3E_VERSION', '0.7.5');
    define('W3E_IMGDIR', '../modules/servers/w3esxi/images/');
    define('W3E_MOD_LINK',$modulelink);
    define('W3E_SET_ERROR',true);
    define('W3E_ADMIN_PATH',dirname(__FILE__));
    define('W3E_PATH',W3E_ADMIN_PATH . DIRECTORY_SEPARATOR . '..'. DIRECTORY_SEPARATOR . '..'. DIRECTORY_SEPARATOR . 'servers'. DIRECTORY_SEPARATOR . 'w3esxi');
    
    require_once(W3E_PATH . DIRECTORY_SEPARATOR . 'vmware_class.php');
    require_once(W3E_ADMIN_PATH . DIRECTORY_SEPARATOR .'render_class.php');


    $w3eRender = new W3ERender;
    $w3eRender->render();

?>