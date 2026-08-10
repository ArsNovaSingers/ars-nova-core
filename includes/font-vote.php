<?php
/**
 * Font Vote — a small internal tool for the site typography review.
 *
 * Registers REST routes (ars-nova/v1/font-vote/*) that persist the option
 * list and each team member's rank vote in a single wp_option row, and the
 * [ans_font_vote] shortcode that renders the review page: a live hero/body
 * preview, a random-pairing generator, per-card delete + rename, cards
 * auto-sorted by the weighted tally with a "Current leader" badge on the
 * top pairing, and a per-person rank + weighted tally panel.
 *
 * Grew out of the Website branch font discussion in the "Website inspiration
 * hunt" email thread (Jul 2026) — see claude/website/HANDOFF.md.
 *
 * DELIBERATELY PUBLIC ROUTES. This page is meant to be reachable by a plain
 * link with no WordPress login for Kim or Tom, and votes here are trivial
 * and reversible. Privacy comes from the page being created unlisted (no
 * menu entry), not from auth. If that changes, add a permission_callback.
 *
 * @package ArsNovaCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARS_NOVA_FONT_VOTE_OPTION', 'arsnova_core_font_vote_data' );

/**
 * The team allowed to vote. Edit this one place to add/remove people.
 *
 * @return array<int,string>
 */
function arsnova_core_font_vote_team() {
	return array( 'Jonathan', 'Kimberly', 'Tom', 'Zahnay' );
}

/**
 * The starting option set — baseline plus the three pairings from the email
 * thread. Seeded once; after that the stored data (which may have had
 * options added/deleted since) always wins.
 *
 * @return array<int,array<string,mixed>>
 */
function arsnova_core_font_vote_default_options() {
	return array(
		array(
			'id'            => 'baseline',
			'tag'           => 'Current Site',
			'name'          => 'Baseline (approximate)',
			'rationale'     => 'Closest system-font stand-in for the live site\'s Mirador / Hergon Grotesk pairing — for comparison only.',
			'headingFont'   => "Georgia, 'Times New Roman', serif",
			'headingWeight' => 700,
			'bodyFont'      => 'Arial, Helvetica, sans-serif',
			'bodyWeight'    => 400,
			'headingLabel'  => 'Georgia (stand-in for Mirador)',
			'bodyLabel'     => 'Arial (stand-in for Hergon Grotesk)',
			'deletable'     => false,
		),
		array(
			'id'            => 'tom',
			'tag'           => "Tom's Direction",
			'name'          => 'Sans Headings / Serif Body',
			'rationale'     => 'Keeps a clean sans for headings and UI, moves programs and long-form copy into a readable serif.',
			'headingFont'   => "'Work Sans', Arial, sans-serif",
			'headingWeight' => 600,
			'bodyFont'      => "'Source Serif 4', Georgia, serif",
			'bodyWeight'    => 400,
			'headingLabel'  => 'Work Sans',
			'bodyLabel'     => 'Source Serif 4',
			'deletable'     => true,
		),
		array(
			'id'            => 'kim',
			'tag'           => "Kim's Direction",
			'name'          => 'Serif Headings / Sans Body',
			'rationale'     => 'A delicate serif for headings and titles, sans-serif for body text.',
			'headingFont'   => "'Cormorant Garamond', Georgia, serif",
			'headingWeight' => 600,
			'bodyFont'      => "'Inter', Arial, sans-serif",
			'bodyWeight'    => 400,
			'headingLabel'  => 'Cormorant Garamond',
			'bodyLabel'     => 'Inter',
			'deletable'     => true,
		),
		array(
			'id'            => 'new',
			'tag'           => 'New Pairing',
			'name'          => 'Fraunces / Manrope',
			'rationale'     => 'A pairing neither has seen yet — a warmer, more editorial serif for headings with a refined sans for body.',
			'headingFont'   => "'Fraunces', Georgia, serif",
			'headingWeight' => 500,
			'bodyFont'      => "'Manrope', Arial, sans-serif",
			'bodyWeight'    => 400,
			'headingLabel'  => 'Fraunces',
			'bodyLabel'     => 'Manrope',
			'deletable'     => true,
		),
	);
}

/**
 * Read the persisted state, seeding it on first use.
 *
 * @return array{options: array, votes: array}
 */
function arsnova_core_font_vote_get_state() {
	$state = get_option( ARS_NOVA_FONT_VOTE_OPTION, null );
	if ( ! is_array( $state ) || empty( $state['options'] ) ) {
		$state = array(
			'options' => arsnova_core_font_vote_default_options(),
			'votes'   => array(),
		);
		update_option( ARS_NOVA_FONT_VOTE_OPTION, $state, false );
	}
	if ( empty( $state['votes'] ) || ! is_array( $state['votes'] ) ) {
		$state['votes'] = array();
	}
	return $state;
}

/**
 * Persist state.
 *
 * @param array $state State to save.
 * @return void
 */
function arsnova_core_font_vote_save_state( $state ) {
	update_option( ARS_NOVA_FONT_VOTE_OPTION, $state, false );
}

/**
 * Register the ars-nova/v1/font-vote/* routes.
 *
 * @return void
 */
function arsnova_core_register_font_vote_routes() {
	register_rest_route(
		'ars-nova/v1',
		'/font-vote/state',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'arsnova_core_font_vote_state_cb',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		'ars-nova/v1',
		'/font-vote/options',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'arsnova_core_font_vote_add_option_cb',
			'permission_callback' => '__return_true',
			'args'                => array(
				'tag'           => array( 'required' => true, 'type' => 'string' ),
				'name'          => array( 'required' => true, 'type' => 'string' ),
				'rationale'     => array( 'required' => false, 'type' => 'string' ),
				'headingFont'   => array( 'required' => true, 'type' => 'string' ),
				'headingWeight' => array( 'required' => false, 'type' => 'integer', 'default' => 600 ),
				'bodyFont'      => array( 'required' => true, 'type' => 'string' ),
				'bodyWeight'    => array( 'required' => false, 'type' => 'integer', 'default' => 400 ),
				'headingLabel'  => array( 'required' => true, 'type' => 'string' ),
				'bodyLabel'     => array( 'required' => true, 'type' => 'string' ),
			),
		)
	);

	register_rest_route(
		'ars-nova/v1',
		'/font-vote/options/(?P<id>[\w-]+)',
		array(
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => 'arsnova_core_font_vote_delete_option_cb',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		'ars-nova/v1',
		'/font-vote/options/(?P<id>[\w-]+)',
		array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => 'arsnova_core_font_vote_rename_option_cb',
			'permission_callback' => '__return_true',
			'args'                => array(
				'name' => array( 'required' => true, 'type' => 'string' ),
			),
		)
	);

	register_rest_route(
		'ars-nova/v1',
		'/font-vote/fonts',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'arsnova_core_font_vote_fonts_cb',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		'ars-nova/v1',
		'/font-vote/vote',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'arsnova_core_font_vote_vote_cb',
			'permission_callback' => '__return_true',
			'args'                => array(
				'person'    => array( 'required' => true, 'type' => 'string' ),
				'option_id' => array( 'required' => true, 'type' => 'string' ),
				'rank'      => array( 'required' => false ),
			),
		)
	);
}
add_action( 'rest_api_init', 'arsnova_core_register_font_vote_routes' );

/**
 * GET /font-vote/state.
 *
 * @return WP_REST_Response
 */
function arsnova_core_font_vote_state_cb() {
	$state = arsnova_core_font_vote_get_state();
	return new WP_REST_Response(
		array(
			'team'    => arsnova_core_font_vote_team(),
			'options' => array_values( $state['options'] ),
			'votes'   => $state['votes'],
		),
		200
	);
}

