<?php
/**
 * Animation d'introduction : logo du site (Personnaliser > Identité du site).
 * Affichée une fois par session, 2 secondes maximum, avec un bouton « Passer ».
 *
 * @package Kasumbalesa
 */

$kb_logo_id = get_theme_mod( 'custom_logo' );
?>
<div class="kb-intro" id="kb-intro">
	<div class="kb-intro__stage">
		<svg class="kb-intro__ring" viewBox="0 0 200 200" aria-hidden="true" focusable="false">
			<circle class="kb-intro__ring-track" cx="100" cy="100" r="96" />
			<circle class="kb-intro__ring-line" cx="100" cy="100" r="96" pathLength="1" />
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
	<button type="button" class="kb-intro__skip" data-intro-skip>
		<?php esc_html_e( 'Passer', 'kasumbalesa' ); ?> <?php echo kb_icon( 'arrow-right' ); // phpcs:ignore ?>
	</button>
</div>
