<?php
/**
 * Fonctions d'affichage réutilisables.
 *
 * @package RevNefty
 */

defined( 'ABSPATH' ) || exit;

/**
 * Logo : celui du Customizer s'il existe, sinon l'anneau RevNefty et le nom en texte.
 *
 * @param string $context header, footer ou app.
 */
function rn_brand( $context = 'header' ) {
	$home = esc_url( home_url( '/' ) );
	$name = get_bloginfo( 'name' );
	if ( has_custom_logo() && 'app' !== $context ) {
		$logo = wp_get_attachment_image( get_theme_mod( 'custom_logo' ), 'medium', false, array( 'class' => 'rn-brand__img', 'alt' => $name ) );
		printf( '<a class="rn-brand rn-brand--%1$s" href="%2$s" rel="home">%3$s</a>', esc_attr( $context ), $home, $logo ); // phpcs:ignore WordPress.Security.EscapeOutput
		return;
	}
	?>
	<a class="rn-brand rn-brand--<?php echo esc_attr( $context ); ?>" href="<?php echo $home; // phpcs:ignore ?>" rel="home" aria-label="<?php echo esc_attr( $name ); ?>">
		<img class="rn-brand__ring" src="<?php echo esc_url( RN_URI . '/assets/img/ring-128.png' ); ?>" width="40" height="40" alt="">
		<span class="rn-brand__text">
			<span class="rn-brand__name"><b>Rev</b>Nefty</span>
			<span class="rn-brand__sub">Capital Finance</span>
		</span>
	</a>
	<?php
}

/**
 * Bouton.
 *
 * @param string $label   Texte.
 * @param string $url     Lien.
 * @param string $variant primary, ghost.
 * @param string $icon    Pictogramme.
 * @return string
 */
function rn_button( $label, $url, $variant = 'primary', $icon = 'arrow-right' ) {
	if ( '' === trim( (string) $label ) || '' === trim( (string) $url ) ) {
		return '';
	}
	return sprintf(
		'<a class="rn-btn rn-btn--%1$s" href="%2$s"><span>%3$s</span>%4$s</a>',
		esc_attr( $variant ),
		esc_url( $url ),
		esc_html( $label ),
		$icon ? rn_icon( $icon ) : ''
	);
}

/**
 * En-tête de section.
 *
 * @param string $eyebrow Sur-titre.
 * @param string $title   Titre.
 * @param string $text    Texte d'introduction.
 */
function rn_section_heading( $eyebrow, $title, $text = '' ) {
	?>
	<header class="rn-section__head">
		<?php if ( $eyebrow ) : ?>
			<p class="rn-eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
		<?php endif; ?>
		<h2 class="rn-section__title"><?php echo rn_rich_title( $title ); // phpcs:ignore ?></h2>
		<?php if ( $text ) : ?>
			<p class="rn-section__lead"><?php echo esc_html( $text ); ?></p>
		<?php endif; ?>
	</header>
	<?php
}

/**
 * Coordonnées et réseaux (pied de page).
 */
function rn_contact_block() {
	$email = sanitize_email( rn_mod( 'contact_email' ) );
	$phone = trim( (string) rn_mod( 'contact_phone' ) );
	$wa    = preg_replace( '/\D+/', '', (string) rn_mod( 'contact_whatsapp' ) );
	$nets  = rn_social_networks();
	if ( ! $email && ! $phone && ! $wa && ! $nets ) {
		return;
	}
	echo '<ul class="rn-contact">';
	if ( $email ) {
		printf( '<li><a href="mailto:%1$s">%2$s<span>%1$s</span></a></li>', esc_attr( antispambot( $email ) ), rn_icon( 'mail' ) ); // phpcs:ignore
	}
	if ( $phone ) {
		printf( '<li><a href="tel:%1$s">%2$s<span>%3$s</span></a></li>', esc_attr( preg_replace( '/[^\d+]/', '', $phone ) ), rn_icon( 'phone' ), esc_html( $phone ) ); // phpcs:ignore
	}
	if ( $wa ) {
		printf( '<li><a href="https://wa.me/%1$s" rel="noopener" target="_blank">%2$s<span>WhatsApp</span></a></li>', esc_attr( $wa ), rn_icon( 'whatsapp' ) ); // phpcs:ignore
	}
	echo '</ul>';
	if ( $nets ) {
		echo '<ul class="rn-social">';
		foreach ( $nets as $n ) {
			printf( '<li><a href="%1$s" rel="noopener" target="_blank" aria-label="%2$s">%3$s</a></li>', esc_url( $n[2] ), esc_attr( $n[1] ), rn_icon( $n[0] ) ); // phpcs:ignore
		}
		echo '</ul>';
	}
}

/**
 * Pagination des listes d'articles.
 */
function rn_pagination() {
	the_posts_pagination(
		array(
			'mid_size'  => 1,
			'prev_text' => __( 'Précédent', 'revnefty' ),
			'next_text' => __( 'Suivant', 'revnefty' ),
		)
	);
}

