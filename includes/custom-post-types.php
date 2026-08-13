<?php
/**
 * Custom post types: Productions, People, Callouts.
 * Lifted verbatim from StageHand (inc/custom-post-types.php). Function renamed
 * from cptui_register_my_cpts() to avoid a fatal name collision while the
 * StageHand theme is still active during the transition.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function arsnova_core_register_cpts() {

	/**
	 * Post Type: Productions.
	 */
	$labels = array(
		"name"          => __( "Productions", "ars-nova-core" ),
		"singular_name" => __( "Production", "ars-nova-core" ),
	);

	$args = array(
		"label"               => __( "Productions", "ars-nova-core" ),
		"labels"              => $labels,
		"description"         => "",
		"public"              => true,
		"publicly_queryable"  => true,
		"show_ui"             => true,
		"show_in_rest"        => true,
		"rest_base"           => "",
		"has_archive"         => true,
		"show_in_menu"        => true,
		"exclude_from_search" => false,
		"capability_type"     => "post",
		"map_meta_cap"        => true,
		"hierarchical"        => false,
		"rewrite"             => array( "slug" => "production", "with_front" => true ),
		"query_var"           => true,
		"supports"            => array( "title", "editor", "thumbnail", "revisions", "excerpt" ),
		"menu_icon"           => "dashicons-tickets-alt",
	);

	register_post_type( "production", $args );

	/**
	 * Post Type: People.
	 */
	$labels = array(
		"name"          => __( "People", "ars-nova-core" ),
		"singular_name" => __( "People", "ars-nova-core" ),
	);

	$args = array(
		"label"               => __( "People", "ars-nova-core" ),
		"labels"              => $labels,
		"description"         => "",
		"public"              => true,
		"publicly_queryable"  => true,
		"show_ui"             => true,
		"show_in_rest"        => false,
		"rest_base"           => "",
		"has_archive"         => false,
		"show_in_menu"        => true,
		"exclude_from_search" => false,
		"capability_type"     => "post",
		"map_meta_cap"        => true,
		"hierarchical"        => false,
		"rewrite"             => array( "slug" => "person", "with_front" => true ),
		"query_var"           => true,
		"supports"            => array( "title", "editor", "thumbnail", "revisions" ),
		"menu_icon"           => "dashicons-admin-users",
	);

	register_post_type( "person", $args );

	/**
	 * Post Type: Callouts (sidebar blocks).
	 */
	$labels = array(
		"name"          => __( "Callouts", "ars-nova-core" ),
		"singular_name" => __( "Callout", "ars-nova-core" ),
		"edit_item"     => __( "Edit Callout", "ars-nova-core" ),
	);

	$args = array(
		"label"               => __( "Callouts", "ars-nova-core" ),
		"labels"              => $labels,
		"description"         => "Callouts are blocks shown in the sidebar.",
		"public"              => true,
		"publicly_queryable"  => true,
		"show_ui"             => true,
		"show_in_rest"        => true,
		"rest_base"           => "",
		"has_archive"         => false,
		"show_in_menu"        => true,
		"exclude_from_search" => false,
		"capability_type"     => "post",
		"map_meta_cap"        => true,
		"hierarchical"        => false,
		"rewrite"             => array( "slug" => "callout", "with_front" => true ),
		"query_var"           => true,
		"supports"            => array( "title", "editor" ),
		"menu_icon"           => "dashicons-megaphone",
	);

	register_post_type( "callout", $args );
}

add_action( 'init', 'arsnova_core_register_cpts' );

/**
 * Give PAGES an excerpt. (1.8.6)
 *
 * WordPress core does not register excerpt support for `page`, so there is no
 * Excerpt box in the page editor. But post_excerpt is still stored, and this
 * site depends on it: [ans_season_projects] (ars-nova-ticketing-bridge)
 * renders each project's blurb from the Event Category description first and
 * the linked page's excerpt second. All eight category descriptions are empty,
 * so in practice the page excerpt is what appears on /this-season/.
 *
 * The consequence was that those blurbs had only ever been written through the
 * REST API, and no human editor could see or change them. Kim filed Site Notes
 * asking for edits to text that had nowhere to be typed — on /this-season/,
 * "Edit this text to match the info on the actual project page as there are
 * errors here", pointing at the House Concert page's excerpt.
 *
 * This surfaces the native Excerpt panel on every page. It changes no data —
 * the excerpts already exist — and is reversible by deactivating the plugin.
 */
function arsnova_core_add_page_excerpt_support() {
	add_post_type_support( 'page', 'excerpt' );
}
add_action( 'init', 'arsnova_core_add_page_excerpt_support' );
