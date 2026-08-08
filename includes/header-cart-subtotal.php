<?php
/**
 * Header cart subtotal — WEB-24.
 *
 * WHY THIS EXISTS
 * Kadence's free header cart element can show an item COUNT — the option is
 * `header_cart_show_total`, and despite the name its customizer label is
 * "Show Item Total Indicator" and it prints `get_cart_contents_count()`. There
 * is no subtotal/price feature anywhere in the theme (no `get_cart_subtotal`
 * call in `inc/template-functions/header-functions.php`), and no filter inside
 * `Kadence\header_cart()` to hook.
 *
 * WHY IT'S ADDITIVE RATHER THAN A REPLACEMENT
 * The obvious route — `remove_action( 'kadence_header_cart', ... )` and
 * reimplement — means duplicating Kadence's three style branches (link / slide /
 * dropdown) and its exact class names. That copy would drift silently on every
 * Kadence update and take the whole cart with it. Instead this appends its own
 * element after Kadence's at priority 20, and registers it as a WooCommerce
 * fragment so it refreshes over AJAX the same way the count does. If Kadence
 * changes its markup, this keeps working. If this file is deleted, the stock
 * cart is untouched.
 *
 * @package ars-nova-core
 * @since   1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the subtotal can render. WC()->cart is null on REST/cron and early
 * in the request, so check the object, not just the class.
 *
 * @return bool
 */
function arsnova_cart_subtotal_available() {
	return class_exists( 'WooCommerce' ) && function_exists( 'WC' ) && ! is_null( WC()->cart );
}

/**
 * The subtotal markup.
 *
 * Also used verbatim as the AJAX fragment payload, so the outer element and its
 * class MUST stay identical in both paths — WooCommerce replaces the element
 * matching the fragment's selector key with this string.
 *
 * @return string
 */
function arsnova_cart_subtotal_markup() {
	if ( ! arsnova_cart_subtotal_available() ) {
		return '';
	}

	// get_cart_subtotal() returns formatted, already-escaped price HTML.
	$subtotal = WC()->cart->get_cart_subtotal();
	$is_empty = 0 === WC()->cart->get_cart_contents_count();

	ob_start();
	?>
	<a class="ans-cart-subtotal<?php echo $is_empty ? ' ans-cart-subtotal--empty' : ''; ?>"
		href="<?php echo esc_url( wc_get_cart_url() ); ?>"
		aria-label="<?php esc_attr_e( 'View cart', 'ars-nova-core' ); ?>">
		<?php echo wp_kses_post( $subtotal ); ?>
	</a>
	<?php
	return trim( ob_get_clean() );
}

/**
 * Render immediately after Kadence's own cart element. Kadence hooks its
 * renderer at the default priority 10; 20 puts us to the right of the icon.
 */
function arsnova_render_cart_subtotal() {
	// Assembled and escaped in arsnova_cart_subtotal_markup().
	echo arsnova_cart_subtotal_markup(); // phpcs:ignore WordPress.Security.EscapingOutput
}
add_action( 'kadence_header_cart', 'arsnova_render_cart_subtotal', 20 );
add_action( 'kadence_mobile_cart', 'arsnova_render_cart_subtotal', 20 );

/**
 * Keep it live without a page reload. Woo swaps the element matching the key.
 *
 * @param array $fragments Existing fragments.
 * @return array
 */
function arsnova_cart_subtotal_fragment( $fragments ) {
	if ( arsnova_cart_subtotal_available() ) {
		$fragments['a.ans-cart-subtotal'] = arsnova_cart_subtotal_markup();
	}
	return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'arsnova_cart_subtotal_fragment' );

/**
 * Minimal presentation, inline and self-contained.
 *
 * Deliberately NOT in ans-site-css: if this file is removed the feature should
 * leave nothing behind. Inherits color from the header so it tracks whatever
 * the Customizer sets rather than hardcoding navy.
 *
 * Hidden below 768px on purpose — WEB-24's original bug was the cart element
 * crowding the mobile hamburger toggle off the header. The icon and its count
 * badge still show there; only the price is suppressed.
 */
function arsnova_cart_subtotal_styles() {
	if ( ! arsnova_cart_subtotal_available() ) {
		return;
	}

	$css = '
	.ans-cart-subtotal {
		display: inline-flex;
		align-items: center;
		margin-left: 0.15em;
		font-size: 0.95em;
		line-height: 1;
		color: inherit;
		text-decoration: none;
		white-space: nowrap;
	}
	.ans-cart-subtotal:hover,
	.ans-cart-subtotal:focus {
		text-decoration: none;
		opacity: 0.7;
	}
	.ans-cart-subtotal .woocommerce-Price-currencySymbol {
		margin-right: 0.05em;
	}
	@media (max-width: 767px) {
		.ans-cart-subtotal { display: none; }
	}
	';

	wp_register_style( 'ans-cart-subtotal', false );
	wp_enqueue_style( 'ans-cart-subtotal' );
	wp_add_inline_style( 'ans-cart-subtotal', $css );
}
add_action( 'wp_enqueue_scripts', 'arsnova_cart_subtotal_styles' );
