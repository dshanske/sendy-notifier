<?php
/**
 * Frontend Class
 * Renders Rebranded Popup and Top Bar.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPSN_Frontend {
	public function __construct() {
		add_shortcode( 'sendy_subscribe', array( $this, 'view_shortcode' ) );
		add_action( 'wp_footer', array( $this, 'view_tools' ) );
		add_action( 'wp_ajax_wpsn_subscribe', array( $this, 'ajax_subscribe' ) );
		add_action( 'wp_ajax_nopriv_wpsn_subscribe', array( $this, 'ajax_subscribe' ) );
	}

	public function view_tools() {
		$btn = get_option('wpsn_button_text','Subscribe');
		if(get_option('wpsn_enable_popup')){
			$vis = get_option('wpsn_popup_visibility','posts');
			if(($vis==='all'&&(is_singular()||is_front_page()))||($vis==='posts'&&is_single())){
				$pos=get_option('wpsn_popup_position','bottom-right');
				$css=($pos==='center')?"top:50%;left:50%;transform:translate(-50%,-50%);width:350px;":"bottom:30px;right:30px;";
				if($pos==='center') echo "<div id='w_ov' style='position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9998;display:none;'></div>"; ?>
				<div id="w_pop" style="position:fixed;<?php echo $css;?>background:#fff;box-shadow:0 10px 30px rgba(0,0,0,0.2);border-radius:12px;padding:30px;z-index:9999;display:none;font-family:sans-serif;">
					<button id="w_pop_c" style="position:absolute;top:10px;right:15px;border:none;background:none;font-size:24px;cursor:pointer;">&times;</button>
					<h4 style="margin:0 0 15px;"><?php echo esc_html(get_option('wpsn_popup_text','Stay Updated!'));?></h4>
					<form class="sn-af"><input type="text" name="name" placeholder="Name" required style="width:100%;padding:10px;margin-bottom:10px;"><input type="email" name="email" placeholder="Email" required style="width:100%;padding:10px;margin-bottom:15px;"><button type="submit" style="width:100%;background:#2563eb;color:#fff;border:none;padding:12px;border-radius:6px;font-weight:bold;"><?php echo esc_html($btn);?></button><div class="sn-m" style="margin-top:10px;text-align:center;"></div></form>
				</div><script>jQuery(document).ready(function($){ if(!localStorage.getItem('sn_p_v1')){ setTimeout(function(){ $('#w_pop, #w_ov').fadeIn(); }, 1000); } $('#w_pop_c').click(function(){ $('#w_pop, #w_ov').fadeOut(); localStorage.setItem('sn_p_v1','1'); }); });</script><?php
			}
		}
		if(get_option('wpsn_enable_topbar')){ ?>
			<div id="w_bar" style="position:fixed;top:0;left:0;width:100%;background:#111;color:#fff;padding:12px;text-align:center;display:none;z-index:10000;font-family:sans-serif;">
				<strong><?php echo esc_html(get_option('wpsn_topbar_heading','Join Us'));?></strong> <span style="margin:0 15px;"><?php echo esc_html(get_option('wpsn_topbar_text'));?></span>
				<form class="sn-af" style="display:inline-block;"><input type="text" name="name" placeholder="Name" required style="padding:6px;width:110px;"><input type="email" name="email" placeholder="Email" required style="padding:6px;width:160px;margin-left:5px;"><button type="submit" style="background:#2563eb;color:#fff;border:none;padding:6px 15px;margin-left:10px;"><?php echo esc_html($btn);?></button><span class="sn-m"></span></form>
				<button id="w_bar_c" style="background:none;border:none;color:#777;margin-left:20px;cursor:pointer;">&times;</button>
			</div><script>jQuery(document).ready(function($){ if(!localStorage.getItem('sn_b_v1')){ $('#w_bar').slideDown(); } $('#w_bar_c').click(function(){ $('#w_bar').slideUp(); localStorage.setItem('sn_b_v1','1'); }); });</script><?php
		}
		echo "<script>jQuery(document).on('submit','.sn-af',function(e){ e.preventDefault(); var f=jQuery(this); $.post('".admin_url('admin-ajax.php')."',{action:'wpsn_subscribe',name:f.find('input[name=\"name\"]').val(),email:f.find('input[name=\"email\"]').val()},function(r){ f.find('.sn-m').text(r.data); }); });</script>";
	}

	public function ajax_subscribe(){ $res=wp_remote_post(rtrim(get_option('wpsn_url'),'/').'/subscribe',array('body'=>array('api_key'=>get_option('wpsn_api_key'),'name'=>$_POST['name'],'email'=>$_POST['email'],'list'=>get_option('wpsn_list_id'),'boolean'=>'true'))); $b=trim(wp_remote_retrieve_body($res)); if($b=='1'||$b=='true'){ wp_send_json_success(esc_html(get_option('wpsn_success_message','Success!'))." Check your inbox to confirm."); } else { wp_send_json_error($b); } }
	public function view_shortcode($atts){ $atts=shortcode_atts(array('title'=>'Subscribe'),$atts); ob_start(); echo '<div class="sn-box" style="background:#f9f9f9;padding:25px;border:1px solid #ddd;border-radius:8px;"><h4>'.esc_html($atts['title']).'</h4><form class="sn-af"><input type="text" name="name" required placeholder="Name" style="width:100%;margin-bottom:10px;"><input type="email" name="email" required placeholder="Email" style="width:100%;margin-bottom:15px;"><button type="submit" style="width:100%;background:#2563eb;color:#fff;border:none;padding:10px;">'.esc_html(get_option('wpsn_button_text','Subscribe')).'</button><div class="sn-m"></div></form></div>'; return ob_get_clean(); }
}
