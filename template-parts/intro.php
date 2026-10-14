<?php
/**
 * Animation d'introduction : logo du site (Personnaliser > Identité du site).
 * Affichée une fois par session, 5 secondes (touche Échap pour la fermer).
 * Le cercle qui tourne reprend les trois couleurs de la RDC (bleu ciel, jaune, rouge).
 *
 * @package Kasumbalesa
 */

$kb_logo_id = get_theme_mod( 'custom_logo' );
?>
<div class="kb-intro" id="kb-intro">
	<div class="kb-intro__stage">
		<svg class="kb-intro__ring" viewBox="0 0 200 200" aria-hidden="true" focusable="false">
			<circle class="kb-intro__ring-track" cx="100" cy="100" r="96" />
			<g class="kb-intro__spin">
				<circle class="kb-intro__arc kb-intro__arc--blue" cx="100" cy="100" r="96" pathLength="300" />
				<circle class="kb-intro__arc kb-intro__arc--yellow" cx="100" cy="100" r="96" pathLength="300" />
				<circle class="kb-intro__arc kb-intro__arc--red" cx="100" cy="100" r="96" pathLength="300" />
			</g>
		</svg>
		<div class="kb-intro__logo">
			<?php
			if ( $kb_logo_id ) {
				echo wp_get_attachment_image(
					$kb_logo_id,
					'medium',
					false,
					array(
						'alt'           => '',
						'loading'       => 'eager',
						'fetchpriority' => 'high',
					)
				);
			} else {
				echo kb_icon( 'mountain' ); // phpcs:ignore
			}
			?>
		</div>
	</div>
	<p class="kb-intro__name"><?php bloginfo( 'name' ); ?></p>
	<?php if ( get_bloginfo( 'description' ) ) : ?>
		<p class="kb-intro__tagline"><?php bloginfo( 'description' ); ?></p>
	<?php endif; ?>
</div>
