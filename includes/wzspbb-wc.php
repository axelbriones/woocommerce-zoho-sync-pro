<?php
// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;

if( !class_exists( 'wzspbb_wc' )):
class wzspbb_wc{
public $plugin_url="https://bbrion.es/woo-zoho-sync-pro";
public function __construct(){
    //Add meta boxes
add_action( 'add_meta_boxes', array($this,'add_meta_box') );
}
/**
* Add Customer information box
*
*/
public function add_meta_box(){

    add_meta_box(
            'vx-user-info',
            __( 'Marketing Data','woo-zoho-sync-pro' ),
            array( $this, 'visitor_info_box' ),
            'shop_order',
            'normal',
            'default'
        );
  }
   public function get_pro_domain(){
     global $vx_wc,$vx_cf,$vx_gf,$vx_all;
    $domain=''; $class='';
     if(!empty($vx_cf)  && is_array($vx_cf)){
    $class=key($vx_cf);
     }else if(!empty($vx_gf) && is_array($vx_gf)){
    $class=key($vx_gf);
     }else if(!empty($vx_wc) && is_array($vx_wc)){
    $class=key($vx_wc);
     }else if(!empty($vx_all) && is_array($vx_all)){
    $class=key($vx_all);
     }
     global ${$class};
  return   ${$class}->domain;
 }
public function order_submit($order_id,$posted){
$track=new vx_track_data();
$track->save_entry($order_id,$posted);
}
public function addons_key(){
       $key='';
    if(class_exists('vxcf_addons')){
        $key=vxcf_addons::addons_key();
    }
   return $key;
}
/**
* display tracked info
*
*/
public function visitor_info_box(){
    global $post;
    $order_id=$post->ID;
 $access=$this->addons_key();

 if(empty($access) ){
     $plugin_url=$this->plugin_url.'?vx_product='.$this->get_pro_domain();
 ?>
<div class="vx_panel" style="text-align: center; font-size: 16px; color: #888; font-weight: bold;">
<p><?php esc_html_e('Need Marketing Insight? ,','woo-zoho-sync-pro')?> <a href="<?php echo esc_attr($plugin_url) ?>&section=wzspbb_premium"><?php esc_html_e('Go Pro!','woo-zoho-sync-pro')?></a></p>
</div>
 <?php
 return;
 }
 $html_added=apply_filters('vx_addons_meta_box',false,$order_id,'wc');

if(!$html_added){
   ?>
   <h3 style="text-align: center;"><?php esc_html_e('No Information Available','woo-zoho-sync-pro')?></h3>
   <?php
return;
}

}
}
$addons=new wzspbb_wc();
endif;
