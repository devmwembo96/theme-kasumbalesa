<?php
/**
 * Haut de la page d'accueil : promesse, boutons et tableau de bord illustratif.
 *
 * @package RevNefty
 */

$rn_points = array_filter( array( rn_mod( 'hero_point_1' ), rn_mod( 'hero_point_2' ), rn_mod( 'hero_point_3' ) ) );
?>
<section class="rn-hero">
	<div class="rn-hero__glow" aria-hidden="true"></div>
	<div class="rn-hero__inner rn-wrap">
		<div class="rn-hero__copy">
			<?php if ( rn_mod( 'hero_eyebrow' ) ) : ?>
				<p class="rn-eyebrow rn-eyebrow--pill"><span class="rn-live" aria-hidden="true"></span><?php echo esc_html( rn_mod( 'hero_eyebrow' ) ); ?></p>
			<?php endif; ?>
			<h1 class="rn-hero__title"><?php echo rn_rich_title( rn_mod( 'hero_title' ) ); // phpcs:ignore ?></h1>
			<?php if ( rn_mod( 'hero_text' ) ) : ?>
				<p class="rn-hero__text"><?php echo esc_html( rn_mod( 'hero_text' ) ); ?></p>
			<?php endif; ?>
			<div class="rn-hero__actions">
				<?php
				echo rn_button( rn_mod( 'hero_cta_label' ), rn_mod( 'hero_cta_url' ), 'primary' ); // phpcs:ignore
				echo rn_button( rn_mod( 'hero_cta2_label' ), '#contact' === rn_mod( 'hero_cta2_url' ) ? rn_contact_url() : rn_mod( 'hero_cta2_url' ), 'ghost', '' ); // phpcs:ignore
				?>
			</div>
			<?php if ( $rn_points ) : ?>
				<ul class="rn-hero__points">
					<?php foreach ( $rn_points as $rn_point ) : ?>
						<li><?php echo rn_icon( 'check' ); // phpcs:ignore ?><?php echo esc_html( $rn_point ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

		<div class="rn-hero__visual" aria-hidden="true">
			<picture class="rn-hero__banner">
				<source media="(min-width: 900px)" srcset="<?php echo esc_url( RN_URI . '/assets/img/banner-1600.webp' ); ?>">
				<img src="<?php echo esc_url( RN_URI . '/assets/img/banner-900.webp' ); ?>" width="900" height="506" alt="" fetchpriority="high">
			</picture>
			<div class="rn-dash">
				<div class="rn-dash__head">
					<span class="rn-dash__logo">
						<img src="https://s3-symbol-logo.tradingview.com/metal/gold.svg" width="22" height="22" alt="" loading="lazy" referrerpolicy="no-referrer">
						<img src="https://s3-symbol-logo.tradingview.com/country/US.svg" width="22" height="22" alt="" loading="lazy" referrerpolicy="no-referrer">
					</span>
					<span class="rn-dash__sym">XAUUSD</span>
					<span class="rn-dash__tag">H1</span>
					<span class="rn-dash__live"><span class="rn-live"></span><?php esc_html_e( 'Plan actif', 'revnefty' ); ?></span>
				</div>
				<svg class="rn-dash__chart" viewBox="0 0 300 110" preserveAspectRatio="none">
					<rect x="0" y="62" width="300" height="20" class="rn-dash__zone"/>
					<line x1="0" y1="22" x2="300" y2="22" class="rn-dash__target"/>
					<g class="rn-dash__candles">
						<?php
						// Bougies décoratives (repli sur la zone, puis départ haussier).
						$rn_c = array( array( 40, 52, 34, 58, 0 ), array( 52, 60, 46, 66, 0 ), array( 60, 70, 55, 76, 0 ), array( 70, 66, 62, 80, 1 ), array( 66, 54, 50, 72, 1 ), array( 54, 44, 40, 58, 1 ), array( 44, 46, 38, 52, 0 ), array( 46, 34, 30, 50, 1 ), array( 34, 26, 22, 38, 1 ), array( 26, 20, 16, 30, 1 ) );
						foreach ( $rn_c as $i => $c ) {
							$x   = 18 + $i * 29;
							$top = min( $c[0], $c[1] );
							$h   = max( 3, abs( $c[0] - $c[1] ) );
							printf( '<line x1="%1$d" y1="%2$d" x2="%1$d" y2="%3$d" class="%4$s"/><rect x="%5$d" y="%6$d" width="12" height="%7$d" rx="2" class="%4$s"/>', $x + 6, $c[2], $c[3], $c[4] ? 'up' : 'down', $x, $top, $h ); // phpcs:ignore
						}
						?>
					</g>
				</svg>
				<div class="rn-dash__stats">
					<div><span><?php esc_html_e( 'Risque', 'revnefty' ); ?></span><b>50 $</b></div>
					<div><span><?php esc_html_e( 'Objectif', 'revnefty' ); ?></span><b class="up">+8 %</b></div>
					<div><span><?php esc_html_e( 'Ratio', 'revnefty' ); ?></span><b>1 : 3</b></div>
				</div>
			</div>
		</div>
	</div>
</section>
