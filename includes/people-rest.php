<?php
/**
 * REST: bulk upsert of People records.
 *
 * The WordPress connector's generic tools can only create core `post` objects
 * with categories/tags — they cannot create a `person`, set `ansc_*` meta, or
 * assign `ans_people_group` terms. Rather than hand-build every record in
 * wp-admin, this exposes one idempotent route.
 *
 * Registered into the `ars-nova/v1` namespace (shared with the ticketing
 * bridge) because that is one of the namespaces `ans_rest_call` is allowed to
 * reach — a new endpoint is therefore usable the moment the plugin deploys,
 * with no connector rebuild.
 *
 * Idempotent on the person's TITLE: running the same payload twice updates
 * the same records rather than duplicating them.
 *
 * @package ArsNovaCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the route.
 *
 * @return void
 */
function arsnova_core_register_people_routes() {
	register_rest_route(
		'ars-nova/v1',
		'/people/upsert',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'arsnova_core_people_upsert',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' ) && current_user_can( 'upload_files' );
			},
			'args'                => array(
				'people'  => array(
					'required' => true,
					'type'     => 'array',
				),
				'dry_run' => array(
					'required' => false,
					'type'     => 'boolean',
					'default'  => false,
				),
			),
		)
	);

	register_rest_route(
		'ars-nova/v1',
		'/people',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'arsnova_core_people_list',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'rest_api_init', 'arsnova_core_register_people_routes' );

/**
 * GET /people — compact listing, so an audit never has to guess.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function arsnova_core_people_list( $request ) {
	$group = sanitize_key( (string) $request->get_param( 'group' ) );

	$args = array(
		'post_type'      => 'person',
		'post_status'    => 'any',
		'posts_per_page' => 300,
		'orderby'        => 'title',
		'order'          => 'ASC',
	);

	if ( $group ) {
		$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => 'ans_people_group',
				'field'    => 'slug',
				'terms'    => $group,
			),
		);
	}

	$out = array();
	foreach ( get_posts( $args ) as $person ) {
		$out[] = array(
			'id'            => $person->ID,
			'title'         => get_the_title( $person ),
			'status'        => $person->post_status,
			'groups'        => wp_get_object_terms( $person->ID, 'ans_people_group', array( 'fields' => 'slugs' ) ),
			'job_title'     => get_post_meta( $person->ID, 'ansc_job_title', true ),
			'board_office'  => get_post_meta( $person->ID, 'ansc_board_office', true ),
			'pronouns'      => get_post_meta( $person->ID, 'ansc_pronouns', true ),
			'occupation'    => get_post_meta( $person->ID, 'ansc_occupation', true ),
			'display_order' => get_post_meta( $person->ID, 'ansc_display_order', true ),
			'thumbnail_id'  => (int) get_post_thumbnail_id( $person->ID ),
		);
	}

	return new WP_REST_Response(
		array(
			'count'  => count( $out ),
			'people' => $out,
		),
		200
	);
}

/**
 * Find an existing `person` by exact title.
 *
 * @param string $title Title to match.
 * @return int Post ID, or 0.
 */
