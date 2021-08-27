<?php
/**
 * Import.
 *
 * @since 1.0.0
 */


// Grab a snapshot of post IDs, just in case it changes during the export.
// $post_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} $join WHERE $where" );

add_action(
	'wp_import_insert_post',
	function( $post_id, $original_post_ID, $postdata, $post ) {
		$_post = (object) $post;
		if ( 'trip-packages' === $_post->post_type ) {

			$postmeta    = $_post->postmeta;
			$postmeta    = array_column( $_post->postmeta, 'value', 'key' );
			$trip_id     = (int) $postmeta['trip_ID'];
			$package_ids = get_metadata_raw( 'post', $trip_id, 'packages_ids', true );
			error_log( print_r( $package_ids, true ) );

			$package_ids = preg_replace( ";i:{$post->post_id};", ";i:{$post_id};", $package_ids );

			error_log( print_r( $package_ids, true ) );

		}
		die;
	},
	10,
	4
);
