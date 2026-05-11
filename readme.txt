
=== Sendy Notifier ===
Contributors: dshanske
Tags: sendy, newsletter, notification, rss, subscription
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 1.0.0
License: GPLv3

The definitive modular suite for Sendy integration. Featuring manual triggers, dynamic visual galleries, and advanced lead-gen tools.

== Description ==
A WordPress/ClassicPress plugin to connect your site to a Sendy installation. Support for immediate per-post updates, daily/weekly digests, and a robust frontend lead-generation suite.

== Installation ==
1. Upload the `sendy-notifier` folder to `/wp-content/plugins/`.
2. Activate via the 'Plugins' menu.
3. Configure your API key and URL in Settings > Sendy Notifier.

== For Developers ==

= 1. Add Custom Post Modules =
`
add_filter( 'wpsn_post_elements_list', function( $elements ) {
    $elements['my_meta'] = 'Custom Data';
    return $elements;
});
`

= 2. Render Custom Post Content =
`
add_action( 'wpsn_render_element_my_meta', function( $post_id ) {
    echo '<p>Dynamic content here.</p>';
});
`

== Changelog ==
= 1.0.0 =
* Initial Release
