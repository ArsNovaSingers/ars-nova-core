<?php
/**
 * People: leadership and board.
 *
 * Extends the EXISTING `person` CPT (registered in custom-post-types.php and
 * inherited verbatim from StageHand) with everything the public Leadership
 * page needs:
 *
 *  - the `ans_people_group` taxonomy — `leadership` / `board`
 *  - per-person meta: pronouns, occupation, job title, display order,
 *    board office, term
 *  - an admin meta box for those fields
 *  - the `[ans_people group="leadership|board"]` shortcode
 *
 * DELIBERATELY SEPARATE FROM THE SINGERS PORTAL. Decision by Jonathan,
 * 2026-08-02: singers and leadership are different profiles, with different
 * headshots and bios. Someone who is both makes two records on purpose.
 *
 * Two hard rules follow, and both matter:
 *
 *  1. The portal's `ans_group` taxonomy is its PRIVATE materials-permission
 *     engine (`ANSP_Permissions::user_can_see()`). Nothing in this file may
 *     read or write it. Public grouping is `ans_people_group` and nothing else.
 *  2. This file never touches the `singer` CPT. The portal owns that, and
 *     renders it via its own [ans_singers] shortcode.
 *
 * @package ArsNovaCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The public group terms, slug => label. Seeded idempotently on activation.
 *
 * @return array<string,string>
 */
function arsnova_core_people_groups() {
	return array(
		'leadership' => __( 'Leadership', 'ars-nova-core' ),
		'board'      => __( 'Board', 'ars-nova-core' ),
	);
}

/**
 * Meta keys handled by the People meta box, key => sanitise callback.
 *
 * @return array<string,string>
 */
function arsnova_core_people_meta_fields() {
	return array(
		'ansc_pronouns'      => 'sanitize_text_field',
		'ansc_occupation'    => 'sanitize_text_field',
		'ansc_job_title'     => 'sanitize_text_field',
		'ansc_board_office'  => 'sanitize_text_field',
		'ansc_term'          => 'sanitize_text_field',
		'ansc_display_order' => 'absint',
	);
}

/**
 * Register the `ans_people_group` taxonomy on the `person` CPT.
 *
 * Registered at init:6 — after arsnova_core_register_cpts() at the default
 * init:10 would be too late for a clean attach, so we also guard on the post
 * type existing and re-attach if it registers afterwards.
 *
 * @return void
 */
function arsnova_core_register_people_taxonomy() {
	if ( taxonomy_exists( 'ans_people_group' ) ) {
		return;
	}

	register_taxonomy(
		'ans_people_group',
		array( 'person' ),
		array(
			'label'             => __( 'People Groups', 'ars-nova-core' ),
			'labels'            => array(
				'name'          => __( 'People Groups', 'ars-nova-core' ),
				'singular_name' => __( 'People Group', 'ars-nova-core' ),
				'menu_name'     => __( 'Groups', 'ars-nova-core' ),
				'add_new_item'  => __( 'Add New Group', 'ars-nova-core' ),
				'edit_item'     => __( 'Edit Group', 'ars-nova-core' ),
				'all_items'     => __( 'All Groups', 'ars-nova-core' ),
			),
			'public'            => false,
			'show_ui'           => true,
			'show_in_menu'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => false,
			'hierarchical'      => true, // Checkbox UI rather than a tag box.
			'rewrite'           => false,
		)
	);
}
add_action( 'init', 'arsnova_core_register_people_taxonomy', 6 );

/**
 * Catch the case where `person` registers after the taxonomy.
 *
 * @param string $post_type Post type just registered.
 * @return void
 */
function arsnova_core_people_attach_taxonomy( $post_type ) {
	if ( 'person' === $post_type && taxonomy_exists( 'ans_people_group' ) ) {
		register_taxonomy_for_object_type( 'ans_people_group', 'person' );
	}
}
add_action( 'registered_post_type', 'arsnova_core_people_attach_taxonomy', 10, 1 );

