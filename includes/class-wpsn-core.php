<?php
/**
 * Core Logic Class
 * Handles API transmission, queueing, and scheduling.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPSN_Core {
	private static $is_preparing_email = false;

	public function __construct() {
		register_activation_hook( __FILE__, array( $this, 'sync_cron' ) );
		add_action( 'wpsn_scheduled_digest', array( $this, 'process_queue' ) );
		add_action( 'transition_post_status', array( $this, 'handle_publish' ), 10, 3 );
	}

	public static function is_preparing() { return self::$is_preparing_email; }

	public function sync_cron() {
		wp_clear_scheduled_hook( 'wpsn_scheduled_digest' );
		$delivery_frequency = get_option( 'wpsn_frequency', 'immediate' );
		if ( in_array( $delivery_frequency, array( 'immediate', 'manual' ), true ) ) { return; }
		
		$time = get_option( 'wpsn_send_time', '09:00' );
		$day  = get_option( 'wpsn_send_day', 'Monday' );
		$next = ( 'daily' === $delivery_frequency ) ? strtotime( "today $time" ) : strtotime( "next $day $time" );
		if ( $next <= time() ) { $next = ( 'daily' === $delivery_frequency ) ? strtotime( "tomorrow $time" ) : strtotime( "next $day +1 week $time" ); }
		wp_schedule_single_event( $next, 'wpsn_scheduled_digest' );
	}

	public function handle_publish( $new_status, $old_status, $post ) {
		if ( 'publish' === $new_status && 'publish' !== $old_status && 'post' === $post->post_type ) {
			if ( get_post_meta( $post->ID, '_wpsn_send_flag', true ) === 'no' ) { return; }
			$freq = get_option( 'wpsn_frequency', 'immediate' );
			if ( 'immediate' === $freq ) { $this->transmit( array( $post->ID ) ); }
			elseif ( 'manual' !== $freq ) {
				$queue = get_option( 'wpsn_queue', array() );
				if ( ! in_array( $post->ID, $queue, true ) ) { $queue[] = $post->ID; update_option( 'wpsn_queue', $queue ); }
			}
		}
	}

	public function process_queue() {
		$queue = get_option( 'wpsn_queue', array() );
		if ( ! empty( $queue ) ) { $this->transmit( $queue ); update_option( 'wpsn_queue', array() ); }
		$this->sync_cron();
	}

	public function transmit( $post_ids ) {
		self::$is_preparing_email = true;
		$api_url = rtrim( get_option( 'wpsn_url' ), '/ ' );
		$api_key = trim( get_option( 'wpsn_api_key' ) );
		if ( ! $api_url || ! $api_key ) { return 'Missing Config'; }
		
		$from = get_option( 'wpsn_from_email' ) ?: get_option( 'admin_email' );
		$renderer = new WPSN_Renderer();
		$content = ''; foreach ( $post_ids as $id ) { $content .= $renderer->render_single_row( $id ); }
		if ( empty( trim( strip_tags( $content ) ) ) ) { $content = '<h3>' . get_the_title( $post_ids[0] ) . '</h3><p>' . get_the_excerpt( $post_ids[0] ) . '</p>'; }
		
		$is_digest = count( $post_ids ) > 1;
		$subject_tpl = get_option( $is_digest ? 'wpsn_subject_digest' : 'wpsn_subject_single' ) ?: '[site_name]: [title]';
		$subject = str_replace( array( '[site_name]', '[title]', '[date]' ), array( get_bloginfo( 'name' ), get_the_title( $post_ids[0] ), get_the_date() ), $subject_tpl );
		
		$final_html = $renderer->wrap_in_shell( $content, $subject );
		$response = wp_remote_post( $api_url . '/api/campaigns/create.php', array( 'timeout' => 45, 'body' => array( 'api_key' => $api_key, 'from_name' => get_bloginfo( 'name' ), 'from_email' => $from, 'reply_to' => $from, 'title' => $subject, 'subject' => $subject, 'html_text' => $final_html, 'list_ids' => get_option( 'wpsn_list_id' ), 'brand_id' => get_option( 'wpsn_brand_id' ), 'query_string' => get_option( 'wpsn_query_string' ), 'track_opens' => get_option( 'wpsn_track_opens', '1' ), 'track_clicks' => get_option( 'wpsn_track_clicks', '1' ), 'send_campaign' => 1 ) ) );
		self::$is_preparing_email = false;
		if ( is_wp_error( $response ) ) { return $response->get_error_message(); }
		$body = trim( wp_remote_retrieve_body( $response ) );
		if ( in_array( $body, array( 'Campaign created', 'Campaign created and now sending' ), true ) ) { foreach ( $post_ids as $id ) { update_post_meta( $id, '_wpsn_last_sent', current_time( 'mysql' ) ); } return '1'; }
		return $body;
	}
}
