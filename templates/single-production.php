<?php
/**
 * Single Production template — markup for every concert page.
 *
 * Owned by the plugin (see includes/production-template.php for how this
 * gets swapped in). Calls get_header()/get_footer() so the site's nav and
 * footer stay normal; everything in between is this file.
 *
 * Section order: Hero -> Tickets -> Story -> Featured Artist -> Support.
 *
 * @package ars-nova-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();

	$post_id        = get_the_ID();
	$hero_eyebrow    = get_field( 'hero_eyebrow' );
	$hero_headline   = get_field( 'hero_headline' );
	$hero_headline   = $hero_headline ? $hero_headline : get_the_title();
	$subheader       = get_field( 'subheader' );
	$details         = get_field( 'details' );
	$story_heading   = get_field( 'story_heading' );
	$story_body      = get_field( 'story_body' );
	$featured_artist = get_field( 'featured_artist' );

	$ticket_link_type    = get_field( 'ticket_link_type' );
	$ticket_link_text    = get_field( 'ticket_link_text' ) ? get_field( 'ticket_link_text' ) : 'Reserve Your Seat';
	$ticket_link_url     = get_field( 'ticket_link_url' );
	$eventbrite_id       = get_field( 'eventbrite_id' );
	$tickera_event_id    = get_field( 'tickera_event_id' );
	$sold_out            = get_field( 'sold_out' );
	$sold_out_button_txt = get_field( 'sold_out_button_text' ) ? get_field( 'sold_out_button_text' ) : 'Sold Out';

	$has_thumb  = has_post_thumbnail( $post_id );
	$thumb_url  = $has_thumb ? get_the_post_thumbnail_url( $post_id, 'large' ) : '';
	?>

	<style>
		.ans-production-hero {
			position: relative;
			padding: 120px 24px;
			text-align: center;
			color: #ffffff;
			background-color: #0e1b3a;
			background-size: cover;
			background-position: center;
		}
		.ans-production-hero.has-image::before {
			content: "";
			position: absolute;
			inset: 0;
			background: linear-gradient( 0deg, rgba( 14, 27, 58, 0.92 ) 0%, rgba( 14, 27, 58, 0.55 ) 55%, rgba( 14, 27, 58, 0.3 ) 100% );
		}
		.ans-production-hero__inner {
			position: relative;
			max-width: 820px;
			margin: 0 auto;
		}
		.ans-production-hero__eyebrow {
			display: block;
			letter-spacing: 3px;
			text-transform: uppercase;
			font-size: 14px;
			color: #d8b25e;
			margin-bottom: 18px;
		}
		.ans-production-hero__title {
			font-size: clamp( 36px, 6vw, 64px );
			font-weight: 700;
			line-height: 1.05;
			margin: 0 0 18px;
			color: #ffffff;
		}
		.ans-production-hero__subheader {
			font-size: 18px;
			line-height: 1.6;
			color: #e7ebf3;
			margin: 0 0 14px;
		}
		.ans-production-hero__details {
			font-size: 16px;
			color: #d8dce6;
			margin: 0 0 30px;
		}
		.ans-production-hero .ans-btn {
			display: inline-block;
			background: #c7a24a;
			color: #0e1b3a;
			border-radius: 40px;
			padding: 16px 40px;
			font-weight: 700;
			font-size: 15px;
			letter-spacing: 1px;
			text-transform: uppercase;
			text-decoration: none;
		}
		.ans-production-section {
			padding: 80px 24px;
		}
		.ans-production-section__inner {
			max-width: 780px;
			margin: 0 auto;
		}
		.ans-production-section--tickets {
			background: #f5f1e8;
			text-align: center;
		}
		.ans-production-section--story {
			background: #ffffff;
		}
		.ans-production-section--artist {
			background: #f5f1e8;
		}
		.ans-production-section--support {
			background: #16423e;
			color: #f5f1e8;
			text-align: center;
		}
		.ans-production-eyebrow {
			display: block;
			letter-spacing: 2px;
			text-transform: uppercase;
			font-size: 13px;
			color: #9a7b2e;
			margin-bottom: 12px;
			text-align: center;
		}
		.ans-production-section--support .ans-production-eyebrow {
			color: #cfead7;
		}
		.ans-production-heading {
			font-size: 36px;
			font-weight: 700;
			text-align: center;
			margin: 0 0 24px;
			color: #0e1b3a;
		}
		.ans-production-section--support .ans-production-heading {
			color: #f5f1e8;
		}
		.ans-production-body p {
			font-size: 18px;
			line-height: 1.7;
			margin: 0 0 18px;
		}
		.ans-production-sold-out {
			display: inline-block;
			background: #9aa1ad;
			color: #ffffff;
			border-radius: 40px;
			padding: 16px 40px;
			font-weight: 700;
			font-size: 15px;
			letter-spacing: 1px;
			text-transform: uppercase;
		}
		.ans-production-section--support .ans-btn {
			display: inline-block;
			background: #f5f1e8;
			color: #16423e;
			border-radius: 40px;
			padding: 16px 40px;
			font-weight: 700;
			font-size: 15px;
			letter-spacing: 1px;
			text-transform: uppercase;
			text-decoration: none;
		}
	</style>

	<section class="ans-production-hero<?php echo $has_thumb ? ' has-image' : ''; ?>"<?php echo $has_thumb ? ' style="background-image:url(' . esc_url( $thumb_url ) . ')"' : ''; ?>>
		<div class="ans-production-hero__inner">
			<?php if ( $hero_eyebrow ) : ?>
				<span class="ans-production-hero__eyebrow"><?php echo esc_html( $hero_eyebrow ); ?></span>
			<?php endif; ?>
			<h1 class="ans-production-hero__title"><?php echo esc_html( $hero_headline ); ?></h1>
			<?php if ( $subheader ) : ?>
				<p class="ans-production-hero__subheader"><?php echo wp_kses_post( nl2br( esc_html( $subheader ) ) ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $details ) ) : ?>
				<p class="ans-production-hero__details">
					<?php
					$lines = array();
					foreach ( $details as $row ) {
						if ( ! empty( $row['title'] ) ) {
							$lines[] = esc_html( $row['title'] );
						}
					}
					echo implode( ' &middot; ', $lines );
					?>
				</p>
			<?php endif; ?>
			<?php if ( ! $sold_out ) : ?>
				<a href="#tickets" class="ans-btn">Reserve Your Seat</a>
			<?php endif; ?>
		</div>
	</section>

	<section id="tickets" class="ans-production-section ans-production-section--tickets">
		<div class="ans-production-section__inner">
			<span class="ans-production-eyebrow">Tickets<?php echo $sold_out ? '' : ' &middot; Limited Seating'; ?></span>
			<h2 class="ans-production-heading"><?php echo esc_html( $ticket_link_text ); ?></h2>

			<?php if ( $sold_out ) : ?>
				<span class="ans-production-sold-out"><?php echo esc_html( $sold_out_button_txt ); ?></span>

			<?php elseif ( 'tickera' === $ticket_link_type && $tickera_event_id ) : ?>
				<?php echo do_shortcode( '[tc_wb_event id="' . esc_attr( $tickera_event_id ) . '"]' ); ?>

			<?php elseif ( 'eventbrite' === $ticket_link_type && $eventbrite_id ) : ?>
				<a class="ans-btn" href="https://www.eventbrite.com/e/<?php echo esc_attr( $eventbrite_id ); ?>" target="_blank" rel="noopener">
					<?php echo esc_html( $ticket_link_text ); ?>
				</a>

			<?php elseif ( 'url' === $ticket_link_type && $ticket_link_url ) : ?>
				<a class="ans-btn" href="<?php echo esc_url( $ticket_link_url ); ?>" target="_blank" rel="noopener">
					<?php echo esc_html( $ticket_link_text ); ?>
				</a>

			<?php else : ?>
				<p>Ticket details coming soon.</p>
			<?php endif; ?>
		</div>
	</section>

	<?php if ( $story_heading || $story_body ) : ?>
		<section class="ans-production-section ans-production-section--story">
			<div class="ans-production-section__inner">
				<?php if ( $story_heading ) : ?>
					<h2 class="ans-production-heading"><?php echo esc_html( $story_heading ); ?></h2>
				<?php endif; ?>
				<?php if ( $story_body ) : ?>
					<div class="ans-production-body"><?php echo wp_kses_post( $story_body ); ?></div>
				<?php endif; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $featured_artist ) :
		$artist_post = get_post( $featured_artist );
		if ( $artist_post ) :
			?>
			<section class="ans-production-section ans-production-section--artist">
				<div class="ans-production-section__inner">
					<span class="ans-production-eyebrow">The Artist</span>
					<h2 class="ans-production-heading"><?php echo esc_html( get_the_title( $artist_post ) ); ?></h2>
					<div class="ans-production-body">
						<?php echo wp_kses_post( wpautop( get_the_excerpt( $artist_post ) ? get_the_excerpt( $artist_post ) : wp_trim_words( $artist_post->post_content, 80 ) ) ); ?>
					</div>
					<p style="text-align:center;">
						<a href="<?php echo esc_url( get_permalink( $artist_post ) ); ?>">Read full artist profile &rarr;</a>
					</p>
				</div>
			</section>
			<?php
		endif;
	endif;
	?>

	<section class="ans-production-section ans-production-section--support">
		<div class="ans-production-section__inner">
			<h2 class="ans-production-heading">Support the Music</h2>
			<p>As a nonprofit chorus, we rely on the generosity of our community to bring this music to life. Your gift keeps Ars Nova singing.</p>
			<p><a href="/support/donate/" class="ans-btn">Donate</a></p>
		</div>
	</section>

	<?php
endwhile;

get_footer();
