<?php
/**
 * Registered image sizes.
 * Lifted from StageHand (inc/post-thumbnails.php) so the crops templates rely on
 * (production banners, bios, gallery, logos, etc.) keep generating for new
 * uploads regardless of the active theme. Existing crops are unaffected.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'after_setup_theme', function () {
	add_image_size( 'background', 1440, 800, true );
	add_image_size( 'bio', 200, 200, true );
	add_image_size( 'latest', 420, 210, true );
	add_image_size( 'production', 1440, 548, true );
	add_image_size( 'gallery', 450, 300, true );
	add_image_size( 'column-block--photo', 720, 400, true );
	add_image_size( 'column-block--icon', 600, 99999, false );
	add_image_size( 'logo', 220, 99999, false );
	add_image_size( 'downloadable', 240, 240, false );
} );