function arsnova_core_find_person_by_title( $title ) {
	$found = get_posts(
		array(
			'post_type'              => 'person',
			'post_status'            => 'any',
			'posts_per_page'         => 1,
			'title'                  => $title,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
	return ! empty( $found ) ? (int) $found[0] : 0;
}

/**
 * Sideload an image URL into the media library and return the attachment ID.
 *
 * Used to convert the Leadership page's raw <img src> headshots — which are
 * files in uploads/ but were never registered as attachments — into real
 * media-library items, so they gain responsive sizes and managed alt text.
 *
 * @param string $url      Image URL.
 * @param int    $post_id  Post to attach to.
 * @param string $alt_text Alt text to store.
 * @return int|WP_Error Attachment ID or error.
 */
function arsnova_core_sideload_headshot( $url, $post_id, $alt_text ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$attachment_id = media_sideload_image( $url, $post_id, $alt_text, 'id' );
	if ( is_wp_error( $attachment_id ) ) {
		return $attachment_id;
	}

	update_post_meta( (int) $attachment_id, '_wp_attachment_image_alt', $alt_text );
	return (int) $attachment_id;
}

/**
 * POST /people/upsert — create or update People records.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function arsnova_core_people_upsert( $request ) {
	$people  = (array) $request->get_param( 'people' );
	$dry_run = (bool) $request->get_param( 'dry_run' );
	$results = array();

	$meta_map = array(
		'pronouns'      => 'ansc_pronouns',
		'occupation'    => 'ansc_occupation',
		'job_title'     => 'ansc_job_title',
		'board_office'  => 'ansc_board_office',
		'term'          => 'ansc_term',
		'display_order' => 'ansc_display_order',
	);

	foreach ( $people as $row ) {
		$row   = (array) $row;
		$title = isset( $row['title'] ) ? sanitize_text_field( (string) $row['title'] ) : '';

		if ( '' === $title ) {
			$results[] = array(
				'title'  => '',
				'action' => 'skipped',
				'error'  => 'missing title',
			);
			continue;
		}

		/*
		 * An explicit `id` wins over title matching.
		 *
		 * The `person` CPT carries 144 legacy StageHand records whose titles
		 * ("00 Staff - Tom Morgan", "Boyd, James") are not the name we want to
		 * display. Matching on title alone would create a duplicate alongside
		 * the legacy record instead of updating it — and the legacy record is
		 * the one that already has the headshot attached.
		 */
		$existing = 0;
		if ( ! empty( $row['id'] ) ) {
			$candidate = (int) $row['id'];
			if ( $candidate > 0 && 'person' === get_post_type( $candidate ) ) {
				$existing = $candidate;
			}
		}
		if ( ! $existing ) {
			$existing = arsnova_core_find_person_by_title( $title );
		}

		if ( $dry_run ) {
			$results[] = array(
				'title'  => $title,
				'action' => $existing ? 'would update' : 'would create',
				'id'     => $existing,
			);
			continue;
		}

		$postarr = array(
			'post_type'   => 'person',
			'post_title'  => $title,
			'post_status' => isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : 'publish',
		);
		if ( isset( $row['bio'] ) ) {
			$postarr['post_content'] = wp_kses_post( (string) $row['bio'] );
		}

		if ( $existing ) {
			$postarr['ID'] = $existing;
			$person_id     = wp_update_post( $postarr, true );
			$action        = 'updated';
		} else {
			$person_id = wp_insert_post( $postarr, true );
			$action    = 'created';
		}

		if ( is_wp_error( $person_id ) ) {
			$results[] = array(
				'title'  => $title,
				'action' => 'failed',
				'error'  => $person_id->get_error_message(),
			);
			continue;
		}
		$person_id = (int) $person_id;

		// Meta.
		foreach ( $meta_map as $key => $meta_key ) {
			if ( ! array_key_exists( $key, $row ) ) {
				continue;
			}
			$value = ( 'display_order' === $key )
				? absint( $row[ $key ] )
				: sanitize_text_field( (string) $row[ $key ] );

			if ( '' === $value || ( 'display_order' === $key && 0 === $value ) ) {
				delete_post_meta( $person_id, $meta_key );
			} else {
				update_post_meta( $person_id, $meta_key, $value );
			}
		}

		// Groups.
		if ( ! empty( $row['group'] ) ) {
			$slugs = array_map( 'sanitize_key', (array) $row['group'] );
			wp_set_object_terms( $person_id, $slugs, 'ans_people_group', false );
		}

		// Headshot — only sideload when there is not already a featured image,
		// so re-running never duplicates attachments.
		$image_note = 'unchanged';
		if ( ! empty( $row['image_url'] ) && ! has_post_thumbnail( $person_id ) ) {
			$alt        = sprintf( 'Headshot of %s', $title );
			$attachment = arsnova_core_sideload_headshot( esc_url_raw( (string) $row['image_url'] ), $person_id, $alt );

			if ( is_wp_error( $attachment ) ) {
				$image_note = 'sideload failed: ' . $attachment->get_error_message();
			} else {
				set_post_thumbnail( $person_id, $attachment );
				$image_note = 'attachment ' . $attachment;
			}
		} elseif ( has_post_thumbnail( $person_id ) ) {
			$image_note = 'already had one';
		}

		$results[] = array(
			'title'  => $title,
			'action' => $action,
			'id'     => $person_id,
			'image'  => $image_note,
			'groups' => wp_get_object_terms( $person_id, 'ans_people_group', array( 'fields' => 'slugs' ) ),
		);
	}

	return new WP_REST_Response(
		array(
			'dry_run' => $dry_run,
			'count'   => count( $results ),
			'results' => $results,
		),
		200
	);
}
