<?php
/**
 * Admin Logic Class
 * Manages Settings, Metaboxes, and API Discovery.
 *
 * @package WPSendyNotifier
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPSN_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'init_settings' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_post_metabox' ) );
		add_action( 'save_post', array( $this, 'save_metabox_data' ) );
		add_action( 'admin_footer', array( $this, 'render_admin_js' ) );
		
		add_action( 'wp_ajax_wpsn_fetch_brands', array( $this, 'ajax_fetch_brands' ) );
		add_action( 'wp_ajax_wpsn_fetch_lists', array( $this, 'ajax_fetch_lists' ) );
		add_action( 'wp_ajax_wpsn_test_single', array( $this, 'ajax_manual_trigger' ) );
	}

	public function add_menu() {
		add_options_page( 'Sendy Notifier', 'Sendy Notifier', 'manage_options', 'wpsn-settings', array( $this, 'render_settings_page' ) );
	}

	public function init_settings() {
		$options = array(
			'wpsn_url', 'wpsn_api_key', 'wpsn_list_id', 'wpsn_brand_id', 'wpsn_list_name', 'wpsn_brand_name',
			'wpsn_from_email', 'wpsn_subject_single', 'wpsn_subject_digest', 'wpsn_email_template',
			'wpsn_show_logo', 'wpsn_show_title', 'wpsn_header_custom', 'wpsn_footer_custom',
			'wpsn_frequency', 'wpsn_send_time', 'wpsn_send_day', 'wpsn_post_elements', 'wpsn_show_featured_image',
			'wpsn_track_opens', 'wpsn_track_clicks', 'wpsn_query_string',
			'wpsn_enable_popup', 'wpsn_popup_text', 'wpsn_popup_position', 'wpsn_popup_visibility',
			'wpsn_enable_topbar', 'wpsn_topbar_heading', 'wpsn_topbar_text', 'wpsn_button_text', 'wpsn_success_message'
		);
		foreach ( $options as $opt ) { register_setting( 'wpsn-settings-group', $opt ); }
	}

	public function add_post_metabox() {
		add_meta_box( 'wpsn_meta', 'Sendy Notification', array( $this, 'render_metabox' ), 'post', 'side', 'high' );
	}

	public function render_metabox( $post ) {
		$is_enabled = get_post_meta( $post->ID, '_wpsn_send_flag', true ) ?: 'yes';
		$last_sent  = get_post_meta( $post->ID, '_wpsn_last_sent', true );
		wp_nonce_field( 'wpsn_metabox_action', 'wpsn_metabox_nonce' );
		?>
		<p><label><input type="checkbox" name="wpsn_send_flag" value="yes" <?php checked( $is_enabled, 'yes' ); ?>> Enable Email Notification</label></p>
		<?php if ( $last_sent ) : ?>
			<p style="color:#666; font-size:11px;"><em>Last sent to Sendy: <?php echo esc_html( $last_sent ); ?></em></p>
		<?php endif; ?>
		<hr>
		<button type="button" id="wpsn_test_btn" class="button button-secondary">Test Send Now</button>
		<p id="wpsn_test_status" style="font-size:11px; margin-top:5px;"></p>
		<script>
		jQuery(document).ready(function($){
			$('#wpsn_test_btn').click(function(){
				var btn = $(this); btn.prop('disabled', true).text('Processing...');
				$.post(ajaxurl, { action: 'wpsn_test_single', post_id: <?php echo $post->ID; ?>, nonce: '<?php echo wp_create_nonce("wpsn_test_nonce"); ?>' }, function(r){
					$('#wpsn_test_status').text(r.success ? 'Success!' : 'Failed: ' + r.data);
					btn.prop('disabled', false).text('Test Send Now');
				});
			});
		});
		</script>
		<?php
	}

	public function save_metabox_data( $post_id ) {
		if ( ! isset( $_POST['wpsn_metabox_nonce'] ) || ! wp_verify_nonce( $_POST['wpsn_metabox_nonce'], 'wpsn_metabox_action' ) ) return;
		update_post_meta( $post_id, '_wpsn_send_flag', isset( $_POST['wpsn_send_flag'] ) ? 'yes' : 'no' );
	}

	public function render_settings_page() {
		$current_skin = get_option( 'wpsn_email_template', 'modern' );
		$latest = get_posts( array( 'numberposts' => 1 ) );
		$p_title = ( ! empty( $latest ) ) ? get_the_title( $latest[0]->ID ) : 'Sample Post Title';
		$p_text  = ( ! empty( $latest ) ) ? wp_trim_words( $latest[0]->post_content, 15 ) : 'Example content for your visual template preview...';
		?>
		<style>
			.wpsn-skin-preview { border: 1px solid #ccc; border-radius: 8px; padding: 20px; transition: 0.3s; min-height: 180px; position: relative; }
			.skin-modern { background: #f8fafc; border-left: 5px solid #2563eb; }
			.skin-modern .card { background: #fff; border: 1px solid #e2e8f0; padding: 10px; border-radius: 5px; }
			.skin-classic { background: #f4f4f4; text-align: center; font-family: "Times New Roman", serif !important; }
			.skin-minimal { background: #fff; border: 1px solid #eee; }
			input[name="wpsn_email_template"]:checked + .wpsn-skin-preview { border: 2px solid #2563eb; opacity: 1; box-shadow: 0 5px 15px rgba(37,99,235,0.2); }
			input[name="wpsn_email_template"] + .wpsn-skin-preview { opacity: 0.6; }
			.wpsn-help { background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 8px; }
		</style>
		<div class="wrap">
			<h1>Sendy Notifier Master Suite v18.0</h1>
			<hr>
			<div style="display:flex; gap:30px; margin-top:20px;">
				<div style="flex:3;">
					<form method="post" action="options.php">
						<?php settings_fields( 'wpsn-settings-group' ); ?>
						
						<h2>1. Delivery Strategy</h2>
						<table class="form-table">
							<tr><th>Frequency</th><td>
								<select name="wpsn_frequency" id="freq_f" class="regular-text">
									<option value="immediate" <?php selected(get_option('wpsn_frequency'),'immediate');?>>Immediate (Send on Publish)</option>
									<option value="daily" <?php selected(get_option('wpsn_frequency'),'daily');?>>Daily Digest (Batch)</option>
									<option value="weekly" <?php selected(get_option('wpsn_frequency'),'weekly');?>>Weekly Digest (Batch)</option>
									<option value="manual" <?php selected(get_option('wpsn_frequency'),'manual');?>>None (Manual Only)</option>
								</select>
								<p class="description">How often emails are sent. Select "None" to only trigger manually via the Post edit screen.</p>
							</td></tr>
							<tr id="sch_row" style="<?php echo (in_array(get_option('wpsn_frequency'), array('daily','weekly'))) ? '' : 'display:none;'; ?>">
								<th>Schedule</th><td>
									At <input type="time" name="wpsn_send_time" value="<?php echo esc_attr(get_option('wpsn_send_time','09:00'));?>"> 
									<span id="sch_day" style="<?php echo (get_option('wpsn_frequency')==='weekly') ? '' : 'display:none;'; ?>">
										on <select name="wpsn_send_day"><?php foreach(array('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') as $d) echo '<option value="'.$d.'" '.selected(get_option('wpsn_send_day'),$d,false).'>'.$d.'</option>'; ?></select>
									</span>
								</td>
							</tr>
						</table>

						<h2>2. API & Connectivity</h2>
						<table class="form-table">
							<tr><th>Sendy URL</th><td><input type="text" id="api_u" name="wpsn_url" value="<?php echo esc_attr(get_option('wpsn_url'));?>" class="large-text" placeholder="https://sendy.example.com"><p class="description">URL to your Sendy installation.</p></td></tr>
							<tr><th>API Key</th><td><input type="password" id="api_k" name="wpsn_api_key" value="<?php echo esc_attr(get_option('wpsn_api_key'));?>" class="regular-text"><p class="description">Masked for security.</p></td></tr>
							<tr><th>Target Discovery</th><td>
								<input type="hidden" name="wpsn_brand_name" id="bname" value="<?php echo esc_attr(get_option('wpsn_brand_name'));?>">
								<input type="hidden" name="wpsn_list_name" id="lname" value="<?php echo esc_attr(get_option('wpsn_list_name'));?>">
								<button type="button" id="f_brand" class="button">Fetch Brands</button>
								<select name="wpsn_brand_id" id="s_brand" style="min-width:180px;"><option value="<?php echo esc_attr(get_option('wpsn_brand_id'));?>"><?php echo get_option('wpsn_brand_name') ?: 'Select Brand';?></option></select>
								<button type="button" id="f_list" class="button">Fetch Lists</button>
								<select name="wpsn_list_id" id="s_list" style="min-width:180px;"><option value="<?php echo esc_attr(get_option('wpsn_list_id'));?>"><?php echo get_option('wpsn_list_name') ?: 'Select List';?></option></select>
							</td></tr>
							<tr><th>From Email</th><td><input type="email" name="wpsn_from_email" value="<?php echo esc_attr(get_option('wpsn_from_email'));?>" class="regular-text" placeholder="<?php echo esc_attr(get_option('admin_email'));?>"><p class="description">If empty, site admin email is used.</p></td></tr>
						</table>

						<h2>3. Dynamic Visual Gallery</h2>
						<p class="description">Previews use your site's actual latest post data.</p>
						<div style="display:grid; grid-template-columns:repeat(3,1fr); gap:15px; margin-bottom:30px;">
							<?php foreach(array('modern'=>'Modern','minimal'=>'Minimal','classic'=>'Classic') as $id=>$l): ?>
								<label style="cursor:pointer;"><input type="radio" name="wpsn_email_template" value="<?php echo $id;?>" <?php checked($current_skin,$id);?> style="display:none;"><div class="wpsn-skin-preview skin-<?php echo $id;?>"><div class="card"><strong><?php echo esc_html($p_title);?></strong><p style="font-size:10px; margin-top:5px;"><?php echo esc_html($p_text);?></p></div><div style="text-align:center; margin-top:15px; font-weight:bold; font-size:12px;"><?php echo $l;?> Layout</div></div></label>
							<?php endforeach;?>
						</div>

						<h2>4. Email Content Modules</h2>
						<table class="form-table">
							<tr><th>Featured Image</th><td><label><input type="checkbox" name="wpsn_show_featured_image" value="1" <?php checked(get_option('wpsn_show_featured_image','1'),'1');?>> Show image next to title</label></td></tr>
							<tr><th>Data Elements</th><td>
								<?php $en=(array)get_option('wpsn_post_elements',array('title','excerpt')); 
								foreach(array('title'=>'Title','date'=>'Date','author'=>'Author','excerpt'=>'Excerpt','content'=>'Content','categories'=>'Categories','tags'=>'Tags') as $k=>$v): ?>
									<label style="display:block; margin-bottom:5px;"><input type="checkbox" name="wpsn_post_elements[]" value="<?php echo $k;?>" <?php checked(in_array($k,$en,true));?>> <?php echo $v;?></label>
								<?php endforeach;?>
							</td></tr>
						</table>

						<h2>5. Branding & Subject Templates</h2>
						<table class="form-table">
							<tr><th>Single Post Subject</th><td><input type="text" name="wpsn_subject_single" value="<?php echo esc_attr(get_option('wpsn_subject_single'));?>" class="large-text" placeholder="[site_name]: [title]"></td></tr>
							<tr><th>Digest Subject</th><td><input type="text" name="wpsn_subject_digest" value="<?php echo esc_attr(get_option('wpsn_subject_digest'));?>" class="large-text" placeholder="Summary from [site_name]"></td></tr>
							<tr><th>Visual Branding</th><td><label><input type="checkbox" name="wpsn_show_logo" value="1" <?php checked(get_option('wpsn_show_logo'),'1');?>> Show Site Logo</label><br><label><input type="checkbox" name="wpsn_show_title" value="1" <?php checked(get_option('wpsn_show_title'),'1');?>> Show Linked Title</label></td></tr>
							<tr><th>Custom Header HTML</th><td><textarea name="wpsn_header_custom" rows="3" class="large-text"><?php echo esc_textarea(get_option('wpsn_header_custom'));?></textarea></td></tr>
						</table>

						<h2>6. Advanced Tracking & Footer</h2>
						<table class="form-table">
							<tr><th>Tracking</th><td><label><input type="checkbox" name="wpsn_track_opens" value="1" <?php checked(get_option('wpsn_track_opens','1'),'1');?>> Opens</label> <label style="margin-left:20px;"><input type="checkbox" name="wpsn_track_clicks" value="1" <?php checked(get_option('wpsn_track_clicks','1'),'1');?>> Clicks</label></td></tr>
							<tr><th>UTM Query String</th><td><input type="text" name="wpsn_query_string" value="<?php echo esc_attr(get_option('wpsn_query_string'));?>" class="large-text" placeholder="utm_source=news"></td></tr>
							<tr><th>Custom Footer HTML</th><td><textarea name="wpsn_footer_custom" rows="3" class="large-text"><?php echo esc_textarea(get_option('wpsn_footer_custom'));?></textarea></td></tr>
						</table>

						<h2>7. Lead Gen: Popup</h2>
						<table class="form-table">
							<tr><th>Logic</th><td><label><input type="checkbox" name="wpsn_enable_popup" value="1" <?php checked(get_option('wpsn_enable_popup'),'1');?>> Enable Modal</label> <select name="wpsn_popup_position" style="margin-left:10px;"><option value="bottom-right" <?php selected(get_option('wpsn_popup_position'),'bottom-right');?>>Bottom Right</option><option value="center" <?php selected(get_option('wpsn_popup_position'),'center');?>>Center Modal</option></select></td></tr>
							<tr><th>Visibility</th><td><select name="wpsn_popup_visibility" class="regular-text"><option value="posts" <?php selected(get_option('wpsn_popup_visibility'),'posts');?>>Posts Only</option><option value="all" <?php selected(get_option('wpsn_popup_visibility'),'all');?>>Global</option></select></td></tr>
							<tr><th>Heading</th><td><input type="text" name="wpsn_popup_text" value="<?php echo esc_attr(get_option('wpsn_popup_text'));?>" class="large-text"></td></tr>
						</table>

						<h2>8. Lead Gen: Top Bar & Global Text</h2>
						<table class="form-table">
							<tr><th>Enable Top Bar</th><td><label><input type="checkbox" name="wpsn_enable_topbar" value="1" <?php checked(get_option('wpsn_enable_topbar'),'1');?>> Enable Bar</label></td></tr>
							<tr><th>Bar Content</th><td><input type="text" name="wpsn_topbar_heading" value="<?php echo esc_attr(get_option('wpsn_topbar_heading'));?>" class="regular-text" placeholder="Heading"> <input type="text" name="wpsn_topbar_text" value="<?php echo esc_attr(get_option('wpsn_topbar_text'));?>" class="regular-text" placeholder="Message"></td></tr>
							<tr><th>Button Label</th><td><input type="text" name="wpsn_button_text" value="<?php echo esc_attr(get_option('wpsn_button_text','Subscribe'));?>" class="regular-text"></td></tr>
							<tr><th>Success Response</th><td><input type="text" name="wpsn_success_message" value="<?php echo esc_attr(get_option('wpsn_success_message','Success!'));?>" class="large-text"></td></tr>
						</table>

						<?php submit_button(); ?>
					</form>
				</div>
				<div style="flex:1;"><div class="wpsn-help">
					<h3>Placeholder Tags</h3>
					<ul style="font-size:12px; line-height:1.6;">
						<li><code>[site_name]</code> - WordPress Title</li>
						<li><code>[title]</code> - Post Title</li>
						<li><code>[date]</code> - Post Date</li>
						<li><code>[unsubscribe]</code> - Required Link</li>
					</ul>
				</div></div>
			</div>
		</div>
		<?php
	}

	public function render_admin_js() {
		$nonce = wp_create_nonce( 'wpsn_admin_nonce' ); ?>
		<script>jQuery(document).ready(function($){
			$('#freq_f').on('change', function(){ var v=$(this).val(); $('#sch_row').toggle(v==='daily'||v==='weekly'); $('#sch_day').toggle(v==='weekly'); });
			$('#f_brand').click(function(){
				var b=$(this); b.prop('disabled',true).text('...');
				$.post(ajaxurl,{action:'wpsn_fetch_brands',url:$('#api_u').val(),key:$('#api_k').val(),nonce:'<?php echo $nonce;?>'},function(r){
					if(r.success){ var s=$('#s_brand'); s.empty(); $.each(r.data,function(i,v){ s.append($('<option>',{value:v.id,text:v.name})); }); }
					b.prop('disabled',false).text('Fetch Brands');
				});
			});
			$('#f_list').click(function(){
				var b=$(this); b.prop('disabled',true).text('...');
				$.post(ajaxurl,{action:'wpsn_fetch_lists',url:$('#api_u').val(),key:$('#api_k').val(),brand:$('#s_brand').val(),nonce:'<?php echo $nonce;?>'},function(r){
					if(r.success){ var s=$('#s_list'); s.empty(); $.each(r.data,function(i,v){ s.append($('<option>',{value:v.id,text:v.name})); }); }
					b.prop('disabled',false).text('Fetch Lists');
				});
			});
			$('#s_brand').on('change',function(){ $('#bname').val($(this).find('option:selected').text()); });
			$('#s_list').on('change',function(){ $('#lname').val($(this).find('option:selected').text()); });
		});</script><?php
	}

	public function ajax_fetch_brands(){ check_ajax_referer('wpsn_admin_nonce','nonce'); $res=wp_remote_post(rtrim($_POST['url'],'/').'/api/brands/get-brands.php',array('body'=>array('api_key'=>$_POST['key']))); wp_send_json_success(json_decode(wp_remote_retrieve_body($res),true)); }
	public function ajax_fetch_lists(){ check_ajax_referer('wpsn_admin_nonce','nonce'); $res=wp_remote_post(rtrim($_POST['url'],'/').'/api/lists/get-lists.php',array('body'=>array('api_key'=>$_POST['key'],'brand_id'=>$_POST['brand']))); wp_send_json_success(json_decode(wp_remote_retrieve_body($res),true)); }
	public function ajax_manual_trigger(){ check_ajax_referer('wpsn_test_nonce','nonce'); $core = new WPSN_Core(); $res=$core->transmit_payload(array((int)$_POST['post_id'])); if($res==='1')wp_send_json_success(); else wp_send_json_error($res); }
}
