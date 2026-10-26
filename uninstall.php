<?php
// Plugin delete karne par apna data saaf karo (posts/images mehfooz rehte hain).
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
foreach ( array( 'aiap_settings', 'aiap_queue', 'aiap_history', 'aiap_queue_sched', 'aiap_notify_last' ) as $opt ) {
	delete_option( $opt );
}
wp_clear_scheduled_hook( 'aiap_queue_run' );
