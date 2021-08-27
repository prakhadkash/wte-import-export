<?php
/**
 * Export.
 *
 * @since 1.0.0
 */

if ( ! function_exists( 'wxr_site_url' ) ) {
	function wxr_site_url() {
		if ( is_multisite() ) {
			// Multisite: the base URL.
			return network_home_url();
		} else {
			// WordPress (single site): the blog URL.
			return get_bloginfo_rss( 'url' );
		}
	}
}

function wptravelengine_ie_post_taxonomy( $post ) {
	// $post = get_post();

	$taxonomies = get_object_taxonomies( $post->post_type );
	if ( empty( $taxonomies ) ) {
		return;
	}
	$terms = wp_get_object_terms( $post->ID, $taxonomies );

	$_terms = array();
	foreach ( (array) $terms as $term ) {
		$_terms[] = array(
			'taxonomy' => $term->taxonomy,
			'slug'     => $term->slug,
			'name'     => $term->name,
		);
		echo "\t\t<category domain=\"{$term->taxonomy}\" nicename=\"{$term->slug}\">" . wxr_cdata( $term->name ) . "</category>\n";
	}
	return $_terms;
}

function wptravelengine_ie_item_data( $post ) {
	global $wpdb;
	$item = new \stdClass();

	$item->ID             = (int) $post->ID;
	$item->title          = $post->post_title;
	$item->content        = $post->post_content;
	$item->excerpt        = $post->post_excerpt;
	$item->is_sticky      = is_sticky( $post->ID ) ? 1 : 0;
	$item->post_type      = $post->post_type;
	$item->post_status    = $post->post_status;
	$item->attachment_url = wp_get_attachment_url( $post->ID );

	// Post Meta.
	$postmeta = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->postmeta WHERE post_id = %d", $post->ID ) );

	$meta = array();
	foreach ( $postmeta as $_meta ) {
		$meta[ $_meta->meta_key ] = $_meta->meta_value;
	}
	$item->meta = $meta;

	// Comment Meta.
	$_comments = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_approved <> 'spam'", $post->ID ) );
	$comments  = array_map( 'get_comment', $_comments );

	$item->terms = wptravelengine_ie_post_taxonomy( $post );

	$export_comments = array();
	foreach ( $comments as $index => $c ) {
		$export_comments[ $index ]['comment_id']           = (int) $c->comment_ID;
		$export_comments[ $index ]['comment_author']       = $c->comment_author;
		$export_comments[ $index ]['comment_author_email'] = $c->comment_author_email;
		$export_comments[ $index ]['comment_author_url']   = $c->comment_author_url;
		$export_comments[ $index ]['comment_author_IP']    = $c->comment_author_IP;
		$export_comments[ $index ]['comment_date']         = $c->comment_date;
		$export_comments[ $index ]['comment_date_gmt']     = $c->comment_date_gmt;
		$export_comments[ $index ]['comment_content']      = $c->comment_content;
		$export_comments[ $index ]['comment_approved']     = $c->comment_approved;
		$export_comments[ $index ]['comment_type']         = $c->comment_type;
		$export_comments[ $index ]['comment_parent']       = (int) $c->comment_parent;
		$export_comments[ $index ]['user_id']              = (int) $c->user_id;

		$c_meta      = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->commentmeta WHERE comment_id = %d", $c->comment_ID ) );
		$commentmeta = array();
		foreach ( $c_meta as $meta ) {
			$commentmeta[ $meta_key ] = $meta_value;
		}
	}

	return $item;
}

