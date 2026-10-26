<?php
/**
 * Plugin Name: Pennova AI – SEO Autoblogger
 * Description: Write SEO-optimised articles with any AI provider, add images and YouTube videos automatically, publish on autopilot, and get instant Telegram / WhatsApp alerts.
 * Version: 3.2.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Pennova contributors
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pennova-ai
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Purana "AI Auto Poster" plugin active ho to double-load (fatal) se bachao.
if ( defined( 'AIAP_VER' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p><strong>Pennova AI:</strong> purana "AI Auto Poster" plugin abhi active hai. Pehle usay Deactivate (aur Delete) karo, phir Pennova AI activate karo. Aapki settings safe rahengi.</p></div>';
		}
	);
	return;
}

define( 'AIAP_VER', '3.2.0' );
define( 'AIAP_NAME', 'Pennova AI' );
define( 'AIAP_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIAP_URL', plugin_dir_url( __FILE__ ) );

foreach ( array( 'settings', 'providers', 'images', 'youtube', 'you', 'generator', 'frontend', 'queue', 'notify', 'admin' ) as $aiap_file ) {
	require_once AIAP_DIR . 'includes/class-' . $aiap_file . '.php';
}

register_deactivation_hook(
	__FILE__,
	function () {
		wp_clear_scheduled_hook( 'aiap_queue_run' );
		delete_option( 'aiap_queue_sched' );
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=aiap-settings' ) ) . '">Settings</a>' );
		return $links;
	}
);

add_action(
	'plugins_loaded',
	function () {
		AIAP_Settings::init();
		AIAP_Admin::init();
		AIAP_Queue::init();
		AIAP_Frontend::init();
	}
);
