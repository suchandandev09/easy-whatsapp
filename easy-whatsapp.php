<?php

/**
 * Plugin Name: Easy WhatsApp
 * Description: Adds a WhatsApp number meta field to selected post types.
 * Version: 1.2.0
 * Author: Suchandan Haldar
 * Text Domain: easy-whatsapp
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (! defined('ABSPATH')) {
	exit;
}

define('EASY_WHATSAPP_VERSION', '1.2.0');
define('EASY_WHATSAPP_PLUGIN_FILE', __FILE__);
define('EASY_WHATSAPP_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('EASY_WHATSAPP_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once EASY_WHATSAPP_PLUGIN_PATH . 'includes/class-easy-whatsapp-plugin.php';
require_once EASY_WHATSAPP_PLUGIN_PATH . 'includes/functions.php';

register_activation_hook(EASY_WHATSAPP_PLUGIN_FILE, array('Easy_WhatsApp_Plugin', 'activate'));

/**
 * Returns the plugin instance.
 *
 * @return Easy_WhatsApp_Plugin
 */
function easy_whatsapp()
{
	return Easy_WhatsApp_Plugin::instance();
}

easy_whatsapp();