/**
 * Carte d'article.
 */
function rn_post_card() {
	?>
	<article <?php post_class( 'rn-card rn-card--post' ); ?>>
		<a class="rn-card__link" href="<?php the_permalink(); ?>">
			<?php if ( has_post_thumbnail() ) : ?>
				<div class="rn-card__media"><?php the_post_thumbnail( 'rn-card', array( 'loading' => 'lazy' ) ); ?></div>
			<?php endif; ?>
			<div class="rn-card__body">
				<p class="rn-card__meta"><?php echo esc_html( get_the_date() ); ?></p>
				<h3 class="rn-card__title"><?php the_title(); ?></h3>
				<p class="rn-card__text"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt() ) ); ?></p>
			</div>
		</a>
	</article>
	<?php
}

/**
 * Petit graphique H1 du haut de page : vraies bougies du courtier (envoyées par l'EA MT5 au
 * carnet), zone du plan la plus proche du prix et biais de la semaine. Null sans bougies.
 *
 * @return array|null { svg, price, zone, bias }
 */
function rn_hero_data() {
	if ( ! class_exists( 'CX_Live' ) || ! class_exists( 'CX_Repo' ) ) {
		return null;
	}
	$all  = get_option( 'cx_candles', array() );
	$bars = array_slice( (array) ( $all['tf']['H1'] ?? array() ), -32 );
	if ( count( $bars ) < 12 ) {
		return null;
	}
	$last  = (float) end( $bars )[4];
	$plan  = CX_Repo::plan( CX_Live::week() );
	$c     = is_array( $plan['content'] ?? null ) ? $plan['content'] : array();
	$zone  = null;
	$dist  = INF;
	foreach ( (array) ( $c['zones'] ?? array() ) as $z ) {
		if ( 'cassee' === ( $z['state'] ?? '' ) || ! isset( $z['low'], $z['high'] ) ) {
			continue;
		}
		$d = $last > $z['high'] ? $last - $z['high'] : ( $last < $z['low'] ? $z['low'] - $last : 0 );
		if ( $d < $dist ) {
			$dist = $d;
			$zone = $z;
		}
	}
	$lo = min( array_column( $bars, 3 ) );
	$hi = max( array_column( $bars, 2 ) );
	if ( $zone && $dist < ( $hi - $lo ) * 1.5 ) {
		$lo = min( $lo, (float) $zone['low'] );
		$hi = max( $hi, (float) $zone['high'] );
	}
	$pad = ( $hi - $lo ) * .06 ?: 1;
	$lo -= $pad;
	$hi += $pad;
	$w   = 300;
	$h   = 110;
	$cw  = $w / count( $bars );
	$y   = static fn( $v ) => round( ( $hi - $v ) * $h / ( $hi - $lo ), 1 );
	$svg = sprintf( '<svg class="rn-dash__chart" viewBox="0 0 %d %d" preserveAspectRatio="none">', $w, $h );
	if ( $zone ) {
		$svg .= sprintf( '<rect x="0" y="%s" width="%d" height="%s" class="rn-dash__zone%s"/>', $y( $zone['high'] ), $w, max( 2, $y( $zone['low'] ) - $y( $zone['high'] ) ), 'vente' === ( $zone['side'] ?? '' ) ? ' rn-dash__zone--sell' : '' );
	}
	$svg .= sprintf( '<line x1="0" y1="%1$s" x2="%2$d" y2="%1$s" class="rn-dash__target"/><g class="rn-dash__candles">', $y( $last ), $w );
	foreach ( array_values( $bars ) as $i => $b ) {
		$x    = round( $cw * ( $i + .5 ), 1 );
		$k    = $b[4] >= $b[1] ? 'up' : 'down';
		$top  = $y( max( $b[1], $b[4] ) );
		$svg .= sprintf( '<line x1="%1$s" y1="%2$s" x2="%1$s" y2="%3$s" class="%4$s"/><rect x="%5$s" y="%6$s" width="%7$s" height="%8$s" rx="1" class="%4$s"/>', $x, $y( $b[2] ), $y( $b[3] ), $k, round( $x - $cw * .32, 1 ), $top, round( $cw * .64, 1 ), max( 1, $y( min( $b[1], $b[4] ) ) - $top ) );
	}
	$svg .= '</g></svg>';

	$bias = '';
	if ( ! empty( $c['prediction'] ) ) {
		$p = array(
			'Haussier' => (int) ( $c['prediction']['bull'] ?? 0 ),
			'Baissier' => (int) ( $c['prediction']['bear'] ?? 0 ),
			'Range'    => (int) ( $c['prediction']['range'] ?? 0 ),
		);
		arsort( $p );
		$bias = array( key( $p ), current( $p ) );
	}
	return array(
		'svg'   => $svg,
		'price' => $last,
		'zone'  => $zone,
		'bias'  => $bias,
	);
}