/**
 * POST /font-vote/options — add a random-generated (or otherwise custom) pairing.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function arsnova_core_font_vote_add_option_cb( $request ) {
	$state = arsnova_core_font_vote_get_state();

	$id = 'rand-' . substr( md5( wp_generate_uuid4() ), 0, 8 );

	$heading_weight = absint( $request->get_param( 'headingWeight' ) );
	$body_weight    = absint( $request->get_param( 'bodyWeight' ) );

	$state['options'][] = array(
		'id'            => $id,
		'tag'           => sanitize_text_field( (string) $request->get_param( 'tag' ) ),
		'name'          => sanitize_text_field( (string) $request->get_param( 'name' ) ),
		'rationale'     => sanitize_text_field( (string) $request->get_param( 'rationale' ) ),
		'headingFont'   => sanitize_text_field( (string) $request->get_param( 'headingFont' ) ),
		'headingWeight' => $heading_weight ? $heading_weight : 600,
		'bodyFont'      => sanitize_text_field( (string) $request->get_param( 'bodyFont' ) ),
		'bodyWeight'    => $body_weight ? $body_weight : 400,
		'headingLabel'  => sanitize_text_field( (string) $request->get_param( 'headingLabel' ) ),
		'bodyLabel'     => sanitize_text_field( (string) $request->get_param( 'bodyLabel' ) ),
		'deletable'     => true,
	);

	arsnova_core_font_vote_save_state( $state );

	return new WP_REST_Response(
		array(
			'id'      => $id,
			'team'    => arsnova_core_font_vote_team(),
			'options' => array_values( $state['options'] ),
			'votes'   => $state['votes'],
		),
		201
	);
}

/**
 * DELETE /font-vote/options/{id}. Baseline (deletable=false) is protected
 * server-side too, not just hidden in the UI.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function arsnova_core_font_vote_delete_option_cb( $request ) {
	$id    = sanitize_text_field( (string) $request->get_param( 'id' ) );
	$state = arsnova_core_font_vote_get_state();

	$state['options'] = array_values(
		array_filter(
			$state['options'],
			function ( $opt ) use ( $id ) {
				return $opt['id'] !== $id || empty( $opt['deletable'] );
			}
		)
	);

	foreach ( $state['votes'] as $person => $ranks ) {
		unset( $state['votes'][ $person ][ $id ] );
	}

	arsnova_core_font_vote_save_state( $state );

	return new WP_REST_Response(
		array(
			'team'    => arsnova_core_font_vote_team(),
			'options' => array_values( $state['options'] ),
			'votes'   => $state['votes'],
		),
		200
	);
}

/**
 * PUT/PATCH /font-vote/options/{id} — rename a pairing. The fixed baseline
 * card (deletable=false) is protected server-side, matching the delete
 * protection above.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function arsnova_core_font_vote_rename_option_cb( $request ) {
	$id   = sanitize_text_field( (string) $request->get_param( 'id' ) );
	$name = trim( sanitize_text_field( (string) $request->get_param( 'name' ) ) );

	if ( '' === $name ) {
		return new WP_Error( 'ans_font_vote_bad_name', 'Name cannot be empty.', array( 'status' => 400 ) );
	}

	$state = arsnova_core_font_vote_get_state();
	$found = false;

	foreach ( $state['options'] as &$opt ) {
		if ( $opt['id'] === $id ) {
			if ( empty( $opt['deletable'] ) ) {
				return new WP_Error( 'ans_font_vote_locked', 'This option is fixed and cannot be renamed.', array( 'status' => 403 ) );
			}
			$opt['name'] = $name;
			$found       = true;
			break;
		}
	}
	unset( $opt );

	if ( ! $found ) {
		return new WP_Error( 'ans_font_vote_bad_option', 'Unknown option.', array( 'status' => 404 ) );
	}

	arsnova_core_font_vote_save_state( $state );

	return new WP_REST_Response(
		array(
			'team'    => arsnova_core_font_vote_team(),
			'options' => array_values( $state['options'] ),
			'votes'   => $state['votes'],
		),
		200
	);
}

/**
 * POST /font-vote/vote — set or clear one person's rank for one option.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function arsnova_core_font_vote_vote_cb( $request ) {
	$person    = sanitize_text_field( (string) $request->get_param( 'person' ) );
	$option_id = sanitize_text_field( (string) $request->get_param( 'option_id' ) );
	$rank_raw  = $request->get_param( 'rank' );

	if ( ! in_array( $person, arsnova_core_font_vote_team(), true ) ) {
		return new WP_Error( 'ans_font_vote_bad_person', 'Unknown team member.', array( 'status' => 400 ) );
	}

	$state = arsnova_core_font_vote_get_state();

	$valid_ids = wp_list_pluck( $state['options'], 'id' );
	if ( ! in_array( $option_id, $valid_ids, true ) ) {
		return new WP_Error( 'ans_font_vote_bad_option', 'Unknown option.', array( 'status' => 400 ) );
	}

	if ( ! isset( $state['votes'][ $person ] ) ) {
		$state['votes'][ $person ] = array();
	}

	if ( '' === (string) $rank_raw || null === $rank_raw ) {
		unset( $state['votes'][ $person ][ $option_id ] );
	} else {
		$state['votes'][ $person ][ $option_id ] = absint( $rank_raw );
	}

	arsnova_core_font_vote_save_state( $state );

	return new WP_REST_Response(
		array(
			'votes' => $state['votes'],
		),
		200
	);
}

/**
 * GET /font-vote/fonts — the Google Fonts family list the picker is built on.
 *
 * Cached server-side (see includes/font-catalog.php), so this is cheap enough
 * to hit on every page load. Public for the same reason the rest of these
 * routes are: the page has no login.
 *
 * @return WP_REST_Response
 */
function arsnova_core_font_vote_fonts_cb() {
	$catalog = arsnova_core_fonts_catalog();

	return new WP_REST_Response(
		array(
			'source' => $catalog['source'],
			'count'  => $catalog['count'],
			'fonts'  => $catalog['fonts'],
		),
		200
	);
}

/**
 * `[ans_font_vote]` — render the typography review page.
 *
 * @return string
 */
