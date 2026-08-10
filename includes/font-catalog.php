<?php
/**
 * Google Fonts catalog — the font list behind the Font Vote picker.
 *
 * Two different Google services matter here, and only one of them needs a key:
 *
 *  - Font DELIVERY (fonts.googleapis.com/css2?family=...) is open to anybody,
 *    no key and no quota. The browser asks for families on demand, which is
 *    what lets the picker preview any typeface in its own face.
 *  - The font CATALOG (googleapis.com/webfonts/v1/webfonts) — the index of
 *    which families exist, their category, and their available weights —
 *    requires a free API key.
 *
 * So this file's only job is answering "what fonts exist". With a key stored
 * in the ARS_NOVA_FONTS_KEY_OPTION option it returns Google's full catalog
 * (~1,800 families, popularity-sorted, cached 7 days). With no key it returns
 * the bundled curated list below, so the picker still works out of the box.
 *
 * Paste a key at Settings -> Font Vote Fonts. The key is never handed to the
 * browser — only the resulting family list is.
 *
 * @package ArsNovaCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARS_NOVA_FONTS_KEY_OPTION', 'arsnova_core_google_fonts_api_key' );
define( 'ARS_NOVA_FONTS_CACHE_KEY', 'arsnova_core_google_fonts_catalog' );

/**
 * Curated fallback list, used when no API key is stored yet. Format is
 * 'Family Name|category' so it stays compact and easy to diff.
 *
 * Weights are deliberately left empty here: an empty weight list tells the
 * browser-side loader to request the family with no weight spec at all, which
 * is valid for every family on Google Fonts. Real weight lists only arrive
 * once a key is in place and the live catalog is used.
 *
 * @return array<int,array<string,mixed>>
 */
