<?php
/*
Plugin Name: Sina_Weibo_Plus
Author:  dosxp
Author URI: http://www.ecihui.com/tech
Plugin URI: http://www.ecihui.com/tech/227.htm
Description: 访客使用新浪微博账号登录你的博客发表评论，使用新浪微博的头像，同步留言到新浪微博。新浪微博登录标志和评论同步提示标签可自定义位置。访客自动获得本地新账户（前缀可自定义）并得到新浪微博私信通知，自动在新浪微博关注你的新浪微博。新文章发布时可以在新浪微博发布消息。
Version: 2.31
Notes:本插件曾借鉴denishua的插件。
*/
$sina_consumer_key = '1849532866';
$sina_consumer_secret = '7cdfb14605a350aea3eff278b65b9d94';
$sc_loaded = false;
$SOA = null;
$setsina = false;

function send_p_m ($uid, $txt) {
	if(get_option("sina_oauth_uid")){
		global $SOA, $sina_consumer_key, $sina_consumer_secret;
		$wc = new WeiboClient( $sina_consumer_key, $sina_consumer_secret , $SOA['oauth_token'], $SOA['oauth_token_secret'] );
		$ec = new WeiboClient( $sina_consumer_key, $sina_consumer_secret , get_option("sina_oauth_token"), get_option("sina_oauth_token_secret") );
		$e_id = get_option("sina_oauth_uid") + 0;
		$msg = $wc->follow($e_id);

		$u_id = $uid;
		$text = urlencode($txt);
		$msg =$ec->send_dm($u_id,$text);
	}
}

function generate_password( $length = 8 ) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ( $i = 0; $i < $length; $i++ ) 
    {
        $password .= $chars[ mt_rand(0, strlen($chars) - 1) ];
    }
    return $password;
}

add_action('init', 'sc_init');
function sc_init(){
	if (session_id() == "") {
		session_start();
	}
	if(is_user_logged_in() && is_admin()) {		
        if(isset($_GET['oauth_token'])){
			global $setsina;
			$setsina = true;
			sc_confirm();
		} 
    } 
	if(!is_user_logged_in()) {		
        if(isset($_GET['oauth_token'])){
			sc_confirm();
        } 
    } 
}

add_action("wp_head", "sc_wp_head");
add_action("admin_head", "sc_wp_head");
add_action("login_head", "sc_wp_head");
add_action("admin_head", "sc_wp_head");
function sc_wp_head(){
    if(is_user_logged_in()) {
        if(isset($_GET['oauth_token'])){
			echo '<script type="text/javascript">window.opener.sc_reload("");window.close();</script>';
        }
	}
}

function show_sina_syn($pre="", $after=""){
	if( is_user_logged_in()){
		global $user_ID;
		$scdata = get_user_meta($user_ID, 'scdata',true);
		if($scdata){
			echo $pre.'<input name="post_2_sina_t" type="checkbox" id="post_2_sina_t" value="1"  checked /> 同步发表到新浪微博'.$after;
		}
	}	
}
add_action('admin_head', 'sina_connect');
add_action('comment_form', 'sina_connect');
add_action("login_form", "sina_connect");
add_action("register_form", "sina_connect",12);
function sina_connect($id=""){
	global $sc_loaded;
	if($sc_loaded) {
		return;
	}
	global $sina_adm;
	if( is_user_logged_in() && is_admin()==false ){
		if( get_option('sina_show_syn')=='true' ){
			show_sina_syn();
		}
		return;
	}	
?>
	<script type="text/javascript">
    function sc_reload(){
       var url=location.href;
       var temp = url.split("#");
       url = temp[0];
       url += "#sc_button";
       location.href = url;
       location.reload();
    }
    </script>	
<?php
	if( !is_admin() ){
		if( get_option('sina_t_login_logo') == 'true'){
			show_sina_t_login();
		}
	}
    $sc_loaded = true;
}