function arsnova_core_font_vote_shortcode() {
	$rest_base = esc_url_raw( rest_url( 'ars-nova/v1/font-vote/' ) );

	/*
	 * Only the page's own UI font is loaded up front. Every pairing font —
	 * including the four seeded options — is fetched on demand by the JS
	 * loader below, which is what makes a 1,800-family picker viable: the
	 * browser never asks Google for a face nobody is looking at.
	 */
	$fonts_href = 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap';

	$css = <<<'CSS'
#ans-font-vote-root{
  --anfv-navy:#0e1b3a; --anfv-gold:#c7a24a; --anfv-gold-light:#d8b25e;
  --anfv-cream:#f5f1e8; --anfv-teal:#16423e; --anfv-ink:#20242c;
  background:var(--anfv-cream); color:var(--anfv-ink);
  font-family:'Inter',Arial,sans-serif; padding:24px 0 12px;
}
#ans-font-vote-root *{box-sizing:border-box;}
#ans-font-vote-root .wrap{max-width:1180px;margin:0 auto;padding:0 20px 80px;}
#ans-font-vote-root .page-head{margin-bottom:36px;}
#ans-font-vote-root .eyebrow{text-transform:uppercase;letter-spacing:.14em;font-size:12px;font-weight:600;color:var(--anfv-gold);margin-bottom:10px;}
#ans-font-vote-root h1{margin:0 0 12px;font-size:clamp(26px,4vw,38px);color:var(--anfv-navy);font-weight:600;letter-spacing:-.01em;}
#ans-font-vote-root .page-head p{max-width:760px;font-size:15.5px;line-height:1.6;color:#3c4150;}
#ans-font-vote-root .page-head p.note{font-size:13.5px;color:#6b7080;margin-top:8px;}
#ans-font-vote-root .picker{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px;}
#ans-font-vote-root .picker button{appearance:none;border:1px solid #d8d2c2;background:#fff;color:var(--anfv-navy);padding:9px 15px;border-radius:999px;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;}
#ans-font-vote-root .picker button:hover{border-color:var(--anfv-gold);}
#ans-font-vote-root .picker button.active{background:var(--anfv-navy);border-color:var(--anfv-navy);color:#fff;}
#ans-font-vote-root .preview{background:var(--anfv-navy);border-radius:14px;padding:48px 40px;margin-bottom:12px;position:relative;overflow:hidden;}
#ans-font-vote-root .preview .eyebrow{text-transform:uppercase;letter-spacing:.16em;font-size:12px;font-weight:600;color:var(--anfv-gold-light);margin-bottom:12px;}
#ans-font-vote-root .preview h2{color:#fff;margin:0 0 14px;font-size:clamp(28px,4vw,44px);line-height:1.1;max-width:640px;}
#ans-font-vote-root .preview .sub{color:#e7e4da;font-size:16px;line-height:1.5;max-width:520px;margin-bottom:22px;}
#ans-font-vote-root .preview .btn{display:inline-block;background:var(--anfv-gold);color:var(--anfv-navy);font-weight:600;font-size:14px;padding:11px 24px;border-radius:4px;text-decoration:none;}
#ans-font-vote-root .preview-body{background:#fff;border-radius:14px;padding:34px 40px;margin-bottom:6px;border:1px solid #e7e1d1;}
#ans-font-vote-root .preview-body .label{text-transform:uppercase;letter-spacing:.12em;font-size:11px;font-weight:600;color:var(--anfv-teal);margin-bottom:8px;}
#ans-font-vote-root .preview-body p{font-size:16px;line-height:1.7;color:#2c2f38;max-width:680px;margin:0 0 12px;}
#ans-font-vote-root .live-caption{font-size:12.5px;color:#6b7080;margin:12px 0 44px;}
#ans-font-vote-root .live-caption b{color:var(--anfv-navy);}
#ans-font-vote-root .section-row{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;border-bottom:1px solid #d8d2c2;padding-bottom:10px;margin:0 0 20px;}
#ans-font-vote-root h3.section-title{font-size:12.5px;text-transform:uppercase;letter-spacing:.14em;color:var(--anfv-navy);margin:0;}
#ans-font-vote-root .gen-btn{appearance:none;border:1px solid var(--anfv-navy);background:var(--anfv-navy);color:#fff;padding:9px 16px;border-radius:6px;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;}
#ans-font-vote-root .gen-btn:hover{background:#182a52;}
#ans-font-vote-root .gen-caption{font-size:11.5px;color:#8a8f9c;margin:-12px 0 20px;}
#ans-font-vote-root .status-line{font-size:11.5px;color:#8a8f9c;margin:-4px 0 18px;}
#ans-font-vote-root .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:18px;margin-bottom:48px;}
#ans-font-vote-root .card{position:relative;background:#fff;border:1px solid #e7e1d1;border-radius:12px;padding:24px 22px;display:flex;flex-direction:column;gap:12px;}
#ans-font-vote-root .card .del-btn{position:absolute;top:10px;right:10px;width:23px;height:23px;border-radius:50%;border:1px solid #e2dac4;background:#fff;color:#a6493f;font-size:14px;line-height:1;cursor:pointer;}
#ans-font-vote-root .card .del-btn:hover{background:#a6493f;color:#fff;border-color:#a6493f;}
#ans-font-vote-root .card .rename-btn{position:absolute;top:10px;right:39px;width:23px;height:23px;border-radius:50%;border:1px solid #e2dac4;background:#fff;color:var(--anfv-navy);font-size:12px;line-height:1;cursor:pointer;}
#ans-font-vote-root .card .rename-btn:hover{background:var(--anfv-navy);color:#fff;border-color:var(--anfv-navy);}
#ans-font-vote-root .card.is-leader{border-color:var(--anfv-gold);box-shadow:0 0 0 2px rgba(199,162,74,.28);}
#ans-font-vote-root .card .card-top-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding-right:56px;}
#ans-font-vote-root .card .rank-badge{font-size:10.5px;font-weight:700;padding:4px 9px;border-radius:999px;background:var(--anfv-cream);color:var(--anfv-navy);border:1px solid #e2dac4;white-space:nowrap;}
#ans-font-vote-root .card .rank-badge.leader{background:var(--anfv-gold);border-color:var(--anfv-gold);color:var(--anfv-navy);}
#ans-font-vote-root .card .tag{align-self:flex-start;font-size:10.5px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;padding:4px 9px;border-radius:999px;background:var(--anfv-cream);color:var(--anfv-navy);border:1px solid #e2dac4;}
#ans-font-vote-root .card .leader-line{font-size:12px;font-weight:700;color:#9a7a2e;margin:-4px 0 2px;}
#ans-font-vote-root .card .opt-name{font-size:14.5px;font-weight:700;color:var(--anfv-navy);margin:0;padding-right:40px;}
#ans-font-vote-root .card .rationale{font-size:12.5px;color:#5c6170;line-height:1.5;margin:0;}
#ans-font-vote-root .card .sample-head{font-size:24px;line-height:1.15;color:var(--anfv-navy);margin:2px 0;}
#ans-font-vote-root .card .sample-body{font-size:14px;line-height:1.6;color:#333743;margin:0;}
#ans-font-vote-root .card .font-names{font-size:11px;color:#8a8f9c;border-top:1px dashed #e2dac4;padding-top:9px;margin-top:auto;}
#ans-font-vote-root .card .rank-pick{display:flex;align-items:center;gap:6px;flex-wrap:wrap;border-top:1px dashed #e2dac4;padding-top:10px;}
#ans-font-vote-root .card .rank-pick .rp-label{font-size:11px;color:#8a8f9c;margin-right:2px;}
#ans-font-vote-root .card .rank-pick .rp-btn{appearance:none;border:1px solid #d8d2c2;background:#fff;color:var(--anfv-navy);padding:5px 11px;border-radius:999px;font-family:inherit;font-size:12px;font-weight:600;cursor:pointer;}
#ans-font-vote-root .card .rank-pick .rp-btn:hover{border-color:var(--anfv-gold);}
#ans-font-vote-root .card .rank-pick .rp-btn.active{background:var(--anfv-navy);border-color:var(--anfv-navy);color:#fff;}
#ans-font-vote-root .card .other-ranks{display:flex;flex-wrap:wrap;gap:7px;font-size:11px;color:#8a8f9c;margin-top:-4px;}
#ans-font-vote-root .card .other-ranks .or-item{white-space:nowrap;}
#ans-font-vote-root .card .other-ranks .or-item b{color:var(--anfv-navy);font-weight:700;}
#ans-font-vote-root .card .other-ranks .or-item.or-ranked b{color:#9a7a2e;}
#ans-font-vote-root .voting-as-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:0 0 6px;}
#ans-font-vote-root .voting-as-label{font-size:12.5px;font-weight:700;color:var(--anfv-navy);text-transform:uppercase;letter-spacing:.08em;}
#ans-font-vote-root .gen-buttons{display:flex;gap:8px;flex-wrap:wrap;}
#ans-font-vote-root .gen-btn.secondary{background:#fff;color:var(--anfv-navy);}
#ans-font-vote-root .gen-btn.secondary:hover{background:var(--anfv-cream);}
#ans-font-vote-root .custom-form{background:var(--anfv-cream);border:1px solid #e2dac4;border-radius:10px;padding:16px 18px;margin:-10px 0 20px;display:flex;flex-direction:column;gap:10px;}
#ans-font-vote-root .custom-form .cf-row{display:flex;gap:14px;flex-wrap:wrap;}
#ans-font-vote-root .custom-form label{display:flex;flex-direction:column;gap:4px;font-size:11.5px;font-weight:600;color:var(--anfv-navy);flex:1;min-width:180px;}
#ans-font-vote-root .custom-form input,#ans-font-vote-root .custom-form select,#ans-font-vote-root .custom-form textarea{font-family:inherit;font-size:13px;padding:8px 10px;border-radius:6px;border:1px solid #d8d2c2;background:#fff;color:var(--anfv-ink);}
#ans-font-vote-root .custom-form textarea{resize:vertical;}
#ans-font-vote-root .custom-form .cf-actions{display:flex;gap:8px;}
#ans-font-vote-root .custom-form .cf-cancel{appearance:none;border:1px solid #d8d2c2;background:#fff;color:#6b7080;padding:9px 16px;border-radius:6px;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;}
#ans-font-vote-root .custom-form .cf-cancel:hover{border-color:#a6493f;color:#a6493f;}
#ans-font-vote-root .other-grid{grid-template-columns:repeat(auto-fit,minmax(200px,1fr));}
#ans-font-vote-root .fp{position:relative;}
#ans-font-vote-root .fp-trigger{width:100%;display:flex;align-items:center;justify-content:space-between;gap:8px;font-family:inherit;font-size:13px;padding:8px 10px;border-radius:6px;border:1px solid #d8d2c2;background:#fff;color:var(--anfv-ink);cursor:pointer;text-align:left;}
#ans-font-vote-root .fp-trigger:hover{border-color:var(--anfv-gold);}
#ans-font-vote-root .fp.is-open .fp-trigger{border-color:var(--anfv-navy);}
#ans-font-vote-root .fp-trigger-name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:15px;}
#ans-font-vote-root .fp-trigger-caret{color:#8a8f9c;font-size:10px;flex:none;}
#ans-font-vote-root .fp-panel{position:absolute;z-index:40;top:calc(100% + 4px);left:0;right:0;background:#fff;border:1px solid #d8d2c2;border-radius:8px;box-shadow:0 12px 28px rgba(14,27,58,.16);padding:10px;display:flex;flex-direction:column;gap:8px;}
#ans-font-vote-root .fp-search{font-family:inherit;font-size:13px;padding:7px 9px;border-radius:6px;border:1px solid #d8d2c2;background:#fff;color:var(--anfv-ink);width:100%;}
#ans-font-vote-root .fp-cats{display:flex;flex-wrap:wrap;gap:5px;}
#ans-font-vote-root .fp-cats button{appearance:none;border:1px solid #d8d2c2;background:#fff;color:var(--anfv-navy);padding:4px 9px;border-radius:999px;font-family:inherit;font-size:11px;font-weight:600;cursor:pointer;}
#ans-font-vote-root .fp-cats button.active{background:var(--anfv-navy);border-color:var(--anfv-navy);color:#fff;}
#ans-font-vote-root .fp-list{max-height:290px;overflow-y:auto;display:flex;flex-direction:column;gap:1px;}
#ans-font-vote-root .fp-row{appearance:none;border:0;border-radius:5px;background:transparent;cursor:pointer;text-align:left;padding:7px 9px;display:flex;align-items:baseline;justify-content:space-between;gap:10px;}
#ans-font-vote-root .fp-row:hover{background:var(--anfv-cream);}
#ans-font-vote-root .fp-row.active{background:var(--anfv-navy);}
#ans-font-vote-root .fp-row.active .fp-row-name,#ans-font-vote-root .fp-row.active .fp-row-cat{color:#fff;}
#ans-font-vote-root .fp-row-name{font-size:18px;line-height:1.3;color:var(--anfv-ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
#ans-font-vote-root .fp-row-cat{font-size:10px;text-transform:uppercase;letter-spacing:.07em;color:#a4a8b4;flex:none;font-family:'Inter',Arial,sans-serif;}
#ans-font-vote-root .fp-count{font-size:11px;color:#8a8f9c;margin:0;}
#ans-font-vote-root .cf-preview{background:#fff;border:1px solid #e2dac4;border-radius:8px;padding:16px 18px;}
#ans-font-vote-root .cf-preview .cfp-label{font-size:10.5px;text-transform:uppercase;letter-spacing:.1em;color:var(--anfv-teal);margin-bottom:8px;font-family:'Inter',Arial,sans-serif;}
#ans-font-vote-root .cf-preview .cfp-head{font-size:26px;line-height:1.15;color:var(--anfv-navy);margin-bottom:6px;}
#ans-font-vote-root .cf-preview .cfp-body{font-size:14px;line-height:1.6;color:#333743;margin:0;}
#ans-font-vote-root .vote-shell{background:#fff;border:1px solid #e7e1d1;border-radius:12px;padding:26px 26px 6px;margin-bottom:48px;}
#ans-font-vote-root .person-tabs{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px;}
#ans-font-vote-root .person-tabs button{appearance:none;border:1px solid #d8d2c2;background:var(--anfv-cream);color:var(--anfv-navy);padding:8px 15px;border-radius:999px;font-family:inherit;font-size:12.5px;font-weight:600;cursor:pointer;}
#ans-font-vote-root .person-tabs button.active{background:var(--anfv-gold);border-color:var(--anfv-gold);}
#ans-font-vote-root .rank-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:9px 0;border-bottom:1px solid #f0ece0;font-size:13.5px;}
#ans-font-vote-root .rank-row:last-child{border-bottom:none;}
#ans-font-vote-root .rank-row .opt-label{color:var(--anfv-navy);font-weight:600;}
#ans-font-vote-root .rank-row select{font-family:inherit;font-size:12.5px;padding:6px 9px;border-radius:6px;border:1px solid #d8d2c2;background:#fff;color:var(--anfv-ink);}
#ans-font-vote-root .vote-hint{font-size:12px;color:#8a8f9c;margin:2px 0 18px;}
#ans-font-vote-root .vote-note{font-size:11.5px;color:#6b7080;background:var(--anfv-cream);border:1px solid #e2dac4;border-radius:8px;padding:9px 13px;margin:18px 0;}
#ans-font-vote-root .tally{margin-top:6px;padding-top:18px;border-top:1px solid #e7e1d1;}
#ans-font-vote-root .tally h4{font-size:11.5px;text-transform:uppercase;letter-spacing:.1em;color:var(--anfv-navy);margin:0 0 14px;}
#ans-font-vote-root .tally-row{margin-bottom:13px;}
#ans-font-vote-root .tally-row .tr-top{display:flex;justify-content:space-between;font-size:13px;margin-bottom:5px;}
#ans-font-vote-root .tally-row .tr-top .opt-name-small{font-weight:700;color:var(--anfv-navy);}
#ans-font-vote-root .tally-row .tr-top .score{color:#6b7080;}
#ans-font-vote-root .tally-bar-bg{background:var(--anfv-cream);border-radius:999px;height:8px;overflow:hidden;}
#ans-font-vote-root .tally-bar-fill{background:linear-gradient(90deg,var(--anfv-gold),var(--anfv-gold-light));height:100%;border-radius:999px;}
#ans-font-vote-root .tally-empty{font-size:12.5px;color:#8a8f9c;padding:10px 0 18px;}
#ans-font-vote-root footer.context{padding-top:24px;border-top:1px solid #d8d2c2;font-size:12.5px;color:#6b7080;line-height:1.7;max-width:820px;}
#ans-font-vote-root footer.context b{color:var(--anfv-navy);}
CSS;

	$js = <<<'JS'