/**
 * Seed the `leadership` and `board` terms. Idempotent — existing terms kept.
 *
 * @return void
 */
function arsnova_core_seed_people_groups() {
	if ( ! taxonomy_exists( 'ans_people_group' ) ) {
		return;
	}
	foreach ( arsnova_core_people_groups() as $slug => $label ) {
		if ( ! term_exists( $slug, 'ans_people_group' ) ) {
			wp_insert_term( $label, 'ans_people_group', array( 'slug' => $slug ) );
		}
	}
}

/**
 * Register the meta so it is available to get_post_meta consumers and is
 * protected from the REST/meta box free-for-all.
 *
 * @return void
 */
function arsnova_core_register_people_meta() {
	foreach ( arsnova_core_people_meta_fields() as $key => $sanitise ) {
		register_post_meta(
			'person',
			$key,
			array(
				'type'              => ( 'absint' === $sanitise ) ? 'integer' : 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => $sanitise,
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}
}
add_action( 'init', 'arsnova_core_register_people_meta', 7 );

/**
 * Add the People details meta box.
 *
 * @return void
 */
function arsnova_core_people_meta_box() {
	add_meta_box(
		'arsnova_core_person_details',
		__( 'Person Details', 'ars-nova-core' ),
		'arsnova_core_render_people_meta_box',
		'person',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'arsnova_core_people_meta_box' );

/**
 * Render the People details meta box.
 *
 * @param WP_Post $post Post being edited.
 * @return void
 */
function arsnova_core_render_people_meta_box( $post ) {
	wp_nonce_field( 'arsnova_core_save_person', 'arsnova_core_person_nonce' );

	$values = array();
	foreach ( array_keys( arsnova_core_people_meta_fields() ) as $key ) {
		$values[ $key ] = get_post_meta( $post->ID, $key, true );
	}
	?>
	<style>
		.ansc-person-grid { display: grid; grid-template-columns: 160px 1fr; gap: 12px 16px; align-items: center; max-width: 760px; }
		.ansc-person-grid label { font-weight: 600; }
		.ansc-person-grid input[type="text"] { width: 100%; }
		.ansc-person-grid .ansc-hint { grid-column: 2; margin: -6px 0 4px; color: #646970; font-size: 12px; }
		.ansc-person-sep { grid-column: 1 / -1; margin: 6px 0 0; padding-top: 10px; border-top: 1px solid #dcdcde; font-weight: 600; }
	</style>
	<div class="ansc-person-grid">

		<label for="ansc_pronouns"><?php esc_html_e( 'Pronouns', 'ars-nova-core' ); ?></label>
		<input type="text" id="ansc_pronouns" name="ansc_pronouns"
			value="<?php echo esc_attr( $values['ansc_pronouns'] ); ?>" placeholder="She/Her" />

		<label for="ansc_occupation"><?php esc_html_e( 'Occupation', 'ars-nova-core' ); ?></label>
		<input type="text" id="ansc_occupation" name="ansc_occupation"
			value="<?php echo esc_attr( $values['ansc_occupation'] ); ?>"
			placeholder="<?php esc_attr_e( 'e.g. Professor Emerita, CU Boulder', 'ars-nova-core' ); ?>" />

		<p class="ansc-person-sep"><?php esc_html_e( 'Leadership', 'ars-nova-core' ); ?></p>

		<label for="ansc_job_title"><?php esc_html_e( 'Job title', 'ars-nova-core' ); ?></label>
		<input type="text" id="ansc_job_title" name="ansc_job_title"
			value="<?php echo esc_attr( $values['ansc_job_title'] ); ?>"
			placeholder="<?php esc_attr_e( 'e.g. Executive Director', 'ars-nova-core' ); ?>" />

		<label for="ansc_display_order"><?php esc_html_e( 'Display order', 'ars-nova-core' ); ?></label>
		<input type="number" id="ansc_display_order" name="ansc_display_order" min="0" step="1"
			value="<?php echo esc_attr( $values['ansc_display_order'] ); ?>" />
		<p class="ansc-hint"><?php esc_html_e( 'Lower numbers appear first. Leave at 0 to sort alphabetically.', 'ars-nova-core' ); ?></p>

		<p class="ansc-person-sep"><?php esc_html_e( 'Board', 'ars-nova-core' ); ?></p>

		<label for="ansc_board_office"><?php esc_html_e( 'Board office', 'ars-nova-core' ); ?></label>
		<input type="text" id="ansc_board_office" name="ansc_board_office"
			value="<?php echo esc_attr( $values['ansc_board_office'] ); ?>"
			placeholder="<?php esc_attr_e( 'e.g. Board Chair', 'ars-nova-core' ); ?>" />

		<label for="ansc_term"><?php esc_html_e( 'Term', 'ars-nova-core' ); ?></label>
		<input type="text" id="ansc_term" name="ansc_term"
			value="<?php echo esc_attr( $values['ansc_term'] ); ?>"
			placeholder="<?php esc_attr_e( 'e.g. 2024–2027', 'ars-nova-core' ); ?>" />

	</div>
	<?php
}

/**
 * Save the People details meta box.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 * @return void
 */
function arsnova_core_save_person( $post_id, $post ) {
	if ( ! isset( $_POST['arsnova_core_person_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['arsnova_core_person_nonce'] ) ), 'arsnova_core_save_person' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( 'person' !== $post->post_type || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	foreach ( arsnova_core_people_meta_fields() as $key => $sanitise ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			continue;
		}
		$raw   = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised on the next line.
		$value = call_user_func( $sanitise, $raw );

		if ( '' === $value || ( 'absint' === $sanitise && 0 === $value ) ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $value );
		}
	}
}
add_action( 'save_post_person', 'arsnova_core_save_person', 10, 2 );

/**
 * `[ans_people]` — render a public group of people.
 *
 * Attributes:
 *   group   (required) `leadership` or `board`, or any ans_people_group slug.
 *   columns (optional) grid columns on desktop. Default 3.
 *   bio     (optional) `yes` to include the bio text. Default `no`.
 *
 * @param array<string,string> $atts Shortcode attributes.
 * @return string
 */
function arsnova_core_people_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'group'   => '',
			'columns' => '3',
			'bio'     => 'no',
		),
		$atts,
		'ans_people'
	);

	$group = sanitize_key( $atts['group'] );
	if ( ! $group ) {
		return '';
	}

	$columns  = max( 1, min( 4, (int) $atts['columns'] ) );
	$with_bio = ( 'yes' === strtolower( $atts['bio'] ) );

	$people = get_posts(
		array(
			'post_type'      => 'person',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'ans_people_group',
					'field'    => 'slug',
					'terms'    => $group,
				),
			),
		)
	);

	if ( empty( $people ) ) {
		return '';
	}

	/*
	 * Sort by display order, then alphabetically.
	 *
	 * Deliberately done in PHP, NOT via meta_key + orderby=meta_value_num:
	 * that adds an INNER JOIN on the meta table, so anyone without an
	 * ansc_display_order row would be dropped from the results entirely.
	 * We delete that meta when it is 0, so that would be almost everyone —
	 * the shortcode would render an empty page.
	 */
	usort(
		$people,
		function ( $a, $b ) {
			$oa = get_post_meta( $a->ID, 'ansc_display_order', true );
			$ob = get_post_meta( $b->ID, 'ansc_display_order', true );
			// Unset / 0 sorts last, so explicitly ordered people lead.
			$oa = ( '' === $oa || 0 === (int) $oa ) ? PHP_INT_MAX : (int) $oa;
			$ob = ( '' === $ob || 0 === (int) $ob ) ? PHP_INT_MAX : (int) $ob;
			if ( $oa === $ob ) {
				return strcasecmp( get_the_title( $a ), get_the_title( $b ) );
			}
			return ( $oa < $ob ) ? -1 : 1;
		}
	);

	arsnova_core_people_styles();

	ob_start();
	echo '<div class="ans-people ans-people--cols-' . esc_attr( (string) $columns ) . '">';

	foreach ( $people as $person ) {
		$job_title = get_post_meta( $person->ID, 'ansc_job_title', true );
		$office    = get_post_meta( $person->ID, 'ansc_board_office', true );
		$pronouns  = get_post_meta( $person->ID, 'ansc_pronouns', true );
		$occupation = get_post_meta( $person->ID, 'ansc_occupation', true );
		$term       = get_post_meta( $person->ID, 'ansc_term', true );

		// Board office wins for board members; job title for staff.
		$role = ( 'board' === $group && $office ) ? $office : $job_title;

		echo '<div class="ans-person">';

		if ( has_post_thumbnail( $person->ID ) ) {
			echo '<div class="ans-person__photo">';
			echo wp_kses_post(
				get_the_post_thumbnail(
					$person->ID,
					'thumbnail',
					array( 'loading' => 'lazy', 'class' => 'ans-person__img' )
				)
			);
			echo '</div>';
		}

		echo '<h3 class="ans-person__name">' . esc_html( get_the_title( $person ) );
		if ( $pronouns ) {
			echo ' <span class="ans-person__pronouns">(' . esc_html( $pronouns ) . ')</span>';
		}
		echo '</h3>';

		// Deliberately NOT a heading — the old page used <h2> for job titles,
		// which put them at the same outline level as the person's name.
		if ( $role ) {
			echo '<p class="ans-person__role">' . esc_html( $role ) . '</p>';
		}
		if ( $occupation ) {
			echo '<p class="ans-person__occupation">' . esc_html( $occupation ) . '</p>';
		}
		if ( $term ) {
			echo '<p class="ans-person__term">' . esc_html( $term ) . '</p>';
		}
		if ( $with_bio && $person->post_content ) {
			echo '<div class="ans-person__bio">' . wp_kses_post( wpautop( $person->post_content ) ) . '</div>';
		}

		echo '</div>';
	}

	echo '</div>';

	return (string) ob_get_clean();
}
add_shortcode( 'ans_people', 'arsnova_core_people_shortcode' );

