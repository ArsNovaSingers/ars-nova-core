<?php
/**
 * Project Template — the shared layout for every concert / residency page.
 *
 * WHY THIS EXISTS
 * ---------------
 * The seven project pages (Rivers & Streams, Darkness & Light, Sound & Motion,
 * Cross Currents, Springs & Gears, Blake Morgan Residency, House Concert) were
 * built as seven independent stacks of core blocks. Six of them carried a
 * byte-identical hero recipe and a byte-identical "Support the Music" band, so
 * every change to either meant seven hand edits — and the seventh page (House
 * Concert, 6488) had drifted into a different hero structure entirely.
 *
 * This template moves the two boilerplate bands OUT of page content and into
 * code, so changing them here changes them on every project page, existing and
 * future, with no per-page edit. The pages keep their unique middle (the
 * program essay, the artist bio, the ticket rows) in the block editor where
 * Kim and Tom can edit them.
 *
 * Companion to includes/page-templates.php (the Season Template). Same
 * registration mechanism; read that file's header first, it explains why we
 * render through Kadence's own page.php rather than shipping theme markup.
 *
 * WHAT IT DOES THAT THE SEASON TEMPLATE DOES NOT
 * ----------------------------------------------
 *   1. Forces the same four Kadence per-page settings (shared helper below).
 *   2. Prepends a hero and appends a Support band via `the_content`.
 *   3. Enqueues one scoped stylesheet with REAL per-breakpoint control at this
 *      site's actual edges (719px / 1024px), which is the thing the Kadence
 *      Blocks conversion was going to buy — obtained here without Kadence Pro.
 *
 * PER-PAGE OVERRIDES
 * ------------------
 * The hero is not blindly derived from the page title, because House Concert's
 * H1 is "Ten strings, one room" while its page title is "House Concert with
 * Nicolò Spera". Every hero field therefore has a post-meta override with a
 * sensible default:
 *
 *   _ans_hero_eyebrow    default: ARS_NOVA_PROJECT_EYEBROW (one constant, all pages)
 *   _ans_hero_title      default: the page title
 *   _ans_hero_subhead    default: none (this is the date / guest-artist line)
 *   _ans_hero_cta_label  default: "Get Tickets"
 *   _ans_hero_cta_href   default: "#tickets"
 *
 * All five are registered in REST, so they are settable from the WordPress MCP
 * connector and appear in the block editor's page sidebar via custom fields.
 *
 * @package ars-nova-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template slug. Stored in the page's _wp_page_template meta.
 * As with the Season Template, no file of this name needs to exist.
 */
define( 'ARS_NOVA_PROJECT_TEMPLATE', 'ans-project-template.php' );

/**
 * THE SEASON LINE. Changing this string changes it on every project page.
 * This is the single clearest demonstration of what the template is for.
 */
define( 'ARS_NOVA_PROJECT_EYEBROW', 'Ars Nova Singers · 2026–27 Season · Confluence' );

/** The Support band's copy and destination — likewise, one place. */
define( 'ARS_NOVA_PROJECT_SUPPORT_HEADING', 'Support the Music' );
define( 'ARS_NOVA_PROJECT_SUPPORT_BODY', 'As a nonprofit chorus, we rely on the generosity of our community to bring this music to life. Your gift keeps Ars Nova singing.' );
define( 'ARS_NOVA_PROJECT_SUPPORT_CTA', 'Donate' );
define( 'ARS_NOVA_PROJECT_SUPPORT_HREF', '/support/donate/' );

/**
 * Is the given page on this template?
 *
 * @param int $post_id Page ID.
 * @return bool
 */
function arsnova_is_project_template( $post_id ) {
	return ARS_NOVA_PROJECT_TEMPLATE === get_page_template_slug( $post_id );
}

/**
 * Add "Project Template" to the page Template dropdown.
 */
add_filter(
	'theme_page_templates',
	function ( $templates ) {
		$templates[ ARS_NOVA_PROJECT_TEMPLATE ] = __( 'Project Template', 'ars-nova-core' );
		return $templates;
	}
);

/**
 * Render pages on this template through the active theme's page.php.
 * Same fall-through behaviour as the Season Template.
 */