function show_sina_t_login($pre="", $after=""){
	$sc_url = WP_PLUGIN_URL.'/'.dirname(plugin_basename (__FILE__));
	if( !is_user_logged_in()){
		echo $pre;
?>
		<img onclick='window.open("<?php echo $sc_url; ?>/sina-start.php", "dcWindow","width=800,height=600,left=100,top=100,scrollbar=no,resize=no");return false;' src="<?php echo $sc_url; ?>/sina_button.png" alt="使用新浪微博登录" style="cursor: pointer; margin-right: 20px;" />
<?php
		echo $after;
	}
}

add_filter("get_avatar", "sc_get_avatar",10,4);
function sc_get_avatar($avatar, $id_or_email='',$size='32') {
	global $comment;
	if(is_object($comment)) {
		$id_or_email = $comment->user_id;
	}
	if (is_object($id_or_email)){
		$id_or_email = $id_or_email->user_id;
	}
	if($scid = get_usermeta($id_or_email, 'scid')){
		$out = 'http://tp3.sinaimg.cn/'.$scid.'/50/1.jpg';
		$avatar = "<img alt='' src='{$out}' class='avatar avatar-{$size}' height='{$size}' width='{$size}' />";
		return $avatar;
	}else {
		return $avatar;
	}
}

function sc_confirm(){
    global $sina_consumer_key, $sina_consumer_secret;
	
	if(!class_exists('SinaOAuth')){
		include dirname(__FILE__).'/sinaOAuth.php';
	}
	
	$to = new SinaOAuth($sina_consumer_key, $sina_consumer_secret, $_GET['oauth_token'],$_SESSION['sina_oauth_token_secret']);
	
	$tok = $to->getAccessToken($_REQUEST['oauth_verifier']);
	global $SOA;
	$SOA = $tok;

	$to = new SinaOAuth($sina_consumer_key, $sina_consumer_secret, $tok['oauth_token'], $tok['oauth_token_secret']);
	
	$sinaInfo = $to->OAuthRequest('http://api.t.sina.com.cn/account/verify_credentials.xml', 'GET',array());

	if($sinaInfo == "no auth"){
		echo '<script type="text/javascript">window.close();</script>';
		return;
	}
	
	$sinaInfo = simplexml_load_string($sinaInfo);

	if((string)$sinaInfo->domain){
		$sc_user_name = $sinaInfo->domain;
	} else {
		$sc_user_name = $sinaInfo->id;
	}

	sc_login($sinaInfo->id.'|'.$sc_user_name.'|'.$sinaInfo->screen_name.'|'.$sinaInfo->url.'|'.$tok['oauth_token'] .'|'.$tok['oauth_token_secret']); 
}

function sc_login($Userinfo) {
	$userinfo = explode('|',$Userinfo);
	if(count($userinfo) < 6) {
		wp_die("An error occurred while trying to contact Sina Connect.");
	}
	$sina_preword = 'sina_';
	if( get_option('sina_weibo_pre') ) { $sina_preword = get_option('sina_weibo_pre'); }
	$newpass = generate_password(8);
	$userdata = array(
		'user_pass' => $newpass,
		'user_login' => $sina_preword.$userinfo[2],
		'display_name' => $sina_preword.$userinfo[2],
		'user_url' =>"http://t.sina.com.cn/".$userinfo[0],
		'user_email' => $userinfo[1].'@t.sina.com.cn'
	);
	if($setsina==true){
		$userdata["role"] = 'Editor';
	}

	if(!function_exists('wp_insert_user')){
		include_once( ABSPATH . WPINC . '/registration.php' );
	} 
  
	$wpuid = get_user_by_login('sina_'.$userinfo[2]);
	
	if(!$wpuid){
		if($userinfo[0]){
			$wpuid = wp_insert_user($userdata); 
		
			if($wpuid){
				update_usermeta($wpuid, 'scid', $userinfo[0]);
				$sc_array = array (
					"oauth_access_token" => $userinfo[4],
					"oauth_access_token_secret" => $userinfo[5],
				);
				update_usermeta($wpuid, 'scdata', $sc_array);
			}
			$bname = get_option('blogname');
			send_p_m($userinfo[0]+0, '您刚才在'.$bname.'发表了评论，您可以继续使用新浪微博账户在'.$bname.'发表评论，也可以使用您在'.$bname.'的新账户：sina_'.$userinfo[2].'，初始口令：'.$newpass); 
		}
	} else {
		update_usermeta($wpuid, 'scid', $userinfo[0]);
		$sc_array = array (
			"oauth_access_token" => $userinfo[4],
			"oauth_access_token_secret" => $userinfo[5],
		);
		update_usermeta($wpuid, 'scdata', $sc_array);
	}
  
  global $setsina;
  if($setsina==false){
		if($wpuid) {
				wp_set_auth_cookie($wpuid, true, false);
				wp_set_current_user($wpuid);
		}
  }else{
		update_option('sina_oauth_token', $userinfo[4]);
		update_option('sina_oauth_token_secret', $userinfo[5]);
		update_option('sina_oauth_uid', $userinfo[0]);
		update_option('sina_oauth_screen_name', $userinfo[2]);	
	}
}

