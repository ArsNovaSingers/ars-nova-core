<?php
/**
 * Font Vote — a small internal tool for the site typography review.
 *
 * Registers REST routes (ars-nova/v1/font-vote/*) that persist the option
 * list and each team member's rank vote in a single wp_option row, and the
 * [ans_font_vote] shortcode that renders the review page: a live hero/body
 * preview, a random-pairing generator, per-card delete, and a per-person
 * rank + weighted tally panel.
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
 * `[ans_font_vote]` — render the typography review page.
 *
 * @return string
 */
function arsnova_core_font_vote_shortcode() {
	$rest_base = esc_url_raw( rest_url( 'ars-nova/v1/font-vote/' ) );

	$fonts_href = 'https://fonts.googleapis.com/css2?family=Work+Sans:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,400;8..60,500;8..60,600&family=Cormorant+Garamond:wght@500;600;700&family=Inter:wght@400;500;600&family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Manrope:wght@400;500;600;700&family=EB+Garamond:wght@400;500;600&family=Playfair+Display:wght@400;500;600;700&family=Libre+Baskerville:wght@400;700&family=DM+Serif+Display:wght@400&family=Spectral:wght@400;500;600;700&family=Prata:wght@400&family=Sora:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&family=Lora:wght@400;500;600;700&family=Newsreader:wght@400;500;600;700&family=Nunito+Sans:wght@400;500;600;700&family=Karla:wght@400;500;600;700&family=Public+Sans:wght@400;500;600;700&display=swap';

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
#ans-font-vote-root .card .tag{align-self:flex-start;font-size:10.5px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;padding:4px 9px;border-radius:999px;background:var(--anfv-cream);color:var(--anfv-navy);border:1px solid #e2dac4;}
#ans-font-vote-root .card .opt-name{font-size:14.5px;font-weight:700;color:var(--anfv-navy);margin:0;padding-right:18px;}
#ans-font-vote-root .card .rationale{font-size:12.5px;color:#5c6170;line-height:1.5;margin:0;}
#ans-font-vote-root .card .sample-head{font-size:24px;line-height:1.15;color:var(--anfv-navy);margin:2px 0;}
#ans-font-vote-root .card .sample-body{font-size:14px;line-height:1.6;color:#333743;margin:0;}
#ans-font-vote-root .card .font-names{font-size:11px;color:#8a8f9c;border-top:1px dashed #e2dac4;padding-top:9px;margin-top:auto;}
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
  const FONT_POOL_HEADINGS = [
    {name:'Fraunces', stack:"'Fraunces', Georgia, serif"},
    {name:'Cormorant Garamond', stack:"'Cormorant Garamond', Georgia, serif"},
    {name:'Playfair Display', stack:"'Playfair Display', Georgia, serif"},
    {name:'Libre Baskerville', stack:"'Libre Baskerville', Georgia, serif"},
    {name:'DM Serif Display', stack:"'DM Serif Display', Georgia, serif"},
    {name:'Spectral', stack:"'Spectral', Georgia, serif"},
    {name:'Prata', stack:"'Prata', Georgia, serif"},
    {name:'Work Sans', stack:"'Work Sans', Arial, sans-serif"},
    {name:'Sora', stack:"'Sora', Arial, sans-serif"},
    {name:'Outfit', stack:"'Outfit', Arial, sans-serif"}
  ];
  const FONT_POOL_BODY = [
    {name:'Source Serif 4', stack:"'Source Serif 4', Georgia, serif"},
    {name:'Lora', stack:"'Lora', Georgia, serif"},
    {name:'EB Garamond', stack:"'EB Garamond', Georgia, serif"},
    {name:'Newsreader', stack:"'Newsreader', Georgia, serif"},
    {name:'Inter', stack:"'Inter', Arial, sans-serif"},
    {name:'Manrope', stack:"'Manrope', Arial, sans-serif"},
    {name:'Nunito Sans', stack:"'Nunito Sans', Arial, sans-serif"},
    {name:'Karla', stack:"'Karla', Arial, sans-serif"},
    {name:'Public Sans', stack:"'Public Sans', Arial, sans-serif"},
    {name:'Work Sans', stack:"'Work Sans', Arial, sans-serif"}
  ];

  let OPTIONS = [];
  let VOTES = {};
  let TEAM = [];
  let activeId = null;
  let activePerson = null;

  const root = document.getElementById('ans-font-vote-root');
  const picker = root.querySelector('#anfv-picker');
  const cardGrid = root.querySelector('#anfv-cardGrid');
  const personTabs = root.querySelector('#anfv-personTabs');
  const rankList = root.querySelector('#anfv-rankList');
  const tallyBlock = root.querySelector('#anfv-tallyBlock');
  const statusLine = root.querySelector('#anfv-status');

  function setStatus(msg){ if (statusLine) statusLine.textContent = msg; }

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
      if (!activePerson || TEAM.indexOf(activePerson) === -1) activePerson = TEAM[0];
      if (!activeId || !OPTIONS.find(o => o.id === activeId)) activeId = OPTIONS.length ? OPTIONS[0].id : null;
      renderPicker(); renderCards(); renderPersonTabs(); renderRankList(); renderTally();
      const cur = OPTIONS.find(o => o.id === activeId);
      if (cur) applyPreview(cur);
      setStatus('Updated just now — shared with the whole team.');
    } catch (e) {
      setStatus('Could not reach the server just now — will retry.');
    }
  }

  function applyPreview(opt){
    activeId = opt.id;
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
    const h = FONT_POOL_HEADINGS[Math.floor(Math.random() * FONT_POOL_HEADINGS.length)];
    let b = FONT_POOL_BODY[Math.floor(Math.random() * FONT_POOL_BODY.length)];
    let guard = 0;
    while (b.name === h.name && guard < 8) { b = FONT_POOL_BODY[Math.floor(Math.random() * FONT_POOL_BODY.length)]; guard++; }
    setStatus('Adding a random pairing...');
    try {
      const data = await api('options', {method:'POST', body: JSON.stringify({
        tag: 'Random Pairing', name: h.name + ' / ' + b.name,
        rationale: 'Randomly generated — keep it if it works, delete it if it doesn’t.',
        headingFont: h.stack, headingWeight: 600, bodyFont: b.stack, bodyWeight: 400,
        headingLabel: h.name, bodyLabel: b.name
      })});
      activeId = data.id;
      await loadState(true);
    } catch (e) { setStatus('Could not add that pairing — try again.'); }
  }

  async function deleteOption(id){
    setStatus('Removing...');
    try { await api('options/' + encodeURIComponent(id), {method:'DELETE'}); await loadState(true); }
    catch (e) { setStatus('Could not delete that — try again.'); }
  }

  async function castVote(person, optionId, rank){
    try { await api('vote', {method:'POST', body: JSON.stringify({person, option_id: optionId, rank: rank || ''})}); await loadState(true); }
    catch (e) { setStatus('Could not save that vote — try again.'); }
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

  function renderCards(){
    cardGrid.innerHTML = '';
    OPTIONS.forEach(opt => {
      const card = document.createElement('div');
      card.className = 'card';
      card.innerHTML = `
        ${opt.deletable ? `<button class="del-btn" title="Delete this option" data-id="${opt.id}">×</button>` : ''}
        <span class="tag">${opt.tag}</span>
        <p class="opt-name">${opt.name}</p>
        <p class="rationale">${opt.rationale}</p>
        <div class="sample-head" style="font-family:${opt.headingFont}; font-weight:${opt.headingWeight};">Rivers &amp; Streams</div>
        <p class="sample-body" style="font-family:${opt.bodyFont}; font-weight:${opt.bodyWeight};">Rivers have always carried more than water — they carry memory, boundary, and passage across five centuries of choral writing.</p>
        <div class="font-names"><span>Heading: ${opt.headingLabel}</span><span>Body: ${opt.bodyLabel}</span></div>
      `;
      cardGrid.appendChild(card);
    });
    cardGrid.querySelectorAll('.del-btn').forEach(btn => {
      btn.addEventListener('click', () => deleteOption(btn.dataset.id));
    });
  }

  function renderPersonTabs(){
    personTabs.innerHTML = '';
    TEAM.forEach(person => {
      const btn = document.createElement('button');
      btn.textContent = person;
      btn.className = person === activePerson ? 'active' : '';
      btn.addEventListener('click', () => { activePerson = person; renderPersonTabs(); renderRankList(); });
      personTabs.appendChild(btn);
    });
  }

  function renderRankList(){
    rankList.innerHTML = '';
    OPTIONS.forEach(opt => {
      const row = document.createElement('div');
      row.className = 'rank-row';
      const personVotes = VOTES[activePerson] || {};
      const currentRank = personVotes[opt.id] || '';
      let selectHtml = `<option value="">—</option>`;
      for (let i = 1; i <= OPTIONS.length; i++){
        selectHtml += `<option value="${i}" ${String(currentRank) === String(i) ? 'selected' : ''}>${i}${i===1?'st':i===2?'nd':i===3?'rd':'th'}</option>`;
      }
      row.innerHTML = `
        <span class="opt-label">${opt.tag === 'Random Pairing' ? opt.name : opt.tag + ' — ' + opt.name}</span>
        <select data-id="${opt.id}">${selectHtml}</select>
      `;
      rankList.appendChild(row);
    });
    rankList.querySelectorAll('select').forEach(sel => {
      sel.addEventListener('change', () => { castVote(activePerson, sel.dataset.id, sel.value); });
    });
  }

  function renderTally(){
    const n = OPTIONS.length;
    const scores = {}; const firstPlace = {};
    OPTIONS.forEach(o => { scores[o.id] = 0; firstPlace[o.id] = 0; });
    let anyVotes = false;
    TEAM.forEach(person => {
      const personVotes = VOTES[person] || {};
      Object.entries(personVotes).forEach(([optId, rank]) => {
        if (!(optId in scores)) return;
        anyVotes = true;
        scores[optId] += (n - rank + 1);
        if (rank === 1) firstPlace[optId] += 1;
      });
    });
    if (!anyVotes){
      tallyBlock.innerHTML = '<p class="tally-empty">No votes yet — pick a person above and rank the options.</p>';
      return;
    }
    const maxScore = Math.max(...Object.values(scores), 1);
    const ranked = OPTIONS.slice().sort((a,b) => scores[b.id] - scores[a.id]);
    tallyBlock.innerHTML = ranked.map(o => `
      <div class="tally-row">
        <div class="tr-top">
          <span class="opt-name-small">${o.tag === 'Random Pairing' ? o.name : o.tag + ' — ' + o.name}</span>
          <span class="score">${scores[o.id]} pts${firstPlace[o.id] ? ' · ' + firstPlace[o.id] + ' first-place vote' + (firstPlace[o.id] > 1 ? 's' : '') : ''}</span>
        </div>
        <div class="tally-bar-bg"><div class="tally-bar-fill" style="width:${(scores[o.id]/maxScore*100).toFixed(0)}%"></div></div>
      </div>
    `).join('');
  }

  root.querySelector('#anfv-genBtn').addEventListener('click', addRandomOption);
  root.querySelector('#anfv-refreshBtn').addEventListener('click', () => loadState(false));

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
	      <button class="gen-btn" id="anfv-genBtn" type="button">+ Random Pairing</button>
	    </div>
	    <p class="gen-caption">Pulls a random heading font + a random body font from a curated pool of
	      ~19 typefaces and saves it for everyone. Delete anything with the × in its corner; baseline
	      stays as the fixed reference point.</p>
	    <div class="grid" id="anfv-cardGrid"></div>

	    <div class="section-row">
	      <h3 class="section-title">Team Vote</h3>
	    </div>
	    <div class="vote-shell">
	      <div class="person-tabs" id="anfv-personTabs"></div>
	      <p class="vote-hint">Pick a person above, then rank the current options 1st, 2nd, 3rd... "—"
	        means no opinion. Everyone's votes save here immediately and are visible to the whole team.</p>
	      <div id="anfv-rankList"></div>
	      <p class="vote-note">Votes are shared across everyone who opens this page — no login required.
	        Since the page link itself isn't published anywhere, keep it to people you've sent it to
	        directly.</p>
	      <div class="tally">
	        <h4>Tally (weighted: 1st place = most points)</h4>
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