add_filter(
	'template_include',
	function ( $template ) {
		if ( ! is_singular( 'page' ) ) {
			return $template;
		}
		if ( ! arsnova_is_project_template( get_queried_object_id() ) ) {
			return $template;
		}
		$page_template = locate_template( 'page.php' );

		return $page_template ? $page_template : $template;
	}
);

/**
 * Force the Kadence per-page settings, exactly as the Season Template does.
 *
 * Recursion note carried over from page-templates.php and it still applies:
 * get_page_template_slug() reads _wp_page_template, which re-enters this
 * filter. Safe ONLY because the isset() check runs FIRST and _wp_page_template
 * is not one of our keys. Do not reorder these checks.
 */
add_filter(
	'get_post_metadata',
	function ( $value, $object_id, $meta_key, $single ) {
		$forced = arsnova_season_template_meta(); // Same four settings; defined in page-templates.php.

		if ( ! isset( $forced[ $meta_key ] ) ) {
			return $value;
		}
		if ( 'page' !== get_post_type( $object_id ) ) {
			return $value;
		}
		if ( ! arsnova_is_project_template( $object_id ) ) {
			return $value;
		}

		return $single ? $forced[ $meta_key ] : array( $forced[ $meta_key ] );
	},
	10,
	4
);

/**
 * Body class, so everything below can be scoped without page IDs.
 */
add_filter(
	'body_class',
	function ( $classes ) {
		if ( is_singular( 'page' ) && arsnova_is_project_template( get_queried_object_id() ) ) {
			$classes[] = 'ans-project-template';
		}
		return $classes;
	}
);

/**
 * Register the five hero override fields in REST.
 *
 * `show_in_rest` is what makes these settable from the MCP connector without a
 * connector rebuild, and readable by the block editor.
 */
add_action(
	'init',
	function () {
		$fields = array(
			'_ans_hero_eyebrow',
			'_ans_hero_title',
			'_ans_hero_subhead',
			'_ans_hero_cta_label',
			'_ans_hero_cta_href',
		);

		foreach ( $fields as $field ) {
			register_post_meta(
				'page',
				$field,
				array(
					'type'              => 'string',
					'single'            => true,
					'default'           => '',
					'show_in_rest'      => true,
					'sanitize_callback' => 'wp_kses_post',
					'auth_callback'     => function () {
						return current_user_can( 'edit_pages' );
					},
				)
			);
		}
	}
);

/**
 * Resolve one hero field: the page's override, else the supplied default.
 *
 * @param int    $post_id Page ID.
 * @param string $key     Meta key.
 * @param string $default Fallback when the override is empty.
 * @return string
 */
function arsnova_project_field( $post_id, $key, $default = '' ) {
	$value = get_post_meta( $post_id, $key, true );

	return ( '' !== trim( (string) $value ) ) ? $value : $default;
}

/**
 * Build the hero markup.
 *
 * The background is applied as an inline background-image on the media layer
 * rather than an <img>, because the parallax effect needs it to be a background.
 * Everything else — sizing, overlay opacity, type scale, responsive behaviour —
 * lives in the stylesheet, so it is tunable in one place.
 *
 * @param WP_Post $post The page.
 * @return string
 */
function arsnova_project_hero( $post ) {
	$post_id = $post->ID;

	$eyebrow = arsnova_project_field( $post_id, '_ans_hero_eyebrow', ARS_NOVA_PROJECT_EYEBROW );
	$title   = arsnova_project_field( $post_id, '_ans_hero_title', get_the_title( $post_id ) );
	$subhead = arsnova_project_field( $post_id, '_ans_hero_subhead' );
	$cta     = arsnova_project_field( $post_id, '_ans_hero_cta_label', 'Get Tickets' );
	$href    = arsnova_project_field( $post_id, '_ans_hero_cta_href', '#tickets' );

	$image_id  = get_post_thumbnail_id( $post_id );
	$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '';

	$media_style = $image_url
		? ' style="background-image:url(' . esc_url( $image_url ) . ')"'
		: '';

	$has_image_class = $image_url ? ' ansp-hero--has-image' : ' ansp-hero--no-image';

	ob_start();
	?>
	<section class="ansp-hero<?php echo esc_attr( $has_image_class ); ?>">
		<div class="ansp-hero__media"<?php echo $media_style; // phpcs:ignore WordPress.Security.EscapeOutput -- built from esc_url above. ?>></div>
		<div class="ansp-hero__overlay" aria-hidden="true"></div>
		<div class="ansp-hero__inner">
			<?php if ( $eyebrow ) : ?>
				<p class="ansp-hero__eyebrow"><?php echo wp_kses_post( $eyebrow ); ?></p>
			<?php endif; ?>

			<h1 class="ansp-hero__title"><?php echo wp_kses_post( $title ); ?></h1>

			<?php if ( $subhead ) : ?>
				<p class="ansp-hero__subhead"><?php echo wp_kses_post( $subhead ); ?></p>
			<?php endif; ?>

			<?php if ( $cta && $href ) : ?>
				<p class="ansp-hero__actions">
					<a class="ansp-hero__cta" href="<?php echo esc_url( $href ); ?>"><?php echo esc_html( $cta ); ?></a>
				</p>
			<?php endif; ?>
		</div>
	</section>
	<?php
	return (string) ob_get_clean();
}