register_activation_hook(__FILE__,'sina_install');
function sina_install(){
		update_option("sina_weibo_pre",'sina_');
		update_option('sina_t_login_logo','true');
		update_option('sina_show_syn','true');
		update_option('sina_post_syn','true');
		update_option('show_ecihui','true');
		global $wpdb;
	    $table_name = $wpdb->prefix . "links";
		if(!chk_link()){
			insert_link();
		}
}
function chk_link(){
		global $wpdb;
	    $table_name = $wpdb->prefix . "links";
		$sql = "select link_url from ".$table_name." where link_url like '%www.ecihui.com%'";
		return $wpdb->query( $sql );
}
function insert_link(){
		global $wpdb;
	    $table_name = $wpdb->prefix . "links";
		$sql = "INSERT INTO ".$table_name." (link_url,link_name,link_image,link_target,link_description,link_visible,link_owner,link_rating,link_updated,link_rel,link_notes,link_rss) VALUES ('http://www.ecihui.com/', '辞汇网', '', '_blank', '', 'Y', 1, 1, '2010-10-12 00:00:00', 'co-worker', '', '')";
		return $wpdb->query( $sql );
}
function update_link($showlink='Y'){
		global $wpdb;
		$link = $showlink;
	    $table_name = $wpdb->prefix . "links";
		if(!chk_link()){
			insert_link();
		}
		$sql = "update ".$table_name." set link_visible='".$link."' where  link_url like '%www.ecihui.com%'";
		$results = $wpdb->query( $sql );
}

function sc_sinauser_to_wpuser($scid) {
  return get_user_by_meta('scid', $scid);
}

if(!function_exists('get_user_by_meta')){

	function get_user_by_meta($meta_key, $meta_value) {
	  global $wpdb;
	  $sql = "SELECT user_id FROM $wpdb->usermeta WHERE meta_key = '%s' AND meta_value = '%s'";
	  return $wpdb->get_var($wpdb->prepare($sql, $meta_key, $meta_value));
	}
	
	function get_user_by_login($user_login) {
	  global $wpdb;
	  $sql = "SELECT ID FROM $wpdb->users WHERE user_login = '%s'";
	  return $wpdb->get_var($wpdb->prepare($sql, $user_login));
	}
}

if(!function_exists('connect_login_form_login')){
	add_action("login_form_login", "connect_login_form_login");
	add_action("login_form_register", "connect_login_form_login");
	function connect_login_form_login(){
		if(is_user_logged_in()){
			$redirect_to = admin_url('profile.php');
			//$redirect_to = get_option('home') . '/';
			wp_safe_redirect($redirect_to);
		}
	}
}

add_action('comment_post', 'sc_comment_post',1000);
function sc_comment_post($id){
	$comment_post_id = $_POST['comment_post_ID'];
	
	if(!$comment_post_id){
		return;
	}
	$current_comment = get_comment($id);
	$current_post = get_post($comment_post_id);
	$scdata = get_user_meta($current_comment->user_id, 'scdata',true);
	if($scdata){
		if($_POST['post_2_sina_t']){
			if(!class_exists('SinaOAuth')){
				include dirname(__FILE__).'/sinaOAuth.php';
			}
			global $sina_consumer_key, $sina_consumer_secret;
			$to = new SinaOAuth($sina_consumer_key, $sina_consumer_secret,$scdata['oauth_access_token'], $scdata['oauth_access_token_secret']);
			$status = urlencode($current_comment->comment_content. ' （于《' .single_post_title("", false). '》' .get_permalink($comment_post_id). '）');			
			$resp = $to->OAuthRequest('http://api.t.sina.com.cn/statuses/update.xml','POST',array('status'=>$status));		
		}
	}
}

