<?php
/**
 * ACF "General Options" page.
 * Lifted from StageHand (inc/options.php). Hooked on acf/init (the recommended
 * timing) and slug kept identical ("general-options") so existing option values
 * in wp_options continue to resolve unchanged.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'acf/init', function () {
	if ( function_exists( 'acf_add_options_page' ) ) {
		acf_add_options_page( array(
			'page_title' => 'General Options',
			'menu_title' => 'Options',
			'menu_slug'  => 'general-options',
			'capability' => 'edit_posts',
			'redirect'   => false,
		) );
	}
} );
