<?php
/**
 * Admin Logic Class
 * Manages rebranding to Sendy Notifier and all UI Sections.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPSN_Admin {
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_post_metabox' ) );
		add_action( 'save_post', array( $this, 'save_post_data' ) );
		add_action( 'admin_footer', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_wpsn_fetch_brands', array( $this, 'fetch_brands' ) );
		add_action( 'wp_ajax_wpsn_fetch_lists', array( $this, 'fetch_lists' ) );
		add_action( 'wp_ajax_wpsn_test_single', array( $this, 'manual_test_ajax' ) );
	}

	public function add_menu() { add_options_page( 'Sendy Notifier', 'Sendy Notifier', 'manage_options', 'sendy-notifier', array( $this, 'render_page' ) ); }

	public function register_settings() {
		$keys = array( 'wpsn_url', 'wpsn_api_key', 'wpsn_list_id', 'wpsn_brand_id', 'wpsn_list_name', 'wpsn_brand_name', 'wpsn_from_email', 'wpsn_subject_single', 'wpsn_subject_digest', 'wpsn_email_template', 'wpsn_show_logo', 'wpsn_show_title', 'wpsn_header_custom', 'wpsn_footer_custom', 'wpsn_frequency', 'wpsn_send_time', 'wpsn_send_day', 'wpsn_post_elements', 'wpsn_show_featured_image', 'wpsn_track_opens', 'wpsn_track_clicks', 'wpsn_query_string', 'wpsn_enable_popup', 'wpsn_popup_text', 'wpsn_popup_position', 'wpsn_popup_visibility', 'wpsn_enable_topbar', 'wpsn_topbar_heading', 'wpsn_topbar_text', 'wpsn_button_text', 'wpsn_success_message' );
		foreach ( $keys as $k ) { register_setting( 'wpsn-settings-group', $k ); }
	}

	public function add_post_metabox() { add_meta_box( 'sn_meta', 'Sendy Notifier', array( $this, 'render_metabox' ), 'post', 'side', 'high' ); }
	public function render_metabox( $post ) {
		$enabled = get_post_meta( $post->ID, '_wpsn_send_flag', true ) ?: 'yes';
		$last = get_post_meta( $post->ID, '_wpsn_last_sent', true );
		wp_nonce_field( 'sn_m_act', 'sn_m_nonce' ); ?>
		<p><label><input type="checkbox" name="wpsn_send_flag" value="yes" <?php checked($enabled,'yes');?>> Enable Notification</label></p>
		<?php if($last) echo '<p style="font-size:11px;color:#666;">Last Sent: '.$last.'</p>'; ?>
		<hr><button type="button" id="sn_t_btn" class="button">Test Send Now</button><p id="sn_t_res" style="font-size:11px;"></p>
	<?php }
	public function save_post_data($id){ if(!isset($_POST['sn_m_nonce']) || !wp_verify_nonce($_POST['sn_m_nonce'], 'sn_m_act')) return; update_post_meta($id, '_wpsn_send_flag', isset($_POST['wpsn_send_flag']) ? 'yes' : 'no'); }

	public function render_page() {
		$cur_skin = get_option( 'wpsn_email_template', 'modern' );
		$latest = get_posts( array( 'numberposts' => 1 ) );
		$t = !empty($latest) ? get_the_title($latest[0]->ID) : 'Latest Post Title';
		$ex = !empty($latest) ? wp_trim_words($latest[0]->post_content, 12) : 'Post preview text...';
		?>
		<style>.w_skin{border:1px solid #ccc;border-radius:8px;padding:15px;background:#fff;opacity:0.6;transition:0.2s;min-height:140px;}.s_modern{background:#f8fafc;border-left:4px solid #2563eb;}.s_classic{background:#f4f4f4;text-align:center;font-family:serif !important;}input[name="wpsn_email_template"]:checked+.w_skin{border:2px solid #2563eb;opacity:1;box-shadow:0 4px 10px rgba(0,0,0,0.1);}.w_help{background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:8px;position:sticky;top:50px;}</style>
		<div class="wrap"><h1>Sendy Notifier v1.0.0</h1><hr>
		<div style="display:flex; gap:30px; margin-top:20px;"><div style="flex:3;"><form method="post" action="options.php"><?php settings_fields('wpsn-settings-group');?>
			<h2>1. Delivery Strategy</h2><table class="form-table"><tr><th>Frequency</th><td><select name="wpsn_frequency" id="f_set" class="regular-text"><option value="immediate" <?php selected(get_option('wpsn_frequency'),'immediate');?>>Immediate</option><option value="daily" <?php selected(get_option('wpsn_frequency'),'daily');?>>Daily Digest</option><option value="weekly" <?php selected(get_option('wpsn_frequency'),'weekly');?>>Weekly Digest</option><option value="manual" <?php selected(get_option('wpsn_frequency'),'manual');?>>None (Manual Only)</option></select><p class="description">Choose frequency. "None" allows manual sending only.</p></td></tr><tr id="s_row" style="<?php echo (in_array(get_option('wpsn_frequency'), array('daily','weekly'))) ? '' : 'display:none;'; ?>"><th>Schedule</th><td>at <input type="time" name="wpsn_send_time" value="<?php echo esc_attr(get_option('wpsn_send_time','09:00'));?>"><span id="d_row" style="<?php echo (get_option('wpsn_frequency')==='weekly')?'':'display:none;';?>"> on <select name="wpsn_send_day"><?php foreach(array('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') as $d) echo '<option value="'.$d.'" '.selected(get_option('wpsn_send_day'),$d,false).'>'.$d.'</option>'; ?></select></span></td></tr></table>
			<h2>2. API Connectivity</h2><table class="form-table"><tr><th>URL</th><td><input type="text" id="api_u" name="wpsn_url" value="<?php echo esc_attr(get_option('wpsn_url'));?>" class="large-text"></td></tr><tr><th>Key</th><td><input type="password" id="api_k" name="wpsn_api_key" value="<?php echo esc_attr(get_option('wpsn_api_key'));?>" class="regular-text"></td></tr><tr><th>Discovery</th><td><input type="hidden" name="wpsn_brand_name" id="bn" value="<?php echo esc_attr(get_option('wpsn_brand_name'));?>"><input type="hidden" name="wpsn_list_name" id="ln" value="<?php echo esc_attr(get_option('wpsn_list_name'));?>"><button type="button" id="fb" class="button">Fetch Brands</button><select name="wpsn_brand_id" id="sb"><option value="<?php echo esc_attr(get_option('wpsn_brand_id'));?>"><?php echo get_option('wpsn_brand_name') ?: 'Select Brand';?></option></select><button type="button" id="fl" class="button">Fetch Lists</button><select name="wpsn_list_id" id="sl"><option value="<?php echo esc_attr(get_option('wpsn_list_id'));?>"><?php echo get_option('wpsn_list_name') ?: 'Select List';?></option></select></td></tr></table>
			<h2>3. Visual Gallery</h2><div style="display:grid; grid-template-columns:repeat(3,1fr); gap:15px; margin-bottom:30px;"><?php foreach(array('modern'=>'Modern','minimal'=>'Minimal','classic'=>'Classic') as $id=>$l): ?><label style="cursor:pointer;"><input type="radio" name="wpsn_email_template" value="<?php echo $id;?>" <?php checked($cur_skin,$id);?> style="display:none;"><div class="w_skin s_<?php echo $id;?>"><strong><?php echo esc_html($t);?></strong><p style="font-size:10px;"><?php echo esc_html($ex);?></p><div style="text-align:center;margin-top:15px;font-weight:bold;font-size:12px;"><?php echo $l;?></div></div></label><?php endforeach;?></div>
			<h2>4. Content Modules</h2><table class="form-table"><tr><th>Options</th><td><label><input type="checkbox" name="wpsn_show_featured_image" value="1" <?php checked(get_option('wpsn_show_featured_image','1'),'1');?>> Show Featured Image next to title</label><hr><?php $en=(array)get_option('wpsn_post_elements',array('title','excerpt')); $els=apply_filters('wpsn_post_elements_list',array('title'=>'Title','date'=>'Date','author'=>'Author','excerpt'=>'Excerpt','content'=>'Content','categories'=>'Categories','tags'=>'Tags')); foreach($els as $k=>$v) echo '<label style="display:block;margin-bottom:5px;"><input type="checkbox" name="wpsn_post_elements[]" value="'.$k.'" '.checked(in_array($k,$en,true),true,false).'> '.$v.'</label>'; ?></td></tr></table>
			<h2>5. Templates & Tracking</h2><table class="form-table"><tr><th>Single Subj</th><td><input type="text" name="wpsn_subject_single" value="<?php echo esc_attr(get_option('wpsn_subject_single'));?>" class="large-text"></td></tr><tr><th>Digest Subj</th><td><input type="text" name="wpsn_subject_digest" value="<?php echo esc_attr(get_option('wpsn_subject_digest'));?>" class="large-text"></td></tr><tr><th>Tracking</th><td><label><input type="checkbox" name="wpsn_track_opens" value="1" <?php checked(get_option('wpsn_track_opens','1'),'1');?>> Opens</label> <label style="margin-left:15px;"><input type="checkbox" name="wpsn_track_clicks" value="1" <?php checked(get_option('wpsn_track_clicks','1'),'1');?>> Clicks</label></td></tr><tr><th>Footer HTML</th><td><textarea name="wpsn_footer_custom" rows="3" class="large-text"><?php echo esc_textarea(get_option('wpsn_footer_custom'));?></textarea></td></tr></table>
			<h2>6. Lead Generation</h2><table class="form-table"><tr><th>Popup</th><td><label><input type="checkbox" name="wpsn_enable_popup" value="1" <?php checked(get_option('wpsn_enable_popup'),'1');?>> Enable Modal</label> <select name="wpsn_popup_position"><option value="bottom-right" <?php selected(get_option('wpsn_popup_position'),'bottom-right');?>>Bottom-R</option><option value="center" <?php selected(get_option('wpsn_popup_position'),'center');?>>Center Modal</option></select></td></tr><tr><th>Top Bar</th><td><label><input type="checkbox" name="wpsn_enable_topbar" value="1" <?php checked(get_option('wpsn_enable_topbar'),'1');?>> Enable Bar</label></td></tr><tr><th>Text Settings</th><td>Heading: <input type="text" name="wpsn_topbar_heading" value="<?php echo esc_attr(get_option('wpsn_topbar_heading','Join Us'));?>" class="regular-text"> Button: <input type="text" name="wpsn_button_text" value="<?php echo esc_attr(get_option('wpsn_button_text','Subscribe'));?>" class="regular-text"></td></tr></table>
			<?php submit_button();?></form></div>
			<div style="flex:1;"><div class="w_help"><h3>Documentation</h3><ul style="font-size:12px;line-height:1.6;"><li><code>[site_name]</code> - WP Name</li><li><code>[title]</code> - Post Title</li><li><code>[date]</code> - Post Date</li><li><code>[unsubscribe]</code> - Sendy Tag</li></ul></div></div>
		</div></div>
		<?php
	}

	public function enqueue_scripts() {
		$nonce = wp_create_nonce('sn_a_nonce'); ?>
		<script>jQuery(document).ready(function($){
			$('#f_set').on('change',function(){ var v=$(this).val(); $('#sch_row').toggle(v==='daily'||v==='weekly'); $('#sch_day').toggle(v==='weekly'); });
			$('#fb').click(function(){ var b=$(this); b.prop('disabled',true).text('...'); $.post(ajaxurl,{action:'wpsn_fetch_brands',url:$('#api_u').val(),key:$('#api_k').val(),nonce:'<?php echo $nonce;?>'},function(r){ if(r.success){ var s=$('#sb'); s.empty(); $.each(r.data,function(i,v){ s.append($('<option>',{value:v.id,text:v.name})); }); } b.prop('disabled',false).text('Fetch Brands'); }); });
			$('#fl').click(function(){ var b=$(this); b.prop('disabled',true).text('...'); $.post(ajaxurl,{action:'wpsn_fetch_lists',url:$('#api_u').val(),key:$('#api_k').val(),brand:$('#sb').val(),nonce:'<?php echo $nonce;?>'},function(r){ if(r.success){ var s=$('#sl'); s.empty(); $.each(r.data,function(i,v){ s.append($('<option>',{value:v.id,text:v.name})); }); } b.prop('disabled',false).text('Fetch Lists'); }); });
			$('#sb').on('change',function(){ $('#bn').val($(this).find('option:selected').text()); });
			$('#sl').on('change',function(){ $('#ln').val($(this).find('option:selected').text()); });
			$('#sn_t_btn').click(function(){ var b=$(this); b.prop('disabled',true).text('...'); $.post(ajaxurl,{action:'wpsn_test_single',post_id:<?php echo get_the_ID();?>,nonce:'<?php echo wp_create_nonce("sn_tn");?>'},function(r){ $('#sn_t_res').text(r.success?'Success':'Error: '+r.data); b.prop('disabled',false).text('Test Send Now'); }); });
		});</script><?php
	}

	public function fetch_brands(){ check_ajax_referer('sn_a_nonce','nonce'); $res=wp_remote_post(rtrim($_POST['url'],'/').'/api/brands/get-brands.php',array('body'=>array('api_key'=>$_POST['key']))); wp_send_json_success(json_decode(wp_remote_retrieve_body($res),true)); }
	public function fetch_lists(){ check_ajax_referer('sn_a_nonce','nonce'); $res=wp_remote_post(rtrim($_POST['url'],'/').'/api/lists/get-lists.php',array('body'=>array('api_key'=>$_POST['key'],'brand_id'=>$_POST['brand']))); wp_send_json_success(json_decode(wp_remote_retrieve_body($res),true)); }
	public function manual_test_ajax(){ check_ajax_referer('sn_tn','nonce'); $core=new WPSN_Core(); $res=$core->transmit(array((int)$_POST['post_id'])); if($res==='1') wp_send_json_success(); else wp_send_json_error($res); }
}
