<?php
if ( ! defined( 'ABSPATH' ) ) {
     exit;
 }
 ?>  <h3><?php esc_html_e('Uninstall WooCommerce Zoho Plugin','woo-zoho-sync-pro'); ?></h3>
  <?php
  if(isset($_POST[$this->id.'_uninstall'])){
  ?>
  <div class="vxc_alert updated  below-h2">
  <h3><?php esc_html_e('Success','woo-zoho-sync-pro'); ?></h3>
  <p><?php esc_html_e('WooCommerce Zoho Plugin has been successfully uninstalled','woo-zoho-sync-pro'); ?></p>
  <p>
  <a class="button button-hero button-primary" href="plugins.php"><?php esc_html_e("Go to Plugins Page",'woo-zoho-sync-pro'); ?></a>
  </p>
  </div>
  <?php
  }else{
  ?>
  <div class="vxc_alert error below-h2">
  <h3><?php esc_html_e("Warning",'woo-zoho-sync-pro'); ?></h3>
  <p><?php esc_html_e('This Operation will delete all Zoho logs and feeds.','woo-zoho-sync-pro'); ?></p>
  <p><button class="button button-hero button-secondary" id="vx_uninstall" type="submit" onclick="return confirm('<?php esc_html_e("Warning! ALL Zoho Feeds and Logs will be deleted. This cannot be undone. OK to delete, Cancel to stop.", 'woo-zoho-sync-pro')?>');" name="<?php echo esc_attr($this->id) ?>_uninstall" title="<?php esc_html_e("Uninstall",'woo-zoho-sync-pro'); ?>" value="yes"><?php esc_html_e("Uninstall",'woo-zoho-sync-pro'); ?></button></p>
  </div>
  <?php
  } ?>
