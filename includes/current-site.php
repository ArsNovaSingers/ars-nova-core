<?php
/**
 * Current-site typography reader — what the REAL live site is actually using.
 *
 * The Font Vote page's "Current Site" baseline card used to be a hardcoded
 * Georgia/Arial approximation with a hand-typed note claiming it stood in for
 * "Mirador / Hergon Grotesk". Two things were wrong with that: it was a guess
 * rather than a reading, so it could not track the live site, and the stated
 * reason for the stand-in ("those fonts aren't available through a web font
 * CDN") was beside the point — the live site self-hosts them and serves the
 * woff2 files with `access-control-allow-origin: *`, so they can be rendered
 * here directly.
 *
 * So this file reads the real thing: fetch the live site, parse the heading
 * and body `font-family` out of its CSS, walk its stylesheets for @font-face
 * blocks, resolve the font URLs absolute, and hand the whole lot to the page.
 * The baseline card then renders in genuine Mirador-Bold and Hergon Grotesk,
 * and carries the timestamp of the read so staleness is visible instead of
 * invisible.
 *
 * Source URL is an option (Settings -> Font Vote Fonts) because the live site
 * moves to Kinsta at cutover, at which point this should be repointed.
 *
 * @package ArsNovaCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARS_NOVA_CURRENT_SITE_URL_OPTION', 'arsnova_core_current_site_url' );
define( 'ARS_NOVA_CURRENT_SITE_CACHE_KEY', 'arsnova_core_current_site_type' );
define( 'ARS_NOVA_CURRENT_SITE_URL_DEFAULT', 'https://arsnovasingers.org/' );

/**
 * Resolve a possibly-relative CSS url() against the stylesheet it came from.
 *
 * @param string $base Absolute URL of the stylesheet.
 * @param string $rel  URL as written in the CSS.
 * @return string Absolute URL, or '' if unresolvable.
 */
function arsnova_core_cs_resolve_url( $base, $rel ) {
	$rel = trim( $rel );
	if ( '' === $rel ) {
		return '';
	}
	if ( preg_match( '#^https?://#i', $rel ) ) {
		return $rel;
	}
	if ( 0 === strpos( $rel, '//' ) ) {
		return 'https:' . $rel;
	}

	$parts = wp_parse_url( $base );
	if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
		return '';
	}
	$origin = $parts['scheme'] . '://' . $parts['host'];

	if ( 0 === strpos( $rel, '/' ) ) {
		return $origin . $rel;
	}

	// Relative to the stylesheet's own directory, collapsing any ../ hops.
	$dir     = isset( $parts['path'] ) ? preg_replace( '#/[^/]*$#', '', $parts['path'] ) : '';
	$segs    = array_filter( explode( '/', $dir . '/' . $rel ), 'strlen' );
	$stack   = array();
	foreach ( $segs as $seg ) {
		if ( '.' === $seg ) {
			continue;
		}
		if ( '..' === $seg ) {
			array_pop( $stack );
			continue;
		}
		$stack[] = $seg;
	}

	return $origin . '/' . implode( '/', $stack );
}

/**
 * Pull the first family name out of a CSS font-family value, unquoted.
 *
 * @param string $value Raw declaration value.
 * @return string
 */
function arsnova_core_cs_first_family( $value ) {
	$first = explode( ',', (string) $value );
	$first = trim( $first[0] );
	$first = trim( $first, "'\"" );
	return trim( $first );
}

/**
 * Walk every rule in a blob of CSS looking for the heading and body
 * font-family declarations. Later rules win, matching how the cascade would
 * actually resolve for equal specificity.
 *
 * @param string $css      CSS text.
 * @param array  $found    Running result, keys 'heading' and 'body'.
 * @return array Updated $found.
 */
function arsnova_core_cs_scan_css( $css, $found ) {
	if ( ! preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER ) ) {
		return $found;
	}

	foreach ( $rules as $rule ) {
		if ( false === stripos( $rule[2], 'font-family' ) ) {
			continue;
		}
		if ( ! preg_match( '/font-family\s*:\s*([^;}]+)/i', $rule[2], $m ) ) {
			continue;
		}
		$family = arsnova_core_cs_first_family( $m[1] );
		if ( '' === $family || 'inherit' === strtolower( $family ) ) {
			continue;
		}

		// Selector list, split and normalised, so we match a real `h1` or
		// `body` selector rather than any string that happens to contain one.
		$selectors = array_map( 'trim', explode( ',', strtolower( $rule[1] ) ) );
		foreach ( $selectors as $sel ) {
			if ( 'h1' === $sel || 'h2' === $sel ) {
				$found['heading'] = $family;
			}
			if ( 'body' === $sel ) {
				$found['body'] = $family;
			}
		}
	}

	return $found;
}

