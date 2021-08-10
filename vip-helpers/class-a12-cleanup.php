<?php

namespace Automattic\VIP\Helpers;

// If the constant is set, don't run the cleanup
if ( defined( 'VIP_SKIP_A12_CLEANUP' ) && true === VIP_SKIP_A12_CLEANUP ) {
	add_filter( 'vip_do_a12_cleanup', '__return_false' );
}

class User_Cleanup {
	public static function parse_emails_string( $emails_string ) {
		$emails = [];

		$emails_string = trim( $emails_string );

		if ( false !== strpos( $emails_string, ',' ) ) {
			$emails_raw = explode( ',', $emails_string );
		} else {
			$emails_raw = [ $emails_string ];
		}

		foreach( $emails_raw as $email_raw ) {
			$email_raw = trim( $email_raw );

			$email_filtered = filter_var( $email_raw, FILTER_VALIDATE_EMAIL );

			if ( empty( $email_filtered ) ) {
				continue;
			}

			$emails[ $email_filtered ] = explode( '@', $email_filtered );
		}

		return $emails;
	}

	public static function fetch_user_ids_for_emails( $emails ) {
		global $wpdb;

		$email_sql_where_array = [];
		foreach ( $emails as $email => $email_username_hostname_split ) {
			list( $email_username, $email_hosname ) = $email_username_hostname_split;

			// TODO: what is username already has +?
			$email_sql_where_array[] = $wpdb->prepare(
				"( user_email = %s OR ( user_email LIKE %s AND user_email LIKE %s ) )",
				$email, // search for exact match
				$wpdb->esc_like( $user_email_username . '+' . '%' ), // search for `username+*`
				$wpdb->esc_like( '%' . '@' . $user_email_host ) // search for `*@example.com`
			);
		}

		$email_sql_where = implode( ' OR ', $email_sql_where_array );

		$sql = "SELECT ID AS role
			FROM {$wpdb->users}
			LEFT JOIN {$wpdb->usermeta} ON {$wpdb->users}.ID = {$wpdb->usermeta}.user_id
			WHERE meta_key LIKE 'wp_%%capabilities'
			AND ( {$email_sql_where} )"; // already escaped

		return $wpdb->get_col( $sql );
	}

	public static function revoke_super_admin_for_users( $user_ids ) {
		$results = [];

		foreach ( $user_ids as $user_id ) {
			if ( is_super_admin( $user_id ) ) {
				$results[ $user_id ] = revoke_super_admin( $user_id );
			}
		}

		return $results;
	}

	public static function revoke_roles_for_users( $user_ids ) {
		$results = [];

		foreach ( $user_ids as $user_id ) {
			$user = get_userdata( $user_id );

			if ( ! $user ) {
				$results[ $user_id ] = false;
				continue;
			}

			$user->remove_all_caps();

			$results[ $user_id ] = true;
		}

		return $results;
	}
}