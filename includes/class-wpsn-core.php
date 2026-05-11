<?php
/**
 * Core Transmission Class
 * Manages the API payload, WP-Cron scheduling, and publication hooks.
 *
 * @package WPSendyNotifier
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPSN_Core {

	/**
	 * Context flag to track if code is running within an email build.
	 * @var bool
	 */
	private static $is_preparing_email = false;

	/**
	 * Hook constructor.
	 */
	public function __construct() {
		register_activation_hook( __FILE__, array( $this, 'sync_cron_schedule' ) );
		add_action( 'wpsn_scheduled_digest', array( $this, 'process_queue' ) );
		add_action( 'transition_post_status', array( $this, 'on_post_status_change' ), 10, 3 );
	}

	/**
	 * Developer helper to check context.
	 * @return bool
	 */
	public static function is_preparing() {
		return self::$is_preparing_email;
	}

	/**
	 * Reschedules the WP-Cron event based on settings.
	 */
	public function sync_cron_schedule() {
		wp_clear_scheduled_hook( 'wpsn_scheduled_digest' );
		$delivery_frequency = get_option( 'wpsn_frequency', 'immediate' );
		
		if ( in_array( $delivery_frequency, array( 'immediate', 'manual' ), true ) ) {
			return;
		}

		$time_of_day = get_option( 'wpsn_send_time', '09:00' );
		$day_of_week = get_option( 'wpsn_send_day', 'Monday' );
		$next_run    = ( 'daily' === $delivery_frequency ) ? strtotime( "today $time_of_day" ) : strtotime( "next $day_of_week $time_of_day" );

		if ( $next_run <= time() ) {
			$next_run = ( 'daily' === $delivery_frequency ) ? strtotime( "tomorrow $time_of_day" ) : strtotime( "next $day_of_week +1 week $time_of_day" );
		}
		wp_schedule_single_event( $next_run, 'wpsn_scheduled_digest' );
	}

	/**
	 * Fires when a post is published.
	 */
	public function on_post_status_change( $new, $old, $post ) {
		if ( 'publish' === $new && 'publish' !== $old && 'post' === $post->post_type ) {
			if ( get_post_meta( $post->ID, '_wpsn_send_flag', true ) === 'no' ) {
				return;
			}
			
			$frequency = get_option( 'wpsn_frequency', 'immediate' );
			if ( 'immediate' === $frequency ) {
				$this->transmit_payload( array( $post->ID ) );
			} elseif ( 'manual' !== $frequency ) {
				$queue = get_option( 'wpsn_queue', array() );
				if ( ! in_array( $post->ID, $queue, true ) ) {
					$queue[] = $post->ID;
					update_option( 'wpsn_queue', $queue );
				}
			}
		}
	}

	public function process_queue() {
		$queue = get_option( 'wpsn_queue', array() );
		if ( ! empty( $queue ) ) {
			$this->transmit_payload( $queue );
			update_option( 'wpsn_queue', array() );
		}
		$this->sync_cron_schedule();
	}

	/**
	 * Sends the email data to the Sendy API.
	 */
	public function transmit_payload( $post_ids ) {
		self::$is_preparing_email = true;
		
		$api_url = rtrim( get_option( 'wpsn_url' ), '/ ' );
		$api_key = trim( get_option( 'wpsn_api_key' ) );
		$list_id = get_option( 'wpsn_list_id' );

		if ( ! $api_url || ! $api_key || ! $list_id ) {
			return __( 'Error: Missing API connectivity configuration.', 'wp-sendy-notifier' );
		}

		$from_email = get_option( 'wpsn_from_email' ) ?: get_option( 'admin_email' );
		$site_name  = get_bloginfo( 'name' );

		$renderer = new WPSN_Renderer();
		$content_html = '';
		foreach ( $post_ids as $id ) {
			$content_html .= $renderer->render_post_row( $id );
		}

		if ( empty( trim( strip_tags( $content_html ) ) ) ) {
			$content_html = '<h3>' . get_the_title( $post_ids[0] ) . '</h3><p>' . get_the_excerpt( $post_ids[0] ) . '</p>';
		}

		$is_digest   = count( $post_ids ) > 1;
		$subject_tpl = get_option( $is_digest ? 'wpsn_subject_digest' : 'wpsn_subject_single' ) ?: '[site_name]: [title]';
		$subject     = str_replace( array( '[site_name]', '[title]', '[date]' ), array( $site_name, get_the_title( $post_ids[0] ), get_the_date() ), $subject_tpl );

		$final_body = $renderer->wrap_in_shell( $content_html, $subject );

		$response = wp_remote_post( $api_url . '/api/campaigns/create.php', array(
			'timeout' => 45,
			'body'    => array(
				'api_key'       => $api_key,
				'from_name'     => $site_name,
				'from_email'    => $from_email,
				'reply_to'      => $from_email,
				'title'         => $subject,
				'subject'       => $subject,
				'html_text'     => $final_body,
				'list_ids'      => $list_id,
				'brand_id'      => get_option( 'wpsn_brand_id' ),
				'query_string'  => get_option( 'wpsn_query_string' ),
				'track_opens'   => get_option( 'wpsn_track_opens', '1' ),
				'track_clicks'  => get_option( 'wpsn_track_clicks', '1' ),
				'send_campaign' => 1
			)
		));

		self::$is_preparing_email = false;
		if ( is_wp_error( $response ) ) {
			return $response->get_error_message();
		}

		$body = trim( wp_remote_retrieve_body( $response ) );
		$is_success = in_array( $body, array( 'Campaign created', 'Campaign created and now sending' ), true );

		if ( $is_success ) {
			foreach ( $post_ids as $id ) {
				update_post_meta( $id, '_wpsn_last_sent', current_time( 'mysql' ) );
			}
			return '1';
		}

		return $body;
	}
}
