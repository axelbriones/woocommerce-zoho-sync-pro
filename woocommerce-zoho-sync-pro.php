<?php
/*
* Plugin Name: WooCommerce Zoho Sync Pro
* Description: Integrates WooCommerce with Zoho allowing new orders to be automatically sent to your Zoho account.
* Version: 1.0.0
* Requires at least: 4.7
* Author: Byron Briones
* Author URI: https://bbrion.es
* Plugin URI: https://bbrion.es
* Text Domain: woo-zoho-sync-pro
* Domain Path: /languages/
*
*/
// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;

if( !class_exists( 'wzspbb_zoho' ) ):

class wzspbb_zoho{
  public $id='wzspbb_zoho';
  public $domain='wzspbb-zoho';
  public $crm_name='zoho';
  public $version = '1.0.0';
  public $min_wc_version = '3.0';
  public $type = 'wzspbb_zoho_pro';

  public $user='';
  public static $path='';
  public static $slug='';
  public static $base_url='';
  public static $tooltips='';

  private $plugin_dir;
  private $filter_condition;
  public static $order;
  private $temp= '';
  public static $note= '';
  public static $title='WooCommerce Zoho Sync Pro';
  public static $save_key='';
  public static $db_version='';
  public static $vx_plugins;
  public static $feeds_res;
  public static $_order;
  public static $_products;
  public static $wc_status;
  public static $wc_status_msg;
  public static $plugin;
    public static $all_feeds;
    public static $is_pr;
  public static $processing_feed;
  public static $api_timeout;
  public static $wp_user_update=0;
  public static $order_sent=false;
  public static $wp_product_update=array();

public function instance(){
 add_action('plugins_loaded', array($this,'setup_main'));
 register_deactivation_hook(__FILE__,array($this,'deactivate'));
register_activation_hook(__FILE__,(array($this,'activate')));
}

public function init(){

  self::$wc_status= $this->wc_status();
    if(self::$wc_status !== 1){
    self::$slug=$this->get_slug();
  add_action( 'admin_notices', array( $this, 'install_wc_notice' ) );
  add_action( 'after_plugin_row_'.self::$slug, array( $this, 'install_wc_notice_plugin_row' ) );
  return;
  }

  require_once(self::$path . "includes/plugin-pages.php");
  require_once(self::$path . "includes/wzspbb-wc.php");

self::$is_pr=true;
 $pro_file=self::$path . 'pro/add-ons.php';
if(file_exists($pro_file)){
include_once($pro_file);
}
}


public function setup_main(){

  add_action( 'woocommerce_order_status_changed',array($this,'status_changed'), 10, 3 );
  add_action( 'ywraq_after_create_order',array($this,'quote_created'), 10, 3 );
  add_action( 'woocommerce_subscription_status_updated',array($this,'status_changed_subscription'), 10, 3 );

  add_action( 'woocommerce_checkout_update_order_meta',array($this,'order_submit'), 99, 2 );
  add_action( 'woocommerce_new_order',array($this,'order_submit_new'),10,2 ); //order_id

  add_action('woocommerce_saved_order_items',array($this,'save_lines'),999,2); //update order items

add_action( 'profile_update',array($this,'profile_update_set'), 999 );
add_action( 'user_register',array($this,'profile_update_set'), 999 );
add_action( 'shutdown',array($this,'profile_update'), 40 );


add_action('woocommerce_update_product',array($this,'save_product_api'),999,2);
add_action('woocommerce_new_product',array($this,'new_product'),999,2);
add_action('woocommerce_save_product_variation',array($this,'save_variation'),999);

self::$path=$this->get_base_path();
$sync_file=self::$path . "pro/sync-cron.php";
if( file_exists($sync_file) ){
require_once( $sync_file );
}

if(is_admin()){
  add_action('init', array($this,'init'));
  load_plugin_textdomain('woo-zoho-sync-pro', FALSE, $this->plugin_dir_name() . '/languages/' );

      self::$db_version=get_option($this->type."_version");
  if( self::$db_version != $this->version && current_user_can( 'manage_options' )){
$this->install_plugin();
  }
}

 add_action(
    'before_woocommerce_init',
    function() {
        if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        }
    }
);
  }

public function install_plugin(){
           self::$path=$this->get_base_path();
  include_once(self::$path . "includes/install.php");
  $class=$this->id.'_install';
  $install=new $class();
  $install->create_tables();
  $install->create_roles();
  update_option($this->type."_version", $this->version);
}

  public  function wc_status() {

  $installed = 0;
  if(!class_exists('WooCommerce')) {
  if(file_exists(WP_PLUGIN_DIR.'/woocommerce/woocommerce.php')) {
  $installed=2;
  }
  }else{
  $installed=1;
  if(!version_compare(WOOCOMMERCE_VERSION, $this->min_wc_version, ">=")){
  $installed=3;
  }
  }
  if($installed !=1){
    if($installed === 0){
  $message = sprintf(__("%sWooCommerce%s is required. %sDownload latest version!%s", 'woo-zoho-sync-pro'), "<a href='https://woocommerce.com/'>", "</a>", "<a href='https://woocommerce.com/'>", "</a>");
  }else if($installed === 2){
  $message = sprintf(__('WooCommerce is installed but not active. %sActivate WooCommerce%s to use the WooCommerce Zoho Sync Pro Plugin','woo-zoho-sync-pro'), '<strong><a href="'.wp_nonce_url(admin_url('plugins.php?action=activate&plugin=woocommerce/woocommerce.php'), 'activate-plugin_woocommerce/woocommerce.php').'">', '</a></strong>');
  } else if($installed === 3){
  $message = sprintf(__("A higher version of %sWooCommerce%s is required. %sDownload latest version!%s", 'woo-zoho-sync-pro'), "<a href='https://woocommerce.com/'>", "</a>", "<a href='https://woocommerce.com/'>", "</a>");
  }
  self::$wc_status_msg=$message;
  }
  return $installed;
  }

  public function install_wc_notice(){
        $message=self::$wc_status_msg;
  if(!empty($message)){
  $this->display_msg('admin',$message,'woocommerce');
     $this->notice_js=true;

  }
  }

  public function install_wc_notice_plugin_row(){
  $message=self::$wc_status_msg;
  if(!empty($message)){
   $this->display_msg('',$message,'woocommerce');
  }
  }

  public function check_filter($feed,$item=array()){
  $filters=$this->post('filters',$feed);
  $final=$this->filter_condition=array();
  if(is_array($filters)){
      $time=current_time('timestamp');

  foreach($filters as $filter_s){
  $check=null; $and=null;  $and_c=array();
  if(is_array($filter_s)){
  foreach($filter_s as $filter){
  $field=$filter['field'];
  $fval=$filter['value'];
  $val=$this->get_field_val($filter,$item);

  if(is_array($val)){ $val=implode(' ',$val); }
  $val=strtolower($val);
  $fval=strtolower($fval);
 $country_fields=array("billing_country","shipping_country","country");
  if(in_array($field,$country_fields)){
  $countries=WC()->countries->countries;
  if(in_array($filter['op'],array("is","is_not"))){
   if(strlen($field)>2){
    $fval=ucwords($fval);
    if(is_array($countries)){
        foreach($countries as $c_code=>$c_name){
            if(preg_match('/'.$fval.'/i',$c_name)){
           $fval=$c_code;
           break;
            }
        }
    }
   }else{
       $fval=strtoupper($fval);
   }
  }else{
  $val=isset($countries[$val]) ? strtolower($countries[$val]) : "";
  }
  }
  switch($filter['op']){
  case"is": $check=$fval == $val;break;
  case"is_not": $check=$fval != $val;     break;
  case"contains": $check=strpos($val,$fval) !==false;     break;
  case"not_contains": $check=strpos($val,$fval) ===false;     break;
  case"is_in": $check=strpos($fval,$val) !==false;     break;
  case"not_in": $check=strpos($fval,$val) ===false;     break;
  case"starts": $check=strpos($val,$fval) === 0;     break;
  case"not_starts": $check=strpos($val,$fval) !== 0;     break;
  case"ends": $check=(strpos($val,$fval)+strlen($fval)) == strlen($val);  break;
  case"not_ends": $check=(strpos($val,$fval)+strlen($fval)) != strlen($val);  break;
  case"less": $check=(float)$val<(float)$fval; break;
  case"greater": $check=(float)$val>(float)$fval;  break;
  case"less_date": $check=strtotime($val,$time) < strtotime($fval,$time);  break;
  case"greater_date": $check=strtotime($val,$time) > strtotime($fval,$time);  break;
  case"equal_date": $check=strtotime($val,$time) == strtotime($fval,$time);  break;
  case"empty": $check=empty($val);  break;
  case"not_empty": $check=$val != "";  break;
  }
  $and_c[]=array("check"=>$check,"field_val"=>$fval,"input"=>$val,"field"=>$field,"op"=>$filter['op']);
  if($check !== null){
  if($and !== null){
  $and=$and && $check;
  }else{
  $and=$check;
  }
  }
  }
  }
  if($and !== null){
  if($final !== null){
  $final=$final || $and;
  }else{
  $final=$and;
  }
  }
  $this->filter_condition[]=$and_c;
  }
  }
  return $final === null ? true : $final;
  }