(function(){
  const REST_BASE = '__ANS_FONT_VOTE_REST_BASE__';
  /*
   * The font catalog, fetched once from our own /font-vote/fonts route. Every
   * family in here is selectable; nothing about it is hardcoded on this side
   * any more. See includes/font-catalog.php for where the list comes from.
   */
  let CATALOG = [];
  let CATALOG_SOURCE = 'bundled';

  // Never ask Google for these — they are local faces or CSS generic names.
  const SYSTEM_FAMILIES = new Set(['georgia','times new roman','arial','helvetica',
    'serif','sans-serif','monospace','cursive','system-ui','-apple-system','courier new',
    'verdana','tahoma','trebuchet ms','palatino linotype','book antiqua','impact']);

  const LOADED_FONTS = new Set();

  function fallbackFor(category){
    if (category === 'serif' || category === 'display') return 'Georgia, serif';
    if (category === 'handwriting') return 'cursive';
    if (category === 'monospace') return 'monospace';
    return 'Arial, sans-serif';
  }

  function stackFor(entry){ return "'" + entry.family + "', " + fallbackFor(entry.category); }

  // First family name out of a CSS font stack, unquoted.
  function primaryFamily(stack){
    const first = String(stack || '').split(',')[0].trim();
    return first.replace(/^['"]|['"]$/g, '').trim();
  }

  /*
   * Ask Google for one family, once. Weights are only named when the catalog
   * actually told us which ones exist — requesting a weight a family doesn't
   * have makes Google reject the whole stylesheet, so with no weight data we
   * request the family bare, which is valid for every family.
   */
  function loadFont(family, weights){
    if (!family) return;
    const key = family.toLowerCase();
    if (SYSTEM_FAMILIES.has(key) || LOADED_FONTS.has(key)) return;
    LOADED_FONTS.add(key);
    let spec = family.replace(/ /g, '+');
    if (Array.isArray(weights) && weights.length) {
      const wanted = weights.filter(w => [400,500,600,700].indexOf(Number(w)) !== -1);
      if (wanted.length) spec += ':wght@' + wanted.join(';');
    }
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'https://fonts.googleapis.com/css2?family=' + spec + '&display=swap';
    document.head.appendChild(link);
  }

  function catalogEntry(family){
    if (!family) return null;
    const key = String(family).toLowerCase();
    return CATALOG.find(f => f.family.toLowerCase() === key) || null;
  }

  // Load whatever a saved pairing needs, catalog member or not.
  function loadFontsForOption(opt){
    [opt.headingFont, opt.bodyFont].forEach(stack => {
      const fam = primaryFamily(stack);
      const entry = catalogEntry(fam);
      loadFont(fam, entry ? entry.weights : []);
    });
  }

  function headingWeightFor(entry){
    const w = (entry && Array.isArray(entry.weights)) ? entry.weights.map(Number) : [];
    if (w.indexOf(600) !== -1) return 600;
    if (w.indexOf(700) !== -1) return 700;
    if (w.indexOf(500) !== -1) return 500;
    return w.length ? 400 : 600;
  }

  /*
   * Random pairing stays inside serif/sans-serif and the popular end of the
   * catalog (the API hands it back popularity-sorted), so "random" produces
   * something plausible instead of a script face for body copy.
   */
  function randomPool(){
    return CATALOG.filter(f => f.category === 'serif' || f.category === 'sans-serif').slice(0, 140);
  }

  let OPTIONS = [];
  let VOTES = {};
  let TEAM = [];
  let activeId = null;
  let activePerson = null;

  const root = document.getElementById('ans-font-vote-root');
  const picker = root.querySelector('#anfv-picker');
  const cardGrid = root.querySelector('#anfv-cardGrid');
  const otherWrap = root.querySelector('#anfv-otherWrap');
  const otherGrid = root.querySelector('#anfv-otherGrid');
  const personTabs = root.querySelector('#anfv-personTabs');
  const tallyBlock = root.querySelector('#anfv-tallyBlock');
  const statusLine = root.querySelector('#anfv-status');

  function setStatus(msg){ if (statusLine) statusLine.textContent = msg; }
  function escAttr(s){ return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;'); }
  const RANK_POINTS = {1:3, 2:2, 3:1};

  async function api(path, opts){
    const res = await fetch(REST_BASE + path, Object.assign({headers:{'Content-Type':'application/json'}}, opts || {}));
    if (!res.ok) { throw new Error('Request failed: ' + res.status); }
    return res.json();
  }

  async function loadState(quiet){
    if (!quiet) setStatus('Syncing with the team...');
    try {
      const data = await api('state', {method:'GET'});
      TEAM = data.team || [];
      OPTIONS = data.options || [];
      VOTES = data.votes || {};
      OPTIONS.forEach(loadFontsForOption);
      if (!activePerson || TEAM.indexOf(activePerson) === -1) activePerson = TEAM[0];
      if (!activeId || !OPTIONS.find(o => o.id === activeId)) activeId = OPTIONS.length ? OPTIONS[0].id : null;
      renderPicker(); renderPersonTabs(); renderCards(); renderTally();
      const cur = OPTIONS.find(o => o.id === activeId);
      if (cur) applyPreview(cur);
      setStatus('Updated just now — shared with the whole team.');
    } catch (e) {
      setStatus('Could not reach the server just now — will retry.');
    }
  }

  function applyPreview(opt){
    activeId = opt.id;
    loadFontsForOption(opt);
    const heading = root.querySelector('#anfv-pvHeadline');
    const eyebrow = root.querySelector('#anfv-pvEyebrow');
    const sub = root.querySelector('#anfv-pvSub');
    const btn = root.querySelector('#anfv-pvBtn');
    const label = root.querySelector('#anfv-pbLabel');
    const p1 = root.querySelector('#anfv-pbP1');
    const p2 = root.querySelector('#anfv-pbP2');
    heading.style.fontFamily = opt.headingFont; heading.style.fontWeight = opt.headingWeight;
    [eyebrow, btn].forEach(el => { el.style.fontFamily = opt.headingFont; });
    [sub, label, p1, p2].forEach(el => { el.style.fontFamily = opt.bodyFont; el.style.fontWeight = opt.bodyWeight; });
    root.querySelector('#anfv-liveCaptionName').textContent = opt.tag + ' — ' + opt.name;
    [...picker.children].forEach(b => b.classList.toggle('active', b.dataset.id === opt.id));
  }

  async function addRandomOption(){
    const pool = randomPool();
    if (pool.length < 2) { setStatus('Font list is still loading — try again in a moment.'); return; }
    const h = pool[Math.floor(Math.random() * pool.length)];
    let b = pool[Math.floor(Math.random() * pool.length)];
    let guard = 0;
    while (b.family === h.family && guard < 8) { b = pool[Math.floor(Math.random() * pool.length)]; guard++; }
    setStatus('Adding a random pairing...');
    try {
      const data = await api('options', {method:'POST', body: JSON.stringify({
        tag: 'Random Pairing', name: h.family + ' / ' + b.family,
        rationale: 'Randomly generated — keep it if it works, delete it if it doesn’t.',
        headingFont: stackFor(h), headingWeight: headingWeightFor(h),
        bodyFont: stackFor(b), bodyWeight: 400,
        headingLabel: h.family, bodyLabel: b.family
      })});
      activeId = data.id;
      await loadState(true);
    } catch (e) { setStatus('Could not add that pairing — try again.'); }
  }

  /*
   * The font picker. This is a custom component rather than a <select> for one
   * concrete reason: Chrome, Edge and Safari all ignore font-family on an
   * <option>, so a native dropdown cannot show each font in its own face.
   * Only Firefox honours it. Since the whole point is seeing the typeface,
   * the native control had to go.
   */
  const CATEGORIES = [
    {key:'', label:'All'},
    {key:'serif', label:'Serif'},
    {key:'sans-serif', label:'Sans'},
    {key:'display', label:'Display'},
    {key:'handwriting', label:'Script'},
    {key:'monospace', label:'Mono'}
  ];
  const ROW_CAP = 300;
  const PICKERS = {};

  function catShort(cat){
    if (cat === 'sans-serif') return 'sans';
    if (cat === 'handwriting') return 'script';
    return cat || '';
  }

  function makePicker(role, initialFamily){
    const el = root.querySelector('#anfv-fp-' + role);
    if (!el) return null;
    const state = { role: role, el: el, family: initialFamily, cat: '', query: '', open: false };
    el.innerHTML = `
      <button type="button" class="fp-trigger">
        <span class="fp-trigger-name"></span>
        <span class="fp-trigger-caret">&#9660;</span>
      </button>
      <div class="fp-panel" style="display:none;">
        <input type="search" class="fp-search" placeholder="Search fonts by name...">
        <div class="fp-cats">${CATEGORIES.map(c => `<button type="button" data-cat="${c.key}" class="${c.key === '' ? 'active' : ''}">${c.label}</button>`).join('')}</div>
        <div class="fp-list"></div>
        <p class="fp-count"></p>
      </div>
    `;
    state.trigger = el.querySelector('.fp-trigger');
    state.triggerName = el.querySelector('.fp-trigger-name');
    state.panel = el.querySelector('.fp-panel');
    state.search = el.querySelector('.fp-search');
    state.list = el.querySelector('.fp-list');
    state.count = el.querySelector('.fp-count');

    // Only fetch the faces actually scrolled into view inside this list.
    if (typeof IntersectionObserver !== 'undefined') {
      state.observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          const row = entry.target;
          loadFont(row.dataset.family, rowWeights(row));
          state.observer.unobserve(row);
        });
      }, { root: state.list, rootMargin: '150px' });
    }

    state.trigger.addEventListener('click', () => setPickerOpen(state, !state.open));
    state.search.addEventListener('input', () => {
      state.query = state.search.value.trim().toLowerCase();
      renderPickerList(state);
    });
    el.querySelectorAll('.fp-cats button').forEach(btn => {
      btn.addEventListener('click', () => {
        state.cat = btn.dataset.cat;
        el.querySelectorAll('.fp-cats button').forEach(b => b.classList.toggle('active', b === btn));
        renderPickerList(state);
      });
    });

    PICKERS[role] = state;
    syncPickerTrigger(state);
    return state;
  }

  function rowWeights(row){
    return row.dataset.weights ? row.dataset.weights.split(',').map(Number) : [];
  }

  function setPickerOpen(state, open){
    state.open = open;
    state.panel.style.display = open ? '' : 'none';
    state.el.classList.toggle('is-open', open);
    if (open) { renderPickerList(state); state.search.focus(); }
  }

  function filteredFonts(state){
    return CATALOG.filter(f => {
      if (state.cat && f.category !== state.cat) return false;
      if (state.query && f.family.toLowerCase().indexOf(state.query) === -1) return false;
      return true;
    });
  }

  function renderPickerList(state){
    const all = filteredFonts(state);
    const shown = all.slice(0, ROW_CAP);
    if (state.observer) state.observer.disconnect();
    state.list.innerHTML = shown.map(f => `
      <button type="button" class="fp-row${f.family === state.family ? ' active' : ''}" data-family="${escAttr(f.family)}" data-weights="${(f.weights || []).join(',')}">
        <span class="fp-row-name" style="font-family:'${escAttr(f.family)}', ${fallbackFor(f.category)};">${escAttr(f.family)}</span>
        <span class="fp-row-cat">${escAttr(catShort(f.category))}</span>
      </button>
    `).join('');
    state.count.textContent = all.length > shown.length
      ? 'Showing ' + shown.length + ' of ' + all.length + ' — type above to narrow the list.'
      : all.length + (all.length === 1 ? ' font' : ' fonts');

    const rows = state.list.querySelectorAll('.fp-row');
    rows.forEach(row => {
      row.addEventListener('click', () => {
        state.family = row.dataset.family;
        syncPickerTrigger(state);
        setPickerOpen(state, false);
        renderCustomPreview();
      });
      if (state.observer) state.observer.observe(row);
    });
    // No IntersectionObserver (very old browser): load the first screenful.
    if (!state.observer) {
      Array.prototype.slice.call(rows, 0, 60).forEach(row => loadFont(row.dataset.family, rowWeights(row)));
    }
  }

  function syncPickerTrigger(state){
    const entry = catalogEntry(state.family);
    state.triggerName.textContent = state.family || 'Pick a font';
    if (entry) {
      state.triggerName.style.fontFamily = stackFor(entry);
      loadFont(entry.family, entry.weights);
    } else {
      state.triggerName.style.fontFamily = '';
    }
  }

  function renderCustomPreview(){
    const head = root.querySelector('#anfv-cfPvHead');
    const body = root.querySelector('#anfv-cfPvBody');
    if (!head || !body) return;
    const he = PICKERS.heading ? catalogEntry(PICKERS.heading.family) : null;
    const be = PICKERS.body ? catalogEntry(PICKERS.body.family) : null;
    if (he) {
      loadFont(he.family, he.weights);
      head.style.fontFamily = stackFor(he);
      head.style.fontWeight = headingWeightFor(he);
    }
    if (be) {
      loadFont(be.family, be.weights);
      body.style.fontFamily = stackFor(be);
      body.style.fontWeight = 400;
    }
  }

  function initFontPickers(){
    if (!CATALOG.length) return;
    const firstOf = (wanted, fallbackIdx) => {
      for (let i = 0; i < wanted.length; i++) { if (catalogEntry(wanted[i])) return wanted[i]; }
      return CATALOG[fallbackIdx] ? CATALOG[fallbackIdx].family : CATALOG[0].family;
    };
    makePicker('heading', firstOf(['Fraunces', 'Playfair Display', 'Cormorant Garamond'], 0));
    makePicker('body', firstOf(['Inter', 'Source Serif 4', 'Lora'], 1));
    renderCustomPreview();
  }

  // Click anywhere else and any open panel closes.
  document.addEventListener('click', function (ev) {
    Object.keys(PICKERS).forEach(function (role) {
      const state = PICKERS[role];
      if (state.open && !state.el.contains(ev.target)) setPickerOpen(state, false);
    });
  });

  async function loadCatalog(){
    const note = root.querySelector('#anfv-fontCount');
    try {
      const data = await api('fonts', {method:'GET'});
      CATALOG = data.fonts || [];
      CATALOG_SOURCE = data.source || 'bundled';
      if (note) {
        note.textContent = CATALOG.length + ' Google Fonts available to choose from'
          + (CATALOG_SOURCE === 'api'
            ? ' — the live Google catalog.'
            : ' — the built-in list. Add a Google Fonts API key under Settings → Font Vote Fonts in WordPress to unlock the full catalog.');
      }
      initFontPickers();
      OPTIONS.forEach(loadFontsForOption);
    } catch (e) {
      if (note) note.textContent = 'Could not load the font list just now.';
    }
  }

  function toggleCustomForm(show){
    const form = root.querySelector('#anfv-customForm');
    if (form) form.style.display = show ? '' : 'none';
  }

  async function addCustomOption(){
    const nameInput = root.querySelector('#anfv-cfName');
    const tagInput = root.querySelector('#anfv-cfTag');
    const rationaleInput = root.querySelector('#anfv-cfRationale');
    const h = PICKERS.heading ? catalogEntry(PICKERS.heading.family) : null;
    const b = PICKERS.body ? catalogEntry(PICKERS.body.family) : null;
    if (!h || !b) { setStatus('Pick a heading font and a body font first.'); return; }
    // Name is optional now — the two font names are a perfectly good default.
    const name = nameInput.value.trim() || (h.family + ' / ' + b.family);
    setStatus('Adding your custom pairing...');
    try {
      const data = await api('options', {method:'POST', body: JSON.stringify({
        tag: tagInput.value.trim() || 'Custom',
        name: name,
        rationale: rationaleInput.value.trim() || (h.family + ' heading with ' + b.family + ' body.'),
        headingFont: stackFor(h), headingWeight: headingWeightFor(h),
        bodyFont: stackFor(b), bodyWeight: 400,
        headingLabel: h.family, bodyLabel: b.family
      })});
      activeId = data.id;
      nameInput.value = ''; tagInput.value = ''; rationaleInput.value = '';
      toggleCustomForm(false);
      await loadState(true);
    } catch (e) { setStatus('Could not add that pairing — try again.'); }
  }

  async function deleteOption(id){
    setStatus('Removing...');
    try { await api('options/' + encodeURIComponent(id), {method:'DELETE'}); await loadState(true); }
    catch (e) { setStatus('Could not delete that — try again.'); }
  }

  async function renameOption(id, currentName){
    const next = window.prompt('Rename this pairing:', currentName || '');
    if (next === null) return;
    const trimmed = next.trim();
    if (!trimmed || trimmed === currentName) return;
    setStatus('Renaming...');
    try {
      await api('options/' + encodeURIComponent(id), {method:'PUT', body: JSON.stringify({name: trimmed})});
      await loadState(true);
    } catch (e) { setStatus('Could not rename that — try again.'); }
  }

  function computeTally(){
    const scores = {}; const firstPlace = {};
    OPTIONS.forEach(o => { scores[o.id] = 0; firstPlace[o.id] = 0; });
    let anyVotes = false;
    TEAM.forEach(person => {
      const personVotes = VOTES[person] || {};
      Object.entries(personVotes).forEach(([optId, rank]) => {
        if (!(optId in scores)) return;
        const r = Number(rank);
        if (!RANK_POINTS[r]) return;
        anyVotes = true;
        scores[optId] += RANK_POINTS[r];
        if (r === 1) firstPlace[optId] += 1;
      });
    });
    const ranked = OPTIONS.slice().sort((a,b) => scores[b.id] - scores[a.id]);
    return { scores, firstPlace, anyVotes, ranked };
  }

  async function setCardVote(person, optionId, rank){
    if (!person) return;
    setStatus('Saving your vote...');
    try {
      if (rank !== '') {
        const personVotes = VOTES[person] || {};
        const conflict = Object.entries(personVotes).find(([id, r]) => Number(r) === Number(rank) && id !== optionId);
        if (conflict) {
          await api('vote', {method:'POST', body: JSON.stringify({person, option_id: conflict[0], rank: ''})});
        }
      }
      await api('vote', {method:'POST', body: JSON.stringify({person, option_id: optionId, rank})});
      await loadState(true);
    } catch (e) { setStatus('Could not save that vote — try again.'); }
  }

  function renderPicker(){
    picker.innerHTML = '';
    OPTIONS.forEach(opt => {
      const btn = document.createElement('button');
      btn.textContent = opt.tag === 'Random Pairing' ? opt.name : opt.tag;
      btn.dataset.id = opt.id;
      btn.addEventListener('click', () => applyPreview(opt));
      picker.appendChild(btn);
    });
  }

  function buildCardEl(opt, idx, scores, firstPlace, anyVotes){
    const rank = idx + 1;
    const isLeader = anyVotes && idx === 0 && scores[opt.id] > 0;
    const myRank = Number((VOTES[activePerson] || {})[opt.id]) || null;
    const otherRanksHtml = TEAM.filter(p => p !== activePerson).map(p => {
      const r = Number((VOTES[p] || {})[opt.id]) || null;
      const label = r === 1 ? '1st' : r === 2 ? '2nd' : r === 3 ? '3rd' : '—';
      return `<span class="or-item${r ? ' or-ranked' : ''}">${escAttr(p)}: <b>${label}</b></span>`;
    }).join('');
    const card = document.createElement('div');
    card.className = 'card' + (isLeader ? ' is-leader' : '');
    card.innerHTML = `
      ${opt.deletable ? `<button class="del-btn" title="Delete this option" data-id="${opt.id}">×</button>` : ''}
      ${opt.deletable ? `<button class="rename-btn" title="Rename this pairing" data-id="${opt.id}" data-name="${escAttr(opt.name)}">✎</button>` : ''}
      <div class="card-top-row">
        ${anyVotes ? `<span class="rank-badge${isLeader ? ' leader' : ''}">${isLeader ? '★ Leading' : '#' + rank}</span>` : ''}
        <span class="tag">${opt.tag}</span>
      </div>
      <p class="opt-name">${opt.name}</p>
      ${isLeader ? `<p class="leader-line">Current leader — ${scores[opt.id]} pt${scores[opt.id] === 1 ? '' : 's'}${firstPlace[opt.id] ? ' · ' + firstPlace[opt.id] + ' first-place vote' + (firstPlace[opt.id] > 1 ? 's' : '') : ''}</p>` : ''}
      <p class="rationale">${opt.rationale}</p>
      <div class="sample-head" style="font-family:${opt.headingFont}; font-weight:${opt.headingWeight};">Rivers &amp; Streams</div>
      <p class="sample-body" style="font-family:${opt.bodyFont}; font-weight:${opt.bodyWeight};">Rivers have always carried more than water — they carry memory, boundary, and passage across five centuries of choral writing.</p>
      <div class="font-names"><span>Heading: ${opt.headingLabel}</span><span>Body: ${opt.bodyLabel}</span></div>
      <div class="rank-pick" data-id="${opt.id}">
        <span class="rp-label">${activePerson || 'Pick who you are'} ranks this:</span>
        ${[1,2,3].map(r => `<button type="button" class="rp-btn${myRank === r ? ' active' : ''}" data-id="${opt.id}" data-rank="${r}">${r===1?'1st':r===2?'2nd':'3rd'}</button>`).join('')}
      </div>
      ${otherRanksHtml ? `<div class="other-ranks">${otherRanksHtml}</div>` : ''}
    `;
    return card;
  }

  function wireCardEvents(grid){
    grid.querySelectorAll('.del-btn').forEach(btn => {
      btn.addEventListener('click', () => deleteOption(btn.dataset.id));
    });
    grid.querySelectorAll('.rename-btn').forEach(btn => {
      btn.addEventListener('click', () => renameOption(btn.dataset.id, btn.dataset.name));
    });
    grid.querySelectorAll('.rp-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        if (!activePerson) { setStatus('Pick who you are above first.'); return; }
        const id = btn.dataset.id;
        const rank = Number(btn.dataset.rank);
        const current = Number((VOTES[activePerson] || {})[id]) || null;
        setCardVote(activePerson, id, current === rank ? '' : rank);
      });
    });
  }

  function renderCards(){
    const { scores, firstPlace, anyVotes, ranked } = computeTally();
    const order = anyVotes ? ranked : OPTIONS;
    const topOrder = order.slice(0, 3);
    const otherOrder = order.slice(3);
    cardGrid.innerHTML = '';
    otherGrid.innerHTML = '';
    topOrder.forEach((opt, idx) => {
      cardGrid.appendChild(buildCardEl(opt, idx, scores, firstPlace, anyVotes));
    });
    otherOrder.forEach((opt, idx) => {
      otherGrid.appendChild(buildCardEl(opt, idx + 3, scores, firstPlace, anyVotes));
    });
    if (otherWrap) otherWrap.style.display = otherOrder.length ? '' : 'none';
    wireCardEvents(cardGrid);
    wireCardEvents(otherGrid);
  }

  function renderPersonTabs(){
    personTabs.innerHTML = '';
    TEAM.forEach(person => {
      const btn = document.createElement('button');
      btn.textContent = person;
      btn.className = person === activePerson ? 'active' : '';
      btn.addEventListener('click', () => { activePerson = person; renderPersonTabs(); renderCards(); });
      personTabs.appendChild(btn);
    });
  }

  function renderTally(){
    const { scores, firstPlace, anyVotes, ranked } = computeTally();
    if (!anyVotes){
      tallyBlock.innerHTML = '<p class="tally-empty">No votes yet — pick who you are above the cards, then mark 1st, 2nd and 3rd on your favorites.</p>';
      return;
    }
    const maxScore = Math.max(...Object.values(scores), 1);
    tallyBlock.innerHTML = ranked.map((o, i) => `
      <div class="tally-row">
        <div class="tr-top">
          <span class="opt-name-small">#${i + 1} · ${o.tag === 'Random Pairing' ? o.name : o.tag + ' — ' + o.name}</span>
          <span class="score">${scores[o.id]} pts${firstPlace[o.id] ? ' · ' + firstPlace[o.id] + ' first-place vote' + (firstPlace[o.id] > 1 ? 's' : '') : ''}</span>
        </div>
        <div class="tally-bar-bg"><div class="tally-bar-fill" style="width:${(scores[o.id]/maxScore*100).toFixed(0)}%"></div></div>
      </div>
    `).join('');
  }

  root.querySelector('#anfv-genBtn').addEventListener('click', addRandomOption);
  root.querySelector('#anfv-refreshBtn').addEventListener('click', () => loadState(false));
  root.querySelector('#anfv-customBtn').addEventListener('click', () => toggleCustomForm(true));
  root.querySelector('#anfv-cfCancel').addEventListener('click', () => toggleCustomForm(false));
  root.querySelector('#anfv-cfSubmit').addEventListener('click', addCustomOption);

  loadCatalog();
  loadState(false);
  setInterval(() => loadState(true), 20000);
})();
JS;
	$js = str_replace( '__ANS_FONT_VOTE_REST_BASE__', esc_js( $rest_base ), $js );

	ob_start();
	?>
	<div id="ans-font-vote-root">
	  <div class="wrap">
	    <link rel="preconnect" href="https://fonts.googleapis.com">
	    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	    <link href="<?php echo esc_url( $fonts_href ); ?>" rel="stylesheet">

	    <header class="page-head">
	      <div class="eyebrow">Website Branch — Design Review</div>
	      <h1>Site Typography Options</h1>
	      <p>Four starting pairings plus a random-generator to keep exploring — click through the picker
	      below for a live mock of a concert page hero and body copy, compare all options side by side,
	      then use the vote panel to rank your favorite. Shared live with the whole team.</p>
	      <p class="note">Prepared for Kim &amp; Tom's review, from the font discussion in the "Website
	      inspiration hunt" email thread (Jul 10, 2026).</p>
	    </header>

	    <div class="picker" id="anfv-picker"></div>

	    <div class="preview" id="anfv-previewHero">
	      <div class="eyebrow" id="anfv-pvEyebrow">2026–27 SEASON · RIVERS &amp; STREAMS</div>
	      <h2 id="anfv-pvHeadline">Rivers &amp; Streams</h2>
	      <div class="sub" id="anfv-pvSub">Music that moves like water — three performances exploring
	        currents, tides, and the spaces between shores. October 9–11, 2026.</div>
	      <a class="btn" id="anfv-pvBtn" href="#">Details &amp; Tickets</a>
	    </div>
	    <div class="preview-body" id="anfv-previewBody">
	      <div class="label" id="anfv-pbLabel">Program Note</div>
	      <p id="anfv-pbP1">Rivers have always carried more than water — they carry memory, boundary, and
	        passage. This program follows that current across five centuries, from Renaissance
	        polyphony written for processions along the Thames to a new commission that sets fragments
	        of the Colorado River Compact against wordless vocalise.</p>
	      <p id="anfv-pbP2">We open in stillness and end in flood: a deliberate arc from the first hushed
	        entrance to the full ensemble at its most expansive. Program notes for each piece are
	        available at the door and in the printed program.</p>
	    </div>
	    <div class="live-caption">Live preview — currently showing <b id="anfv-liveCaptionName">Current Site
	      (baseline)</b>. <span id="anfv-status">Loading...</span> <a href="#" id="anfv-refreshBtn">Refresh now</a></div>

	    <div class="section-row">
	      <h3 class="section-title">All Options — Side by Side</h3>
	      <div class="gen-buttons">
	        <button class="gen-btn" id="anfv-genBtn" type="button">+ Random Pairing</button>
	        <button class="gen-btn secondary" id="anfv-customBtn" type="button">+ Custom Option</button>
	      </div>
	    </div>
	    <p class="gen-caption">"+ Random Pairing" pulls a random heading font + a random body font from
	      the popular end of the serif and sans-serif catalog. "+ Custom Option" opens a searchable
	      picker over the whole Google Fonts library — every font is shown in its own typeface, so you
	      can see what you're choosing. Either way it saves for everyone. Delete anything with the ×
	      in its corner; baseline stays as the fixed reference point.
	      <br><span id="anfv-fontCount">Loading the font list...</span></p>
	    <div class="custom-form" id="anfv-customForm" style="display:none;">
	      <div class="cf-row">
	        <label>Pairing name (optional)
	          <input type="text" id="anfv-cfName" placeholder="e.g. Warm Editorial">
	        </label>
	        <label>Tag (optional)
	          <input type="text" id="anfv-cfTag" placeholder="Custom">
	        </label>
	      </div>
	      <div class="cf-row">
	        <label>Heading font
	          <div class="fp" id="anfv-fp-heading"></div>
	        </label>
	        <label>Body font
	          <div class="fp" id="anfv-fp-body"></div>
	        </label>
	      </div>
	      <div class="cf-preview">
	        <div class="cfp-label">Live preview of this pairing</div>
	        <div class="cfp-head" id="anfv-cfPvHead">Rivers &amp; Streams</div>
	        <p class="cfp-body" id="anfv-cfPvBody">Rivers have always carried more than water — they
	          carry memory, boundary, and passage across five centuries of choral writing.</p>
	      </div>
	      <label>Rationale (optional)
	        <textarea id="anfv-cfRationale" rows="2" placeholder="Why this pairing?"></textarea>
	      </label>
	      <div class="cf-actions">
	        <button type="button" class="gen-btn" id="anfv-cfSubmit">Add Custom Option</button>
	        <button type="button" class="cf-cancel" id="anfv-cfCancel">Cancel</button>
	      </div>
	    </div>

	    <div class="voting-as-bar">
	      <span class="voting-as-label">Voting as:</span>
	      <div class="person-tabs" id="anfv-personTabs"></div>
	    </div>
	    <p class="vote-hint">Pick who you are above, then mark your 1st, 2nd and 3rd favorite pairing
	      right on its card below — click a rank again to remove it. Everyone's picks save immediately
	      and are visible to the whole team.</p>

	    <div class="grid" id="anfv-cardGrid"></div>

	    <div id="anfv-otherWrap" style="display:none;">
	      <div class="section-row">
	        <h3 class="section-title">Other Options</h3>
	      </div>
	      <div class="grid other-grid" id="anfv-otherGrid"></div>
	    </div>

	    <div class="section-row">
	      <h3 class="section-title">Team Standings</h3>
	    </div>
	    <div class="vote-shell">
	      <p class="vote-note">Votes are shared across everyone who opens this page — no login required.
	        Since the page link itself isn't published anywhere, keep it to people you've sent it to
	        directly.</p>
	      <div class="tally">
	        <h4>Tally (1st = 3 pts, 2nd = 2 pts, 3rd = 1 pt)</h4>
	        <div id="anfv-tallyBlock"></div>
	      </div>
	    </div>

	    <footer class="context">
	      <b>Where this came from:</b> Tom (Artistic Director) wants to revisit a serif for long-form
	      text — programs, notes, articles — feeling sans-serif body copy reads as "marketing" rather
	      than "art," while noting Chanticleer and VOCES8 go all-sans as a counterpoint. Kim (Executive
	      Director) leans the other way for the pairing — a more delicate serif for headings, sans for
	      body — and said the current fonts (Mirador for headings, Hergon Grotesk for text) feel
	      "chunky." Neither picked a specific typeface, so options beyond the baseline are candidates to
	      react to, not decisions already made. The baseline card approximates the current site in
	      system fonts, since Mirador and Hergon Grotesk aren't available through a web font CDN.
	    </footer>

	  </div>
	  <style><?php echo $css; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static CSS, no user input */ ?></style>
	  <script><?php echo $js; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static JS + esc_js'd REST URL only */ ?></script>
	</div>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'ans_font_vote', 'arsnova_core_font_vote_shortcode' );