add_action(
	'export_wp',
	function( $args ) {
		if ( 'trip' === $args['content'] ) {
			global $wpdb;
			$defaults = array(
				'content'    => 'all',
				'author'     => false,
				'category'   => false,
				'start_date' => false,
				'end_date'   => false,
				'status'     => false,
			);
			$args     = wp_parse_args( $args, $defaults );

			$sitename = sanitize_key( get_bloginfo( 'name' ) );
			if ( ! empty( $sitename ) ) {
				$sitename .= '.';
			}
			$date        = gmdate( 'Y-m-d' );
			$wp_filename = $sitename . 'WordPress.' . $date . '.json';
			/**
			 * Filters the export filename.
			 *
			 * @since 4.4.0
			 *
			 * @param string $wp_filename The name of the file for download.
			 * @param string $sitename    The site name.
			 * @param string $date        Today's date, formatted.
			 */
			$filename = apply_filters( 'export_wp_filename', $wp_filename, $sitename, $date );

			header( 'Content-Description: File Transfer' );
			header( 'Content-Disposition: attachment; filename=' . $filename );
			header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ), true );

			if ( 'all' !== $args['content'] && post_type_exists( $args['content'] ) ) {
				$ptype = get_post_type_object( $args['content'] );
				if ( ! $ptype->can_export ) {
					$args['content'] = 'post';
				}

				$where = $wpdb->prepare( "{$wpdb->posts}.post_type = %s", $args['content'] );
			}

			if ( $args['status'] && ( 'post' === $args['content'] || 'page' === $args['content'] ) ) {
				$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_status = %s", $args['status'] );
			} else {
				$where .= " AND {$wpdb->posts}.post_status != 'auto-draft'";
			}

			$join = '';
			if ( $args['category'] && 'post' === $args['content'] ) {
				$term = term_exists( $args['category'], 'category' );
				if ( $term ) {
					$join   = "INNER JOIN {$wpdb->term_relationships} ON ({$wpdb->posts}.ID = {$wpdb->term_relationships}.object_id)";
					$where .= $wpdb->prepare( " AND {$wpdb->term_relationships}.term_taxonomy_id = %d", $term['term_taxonomy_id'] );
				}
			}

			if ( in_array( $args['content'], array( 'post', 'page', 'attachment' ), true ) ) {
				if ( $args['author'] ) {
					$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_author = %d", $args['author'] );
				}

				if ( $args['start_date'] ) {
					$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_date >= %s", gmdate( 'Y-m-d', strtotime( $args['start_date'] ) ) );
				}

				if ( $args['end_date'] ) {
					$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_date < %s", gmdate( 'Y-m-d', strtotime( '+1 month', strtotime( $args['end_date'] ) ) ) );
				}
			}

			// print_r( "SELECT ID FROM {$wpdb->posts} $join WHERE $where" );

			$post_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} $join WHERE $where" );

			$export_data = new \stdClass();

			$export_data->title             = get_bloginfo_rss( 'name' );
			$export_data->link              = get_bloginfo_rss( 'url' );
			$export_data->description       = get_bloginfo_rss( 'description' );
			$export_data->pubDate           = gmdate( 'D, d M Y H:i:s +0000' );
			$export_data->language          = get_bloginfo_rss( 'language' );
			$export_data->{'base_site_url'} = wxr_site_url();
			$export_data->{'base_blog_url'} = get_bloginfo_rss( 'url' );

			if ( $post_ids ) {
				/**
				 * @global WP_Query $wp_query WordPress Query object.
				 */
				global $wp_query;

				// Fake being in the loop.
				$wp_query->in_the_loop = true;

				// Fetch 20 posts at a time rather than loading the entire table into memory.
				$items = array();
				while ( $next_posts = array_splice( $post_ids, 0, 20 ) ) {
					$where = 'WHERE ID IN (' . implode( ',', $next_posts ) . ')';
					$posts = $wpdb->get_results( "SELECT * FROM {$wpdb->posts} $where" );

					// Begin Loop.
					foreach ( $posts as $post ) {
						setup_postdata( $post );
						$item = wptravelengine_item_data( $post );

						$items[] = $item;
					}
				}

				$export_data->items = $items;

				echo json_encode( $export_data, JSON_PRETTY_PRINT );

				// error_log( json_encode( $export_data, JSON_PRETTY_PRINT ) );
			}

			die;
		}
	}
);