public function profile_update(){
    if(!empty(self::$wp_user_update)){
$this->push(self::$wp_user_update,'save_user');
    }

}
public function profile_update_set($user_id){
    self::$wp_user_update=$user_id;
}

public function new_product($post_id,$product){
    if(defined('REST_REQUEST') && method_exists($product,'get_status') && $product->get_status() == 'publish' ){
 $res=$this->push($post_id,'save_product');
    }
}
public function save_product_api($post_id,$product=''){
    if(defined( 'REST_REQUEST' ) ){
   $this->save_product($post_id,$product);
    }
}
public function save_product($post_id,$product=''){

   if(!isset(self::$wp_product_update[$post_id]) &&  !defined( 'DOING_CRON' ) ){
   $product=wc_get_product($post_id);
  if( is_object($product)  && method_exists($product,'get_status') && $product->get_status() == 'publish' && !in_array($product->get_type(),array('variable')) ){
$res=$this->push($post_id,'save_product');
self::$wp_product_update[$post_id]='';
  }
   }
}

public function save_variation($post_id){
   if(!isset(self::$wp_product_update[$post_id]) && !defined( 'DOING_CRON' ) ){
$res=$this->push($post_id,'save_product');
self::$wp_product_update[$post_id]='';
   }
}

public function status_changed_subscription($sub,$status,$old){

    $this->push($sub,'vxswc-'.$status);
}
public function quote_created($id,$post,$old){
    $this->push($id,'ywraq-new');
}
  public function status_changed($id,$old_status,$new_status){

  if(!in_array($new_status,array('ywraq-new')) ){
  $this->push($id,$new_status);
  }
  }