add_action('admin_menu', 'sc_options_add_page');

function sc_options_add_page() {
	add_options_page('管理新浪微博', '管理新浪微博', 'manage_options', 'sc_options', 'sc_options_do_page');
}

function sc_options_do_page() {
	if(isset($_POST["sina_weibo_pre"])){
		update_option("sina_weibo_pre",$_POST["sina_weibo_pre"]);
		if($_POST["sina_t_login_logo"]==true){
			update_option('sina_t_login_logo','true');
		}else{
			update_option('sina_t_login_logo','false');
		}
		if($_POST["sina_show_syn"]==true){
			update_option('sina_show_syn','true');
		}else{
			update_option('sina_show_syn','false');
		}
		if($_POST["show_ecihui"]==true){
			update_option('show_ecihui','true');
			update_link('Y');
		}else{
			update_option('show_ecihui','false');
			update_link('N');
		}
		if($_POST["sina_post_syn"]==true){
			update_option('sina_post_syn','true');
		}else{
			update_option('sina_post_syn','false');
		}
	}

	//delete_option('sina_access_token');
	$sina_oauth_screen_name = '';
	if(get_option( 'sina_oauth_screen_name' )){
		$sina_oauth_screen_name = get_option( 'sina_oauth_screen_name' );
	}
	$sc_url = WP_PLUGIN_URL.'/'.dirname(plugin_basename (__FILE__));
	?>	
	<div class="wrap">
		<h2>管理新浪微博</h2>
		<p><BR></p>
		<TABLE border=0 width='100%'>
		<TR>
			<TD>

					<p>已成功启用。访客现在可以在其他页面以其新浪微博账户登录发表评论，访客发表的评论会自动同步在其自己的新浪微博上发表，访客首次以新浪微博账户发表评论时，会自动在本站生成一个“sina_”前缀的同名用户。</p>
					<p>如果你成功设定博客管理员在新浪微博的账户，则访客首次以新浪微博账户发表评论并自动在本站生成一个“sina_”前缀的同名用户时，访客可以在其自己的新浪微博私信中收到该“sina_”前缀本站用户的初始口令，并自动关注本站的新浪微博成为粉丝。如果你没有设定博客管理员在新浪微博的账户，则访客无法收到初始口令，也无法自动关注本站的新浪微博。</p><p><BR></p>
			<?php
			if( $sina_oauth_screen_name == ''){		
			?>
				<p>尚未设定博客管理员在新浪微博的账户，如果没有新浪微博账户请先在新浪注册开通微博，然后请点击下方图标，以你的新浪微博账户认证一次，以获取新浪授权。</p>
			<?php
			}else{
			?>
				<p>已经成功设定博客管理员在新浪微博的账户为：<?php echo $sina_oauth_screen_name; ?> </p>
				<p>同时本站自动生成了一个新的关联普通用户为：sina_<?php echo $sina_oauth_screen_name; ?></p>
				<p>如果需要重新设定账户，请点击下方图标，以你的新浪微博账户认证一次，以获取新浪授权。</p>
			<?php
			}
			$sc_url = WP_PLUGIN_URL.'/'.dirname(plugin_basename (__FILE__));
			?>
					<p><BR></p>
					<p id="sc_connect" class="sc_button">
						<img onclick='window.open("<?php echo $sc_url;?>/sina-start.php", "dcWindow","width=800,height=600,left=100,top=100,scrollbar=no,resize=no");return false;' src="<?php echo $sc_url;?>/sina_button.png" alt="使用新浪微博登录" style="cursor: pointer; margin-right: 20px;" /> ←请点击按钮进行新浪认证
					</p>
					<p><BR></p><hr size=1>
					<form method='post' action=<?php echo $_SERVER['PHP_SELF']; ?>?page=sc_options>
						<p>新浪微博账户自动附加前缀：<input type='text' size='12' name='sina_weibo_pre' value='<?php echo get_option('sina_weibo_pre'); ?>'>&nbsp;&nbsp;（设定前缀可以防止与本站用户重名、防止伪造用户，请以英文字符为前缀，默认sina_）</p>
						<p><input type='checkbox' name='sina_t_login_logo' <?php if(get_option('sina_t_login_logo')=='true'){echo 'checked';}else{echo 'unchecked';} ?>>自动显示新浪微博登录图标&nbsp;&nbsp;（自动在需要的地方添加新浪微博登录图标即入口；如不选中本项，则应在计划显示新浪微博登录入口的地方手动添加show_sina_t_login($pre,$after)进行调用，$pre、$after定义前置和后置HTML标签，默认为空）</p>
						<p><input type='checkbox' name='sina_show_syn' <?php if(get_option('sina_show_syn')=='true'){echo 'checked';}else{echo 'unchecked';} ?>>自动显示新浪微博同步选项&nbsp;&nbsp;（自动在评论撰写框下方添加新浪微博同步选项；如不选中本项，则应在计划显示新浪微博同步选项的地方手动添加show_sina_syn($pre,$after)进行调用，$pre、$after定义前置和后置HTML标签，默认为空）</p>
						<p><input type='checkbox' name='sina_post_syn' <?php if(get_option('sina_post_syn')=='true'){echo 'checked';}else{echo 'unchecked';} ?>>发布新文章时自动发布消息到本站新浪微博</p>
						<p><input type='checkbox' name='show_ecihui' <?php if(get_option('show_ecihui')=='true'){echo 'checked';}else{echo 'unchecked';} ?>>支持本插件作者，友情链接本插件网站</p>
						<p><input type='submit' value='保存设定''></p>
					</form>
				</div>		
			</TD>
			<TD width='50' valign='top'>
			</TD>
			<TD width='200' valign='top'>
				<a href='http://www.ecihui.com/tech' target='new'>辞汇网技术栏目</a><BR>
				<a href='http://www.ecihui.com/tech/227.htm' target='new'>提交建议或bug</a>
			</TD>
		</TR>
		</TABLE>
	<?php
}

