<?php
/**
 * Plugin helper functions.
 *
 * @package EasyWhatsApp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns WhatsApp number for a post.
 *
 * @param int $post_id Post ID. Defaults to current post.
 *
 * @return string
 */
function easy_whatsapp_get_number( $post_id = 0 ) {
	$post_id = absint( $post_id );

	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}

	if ( ! $post_id ) {
		return '';
	}

	$number = get_post_meta( $post_id, Easy_WhatsApp_Plugin::META_KEY, true );

	if ( ! is_string( $number ) ) {
		return '';
	}

	return $number;
}