public function save_lines($id,$items){
    if(defined( 'DOING_AJAX' )){
     $meta=get_option($this->type.'_settings',array());
 if(isset($meta['update']) && $meta['update'] == 'yes'){
 $this->push($id,'update_order');
 }
    }
}
    public function order_submit($id){
   if(!self::$order_sent){
      $status="submit";
  $this->push($id,$status);
      }
  }
    public function order_submit_new($id,$order){
   if(is_object($order) && method_exists($order,'get_type') && $order->get_type() == 'shop_order'){
   if(defined('REST_REQUEST') || is_admin()){
    $items = $order->get_items();
    if(!empty($items)){
    self::$order_sent=true;
   $this->push($id,'submit');
    }
   }
   }
  }
  public function verify_settings_msg($info=""){
  $link=$this->link_to_settings();

  return "<div class='alert_danger crm_alert'>".sprintf(__("Please Configure %sZoho Settings%s",'woo-zoho-sync-pro'),"<a href='".$link."'>","</a>")."</div>";

  }

  public function get_info($id){
      global $wpdb; $id=(int)$id;
 $table= $this->get_table_name('accounts');
$info = $wpdb->get_row( 'SELECT * FROM '.$table.' where id='.$id.' limit 1',ARRAY_A );
$info_arr=array(); $data=array();  $meta=array();
if(is_array($info)){
if(!empty($info['data'])){
    $info['data']=trim($info['data']);
    if(strpos($info['data'],'{') !== 0){
        $info['data']=$this->de_crypt($info['data']);
    }
  $info_arr=json_decode($info['data'],true);
if(!is_array($info_arr)){
    $info_arr=array();
}
}

$info_arr['time']=$info['time'];
$info_arr['id']=$info['id'];
$info['data']=$info_arr;
if(!empty($info['meta'])){
  $meta=json_decode($info['meta'],true);
}
$info['meta']=is_array($meta) ? $meta : array();

}
  return $info;
  }

  public function display_msg($type,$message,$id=""){
  global $wp_version;
  $ver=floatval($wp_version);
  if($type == "admin"){
     if($ver<4.2){
  ?>
    <div class="error vx_notice notice" data-id="<?php echo esc_attr($id) ?>"><p style="display: table"><span style="display: table-cell; width: 98%"><span class="dashicons dashicons-megaphone"></span> <b><?php echo esc_html(self::$title) ?>. </b><?php echo wp_kses_post($message);?> </span>
<span style="display: table-cell; padding-left: 10px; vertical-align: middle;"><a href="#" class="notice-dismiss" title="<?php esc_html_e('Dismiss Notice','woo-zoho-sync-pro') ?>">dismiss</a></span> </p></div>
  <?php
     }else{
  ?>
  <div class="error vx_notice notice is-dismissible" data-id="<?php echo esc_attr($id) ?>"><p><span class="dashicons dashicons-megaphone"></span> <b><?php echo esc_html(self::$title) ?>. </b> <?php echo wp_kses_post($message);?> </p>
  </div>
  <?php
     }
  }else{
  ?>
  <tr class="plugin-update-tr"><td colspan="5" class="plugin-update">
  <style type="text/css"> .vx_msg a{color: #fff; text-decoration: underline;} .vx_msg a:hover{color: #eee} </style>
  <div style="background-color: rgba(224, 224, 224, 0.5);  padding: 9px; margin: 0px 10px 10px 28px "><div style="background-color: #d54d21; padding: 5px 10px; color: #fff" class="vx_msg"> <span class="dashicons dashicons-info"></span> <?php echo wp_kses_post($message) ?>
</div></div></td></tr>
  <?php
  }
  }
  public function screen_msg($type,$message){
      $type=$type == "" ? "updated" : $type;
  ?>
  <div class="<?php echo $type ?> notice is-dismissible"><p><?php echo $message;?></p></div>
  <?php
  }
  public  function format_user_info($info,$is_html=false){
  $str=""; $file="";
  self::$path=$this->get_base_path();
  if($is_html){
  if(file_exists(self::$path."templates/email.php")){
  ob_start();
  include_once(self::$path."templates/email.php");
  $file= ob_get_contents();
  ob_end_clean();
  }
  if(trim($file) == "")
  $is_html=false;
  }
  if(isset($info['info']) && is_array($info['info'])){
  if($is_html){
  if(isset($info['info_title'])){
  $str.='<tr><td style="font-family: Helvetica, Arial, sans-serif;background-color: #C35050; height: 36px; color: #fff; font-size: 24px; padding: 0px 10px">'.$info['info_title'].'</td></tr>'."\n";
  }
  if(is_array($info['info']) && count($info['info'])>0){
  $str.='<tr><td style="padding: 10px;"><table border="0" cellpadding="0" cellspacing="0" width="100%;"><tbody>';
  foreach($info['info'] as $f_k=>$f_val){
  $str.='<tr><td style="padding-top: 10px;color: #303030;font-family: Helvetica;font-size: 13px;line-height: 150%;text-align: right; font-weight: bold; width: 28%; padding-right: 10px;">'.$f_k.'</td><td style="padding-top: 10px;color: #303030;font-family: Helvetica;font-size: 13px;line-height: 150%;text-align: left; word-break:break-all;">'.$f_val.'</td></tr>'."\n";
  }
  $str.="</table></td></tr>";
  }
  }else{
  if(isset($info['title']))
  $str.="\n".$info['title']."\n";
  foreach($info['info'] as $f_k=>$f_val){
  $str.=$f_k." : ".$f_val."\n";
  }
  }
  }
  if($is_html){
  $str=str_replace(array("{title}","{msg}","{sf_contents}"),array($info['title'],$info['msg'],$str),$file);
  }
  return $str;
  }
  public function deactivate($action="deactivate"){

  do_action('plugin_status_'.$this->type,$action);
  }
  public function activate(){
$this->install_plugin();
do_action('plugin_status_'.$this->type,'activate');
  }
  public function __log($arr,$log_id=""){
  global $wpdb;
  if(!is_array($arr) || count($arr) == 0)
  return;
  $table_name = $this->get_table_name();
  $sql_arr=array();
  foreach($arr as $k=>$v){
   $sql_arr[$k]=is_array($v) ? json_encode($v) : $v;
  }
  $log_id=(int)$log_id;
  $res=false;
  if(!empty($log_id)){
   $res=$wpdb->update($table_name,$sql_arr,array("id"=>$log_id));
  }else{
   $res=$wpdb->insert($table_name,$sql_arr);
   $log_id=$wpdb->insert_id;
  }
  return $log_id;
  }
  public function tooltip($str){
  if($str == ""){return;}
  ?>
  <i class="vx_icons vxc_tips fa fa-question-circle" data-tip="<?php echo esc_html($str) ?>"></i>
  <?php
  }
  public function add_notice_query_var($location){

  remove_filter( 'redirect_post_location', array( $this, 'add_notice_query_var' ), 99 );
  return add_query_arg( array( $this->id.'_msg' => 'true' ), $location );
  }
  public function is_crm_page($plugin_page=""){
      $page='';
      if(isset($_GET['tab'])){
      $page=$this->post('tab');
      }else if(isset($_GET['page'])){
      $page=$this->post('page');
      }
   if(!empty($plugin_page)){
      if($page == $plugin_page){
          return true;
      }else{
          return false;
      }
   }
$pages=array($this->id,$this->id.'_log');
if(in_array($page,$pages)){
    return true;
}

  global $post;
  if(isset($post->post_type) && $post->post_type == $this->id){
  return true;
  }
  return false;
  }
  public function verify_address($value,$f_key,$order){
  if( $f_key=='_billing_address_1' && isset($order['_billing_address_2'][0]) && $order['_billing_address_2'][0]!=""){
  $value.=" ".$order['_billing_address_2'][0];
  }
  if( $f_key=='_shipping_address_1' && isset($order['_shipping_address_2'][0]) && $order['_shipping_address_2'][0]!=""){
  $value.=" ".$order['_shipping_address_2'][0];
  }
  return $value;
  }

  public function get_base_url(){
  return plugin_dir_url(__FILE__);
  }
  public function get_slug(){
       if(empty(self::$slug)){
  self::$slug=plugin_basename(__FILE__);
 }
  return self::$slug;
  }

  public function get_base_path(){
  return plugin_dir_path(__FILE__);
  }
  public function link_to_settings($part=""){
  return admin_url( 'admin.php?page=wc-settings&tab='.$this->id.$part);
  }
  public function plugin_dir_name(){
  if(!empty($this->plugin_dir)){
  return $this->plugin_dir;
  }
  self::$path=$this->get_base_path();
  $this->plugin_dir=basename(self::$path);
  return $this->plugin_dir;
  }
  public function is_valid_email($email){
         if(function_exists('filter_var')){
      if(filter_var($email, FILTER_VALIDATE_EMAIL)){
      return true;
      }
       }else{
       if(strpos($email,"@")>1){
      return true;
       }
       }
   return false;
  }
  public function get_table_name($table="log"){
  global $wpdb;
  return $wpdb->prefix . $this->id."_".$table;
  }
  public function time_offset(){
 $offset = (int) get_option('gmt_offset');
  return $offset*3600;
  }
  public static function get_key(){
  $k='Wezj%+l-x.4fNzx%hJ]FORKT5Ay1w,iczS=DZrp~H+ve2@1YnS;;g?_VTTWX~-|t';
  if(defined('AUTH_KEY')){
  $k=AUTH_KEY;
  }
  return substr($k,0,30);
  }
  public static function de_crypt($info){
  $info=trim($info);
  if($info == "")
  return '';
  $str=base64_decode($info);
  $key=self::get_key();
      $decrypted_string='';
     if(function_exists("openssl_encrypt") && strpos($str,':')!==false ) {
$method='AES-256-CBC';
$arr = explode(':', $str);
 if(isset($arr[1]) && $arr[1]!=""){
 $decrypted_string=openssl_decrypt($arr[0],$method,$key,false, base64_decode($arr[1]));
 }
 }else{
     $decrypted_string=$str;
 }
  return $decrypted_string;
  }
  public static function en_crypt($str){
  $str=trim($str);
  if($str == "")
  return '';
  $key=self::get_key();
   if(function_exists("openssl_encrypt")) {
$method='AES-256-CBC';
$iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));
$enc_str=openssl_encrypt($str,$method, $key,false,$iv);
$enc_str.=":".base64_encode($iv);
  }else{
      $enc_str=$str;
  }
  $enc_str=base64_encode($enc_str);
  return $enc_str;
  }
  public function post($key, $arr="") {
  if(is_array($arr)){
  return isset($arr[$key])  ? $arr[$key] : "";
  }
  return isset($_REQUEST[$key]) ? $this->clean($_REQUEST[$key]) : "";
  }
