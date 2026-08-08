<?php
/**
 * Cart total block — WEB-24.
 *
 * WHAT THIS IS
 * A `[ans_cart_total]` shortcode that renders the WooCommerce cart subtotal,
 * refreshed over AJAX. It is NOT attached to anything — you place it yourself.
 *
 * HOW TO PLACE IT IN THE HEADER
 * Appearance > Customize > Header, drag in the "HTML" element (desktop) or
 * "Mobile HTML" element (tablet/mobile), then put this in the content field:
 *
 *     [ans_cart_total]
 *
 * Kadence's `header_html()` and `mobile_html()` both call `do_shortcode()` on
 * every branch (see kadence/inc/template-functions/header-functions.php), so
 * shortcodes in those fields execute. The field sanitizes with `wp_kses_post`,
 * which leaves shortcode brackets intact.
 *
 * WHY A SHORTCODE AND NOT RAW HTML
 * The value has to be computed per request and updated when the cart changes.
 * Static HTML pasted into the block would be a frozen number.
 *
 * WHY NOT ATTACHED TO THE CART ELEMENT
 * It was, in 1.4.0–1.4.1, hooked onto `kadence_header_cart` at priority 20.
 * That forced it to sit immediately after the cart icon and nowhere else.
 * Making it placeable is both more flexible and the general pattern for adding
 * anything custom to a Kadence header: expose a shortcode from a plugin, then
 * drop Kadence's HTML element wherever you want it.
 *
 * @package ars-nova-core
 * @since   1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the total can render. WC()->cart is null on REST/cron and early in
 * the request, so check the object, not just the class.
 *
 * @return bool
 */
function arsnova_cart_subtotal_available() {
	return class_exists( 'WooCommerce' ) && function_exists( 'WC' ) && ! is_null( WC()->cart );
}

/**
 * The markup.
 *
 * Also used verbatim as the AJAX fragment payload, so the outer element and its
 * class MUST stay identical in both paths — WooCommerce replaces the element
 * matching the fragment's selector key with this string.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function arsnova_cart_subtotal_markup( $atts = array() ) {
	if ( ! arsnova_cart_subtotal_available() ) {
		return '';
	}

	$atts = shortcode_atts(
		array(
			'prefix'     => '',
			'suffix'     => '',
			'hide_empty' => 'no',
		),
		$atts,
		'ans_cart_total'
	);

	$count    = WC()->cart->get_cart_contents_count();
	$is_empty = 0 === $count;

	if ( $is_empty && 'yes' === $atts['hide_empty'] ) {
		// Still emit the element so the AJAX fragment has something to replace.
		return '<a class="ans-cart-subtotal ans-cart-subtotal--empty ans-cart-subtotal--hidden" href="' . esc_url( wc_get_cart_url() ) . '" aria-hidden="true"></a>';
	}

	// get_cart_subtotal() returns formatted, already-escaped price HTML.
	$subtotal = WC()->cart->get_cart_subtotal();

	ob_start();
	?>
	<a class="ans-cart-subtotal<?php echo $is_empty ? ' ans-cart-subtotal--empty' : ''; ?>"
		href="<?php echo esc_url( wc_get_cart_url() ); ?>"
		aria-label="<?php esc_attr_e( 'View cart', 'ars-nova-core' ); ?>">
		<?php
		if ( '' !== $atts['prefix'] ) {
			echo '<span class="ans-cart-subtotal__prefix">' . esc_html( $atts['prefix'] ) . '</span>';
		}
		echo wp_kses_post( $subtotal );
		if ( '' !== $atts['suffix'] ) {
			echo '<span class="ans-cart-subtotal__suffix">' . esc_html( $atts['suffix'] ) . '</span>';
		}
		?>
	</a>
	<?php
	return trim( ob_get_clean() );
}

/**
 * [ans_cart_total] — the placeable block.
 *
 * @param array $atts Attributes.
 * @return string
 */
function arsnova_cart_total_shortcode( $atts ) {
	return arsnova_cart_subtotal_markup( is_array( $atts ) ? $atts : array() );
}
add_shortcode( 'ans_cart_total', 'arsnova_cart_total_shortcode' );

/*
 * NOTE: as of 1.5.0 there are deliberately NO add_action() calls on
 * kadence_header_cart / kadence_mobile_cart. The total is placed by hand via
 * Kadence's HTML element. Re-adding an auto-attach here would put it back in
 * two places at once.
 */

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
 * Colour is inherited, so it tracks whatever the Customizer sets for the
 * surrounding header element rather than hardcoding navy. Size is inherited too
 * — set it on the HTML element's Font control in the Customizer.
 */
function arsnova_cart_subtotal_styles() {
	if ( ! arsnova_cart_subtotal_available() ) {
		return;
	}

	$css = '
	.ans-cart-subtotal {
		display: inline-flex;
		align-items: center;
		gap: 0.25em;
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
	.ans-cart-subtotal--hidden {
		display: none;
	}
	';

	wp_register_style( 'ans-cart-subtotal', false );
	wp_enqueue_style( 'ans-cart-subtotal' );
	wp_add_inline_style( 'ans-cart-subtotal', $css );
}
add_action( 'wp_enqueue_scripts', 'arsnova_cart_subtotal_styles' );