/**
 * Build the Support band markup.
 *
 * The band now mirrors the hero's treatment rather than rendering as a solid
 * fill: the page's FEATURED IMAGE is applied as an inline background-image on
 * a media layer (same reason as the hero — parallax needs a background, not an
 * <img>), with a cream overlay above it. Pages with no featured image fall
 * back to the flat cream background set in the stylesheet.
 *
 * @param WP_Post $post The page.
 * @return string
 */
function arsnova_project_support( $post ) {
	$post_id = $post->ID;

	$image_id  = get_post_thumbnail_id( $post_id );
	$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '';

	$media_style = $image_url
		? ' style="background-image:url(' . esc_url( $image_url ) . ')"'
		: '';

	$has_image_class = $image_url ? ' ansp-support--has-image' : ' ansp-support--no-image';

	ob_start();
	?>
	<section class="ansp-support<?php echo esc_attr( $has_image_class ); ?>">
		<div class="ansp-support__media"<?php echo $media_style; // phpcs:ignore WordPress.Security.EscapeOutput -- built from esc_url above. ?>></div>
		<div class="ansp-support__overlay" aria-hidden="true"></div>
		<div class="ansp-support__inner">
			<h2 class="ansp-support__title"><?php echo esc_html( ARS_NOVA_PROJECT_SUPPORT_HEADING ); ?></h2>
			<p class="ansp-support__body"><?php echo esc_html( ARS_NOVA_PROJECT_SUPPORT_BODY ); ?></p>
			<p class="ansp-support__actions">
				<a class="ansp-support__cta" href="<?php echo esc_url( ARS_NOVA_PROJECT_SUPPORT_HREF ); ?>"><?php echo esc_html( ARS_NOVA_PROJECT_SUPPORT_CTA ); ?></a>
			</p>
		</div>
	</section>
	<?php
	return (string) ob_get_clean();
}

/**
 * Inject the hero before the page's blocks and the Support band after them.
 *
 * Deliberately NOT wrapped in a container div: every band on these pages is
 * `alignfull`, and inserting an element between .entry-content and the blocks
 * risks breaking the theme's full-bleed selectors. Hero and Support are
 * siblings of the blocks, not parents of them.
 *
 * The three guards matter: `the_content` also fires for excerpts, feeds, SEO
 * description generation and any secondary loop. Without in_the_loop() and
 * is_main_query() the hero would be duplicated into places it has no business
 * appearing — including Yoast's meta description.
 *
 * @param string $content Page content.
 * @return string
 */
add_filter(
	'the_content',
	function ( $content ) {
		if ( ! is_singular( 'page' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post = get_post();

		if ( ! $post || ! arsnova_is_project_template( $post->ID ) ) {
			return $content;
		}

		return arsnova_project_hero( $post )
			. $content
			. arsnova_project_support( $post );
	},
	9 // Before wpautop (10), so our markup is not mangled by paragraph insertion.
);

/**
 * Enqueue the template stylesheet, only on pages that use the template.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! is_singular( 'page' ) || ! arsnova_is_project_template( get_queried_object_id() ) ) {
			return;
		}

		wp_enqueue_style(
			'ans-project-template',
			plugins_url( 'assets/css/project-template.css', ARS_NOVA_CORE_DIR . 'ars-nova-core.php' ),
			array(),
			ARS_NOVA_CORE_VERSION
		);
	}
);