public function clean($var,$key=''){
    if ( is_array( $var ) ) {
$a=array();
    foreach($var as $k=>$v){
  $a[$k]=$this->clean($v,$k);
    }
  return $a;
    }else {
     $var=wp_unslash($var);
  if(in_array($key,array('note_val','value','email_body','item_desc'))){
 $var=sanitize_textarea_field($var);
  }else{
  $var=sanitize_text_field($var);
  }
return  $var;
    }
}
  public function post2($key,$key2, $arr="") {
  if(is_array($arr) && isset($arr[$key]) && is_array($arr[$key])){
  return isset($arr[$key][$key2])  ? $arr[$key][$key2] : "";
  }
  return isset($_REQUEST[$key][$key2]) && is_array($_REQUEST[$key]) ? $_REQUEST[$key][$key2] : "";
  }
  public function post3($key,$key2,$key3, $arr="") {
  if(is_array($arr)){
  return isset($arr[$key][$key2][$key3])  ? $arr[$key][$key2][$key3] : "";
  }
  return isset($_REQUEST[$key][$key2][$key3]) ? $_REQUEST[$key][$key2][$key3] : "";
  }
  public function log_msg($msg){
       if ( class_exists( 'WC_Logger' ) ) {
          $logger = new WC_Logger();
          $slug=$this->plugin_dir_name();
          $logger->add( $slug, $msg);
       }
  }
  public function format_note($note,$show_error=false){
       $object=$this->post('object',$note);

  $id=$this->post('id',$note);
  $error=$this->post('error',$note);
  $msg="";
  if(!empty($note['status'])){
  if($note['status'] == "4"){
    $msg=sprintf(__('Zoho (%s) Filtered','woo-zoho-sync-pro'),$object);
  }else{
  $link=sprintf(__("with ID # %s",'woo-zoho-sync-pro'),$this->post('id',$note));
  if($this->post('link',$note) !=""){
      $id_link='<a href="'.$this->post('link',$note).'" target="_blank" title="'.$this->post('id',$note).'">'.$this->post('id',$note).'</a>';
  $link=sprintf(__('with ID # %s','woo-zoho-sync-pro'),$id_link);
  }
  if($this->post('status',$note) == 3){
  $link="Web2".$object;
  }
  $action=$this->post('action',$note);
if($note['status'] == '1'){
  $msg=sprintf(__('Added to Zoho (%s) ','woo-zoho-sync-pro'),$object).$link;
  }else{
  $msg=sprintf(__("Updated to Zoho (%s) ",'woo-zoho-sync-pro'),$object).$link;
  }
  }
  }else if($show_error){
     if(empty($error)){
                      if(!empty($note['meta'])){
              $error=$note['meta'];
                      }else{
              $error= __("Error While Posting to Zoho",'woo-zoho-sync-pro');
                      }
      }
  $msg=$error;
  }
  if(isset($note['log_id'])){
  $log_url=admin_url( 'admin.php?page='.$this->id.'_log&id='.$note['log_id']);
  $msg.=' - <a href="'.esc_url($log_url).'" class="vx_log_link" title="'.__('View Detail','woo-zoho-sync-pro').'" data-id="'.esc_html($note['log_id']).'">'.__('View Detail','woo-zoho-sync-pro')."</a>";
  }
  return $msg;
  }
    public function wc_get_data_from_item($item){
      $product_id='';
      $var_id='';
      $qty='';
      $meta=array();
      if(isset($item['item_meta_array']) && is_array($item['item_meta_array']) ){
          foreach($item['item_meta_array'] as $k=>$v){
          if($v->key){
              $key=$v->key;
              if(strpos($key,'_') !== false){
              $key=substr($key,1);
              }
              $meta[$key]=$v->value;
          }

          }
      }


    return $meta;
  }
 public function order_info_fields($f_key=""){
         $_order=self::$_order;
         $val="";

        switch($f_key){
            case"_order_total": $val=$_order->get_total(); break;
            case"_order_subtotal": $val=$_order->get_subtotal(); break;
            case"_total_refunded": $val=$_order->get_total_refunded(); break;
            case"_total_refunded_tax": $val=$_order->get_total_tax_refunded(); break;
            case"_total_shipping_refunded": $val=$_order->get_total_shipping_refunded(); break;
            case"_total_qty_refunded": $val=$_order->get_total_qty_refunded(); break;
            case"_items_count": $val=$_order->get_item_count(); break;
            case"_order_status": $val=$_order->get_status(); break;
            case"_customer_notes": $val=$_order->get_customer_note(); break;
            case"_shipping_method_title": $val=$_order->get_shipping_method(); break;
                 case"parent_post_id":
            $post_id=$_order->get_id();
            $val=wp_get_post_parent_id($post_id);
            break;
            case"last_refund_date":
            $order_refunds=$_order->get_refunds();
if(is_array($order_refunds) && isset($order_refunds[0])){
$val=$order_refunds[0]->get_date_created()->date('d-M-Y H:i:s');
}
            break;
            case"_order_discount_total":
            $val=$_order->get_discount_total();
            break;
            case"_order_discount_total_refunded":
            $val=$_order->get_discount_total();
            $refund=$_order->get_total_refunded();
            $val+=$refund;
            break;
            case"_order_total_refunded":
            $val=$_order->get_total() - $_order->get_total_refunded();
            break;
            case"_order_tax_total": $val=$_order->get_total_tax(); break;
            case"_order_shipping_total": $val=$_order->get_shipping_total(); break;
            case"_order_shipping_total_tax": $val=$_order->get_shipping_total()+$_order->get_shipping_tax(); break;
            case"_used_coupns":
            $coupons=$_order->get_coupon_codes();
             if(is_array($coupons)){
                 $val=implode(', ',$coupons);
             }
             break;
            case"_order_fees":
              $fees=$_order->get_fees();
              if(is_array($fees) && version_compare( WC_VERSION, '3.0.0', '>=' ) ){
  $val=array();
  foreach($fees as $fee){
    $val[]=$fee->get_name().' : '.$fee->get_total();
  }
  $val=implode("\r\n ----- \r\n",$val);
              }
             break;
            default:

if(in_array($f_key,array('_order_fees_total','_order_fees_total_tax','_order_fees_total_shipping'))){
$fees=$_order->get_fees();
$val=$valt=0;
 if(version_compare( WC_VERSION, '3.0.0', '>=' )){
 foreach($fees as $fee){
    $val+=$fee->get_total();
     $valt+=$fee->get_total()+$fee->get_total_tax();
  }
 }
self::$order['_order_fees_total_tax']= abs($valt);
self::$order['_order_fees_total']= abs($val);
self::$order['_order_fees_total_shipping']= $val + $_order->get_shipping_total();
}
else if(in_array($f_key,array('_order_items_skus','_order_items_titles','_order_items_qty','_order_items'))){
            $items=$_order->get_items();
            $info=array();
            if(is_array($items) && count($items)>0){

 foreach($items as $k=>$item){
 if(method_exists($item,'get_product_id')){
          $product=$item->get_product();
          if(!$product){ continue; }
          $sku=$product->get_sku();

             $item_info=array(
             __('Title','woo-zoho-sync-pro')=>$item->get_name()
             ,__('Quantity','woo-zoho-sync-pro')=>$item->get_quantity()
             ,__('Total','woo-zoho-sync-pro')=>$item->get_total()
             );
             if(!empty($sku)){
          $item_info['SKU']=$sku;
             }
             if(method_exists($item,'get_total_tax')){
             }
  $extra_ops=wc_get_order_item_meta($item->get_id(),'_tmcartepo_data',true);
  if(!empty($extra_ops)){
      foreach($extra_ops as $v){
          if(!empty($v['name'])){
          $item_info[$v['name']]=$v['value'].' - '.$v['price'];
          }
      }
  }

  $item_attrs=$this->get_item_attrs($item);
  foreach($item_attrs as $attr=>$attr_val){
    $item_info[$attr]=$attr_val;
  }

             $info[]=$item_info;
     } }
            }
          if(count($info)>0){
           $skus=array(); $titles=$qtys=array();
            foreach($info as $meta){
                if(isset($meta['SKU'])){
                $skus[]=$meta['SKU'];
                }
                $titles[]=$meta['Title'];
                $qtys[]=$meta['Title'].'('.$meta['Quantity'].')';
             if(!empty($val)){
              $val.="------------\n";
             }
             foreach($meta as $k=>$v){
              $val.=$k." : ".$v."\n";
             }
            }
            self::$order['_order_items_titles']=implode(', ', $titles);
           self::$order['_order_items_skus']= implode(', ', $skus);
           self::$order['_order_items_qty']= implode(', ', $qtys);
           self::$order['_order_items']= $val;

          }

            }
else if( strpos($f_key,'__vxo') !== false && !isset(self::$order[ '__vxo_last_order_number' ]) ){
             $customer_orders=array();
            $user_id = $_order->get_user_id();
            if(!empty($user_id)){
                $customer_orders = get_posts( array(
            'numberposts'     => -1,
            'meta_key'        => '_customer_user',
            'meta_value'      => $user_id,
            'post_type'       => 'shop_order',
            'post_status'     => array_keys( wc_get_order_statuses() ),
            'order'              => 'DESC',
        ) );
            }

            $counter = 0;
self::$order[ '__vxo_order_total' ]=0;
            foreach( $customer_orders as $order_details ){

                $order_id = isset( $order_details->ID ) ? intval( $order_details->ID ) : 0;

                if( !$order_id ) {

                    continue;
                }

                $order = new WC_Order( $order_id );

                if( empty( $order ) || is_wp_error( $order ) ) {

                    continue;
                }
                $order_items = $order->get_items(); $order_total=0;
if(!in_array($order->get_status(),array('cancelled','refunded'))){
                $order_total = $order->get_total();
}
                $order_count=count( $customer_orders );
                self::$order[ '__vxo_order_total' ] += floatval( $order_total );
                self::$order[ '__vxo_order_count' ]=$order_count;
                                                   $order_date='';
if(method_exists($order,'get_date_created')){
  $order_date=$order->get_date_created()->format('F d, Y H:i:s');
  }else{
   $order_date=$order->order_date;
  }
                if( !$counter ){
                    self::$order[ '__vxo_last_order_date' ] = $order_date;

                    self::$order[ '__vxo_last_order_value' ] = $order_total;

                    self::$order[ '__vxo_last_order_number' ] = $order_id;

                    self::$order[ '__vxo_last_order_status' ] = "wc-".$order->get_status();
                }
                if( $counter == $order_count - 1 ) {
                    self::$order[ '__vxo_first_order_date' ] = $order_date;
                    self::$order[ '__vxo_first_order_value' ] = $order_total;
                }

                $counter++;
            }
            }
else if(strpos($f_key,'_vxst') === 0 && !isset(self::$order['_vxst_billing_country']) && is_object($_order) && method_exists($_order,'get_billing_country')){
$contb=$_order->get_billing_country();
$conts=$_order->get_shipping_country();
 $stateb=$_order->get_billing_state();
 $states=$_order->get_shipping_state();
  $contbs=WC()->countries->get_countries();
   if(!empty($contb) && !empty($contbs[$contb])){
self::$order['_vxst_billing_country'] = $contbs[$contb];
 }

if(!empty($stateb)){
  $statesb=WC()->countries->get_states($contb);
 if(!empty($statesb[$stateb])){
self::$order['_vxst_billing_state'] = $statesb[$stateb];
 }
}
if(!empty($conts) && !empty($contbs[$conts])){
self::$order['_vxst_shipping_country'] = $contbs[$conts];
 }

if(!empty($states)){
  $statesb=WC()->countries->get_states($conts);
 if(!empty($statesb[$states])){
self::$order['_vxst_shipping_state'] = $statesb[$states];
 }
}


}
else if($f_key == '_order_notes' && !isset(self::$order[$f_key]) ){
                $order_id=$_order->get_id();
 $comments = wc_get_order_notes(array('order_id'=>$order_id));
 $notes=array();
 foreach($comments as $v){
     $notes[]=$v->content;
 }
 $val=self::$order[$f_key]=implode("\r\n ----- \r\n",$notes);
 }
else if(is_object($_order) && method_exists($_order,'get_meta')){
    $keys=array('_address', '_address_1', '_address_2', '_city', '_postcode', '_state', '_country', '_company', '_email', '_first_name', '_last_name', '_phone' );
    $s_key=str_replace(array('_billing','_shipping'),'',$f_key);
     if(in_array($s_key,$keys)){
         $function = 'get_' . ltrim( $f_key, '_' );
            if ( is_callable( array( $_order, $function ) ) ) {
            $val=$_order->{$function}();
            }

     }else{
  $val=$_order->get_meta($f_key);

     }

}
 if(isset(self::$order[$f_key])){
  $val=self::$order[$f_key];
}

         $f_key='';
             break;
        }
        if(!empty($f_key)){
       self::$order[$f_key]=$val;
        }

      return $val;
 }
  public function get_item_attrs($item){
  $meta_data=$item->get_meta_data(); $item_info=array();
        foreach ( $meta_data as $meta ) {
            if ( empty( $meta->id ) || '' === $meta->value || ! is_scalar( $meta->value ) || substr( $meta->key, 0, 1 ) == '_' ) {
                continue;
            }

            $meta->key     = rawurldecode( (string) $meta->key );
            $meta->value   = rawurldecode( (string) $meta->value );
            $attribute_key = str_replace( 'attribute_', '', $meta->key );
              $product=$item->get_product();
            $display_key   = wc_attribute_label( $attribute_key, $product );
            $display_value = wp_strip_all_tags( $meta->value );

            if ( taxonomy_exists( $attribute_key ) ) {
                $term = get_term_by( 'slug', $meta->value, $attribute_key );
                if ( ! is_wp_error( $term ) && is_object( $term ) && $term->name ) {
                    $display_value = $term->name;
                }
            }
            $item_info[$display_key]=$display_value;

        }
   return $item_info;
 }
 public function get_feed_log($feed_id,$order_id,$object,$parent_id=""){
          global $wpdb;
 $table= $this->get_table_name('log');
 $sql= $wpdb->prepare('SELECT * FROM '.$table.' where order_id = %d and feed_id = %d and crm_id!="" and object=%s  and parent_id=%d order by id desc limit 1',$order_id,$feed_id,$object,$parent_id);
$results = $wpdb->get_row( $sql ,ARRAY_A );
if(empty($results)){ $results=array('status'=>100,'object'=>'xxx','crm_id'=>'','link'=>'','extra'=>''); }

return $results;
 }
  public function push($order_id,$status="user",$log=array()){

      global $post_id;
  $log_id=''; self::$processing_feed=true;
  if(is_array($log) && !empty($log)){
      if(isset($log['id'])){
   $log_id=$log['id'];
      }
   $feeds=array($log['feed_id']);
  }else{
  if(empty(self::$all_feeds)){
  self::$all_feeds= get_posts( array(
  'post_type'           => $this->id,
  'ignore_sticky_posts' => true,
  'nopaging'            => true,
  'post_status'         => 'publish',
  'fields'              => 'ids'
  ) ); }
  $feeds=self::$all_feeds;
  }
$other_events=array('save_user','save_product');
$is_order=true;
$item=array();

  $feeds_meta=array();  self::$feeds_res=array();
   $k=1000; $e=2000; $f=3000; $i=1;
foreach($feeds as $id){
  $feed=get_post_meta($id,$this->id.'_meta',true);
  $object=$this->post('object',$feed);
  $event=$this->post('event',$feed);
  if(!is_array($feed)){
  continue;
}
if(in_array($status,$other_events)  && !in_array($event,$other_events) ){
    continue;
}
if( $status == '' && (in_array($event,$other_events) || strpos($event,'vxswc-') === 0 ) ){
    continue;
}

if( $status == 'admin_sub' && strpos($event,'vxswc-') === false){
    continue;
}
if( $status == 'update_order' && !in_array($object,array('invoices','estimates','recurringinvoices','creditnotes','purchaseorders','salesorders','Sales_Orders','Invoices','Quotes','Deals'))){
   continue;
}
  $feed['id']=$id;

 if(!empty($feed['invoice_check'])){
     $feeds_meta[$f++]=$feed;
 }else if(!empty($feed['order_check'])){
     $feeds_meta[$e++]=$feed;
 } else if(!empty($feed['vendor_check']) || !empty($feed['account_check']) || !empty($feed['contact_check']) ){

  $feeds_meta[$k++]=$feed;

 }else{
     $feeds_meta[$i++]=$feed;
 }
}

if($status == 'update_order'){ $status = 'update';  }

  ksort($feeds_meta);
  if(is_array($feeds_meta) && count($feeds_meta)>0){

      $is_subscription=false;
      if( is_object($order_id) && (strpos($status,'vxswc-') === 0 || $status == 'admin_sub') ){
          self::$_order=$_order=$order_id;
       $order_id=$order_id->get_id();
       $is_subscription=true;
      }
if($status == 'admin_sub'){
    $status='';
}

  self::$order=$order=array();
        if(in_array($status,array('save_user'))){
   self::$_order=get_user_by('id',esc_attr($order_id));
   }else{
      if(empty($post_id)){
   $post_id=$order_id;
   }
       if($post_id ){
    self::$order=$order=get_post_meta($order_id);
   }
    self::$order['_post_id']=$order_id;
   }
     if(isset($log['__vx_data'])){
   self::$order=$log['__vx_data'];
     }else if(!in_array($status,$other_events)){

  if(!empty($order['_billing_email'][0])){
    $email=$order['_billing_email'][0];
    $email_domain=substr($email,strpos($email,'@')+1);
   self::$order['_email_domain']=array($email_domain);
  }
   self::$order['_order_id']=$order_id;
if(!$is_subscription){
  self::$_order=$_order = new WC_Order($order_id);

  if($_order){
  self::$order['_order_id']=$_order->get_order_number();
  }
}

   $order_status=$_order->get_status();
   if(!$order_status){
       $this->log_msg('Order #'.$order_id.' - order status is not valid');
       return;
   }

   $date= current_time( 'mysql' );
  if(method_exists(self::$_order,'get_date_created') && !empty(self::$_order->get_date_created())){
  $date=self::$_order->get_date_created()->date('d-M-Y H:i:s');
  }

  self::$order['_order_date']=$date;

  if(!isset($order['_completed_date'])){
   self::$order['_completed_date']=$date;
  }

  self::$order=$order=apply_filters('vx_crm_post_fields', self::$order ,$order_id,'wc','');
     }else{
          $is_order=false;
    $key= $status == 'save_product' ? 'product_id': 'user_id';
    self::$order['_'.$key]=$order_id;
    $item=array($key=>$order_id);
     }


$data=$res=$moved=array(); $msg=""; $notice=""; $class="updated";  $error="";
$n=0;
while($feed=current($feeds_meta)){
      next($feeds_meta);
      $current_pos=key($feeds_meta);
    $n++;

  $id=$this->post('id',$feed);

   $no_filter=true;

  $account=$this->post('account',$feed);
   $info=$this->get_info($account);
       $info_data=array();
  if(isset($info['data'])){
$info_data=$info['data'];
  }

   $meta=$this->post('meta',$info);
if(is_array($feed) && is_array($meta)){
   $feed=array_merge($meta,$feed);
}

  $object=$this->post('object',$feed);
  $map=$this->post('map',$feed);

  $fields=isset($feed['fields']) ? $feed['fields'] : array();
  if(!is_array($fields) || count($fields) == 0 || empty($object) || empty($object)){
    continue;
  }

  $parent_id=0;
  if(isset(self::$order['__vx_parent_id'])){
  $parent_id=self::$order['__vx_parent_id'];
  }
    $temp=array();
      $force_send=false;
      $post_comment=true;

if($status !="" ){
if( in_array($status,array('restore','update','delete','add_note','delete_note'))){


   if($status == 'delete_note' && !empty(self::$note)){
         $parent_id=self::$note['id'];
   }
   $search_object=$object;
    if(in_array($status,array('delete_note','add_note'))){
    $order_notes=$this->post('order_notes',$feed);
    if( empty($order_notes) ){
        continue;
    }
         $feed['related_object']=$object;
        $object='Note';
 }

 if($status == 'delete_note'){
     $search_object='Note';
 }
$feed_log=$this->get_feed_log($id,$order_id,$search_object,$parent_id);

 if($status == 'restore' && $feed_log['status'] != 5) {
     continue;
 }
  if( in_array($status,array('update','delete') ) && !in_array($feed_log['status'],array(1,2) )  ){
     continue;
 }

if(empty($feed_log['crm_id']) || empty($feed_log['object']) || $feed_log['object'] != $search_object){
   continue;
}


if($status !='restore'){
$feed['crm_id']=$feed_log['crm_id'];
unset($feed['primary_key']);

}
  $feed['event']=$status;
 if( $status == 'add_note' && !empty(self::$note)){
    $temp=array('Title'=>array('label'=>'Note Title','value'=>self::$note['title']),'Body'=>array('label'=>'Note Content','value'=>self::$note['body']),'ParentId'=>array('value'=>$feed['crm_id']));
$parent_id=self::$note['id'];
 $feed['note_object_link']='<a href="'.esc_url($feed_log['link']).'" target="_blank">'.esc_html($feed_log['crm_id']).'</a>';
 }

 if( $status == 'delete_note'){

     $feed_log_arr= json_decode($feed_log['extra'],true);
     if(isset($feed_log_arr['note_object_link'])){
         $feed['note_object_link']=$feed_log_arr['note_object_link'];
     }
  $temp=array('ParentId'=>array('value'=> $feed['crm_id']));
 }
 if( $status == 'delete'){
   $temp=array('Id'=>array('value'=> $feed['crm_id']));
 }
  if(!in_array($status, array('update','restore'))){
      $force_send=true;
  }

     $post_comment=false;

 }
  if($feed['event'] == "manual" && $status=="user")
  continue;
  if($feed['event'] != $status)
  continue;
  if($status == "user_created"){
      $user=false;
  if(method_exists($_order,'get_user') ){
   $user=$_order->get_user(); }
  if(!$user){ continue;  }
  }
  }


if(!$force_send && isset($feed['map']) && count($feed['map'])>0){
if($is_order){
$items=self::$_order->get_items();

if(!empty($feed['each_line'])){
unset($feed['each_line']);
$items_total=count($items);
if($items_total>1){
$feed_n=$feed; $f_n=0;
foreach($items as $item_k=>$itm){
 $f_n++;
    if($f_n>1){
$feed_n['_vx_item_id']=$item_k;
$feeds_meta[]=$feed_n;
}
} }
}

if(!empty($feed['_vx_item_id'])){
$item=$items[$feed['_vx_item_id']];
}else{
$item=reset($items);
}
}


self::$order=$order=apply_filters('vx_crm_add_map_fields', self::$order ,$order_id,$feed,'wc','');
 $skip_feed=false;
foreach($feed['map'] as $k=>$v){
  if(isset($v['field'])){
    if(!isset($fields[$k])){
      continue;
  }
$value=false;
  $field=$fields[$k];
  $field_name=$this->post('field',$v);

$field_type=$this->post('type',$v);

if($field_type == "value"){
        $value=trim($this->post('value',$v));
$value=$this->process_tags($value,$item);
$value=trim($value);
if($value == ''){ $value=false; }
}
else if($field_type !='custom' && strpos($field_name,'_vx_feed-') !== false ){
$temp_feed_id=substr($field_name,9);
if(!empty($temp_feed_id)){
if(!isset($moved[$id])){ $moved[$id]=0; }
if(isset(self::$feeds_res[$temp_feed_id])){
$value=!empty(self::$feeds_res[$temp_feed_id]['id']) ? self::$feeds_res[$temp_feed_id]['id'] : '';
}else if($moved[$id] < 20 ){
        $feeds_meta[]=$feed; $skip_feed=true;
        $moved[$id]++;
        break;
} }
}
else{
  $value=$this->get_field_val($v,$item);
}

if($value !==false){
$f=array("value"=>$value,"label"=>$field['label']);
if($value === ''){
  $f['field']=$v['field'];
  }
  $temp[$k]=$f;
}

  }
}
  if($skip_feed){ continue; }
    if(!empty($feed['note_check']) ){
          $entry_note=''; $entry_note_title='';
if(!empty($feed['note_fields']) && is_array($feed['note_fields'])){
          $feed['note_val']='{'.implode("}\n{",$feed['note_fields'])."}\n";
}
if(!empty($feed['note_val'])){
    $entry_note=$this->process_tags($feed['note_val'],$item);
           if(empty($entry_note_title)){
            $entry_note_title=substr($entry_note,0,20);
           }
          if(!empty($entry_note)){
     $feed['__vx_entry_note']=array('Title'=>$entry_note_title,'Body'=>$entry_note);
          }
}
  }

  if(!empty($feed['owner']) ){
   $feed['user']=apply_filters('vx_assigned_user_id',$feed['user'],$this->id,$feed['id'],esc_attr($order_id));
  }
  }
$temp=apply_filters($this->id.'_post_data', $temp ,esc_attr($order_id));
if(isset($_REQUEST['bulk_action']) && $_REQUEST['bulk_action'] =="send_to_crm_bulk_force" && !empty($log_id)){
$force_send=true;
}
if(!$force_send && $this->post('optin_enabled',$feed) == "1" ){
  $no_filter=$this->check_filter($feed,$item);
  $res=array("status"=>"4","extra"=>array("filter"=>$this->filter_condition),"data"=>$temp);
}

 $feed['object']=$object;
if($no_filter){
  $api=$this->get_api($info);
  $res=$api->push_object($object,$temp,$feed);
  if(!$res){
     return false;
  }
  if( is_object(self::$_order) && method_exists(self::$_order,'update_meta_data') && in_array($feed['object'],array('Sales_Orders','salesorders')) && !empty($res['id']) ){
  self::$_order->update_meta_data('wzspbb_zoho_order',$res['id']);  self::$_order->save();
  }

}

  $res['time']=current_time('timestamp');
  $res['object']=$feed['object'];
self::$feeds_res[$id]=$res;

  if(empty($res['status'])){
  $class="error";
  }
  if(isset($res['error']) && $res['error']!="" && !is_admin()){
$this->send_error_email($order_id,$info,$res);
  }
 if($this->post('disable_log',$settings) !="yes"){
  $arr=array("object"=>$feed["object"],"order_id"=>$order_id,"crm_id"=>$this->post('id',$res),"meta"=>$this->post('error',$res),"time"=>date('Y-m-d H:i:s'),"status"=>$this->post('status',$res),"link"=>$this->post('link',$res),"data"=>$this->post('data',$res),"response"=>$this->post('response',$res),"extra"=>$this->post('extra',$res),"feed_id"=>$id,'parent_id'=>$parent_id,'event'=>$status);

  $log_id_i=$this->__log($arr,$log_id);
  if($log_id_i!=""){
  $res['log_id']=$log_id_i;
  }
  }

  $note_text=$this->format_note($res,true);
  if($notice!=""){
  $notice.="\n";
  }
  $notice.=$note_text;
  if($notice!=""){
  }

  if(count($res)>0){
  $data[]=$res;
  }
  do_action('crm_response_'.$this->id,$res);
  }

  if(count($data)>0){
      if($post_comment){
      }
  return array("class"=>$class,"msg"=>nl2br($notice));
  }

  }
  return false;

  }
  public function process_tags($value,$item=array()){
  preg_match_all('/\{[^\{]+\}/',$value,$matches);
  if(!empty($matches[0])){
      $vals=array();
   foreach($matches[0] as $m){
       $m=trim($m,'{}');
       $val_cust=$this->get_field_val(array('field'=>$m),$item);
       if(is_array($val_cust)){ $val_cust=trim(implode(' ',$val_cust)); }
    $vals['{'.$m.'}']=$val_cust;
   }

  $value=str_replace(array_keys($vals),array_values($vals),$value);
  }

  return $value;
}

  public function send_error_email($order_id,$info,$res){
         if(!empty($info['data']['error_email'])){
  $subject=__('Error While Posting to Zoho','woo-zoho-sync-pro');
$page_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

  $order_url='<a href="'.esc_url(add_query_arg(array('post'=>$order_id,'action'=>'edit'), admin_url('post.php'))).'" target="_blank">'.esc_html($order_id).'</a>';
  $email_info=array("msg"=>$res['error'],"title"=>__("Zoho Error",'woo-zoho-sync-pro'),"info_title"=>"More Detail","info"=>array("Order Id"=>$order_url,"Time"=>date('d/M/y H:i:s',current_time('timestamp')),"Page URL"=>'<a href="'.esc_url($page_url).'" style="word-break:break-all;">'.esc_url($page_url).'</a>'));
  $email_body=$this->format_user_info($email_info,true);
  $error_emails=explode(",",$info['data']['error_email']);
  $headers = array('Content-Type: text/html; charset=UTF-8');
  foreach($error_emails as $email)
  wp_mail(trim($email),$subject, $email_body,$headers);
  }
  }
  public function other_plugin_version(){
  $status=0;
  if(class_exists('wzspbb_zohos_wp')){
      $status=1;
  }else if( file_exists(WP_PLUGIN_DIR.'/woo-zoho-sync-pro/woo-zoho-sync-pro.php')) {
  $status=2;
  }
  return $status;
  }