/**
 * Parse every @font-face block in a stylesheet into descriptors the browser
 * can re-declare. woff2 is preferred; woff is accepted as a fallback.
 *
 * @param string $css      CSS text.
 * @param string $base_url Absolute URL of that stylesheet (for url() resolution).
 * @return array<int,array<string,mixed>>
 */
function arsnova_core_cs_parse_font_faces( $css, $base_url ) {
	$faces = array();

	if ( ! preg_match_all( '/@font-face\s*\{([^}]*)\}/i', $css, $blocks, PREG_SET_ORDER ) ) {
		return $faces;
	}

	foreach ( $blocks as $block ) {
		$body = $block[1];

		if ( ! preg_match( '/font-family\s*:\s*([^;}]+)/i', $body, $fm ) ) {
			continue;
		}
		$family = arsnova_core_cs_first_family( $fm[1] );
		if ( '' === $family ) {
			continue;
		}

		$weight = 400;
		if ( preg_match( '/font-weight\s*:\s*([^;}]+)/i', $body, $wm ) ) {
			$raw = strtolower( trim( $wm[1] ) );
			if ( 'bold' === $raw ) {
				$weight = 700;
			} elseif ( preg_match( '/^[1-9]00$/', $raw ) ) {
				$weight = (int) $raw;
			}
		}

		$style = 'normal';
		if ( preg_match( '/font-style\s*:\s*italic/i', $body ) ) {
			$style = 'italic';
		}

		// Prefer woff2, fall back to woff; ignore eot/svg/ttf entirely.
		$url = '';
		if ( preg_match_all( '/url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/i', $body, $urls ) ) {
			foreach ( $urls[1] as $candidate ) {
				if ( preg_match( '/\.woff2(\?|$)/i', $candidate ) ) {
					$url = $candidate;
					break;
				}
				if ( '' === $url && preg_match( '/\.woff(\?|$)/i', $candidate ) ) {
					$url = $candidate;
				}
			}
		}
		$url = arsnova_core_cs_resolve_url( $base_url, $url );
		if ( '' === $url ) {
			continue;
		}

		$faces[] = array(
			'family' => $family,
			'weight' => $weight,
			'style'  => $style,
			'url'    => $url,
		);
	}

	return $faces;
}

/**
 * Read the live site's real typography.
 *
 * Returns a structure the page can apply directly. On any failure the 'ok'
 * flag is false and the caller shows a labelled stand-in rather than pretending
 * the read succeeded.
 *
 * @param bool $force Skip the cache and re-read now.
 * @return array<string,mixed>
 */