function arsnova_core_fonts_bundled_catalog() {
	$raw = array(
		// Serif.
		'Playfair Display|serif', 'Merriweather|serif', 'Lora|serif', 'PT Serif|serif',
		'Noto Serif|serif', 'Crimson Text|serif', 'Crimson Pro|serif', 'Libre Baskerville|serif',
		'EB Garamond|serif', 'Cormorant Garamond|serif', 'Cormorant|serif', 'Source Serif 4|serif',
		'Spectral|serif', 'Bitter|serif', 'Domine|serif', 'Arvo|serif',
		'Rokkitt|serif', 'Zilla Slab|serif', 'Josefin Slab|serif', 'Cardo|serif',
		'Neuton|serif', 'Vollkorn|serif', 'Alegreya|serif', 'Gentium Book Plus|serif',
		'Frank Ruhl Libre|serif', 'Newsreader|serif', 'Fraunces|serif', 'Petrona|serif',
		'Literata|serif', 'Faustina|serif', 'Bodoni Moda|serif', 'Prata|serif',
		'DM Serif Display|serif', 'DM Serif Text|serif', 'Rosarivo|serif', 'Eczar|serif',
		'Marcellus|serif', 'Cinzel|serif', 'Gilda Display|serif', 'Old Standard TT|serif',
		'Sorts Mill Goudy|serif', 'Quattrocento|serif', 'Kreon|serif', 'Enriqueta|serif',
		'Coustard|serif', 'Halant|serif', 'Martel|serif', 'Amiri|serif',
		'Playfair Display SC|serif', 'Abhaya Libre|serif', 'Judson|serif', 'Trirong|serif',
	);

	// Sans-serif.
	$raw = array_merge(
		$raw,
		array(
			'Inter|sans-serif', 'Roboto|sans-serif', 'Open Sans|sans-serif', 'Lato|sans-serif',
			'Montserrat|sans-serif', 'Poppins|sans-serif', 'Raleway|sans-serif', 'Nunito|sans-serif',
			'Nunito Sans|sans-serif', 'Work Sans|sans-serif', 'Source Sans 3|sans-serif', 'Noto Sans|sans-serif',
			'PT Sans|sans-serif', 'Mulish|sans-serif', 'Manrope|sans-serif', 'Rubik|sans-serif',
			'Karla|sans-serif', 'DM Sans|sans-serif', 'Public Sans|sans-serif', 'Figtree|sans-serif',
			'Plus Jakarta Sans|sans-serif', 'Outfit|sans-serif', 'Sora|sans-serif', 'Space Grotesk|sans-serif',
			'Barlow|sans-serif', 'Barlow Condensed|sans-serif', 'Cabin|sans-serif', 'Assistant|sans-serif',
			'Heebo|sans-serif', 'Hind|sans-serif', 'Josefin Sans|sans-serif', 'Jost|sans-serif',
			'Lexend|sans-serif', 'Lexend Deca|sans-serif', 'Libre Franklin|sans-serif', 'Archivo|sans-serif',
			'Archivo Narrow|sans-serif', 'Asap|sans-serif', 'Catamaran|sans-serif', 'Chivo|sans-serif',
			'Dosis|sans-serif', 'Exo 2|sans-serif', 'Fira Sans|sans-serif', 'IBM Plex Sans|sans-serif',
			'Inter Tight|sans-serif', 'Kanit|sans-serif', 'Maven Pro|sans-serif', 'Mukta|sans-serif',
			'Overpass|sans-serif', 'Oxygen|sans-serif', 'Prompt|sans-serif', 'Quicksand|sans-serif',
			'Red Hat Display|sans-serif', 'Red Hat Text|sans-serif', 'Signika|sans-serif', 'Titillium Web|sans-serif',
			'Ubuntu|sans-serif', 'Urbanist|sans-serif', 'Varela Round|sans-serif', 'Be Vietnam Pro|sans-serif',
			'Epilogue|sans-serif', 'Albert Sans|sans-serif', 'Commissioner|sans-serif', 'Hanken Grotesk|sans-serif',
			'Kumbh Sans|sans-serif', 'League Spartan|sans-serif', 'Oswald|sans-serif', 'Teko|sans-serif',
			'Fjalla One|sans-serif', 'Roboto Condensed|sans-serif', 'Encode Sans|sans-serif', 'Saira|sans-serif',
			'Sarabun|sans-serif', 'Schibsted Grotesk|sans-serif', 'Gabarito|sans-serif', 'Onest|sans-serif',
			'Golos Text|sans-serif', 'Familjen Grotesk|sans-serif', 'Bricolage Grotesque|sans-serif', 'Philosopher|sans-serif',
		)
	);

	// Display, handwriting and monospace.
	$raw = array_merge(
		$raw,
		array(
			'Abril Fatface|display', 'Alfa Slab One|display', 'Anton|display', 'Bebas Neue|display',
			'Righteous|display', 'Lobster|display', 'Comfortaa|display', 'Bungee|display',
			'Titan One|display', 'Passion One|display', 'Staatliches|display', 'Monoton|display',
			'Ultra|display', 'Chonburi|display', 'Yeseva One|display', 'Cinzel Decorative|display',
			'Poiret One|display', 'Rampart One|display', 'Silkscreen|display', 'Bungee Shade|display',
			'Dancing Script|handwriting', 'Great Vibes|handwriting', 'Pacifico|handwriting', 'Satisfy|handwriting',
			'Sacramento|handwriting', 'Caveat|handwriting', 'Kalam|handwriting', 'Shadows Into Light|handwriting',
			'Indie Flower|handwriting', 'Amatic SC|handwriting', 'Courgette|handwriting', 'Parisienne|handwriting',
			'Allura|handwriting', 'Tangerine|handwriting', 'Cookie|handwriting', 'Marck Script|handwriting',
			'Yellowtail|handwriting', 'Permanent Marker|handwriting', 'Architects Daughter|handwriting', 'Patrick Hand|handwriting',
			'Gloria Hallelujah|handwriting', 'Homemade Apple|handwriting', 'Cedarville Cursive|handwriting', 'La Belle Aurore|handwriting',
			'Roboto Mono|monospace', 'Source Code Pro|monospace', 'JetBrains Mono|monospace', 'IBM Plex Mono|monospace',
			'Space Mono|monospace', 'Inconsolata|monospace', 'Fira Code|monospace', 'Ubuntu Mono|monospace',
			'Courier Prime|monospace', 'DM Mono|monospace', 'Overpass Mono|monospace', 'Azeret Mono|monospace',
			'Red Hat Mono|monospace', 'Martian Mono|monospace', 'Cousine|monospace', 'PT Mono|monospace',
		)
	);

	$out = array();
	foreach ( $raw as $entry ) {
		$parts = explode( '|', $entry );
		$out[]  = array(
			'family'   => $parts[0],
			'category' => isset( $parts[1] ) ? $parts[1] : 'sans-serif',
			'weights'  => array(),
		);
	}

	return $out;
}

