<?php
/**
 * Uninstall
 */
 if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$path=plugin_dir_path(__FILE__);
include_once($path . "woocommerce-zoho-sync-pro.php");
 include_once($path . "includes/install.php");
   $install=new vxc_zoho_install();
$settings=get_option($install->type.'_settings',array());
if(!empty($settings['plugin_data'])){
  $install->remove_data();
}
