<?php
/**
 * Renderer Class
 * Generates the HTML for individual posts and the final email shell.
 *
 * @package WPSendyNotifier
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPSN_Renderer {

	public function render_post_row( $post_id ) {
		$modules = (array) get_option( 'wpsn_post_elements', array( 'title', 'excerpt' ) );
		$post = get_post( $post_id );
		$html = "<div style='margin-bottom:35px; padding-bottom:20px; border-bottom:1px solid #eee;'>";
		
		$thumb = "";
		if ( get_option( 'wpsn_show_featured_image', '1' ) && has_post_thumbnail( $post_id ) ) {
			$img_url = get_the_post_thumbnail_url( $post_id, 'thumbnail' );
			$thumb = "<td width='80' valign='top' style='padding-right:15px;'><img src='".esc_url($img_url)."' width='80' height='80' style='border-radius:6px;'></td>";
		}

		foreach ( $modules as $key ) {
			switch ( $key ) {
				case 'title':
					$t = get_the_title( $post_id );
					if ( $thumb ) { $html .= "<table width='100%'><tr>$thumb<td valign='top'><h3><a href='".get_permalink($post_id)."'>".esc_html($t)."</a></h3></td></tr></table>"; $thumb = ""; }
					else { $html .= "<h3><a href='".get_permalink($post_id)."'>".esc_html($t)."</a></h3>"; }
					break;
				case 'excerpt': $html .= "<p style='color:#444; font-size:14px;'>".get_the_excerpt($post_id)."</p>"; break;
				case 'content': $html .= "<div>".wp_kses_post($post->post_content)."</div>"; break;
				case 'date': $html .= "<p style='color:#999; font-size:12px;'>Published: ".get_the_date('',$post_id)."</p>"; break;
				case 'categories': $html .= "<p style='font-size:11px;'>Categories: ".get_the_category_list(',','',$post_id)."</p>"; break;
				case 'tags': $tags=get_the_tag_list('','','',$post_id); if($tags)$html.="<p style='font-size:11px;'>Tags: $tags</p>"; break;
			}
		}
		return $html . "</div>";
	}

	public function wrap_in_shell( $content, $subject ) {
		$style = get_option( 'wpsn_email_template', 'modern' );
		$bg = ($style === 'classic') ? '#f4f4f4' : '#f8fafc';
		$site_url = home_url();
		$site_name = get_bloginfo('name');
		
		return "<!DOCTYPE html><html><head><style>body{background:$bg; padding:20px; font-family:sans-serif;} .w{max-width:600px; margin:auto; background:#fff; padding:40px; border-radius:10px;} a{color:#2563eb; text-decoration:none;}</style></head><body><div class='w'><div style='text-align:center;'><h1><a href='".esc_url($site_url)."'>".esc_html($site_name)."</a></h1></div><h1>".esc_html($subject)."</h1>{$content}<div style='text-align:center; font-size:12px; color:#999; margin-top:30px;'><a href='[unsubscribe]'>Unsubscribe</a></div></div></body></html>";
	}
}
