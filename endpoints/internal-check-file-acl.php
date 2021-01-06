<?php

namespace Automattic\VIP\Files\Acl;

require_once __DIR__ . '/../files/acl/pre-wp-utils.php';

$file_request_uri = $_SERVER['HTTP_X_ORIGINAL_URI'] ?? null;

if ( ! $file_request_uri ) {
	trigger_error( 'VIP Files ACL failed due to empty URI', E_USER_WARNING );

	http_response_code( 500 );

	exit;
}

$file_path = parse_url( $file_request_uri, PHP_URL_PATH );

$is_valid_path = Pre_WP_Utils\validate_path( $file_path );
if ( ! $is_valid_path ) {
	http_response_code( 500 );

	exit;
}

list( $subdirectory, $sanitized_file_path ) = Pre_WP_Utils\sanitize_and_split_path( $file_path );

if ( $subdirectory ) {
	$_SERVER['REQUEST_URI'] = $subdirectory . ( $_SERVER['REQUEST_URI'] ?? '' );
}

// Unset vars we no longer need, so they don't leak into global scope.
// Should probably move the logic above into a function :)
unset( $file_request_uri, $file_path, $is_valid_path, $subdirectory );

// Bootstap WordPress
require __DIR__ . '/../../../wp-load.php';

// Load the ACL lib
require_once __DIR__ . '/../files/acl.php';

/**
 * Hook in here to adjust the visibility of a given file.
 *
 * Note: this is currently for VIP internal use only.
 *
 * @access private 
 *
 * @param string|boolean $file_visibility Return one of Automattic\VIP\Files\Acl\(FILE_IS_PUBLIC | FILE_IS_PRIVATE_AND_ALLOWED | FILE_IS_PRIVATE_AND_DENIED | FILE_NOT_FOUND) to set visibility.
 * @param string $sanitized_file_path The requested file path (note: on multisite subdirectory installs, this does not includes the subdirectory).
 */
$file_visibility = apply_filters( 'vip_files_acl_file_visibility', FILE_IS_PUBLIC, $sanitized_file_path );

send_visibility_headers( $file_visibility, $sanitized_file_path );

exit;
