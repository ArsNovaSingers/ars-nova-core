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
		// v1.4.0 (2026-08-03): slug changed from "production" to "concerts" so
		// production entries publish at the URL pattern the site already uses
		// (/concerts/<slug>/) instead of the CPT's original default. Old
		// hand-built concert pages get 301-redirected to their production
		// entry once approved. See production-template.php for the front-end
		// template this now serves under.
		"rewrite"             => array( "slug" => "concerts", "with_front" => true ),
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