public function get_field_val($map_field,$item=array()){
      $order=self::$order;
if($this->post('type',$map_field) == ""){
  $type="field";
  $f_key=$map_field[$type];
}else{
  $type=$map_field['type'];
  $f_key=$map_field[$type];
} $value='';
if($this->post($type,$map_field) == ""){
return false;
}
if(in_array($type,array("field",'custom'))){
if(strpos($f_key,"__vx_wp-") ===0){
  $f_key=substr($f_key,8);

  if(is_object(self::$_order) && method_exists(self::$_order,'get_user_id')){
  $user=self::$_order->get_user();
  }else{
  $user=self::$_order;
  }
if( is_object($user) && property_exists($user,'ID')){
 $user_id=$user->ID;
  if(in_array($f_key,array('user_email','display_name','user_nicename','user_registered','roles','user_login','user_url','ID','caps'))){
 $value=$user->$f_key;
  }else{
  $value=get_user_meta($user_id,$f_key,true);
  } }
}
else if(strpos($f_key,"__vxp") === 0 && !empty($item) ){

      $f_key_type=substr($f_key,0,10);
      $f_key=substr($f_key,10);
      $p_id='';
      if(is_object($item) && method_exists($item,'get_product_id')){
          $p_id=$item->get_product_id();
      }else if(is_array($item) && isset($item['product_id'])){ $p_id=$item['product_id']; $item=new stdClass(); }

      $product=wc_get_product($p_id);
        if( in_array($f_key,array('sku','price','regular_price','sale_price')) && is_object($item) && method_exists($item,'get_variation_id')){
              $var_id=$item->get_variation_id();
      if(!empty($var_id)){
          $product=wc_get_product($var_id);
      }
          }
       if(is_object($product) && method_exists($product,'get_attributes')){
      if($f_key_type == '__vxp_fun-'){
          $fun='get_'.$f_key;
            if(in_array($f_key,array('get_category_ids','get_category','get_tags','get_tag'))){
             $term_type=strpos($f_key,'tag') !== false ? 'product_tag' : 'product_cat';
           $terms = wp_get_post_terms( $p_id, $term_type );
           $val_temp=array('');
           if($terms){
              $val_temp=array();
               foreach($terms as $term){
               $val_temp[]=$term->name;
               }
           }
           if(in_array($f_key,array('get_category','get_tag'))){
               $value=$val_temp[0];
           }else{
           $value=implode(', ',$val_temp);
           }
           }
           else if(in_array($f_key,array('product_img'))){
              $img_id=$product->get_image_id();
              if(!empty($img_id)){
              $value=wp_get_attachment_url( $img_id );
              }
          }else{
         $value=$product->$fun();
          }

         if( in_array($f_key,array('short_description','description','name'))){
          $value=wp_strip_all_tags($value);
         }
      }
      else if($f_key_type == '__vxp_iun-'){
          $fun='get_'.$f_key;
          if(method_exists($item,$fun)){
         $value=$item->$fun();
          }
      }else  if($f_key_type == '__vxp_vtr-'){
          if(method_exists($item,'get_id')){
$value=$item->get_meta($f_key,true);
$book_items=array('get_end_date','get_start_date','get_persons_total');
if( in_array($f_key,$book_items) && class_exists('WC_Booking')){
$f_key_temp=$f_key;
if(!isset($order['get_start_date'])){
$item_id=$item->get_id();
$booking_id=$this->get_booking_ids_from_order_item_id($item_id);
if(!empty($booking_id[0])){
$booking = new WC_Booking( $booking_id[0] );
foreach($book_items as $v){
 self::$order[$v]=$booking->$v();
}
$order=self::$order;
}
}
if(isset($order[$f_key_temp])){
 $value=$order[$f_key_temp];
}
}
    if ( taxonomy_exists( $f_key ) ) {
                $term = get_term_by( 'slug', $value, $f_key );
                if ( ! is_wp_error( $term ) && is_object( $term ) && $term->name ) {
                    $value = $term->name;
                }
    }

          }
      }
      else  if($f_key_type == '__vxp_mta-'){
          $value=get_post_meta($p_id,$f_key,true);
      }else{
          if($f_key == 'product_cats'){
$terms = get_the_terms( $p_id, 'product_cat' );
 $cats=array();
 foreach($terms as $v){
 $cats[]=$v->slug;
 }
 $value=implode(', ',$cats);
          }else{
      $value=$product->get_attribute($f_key);
          }
      }
  }
}
else if(strpos($f_key,"__vx_pa") === 0 && is_object($item) && method_exists($item,'get_id') ){

    $f_key=substr($f_key,8);
     if($f_key == 'discount_amount' ){
      $value= $item->get_subtotal() - $item->get_total();
    }else{
   $value=wc_get_order_item_meta($item->get_id(),$f_key,true);
  }

  }
else if(strpos($f_key,"__vx_sh") === 0 && is_object($item) && method_exists($item,'get_id') ){
    $f_key=substr($f_key,8);

  $items=self::$_order->get_items('shipping');
  if(!empty($items)){
  $item=reset($items);
   $value=wc_get_order_item_meta($item->get_id(),$f_key,true);
  }

 }
else if(strpos($f_key,'__vxs_') === 0  && is_object(self::$_order) && method_exists(self::$_order,'get_date') ){
      $f_key_type=substr($f_key,0,10);
      $f_key=substr($f_key,10);
   $value=self::$_order->get_date($f_key);
}
else if(strpos($f_key,'__yth_qte') === 0 ){
  if(!empty($order['_post_id'])){
 $qdata=get_post_meta($order['_post_id'],'_raq_request',true);
 $f_key_type=substr($f_key,0,10);
 $f_key=substr($f_key,10);
 if(isset($qdata[$f_key]['value'])){
$value=trim($qdata[$f_key]['value']);
 }
  }
}
else if($f_key == '_refund_reason'){
    if(is_object(self::$_order) && method_exists(self::$_order,'get_refunds')){
       $re=self::$_order->get_refunds();
      if(isset($re[0]) && is_object($re[0]) && method_exists($re[0],'get_reason')){
     $value=$re[0]->get_reason();
      }
    }
}
else if($f_key == '_order_status_label'){
    if(is_object(self::$_order) && method_exists(self::$_order,'get_status')){
    $wc_status=self::$_order->get_status();
    $status_list=wc_get_order_statuses();
    if(is_array($status_list) && isset($status_list['wc-'.$wc_status])){
    $value=$status_list['wc-'.$wc_status];
    }
    }

}
else if(strpos($f_key,'vxship_') === 0){
  if( isset($order['_wc_shipment_tracking_items'][0]) ){
 $value=maybe_unserialize($order['_wc_shipment_tracking_items'][0]);
 if(isset($value[0])){ $value=$value[0];  }
$real_key=substr($f_key,7);
if(isset($value[$real_key])){
  $value=$value[$real_key];
}
  }
 }
else{
  if(isset($order[$f_key])){
  if( is_array($order[$f_key])){
  $value=maybe_unserialize($order[$f_key][0]);
  }else{
    $value=$order[$f_key];
  }
}else{
 $f_key_temp=trim($f_key,'_');
  if(isset($order[$f_key_temp]) && is_array($order[$f_key_temp])){
  $value=maybe_unserialize($order[$f_key_temp][0]);
  }else{
   $value=$this->order_info_fields($f_key);
  }
}

}
  if(is_array($value)){
  $value=implode("; ",$value);
  }
}else{
  $value=$map_field['value'];
  }
return $value;
  }

