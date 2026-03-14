=== Easy WhatsApp ===
Contributors: suchandanhaldar
Tags: whatsapp, post-meta, custom-fields
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds a WhatsApp number meta field to selected post types.

== Description ==

Easy WhatsApp adds a secure, sanitized WhatsApp number field as post meta.

You can choose one or multiple post types from **Easy WhatsApp > Settings**.

By default, the field is enabled for the `post` post type.

Developers can still customize with a filter:

`add_filter( 'easy_whatsapp_post_types', function( $post_types ) { return $post_types; } );`

== Installation ==

1. Upload the `easy-whatsapp` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins screen.
3. Go to **Easy WhatsApp > Settings** and select post types.
4. Edit a selected post type item and fill in the "WhatsApp Number" meta box.

== Frequently Asked Questions ==

= Which post types are supported? =

Select supported post types in **Easy WhatsApp > Settings**.

= What format should I use for numbers? =

Use international format with country code, for example `+12025550123`.

== Changelog ==

= 1.2.0 =
* Added a floating WhatsApp button to the frontend for desktop and mobile apps.

= 1.1.0 =
* Added admin settings page to choose one or multiple post types.
* Added sanitized option storage for selected post types.

= 1.0.0 =
* Initial release.