function update_sina_t($status=null){
	$tok = get_option('sina_access_token');
	if(!class_exists('SinaOAuth')){
		include dirname(__FILE__).'/sinaOAuth.php';
	}
	global $sina_consumer_key, $sina_consumer_secret;
	$to = new SinaOAuth($sina_consumer_key, $sina_consumer_secret,$tok['oauth_token'], $tok['oauth_token_secret']);
	$status = urlencode($status);
	$resp = $to->OAuthRequest('http://api.t.sina.com.cn/statuses/update.xml','POST',array('status'=>$status));
}

add_action('publish_post', 'publish_post_2_sina_t', 0);
function publish_post_2_sina_t($post_ID){
	if(get_option('sina_post_syn')!='true'){return;}
	$tok = get_option('sina_oauth_uid');
	if(!$tok) return;
	$sina_t = get_post_meta($post_ID, 'sina_t', true);
	if($sina_t) return;
	$c_post = get_post($post_ID);
	if(!class_exists('SinaOAuth')){
		include dirname(__FILE__).'/sinaOAuth.php';
	}
	global $sina_consumer_key, $sina_consumer_secret;
	$bname = get_option('blogname');
	$to = new SinaOAuth($sina_consumer_key, $sina_consumer_secret, get_option('sina_oauth_token'), get_option('sina_oauth_token_secret'));
	$status = urlencode('《'.$c_post->post_title. ' 》刚刚发表于'.$bname.'（' .get_permalink($post_ID). '），有沙发！');			
	$resp = $to->OAuthRequest('http://api.t.sina.com.cn/statuses/update.xml','POST',array('status'=>$status));	
	add_post_meta($post_ID, 'sina_t', 'true', true);
}