public function get_booking_ids_from_order_item_id( $order_item_id ) {
        global $wpdb;
        return wp_parse_id_list(
            $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_booking_order_item_id' AND meta_value = %d;",
                    $order_item_id
                )
            )
        );
}
  public function get_api($crm){
  $api = false;
  $api_class=$this->id."_api";
  if(!class_exists($api_class))
  require_once(self::$path."api/api.php");

  $api = new $api_class($crm);
  return $api;
  }
  public function get_warehouses($info){
    $api=$this->get_api($info);
$res=$api->post_crm('locations');
$wares=array();
if(!empty($res['locations'])){
    foreach($res['locations'] as $v){
      $loc_id=$v['location_id'];
      $loc_id.='_'.$v['parent_location_id'];
 $wares[$loc_id]=$v['location_name'];
    }
}
$meta=isset($info['meta']) && is_array($info['meta']) ? $info['meta'] : array();
$meta['warehouses']=$wares;
  if(isset($info['id'])){
$this->update_info( array("meta"=>$meta) , $info['id'] );
}
return $wares;
}
  public function update_info($data,$id) {
global $wpdb;
if(empty($id)){
    return;
}
 $table= $this->get_table_name('accounts');
 $time = current_time( 'mysql' ,1);

  $sql=array('updated'=>$time);
  if(is_array($data)){


    if(isset($data['meta'])){
  $sql['meta']= json_encode($data['meta']);
  }
  if( isset($data['data']) && is_array($data['data'])){

      if(array_key_exists('time' , $data['data']) && empty($data['data']['time'])){
  $sql['time']= $time;
  $sql['status']= '2';
  }

  if(isset($data['data']['class'])){
  $sql['status']= $data['data']['class'] == 'updated' ? '1' : '2';
  }
  if(isset($data['data']['meta'])){
      unset($data['data']['meta']);
  }
  if(isset($data['data']['status'])){
      unset($data['data']['status']);
  }
  if(isset($data['data']['name'])){
     $sql['name']=$data['data']['name'];

  }else if(isset($_GET['id'])){
       $sql['name']="Account #".$this->post('id');
  }

    $enc_str=json_encode($data['data']);
  $sql['data']=$enc_str;
  }
  }

$result = $wpdb->update( $table,$sql,array('id'=>$id) );

return $result;
}
public function get_objects($info="",$refresh=false){

   $objects=array();
   if(empty($info)){
     $option=get_option($this->id.'_meta',array());
 return  isset($option['objects']) ? $option['objects'] : array();
   }
   $meta=$this->post('meta',$info);

   if(! isset($meta['objects'])){
    $refresh=true;
   }else{
     $objects=$meta['objects'];
   }
 if($refresh){

  $api=$this->get_api($info);
  $objects=$api->get_crm_objects();

  if(is_array($objects)){
  $option=get_option($this->id.'_meta',array());
  $option['objects']=$objects;
  update_option($this->id.'_meta',$option);
  $meta["objects"]=$objects;
  $this->update_info(array("meta"=>$meta),$info['id']);
  }
 }
  return $objects;
}





}

endif;

require_once(wzspbb_zoho_DIR . "includes/install.php");
$wzspbb_zoho=new wzspbb_zoho();
$wzspbb_zoho->instance();
$vx_wc['wzspbb_zoho']='wzspbb_zoho';