/**
 * The catalog the picker actually gets. Live from Google when a key exists,
 * bundled list otherwise. Cached either way so the picker never waits on an
 * outbound HTTP call.
 *
 * @return array{source:string,count:int,fonts:array}
 */
function arsnova_core_fonts_catalog() {
	$cached = get_transient( ARS_NOVA_FONTS_CACHE_KEY );
	if ( is_array( $cached ) && ! empty( $cached['fonts'] ) ) {
		return $cached;
	}

	$result = array(
		'source' => 'bundled',
		'fonts'  => arsnova_core_fonts_bundled_catalog(),
	);
	$ttl    = HOUR_IN_SECONDS;

	$key = trim( (string) get_option( ARS_NOVA_FONTS_KEY_OPTION, '' ) );
	if ( '' !== $key ) {
		$live = arsnova_core_fonts_fetch_live_catalog( $key );
		if ( ! empty( $live ) ) {
			$result = array(
				'source' => 'api',
				'fonts'  => $live,
			);
			$ttl    = 7 * DAY_IN_SECONDS;
		}
	}

	$result['count'] = count( $result['fonts'] );
	set_transient( ARS_NOVA_FONTS_CACHE_KEY, $result, $ttl );

	return $result;
}

/**
 * One call to the Web Fonts Developer API, normalised down to just what the
 * picker needs: family, category, and the numeric upright weights available.
 * Italic variants are dropped — the picker only previews upright faces.
 *
 * Returns an empty array on any failure, so the caller falls back quietly to
 * the bundled list rather than showing an empty picker.
 *
 * @param string $key Google Fonts API key.
 * @return array<int,array<string,mixed>>
 */
function arsnova_core_fonts_fetch_live_catalog( $key ) {
	$url = add_query_arg(
		array(
			'sort' => 'popularity',
			'key'  => $key,
		),
		'https://www.googleapis.com/webfonts/v1/webfonts'
	);

	$res = wp_remote_get( $url, array( 'timeout' => 15 ) );
	if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		return array();
	}

	$body = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( ! isset( $body['items'] ) || ! is_array( $body['items'] ) ) {
		return array();
	}

	$fonts = array();
	foreach ( $body['items'] as $item ) {
		if ( empty( $item['family'] ) ) {
			continue;
		}

		$weights  = array();
		$variants = isset( $item['variants'] ) ? (array) $item['variants'] : array();
		foreach ( $variants as $variant ) {
			if ( 'regular' === $variant ) {
				$weights[] = 400;
				continue;
			}
			if ( preg_match( '/^[1-9]00$/', (string) $variant ) ) {
				$weights[] = (int) $variant;
			}
		}
		$weights = array_values( array_unique( $weights ) );
		sort( $weights );

		$fonts[] = array(
			'family'   => (string) $item['family'],
			'category' => isset( $item['category'] ) ? (string) $item['category'] : 'sans-serif',
			'weights'  => $weights,
		);
	}

	return $fonts;
}

/**
 * Drop the cached catalog whenever the key changes, so pasting a key takes
 * effect immediately instead of waiting out the bundled list's TTL.
 *
 * @return void
 */
function arsnova_core_fonts_flush_catalog_cache() {
	delete_transient( ARS_NOVA_FONTS_CACHE_KEY );
}
add_action( 'add_option_' . ARS_NOVA_FONTS_KEY_OPTION, 'arsnova_core_fonts_flush_catalog_cache' );
add_action( 'update_option_' . ARS_NOVA_FONTS_KEY_OPTION, 'arsnova_core_fonts_flush_catalog_cache' );

/**
 * Settings -> Font Vote Fonts. One field: the API key.
 *
 * @return void
 */
