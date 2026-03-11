<?php
/**
 * Uninstall logic for Easy WhatsApp plugin.
 *
 * @package EasyWhatsApp
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
		'_easy_whatsapp_number'
	)
);
