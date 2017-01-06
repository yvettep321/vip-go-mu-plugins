<?php

function wpcom_vip_sanity_check_alloptions( $alloptions ) {
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return $alloptions;
	}

	// Warn should *always* be =< die
	$alloptions_size_warn  =  800000;
	$alloptions_size_die   = 1000000; // 1000000 ~ 1MB, too big for memcache

	static $alloptions_size = null; // Avoids repeated cache requests

	if ( ! $alloptions_size ) {
		$alloptions_size = wp_cache_get( 'alloptions_size' );
	}

	// Cache miss
	if ( false === $alloptions_size ) {
		$alloptions_size = strlen( serialize( $alloptions ) );

		wp_cache_add( 'alloptions_size', $alloptions_size, '', 60 );
	}

	$blocked = $alloptions_size > $alloptions_size_die;
	$warning = $alloptions_size > $alloptions_size_warn;

	// If it's at least over the warning threshold (will also run when blocked), notify
	if ( $warning ) {
		// NOTE - This function has built-in rate limiting so it's ok to call on every request
		wpcom_vip_sanity_check_alloptions_notify( $alloptions_size, $alloptions, $blocked );
	}

	// Will exit with a 503
	if ( $blocked ) {
		wpcom_vip_sanity_check_alloptions_die( $alloptions_size, $alloptions );
	}

	return $alloptions;
}




// @TODO need a new hook
// also see https://core.trac.wordpress.org/ticket/33958

add_filter( 'alloptions', 'wpcom_vip_sanity_check_alloptions' );




function wpcom_vip_sanity_check_alloptions_die( $size, $alloptions ) {
	// 503 Service Unavailable - prevent caching, indexing, etc and alert Varnish of the problem
	http_response_code( 503 );

	echo file_get_contents( __DIR__ . '/errors/alloptions-limit.html' );

	exit;
}

function wpcom_vip_sanity_check_alloptions_notify( $size, $alloptions, $blocked = false ) {
	global $wpdb;

	// Rate limit the alerts to avoid flooding
	if ( false !== wp_cache_get( 'alloptions', 'throttle' ) ) {
		return;
	}

	wp_cache_add( 'alloptions', 1, 'throttle', 15 * MINUTE_IN_SECONDS );

	if ( $blocked ) {
		$msg = "This site is now BLOCKED from loading until option sizes are under control.";
	} else {
		$msg = "Site will be blocked from loading if option sizes get too much bigger.";
	}

	$msg .= "\n\nDebug information can be found in the Fieldguide";

	$is_vip_env    = ( defined( 'WPCOM_IS_VIP_ENV' ) && true === WPCOM_IS_VIP_ENV );
	$environment   = ( ( defined( 'VIP_GO_ENV' ) && VIP_GO_ENV ) ? VIP_GO_ENV : 'unknown' );
	$site_id       = defined( 'FILES_CLIENT_SITE_ID' ) ? FILES_CLIENT_SITE_ID : false;

	// Send notices to VIP staff if this is happening on VIP-hosted sites
	if ( $is_vip_env && $site_id ) {
		/** silence alerts on selected sites due to known issues **/

		// Array of VIP Go site IDs to silence alerts on
		$vip_alerts_blocked = array(

		);

		if ( in_array( $site_id, $vip_alerts_blocked, true ) ) {
			return;
		}

		$subject = 'ALLOPTIONS: %s (%s VIP Go site ID: %s';

		if ( 0 !== $wpdb->blogid ) {
			$subject .= ", blog ID {$wpdb->blogid}";
		}

		$subject .= ') options is up to %s';

		$subject = sprintf(
			$subject,
			esc_url( $_SERVER['HTTP_HOST'] ),
			esc_html( $environment ),
			(int) $site_id,
			size_format( $size )
		);

		$to_irc = wpcom_vip_irc_color( 'CRITICAL', 'red', 'black' ) . $subject . ' #vipoptions';

		wpcom_vip_irc( '#nagios-vip', $to_irc, 'a8c-alloptions' );
		wpcom_vip_irc( '#wordpress.com-errors', $to_irc , 'a8c-alloptions' );

		$email_recipient = defined( 'VIP_ALLOPTIONS_NOTIFY_EMAIL' ) ? VIP_ALLOPTIONS_NOTIFY_EMAIL : false;

		/// @TODO Can we use wp_mail()?
		if ( $email_recipient ) {
			mail( $email_recipient, $email_subject, "Alloptions size when serialized: $size\n\n$msg" );
		}
	}





	// @TODO Set an admin warning for certain roles
}