function arsnova_core_fonts_register_settings() {
	register_setting(
		'arsnova_core_fonts',
		ARS_NOVA_FONTS_KEY_OPTION,
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
			'show_in_rest'      => false,
		)
	);
}
add_action( 'admin_init', 'arsnova_core_fonts_register_settings' );

/**
 * Add the settings page.
 *
 * @return void
 */
function arsnova_core_fonts_add_settings_page() {
	add_options_page(
		'Font Vote Fonts',
		'Font Vote Fonts',
		'manage_options',
		'ans-font-vote-fonts',
		'arsnova_core_fonts_render_settings_page'
	);
}
add_action( 'admin_menu', 'arsnova_core_fonts_add_settings_page' );

/**
 * Render the settings page.
 *
 * @return void
 */
function arsnova_core_fonts_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$catalog = arsnova_core_fonts_catalog();
	$has_key = '' !== trim( (string) get_option( ARS_NOVA_FONTS_KEY_OPTION, '' ) );
	?>
	<div class="wrap">
		<h1>Font Vote Fonts</h1>
		<p>
			The Font Vote picker needs to know which Google Fonts exist. Loading a font is
			free and needs no key; only the <em>list</em> of families does. Paste a free
			Google Fonts API key below and the picker switches from the built-in curated
			list to Google's full catalog.
		</p>
		<p>
			<strong>Currently serving:</strong>
			<?php echo esc_html( (string) $catalog['count'] ); ?> families
			<?php if ( 'api' === $catalog['source'] ) : ?>
				&mdash; live from the Google Fonts API.
			<?php else : ?>
				&mdash; from the built-in curated list<?php echo $has_key ? ' (the key on file did not return a catalog &mdash; check that the Web Fonts Developer API is enabled for it).' : ' (no API key stored yet).'; ?>
			<?php endif; ?>
		</p>
		<p>
			To get a key: Google Cloud Console &rarr; pick or create a project &rarr;
			enable the <em>Web Fonts Developer API</em> &rarr; Credentials &rarr; Create
			credentials &rarr; API key. It is free and there is no billing for this API.
		</p>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'arsnova_core_fonts' );
			$key_value = (string) get_option( ARS_NOVA_FONTS_KEY_OPTION, '' );
			?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="ans-gf-key">Google Fonts API key</label>
					</th>
					<td>
						<input
							type="text"
							id="ans-gf-key"
							class="regular-text"
							name="<?php echo esc_attr( ARS_NOVA_FONTS_KEY_OPTION ); ?>"
							value="<?php echo esc_attr( $key_value ); ?>"
							autocomplete="off"
							spellcheck="false"
						>
						<p class="description">
							Leave empty to keep using the built-in list. Saving a new key clears
							the cached catalog immediately.
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="ans-cs-url">Current-site URL</label>
					</th>
					<td>
						<input
							type="url"
							id="ans-cs-url"
							class="regular-text"
							name="<?php echo esc_attr( ARS_NOVA_CURRENT_SITE_URL_OPTION ); ?>"
							value="<?php echo esc_attr( (string) get_option( ARS_NOVA_CURRENT_SITE_URL_OPTION, ARS_NOVA_CURRENT_SITE_URL_DEFAULT ) ); ?>"
							spellcheck="false"
						>
						<p class="description">
							The Font Vote page's "Current Site" baseline card reads its real
							heading and body fonts from this URL rather than using hardcoded
							values. Repoint it after the Kinsta cutover.
							<?php
							$cs = arsnova_core_current_site_typography();
							if ( ! empty( $cs['ok'] ) ) {
								printf(
									/* translators: 1: heading font, 2: body font, 3: number of font files */
									' Last read: heading <strong>%1$s</strong>, body <strong>%2$s</strong>, %3$d font file(s).',
									esc_html( $cs['heading'] ),
									esc_html( $cs['body'] ),
									count( $cs['faces'] )
								);
							} else {
								echo ' <strong>Last read failed:</strong> ' . esc_html( (string) $cs['error'] );
							}
							?>
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Save key' ); ?>
		</form>
	</div>
	<?php
}