/**
 * Print the People grid CSS once per request.
 *
 * Kept deliberately minimal — site-wide typography and the season palette
 * live in ans-site-css and the Kadence Customizer, not here.
 *
 * @return void
 */
function arsnova_core_people_styles() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
	<style id="ans-people-css">
		.ans-people { display: grid; gap: 2rem 1.5rem; margin: 2rem 0; }
		.ans-people--cols-1 { grid-template-columns: 1fr; }
		.ans-people--cols-2 { grid-template-columns: repeat(2, 1fr); }
		.ans-people--cols-3 { grid-template-columns: repeat(3, 1fr); }
		.ans-people--cols-4 { grid-template-columns: repeat(4, 1fr); }
		@media (max-width: 900px) { .ans-people { grid-template-columns: repeat(2, 1fr); } }
		@media (max-width: 560px) { .ans-people { grid-template-columns: 1fr; } }
		.ans-person { text-align: center; }
		.ans-person__photo { margin: 0 0 .75rem; }
		.ans-person__img { border-radius: 50%; width: 160px; height: 160px; object-fit: cover; }
		.ans-person__name { margin: 0 0 .25rem; font-size: 1.15rem; line-height: 1.3; }
		.ans-person__pronouns { font-weight: 400; font-size: .85em; opacity: .75; }
		.ans-person__role { margin: 0 0 .2rem; font-weight: 600; }
		.ans-person__occupation,
		.ans-person__term { margin: 0 0 .2rem; font-size: .92rem; opacity: .85; }
		.ans-person__bio { margin-top: .6rem; text-align: left; font-size: .95rem; }
	</style>
	<?php
}
