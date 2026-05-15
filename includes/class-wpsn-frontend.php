<?php
/**
 * Frontend Class
 * Renders Popup, Top Bar, and Widget functionality.
 *
 * @package WPSendyNotifier
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class WPSN_Frontend {
	public function __construct() {
		add_shortcode( 'sendy_subscribe', array( $this, 'shortcode_view' ) );
		add_action( 'wp_footer', array( $this, 'render_engagement' ) );
		add_action( 'wp_ajax_wpsn_subscribe', array( $this, 'ajax_subscribe' ) );
		add_action( 'wp_ajax_nopriv_wpsn_subscribe', array( $this, 'ajax_subscribe' ) );
	}

	public function render_engagement() {
		$btn_text = get_option( 'wpsn_button_text', 'Subscribe' );

		// POPUP logic (1s snappy delay)
		if ( get_option( 'wpsn_enable_popup' ) ) {
			$visibility = get_option( 'wpsn_popup_visibility', 'posts' );
			$show       = ( $visibility === 'all' && ( is_singular() || is_front_page() ) ) || ( $visibility === 'posts' && is_single() );

			if ( $show ) {
				$pos = get_option( 'wpsn_popup_position', 'bottom-right' );
				$css = ( $pos === 'center' ) ? 'top:50%;left:50%;transform:translate(-50%,-50%);width:400px;' : 'bottom:30px;right:30px;';
				if ( $pos === 'center' ) {
					echo "<div id='wpsn-ov' style='position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9998;display:none;'></div>";
				} ?>
				<div id="wpsn-p" style="position:fixed;<?php echo $css; ?>background:#fff;box-shadow:0 10px 30px rgba(0,0,0,0.2);border-radius:12px;padding:30px;z-index:99999;border:1px solid #eee;display:none;">
					<button id="wpsn-p-c" style="position:absolute;top:10px;right:15px;border:none;background:none;font-size:24px;cursor:pointer;color:#999;">&times;</button>
					<h4 style="margin:0 0 15px;"><?php echo esc_html( get_option( 'wpsn_popup_text', 'Stay Updated!' ) ); ?></h4>
					<form class="wpsn-af"><input type="text" name="name" placeholder="Name" required style="width:100%;padding:10px;margin-bottom:10px;"><input type="email" name="email" placeholder="Email" required style="width:100%;padding:10px;margin-bottom:15px;"><button type="submit" style="width:100%;background:#2563eb;color:#fff;border:none;padding:12px;border-radius:6px;font-weight:bold;"><?php echo esc_html( $btn_text ); ?></button><div class="wpsn-m" style="margin-top:10px;text-align:center;"></div></form>
				</div><script>jQuery(document).ready(function($){ if(!localStorage.getItem('wpsn_v18_p')){ setTimeout(function(){ $('#wpsn-p, #wpsn-ov').fadeIn(); }, 1000); } $('#wpsn-p-c').click(function(){ $('#wpsn-p, #wpsn-ov').fadeOut(); localStorage.setItem('wpsn_v18_p','1'); }); });</script>
				<?php
			}
		}

		// TOP BAR logic
		if ( get_option( 'wpsn_enable_topbar' ) ) {
			?>
			<div id="wpsn-b" style="position:fixed;top:0;left:0;width:100%;background:#111;color:#fff;padding:12px 20px;z-index:100000;text-align:center;display:none;font-family:sans-serif;">
				<strong><?php echo esc_html( get_option( 'wpsn_topbar_heading', 'Join Us' ) ); ?></strong> <span style="margin:0 15px; opacity:0.8;"><?php echo esc_html( get_option( 'wpsn_topbar_text' ) ); ?></span>
				<form class="wpsn-af" style="display:inline-block;"><input type="text" name="name" placeholder="Name" required style="padding:6px;width:110px;"><input type="email" name="email" placeholder="Email" required style="padding:6px;width:170px;margin-left:5px;"><button type="submit" style="background:#2563eb;color:#fff;border:none;padding:6px 15px;border-radius:4px;margin-left:10px;"><?php echo esc_html( $btn_text ); ?></button><span class="wpsn-m" style="margin-left:10px;"></span></form>
				<button id="wpsn-b-c" style="background:none;border:none;color:#777;margin-left:20px;cursor:pointer;">&times;</button>
			</div><script>jQuery(document).ready(function($){ if(!localStorage.getItem('wpsn_v18_b')){ $('#wpsn-b').slideDown(); } $('#wpsn-b-c').click(function(){ $('#wpsn-b').slideUp(); localStorage.setItem('wpsn_v18_b','1'); }); });</script>
			<?php
		}

		?>
		<script>
		jQuery(document).on('submit', '.wpsn-af', function(e) {
				e.preventDefault();
				var f = jQuery(this);
				var msg_container = f.find('.wpsn-m');
					jQuery.post('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
						action: 'wpsn_subscribe',
								name: f.find('input[name="name"]').val(),
									email: f.find('input[name="email"]').val()
									}, function(r) {
										f.find('.sn-m').text(r.data);
										if(r.success) {
											msg_container.css('color', '#22c55e').html(r.data); // Green
											f[0].reset();
										} else {
											msg_container.css('color', '#ef4444').html(r.data); // Red
										}
									});
		});
		</script>
		<?php
	}

	public function ajax_subscribe() {
		$res  = wp_remote_post(
			rtrim( get_option( 'wpsn_url' ), '/' ) . '/subscribe',
			array(
				'body' => array(
					'api_key' => get_option( 'wpsn_api_key' ),
					'name'    => $_POST['name'],
					'email'   => $_POST['email'],
					'list'    => get_option( 'wpsn_list_id' ),
					'boolean' => 'true',
				),
			)
		);
		$body = trim( wp_remote_retrieve_body( $res ) );
		if ( $body == '1' || $body == 'true' ) {
			wp_send_json_success( get_option( 'wpsn_success_message', 'Success!' ) . ' Please check your inbox to confirm.' );
		} else {
			wp_send_json_error( $body ); }
	}

	public function shortcode_view( $atts ) {
		$atts = shortcode_atts( array( 'title' => 'Subscribe' ), $atts );
		ob_start();
		?>
		<div class="wpsn-box" style="background:#f9f9f9; padding:25px; border:1px solid #ddd; border-radius:8px;">
			<h4><?php echo esc_html( $atts['title'] ); ?></h4>
			<form class="wpsn-af"><input type="text" name="name" required placeholder="Name" style="width:100%; margin-bottom:10px;"><input type="email" name="email" required placeholder="Email" style="width:100%; margin-bottom:15px;"><button type="submit" style="width:100%; background:#2563eb; color:#fff; border:none; padding:10px; border-radius:5px;"><?php echo esc_html( get_option( 'wpsn_button_text', 'Subscribe' ) ); ?></button><div class="wpsn-m"></div></form>
		</div>
		<?php
		return ob_get_clean();
	}
}