function arsnova_core_current_site_typography( $force = false ) {
	if ( ! $force ) {
		$cached = get_transient( ARS_NOVA_CURRENT_SITE_CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}
	}

	$url = trim( (string) get_option( ARS_NOVA_CURRENT_SITE_URL_OPTION, ARS_NOVA_CURRENT_SITE_URL_DEFAULT ) );
	if ( '' === $url ) {
		$url = ARS_NOVA_CURRENT_SITE_URL_DEFAULT;
	}

	$result = array(
		'ok'         => false,
		'source_url' => $url,
		'fetched_at' => time(),
		'heading'    => '',
		'body'       => '',
		'faces'      => array(),
		'error'      => '',
	);

	$res = wp_remote_get( $url, array( 'timeout' => 20 ) );
	if ( is_wp_error( $res ) ) {
		$result['error'] = $res->get_error_message();
		set_transient( ARS_NOVA_CURRENT_SITE_CACHE_KEY, $result, 10 * MINUTE_IN_SECONDS );
		return $result;
	}
	if ( 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		$result['error'] = 'HTTP ' . wp_remote_retrieve_response_code( $res );
		set_transient( ARS_NOVA_CURRENT_SITE_CACHE_KEY, $result, 10 * MINUTE_IN_SECONDS );
		return $result;
	}

	$html = (string) wp_remote_retrieve_body( $res );
	$found = array( 'heading' => '', 'body' => '' );

	// Inline <style> blocks first — on the StageHand site this is where the
	// live body/heading declarations actually are.
	if ( preg_match_all( '#<style[^>]*>(.*?)</style>#is', $html, $styles ) ) {
		foreach ( $styles[1] as $css ) {
			$found = arsnova_core_cs_scan_css( $css, $found );
		}
	}

	// Then same-host stylesheets, for both any font-family rules they carry
	// and — the important part — their @font-face definitions.
	$host  = (string) wp_parse_url( $url, PHP_URL_HOST );
	$sheets = array();
	if ( preg_match_all( '#<link[^>]+rel=[\'"]stylesheet[\'"][^>]*>#i', $html, $links ) ) {
		foreach ( $links[0] as $tag ) {
			if ( ! preg_match( '#href=[\'"]([^\'"]+)[\'"]#i', $tag, $hm ) ) {
				continue;
			}
			$sheet = arsnova_core_cs_resolve_url( $url, html_entity_decode( $hm[1] ) );
			if ( '' === $sheet || wp_parse_url( $sheet, PHP_URL_HOST ) !== $host ) {
				continue;
			}
			$sheets[ $sheet ] = true;
		}
	}
	$sheets = array_slice( array_keys( $sheets ), 0, 14 );

	$faces = array();
	foreach ( $sheets as $sheet ) {
		$sres = wp_remote_get( $sheet, array( 'timeout' => 15 ) );
		if ( is_wp_error( $sres ) || 200 !== (int) wp_remote_retrieve_response_code( $sres ) ) {
			continue;
		}
		$css = (string) wp_remote_retrieve_body( $sres );
		if ( strlen( $css ) > 600000 ) {
			continue;
		}
		$found = arsnova_core_cs_scan_css( $css, $found );
		$faces = array_merge( $faces, arsnova_core_cs_parse_font_faces( $css, $sheet ) );
	}

	$result['heading'] = $found['heading'];
	$result['body']    = $found['body'];

	// Keep only the faces belonging to the two families actually in use, so
	// the page loads three font files rather than the theme's whole library.
	$wanted = array_filter( array( strtolower( $found['heading'] ), strtolower( $found['body'] ) ) );
	$keep   = array();
	foreach ( $faces as $face ) {
		if ( in_array( strtolower( $face['family'] ), $wanted, true ) ) {
			$keep[ $face['family'] . '|' . $face['weight'] . '|' . $face['style'] ] = $face;
		}
	}
	$result['faces'] = array_values( $keep );
	$result['ok']    = ( '' !== $found['heading'] || '' !== $found['body'] );

	if ( ! $result['ok'] ) {
		$result['error'] = 'No heading or body font-family found in the live site CSS.';
	}

	set_transient(
		ARS_NOVA_CURRENT_SITE_CACHE_KEY,
		$result,
		$result['ok'] ? 12 * HOUR_IN_SECONDS : 10 * MINUTE_IN_SECONDS
	);

	return $result;
}

/**
 * Register the source-URL setting. Shares the 'arsnova_core_fonts' option
 * group with the Google Fonts key so both save from one form.
 *
 * @return void
 */
function arsnova_core_current_site_register_settings() {
	register_setting(
		'arsnova_core_fonts',
		ARS_NOVA_CURRENT_SITE_URL_OPTION,
		array(
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default'           => ARS_NOVA_CURRENT_SITE_URL_DEFAULT,
			'show_in_rest'      => false,
		)
	);
}
add_action( 'admin_init', 'arsnova_core_current_site_register_settings' );

/**
 * Re-read on save, so changing the URL takes effect immediately.
 *
 * @return void
 */
function arsnova_core_current_site_flush_cache() {
	delete_transient( ARS_NOVA_CURRENT_SITE_CACHE_KEY );
}
add_action( 'add_option_' . ARS_NOVA_CURRENT_SITE_URL_OPTION, 'arsnova_core_current_site_flush_cache' );
add_action( 'update_option_' . ARS_NOVA_CURRENT_SITE_URL_OPTION, 'arsnova_core_current_site_flush_cache' );
