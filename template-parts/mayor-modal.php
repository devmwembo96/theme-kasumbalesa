<?php
/**
 * Pop-up d'accueil du Maire (une fois par session, après l'introduction).
 *
 * @package Kasumbalesa
 */

$kb_page      = kb_get_mayor_page();
$kb_signature = absint( kb_mod( 'mayor_signature' ) );
?>
<dialog class="kb-modal" id="kb-mayor-modal" aria-labelledby="kb-modal-title">
	<div class="kb-modal__inner">
		<button type="button" class="kb-modal__close" data-modal-close>
			<?php echo kb_icon( 'close' ); // phpcs:ignore ?>
			<span class="screen-reader-text"><?php esc_html_e( 'Fermer', 'kasumbalesa' ); ?></span>
		</button>

		<?php if ( has_post_thumbnail( $kb_page ) ) : ?>
			<figure class="kb-modal__media">
				<?php
				echo get_the_post_thumbnail(
					$kb_page,
					'kb-portrait',
					array(
						'loading'  => 'lazy',
						'decoding' => 'async',
						'sizes'    => '(min-width: 900px) 380px, 100vw',
					)
				);
				?>
			</figure>
		<?php endif; ?>

		<div class="kb-modal__content">
			<?php if ( kb_mod( 'popup_eyebrow' ) ) : ?>
				<p class="kb-eyebrow kb-eyebrow--light"><?php echo esc_html( kb_mod( 'popup_eyebrow' ) ); ?></p>
			<?php endif; ?>
			<h2 class="kb-modal__title" id="kb-modal-title"><?php echo esc_html( get_the_title( $kb_page ) ); ?></h2>
			<div class="kb-modal__excerpt">
				<?php echo wpautop( esc_html( kb_mayor_excerpt( $kb_page, 55 ) ) ); // phpcs:ignore ?>
			</div>
			<div class="kb-signature kb-signature--light">
				<?php
				if ( $kb_signature ) {
					echo wp_get_attachment_image( $kb_signature, 'medium', false, array( 'class' => 'kb-signature__img', 'alt' => '', 'loading' => 'lazy' ) );
				}
				?>
				<p class="kb-signature__name"><?php echo esc_html( kb_mod( 'mayor_name' ) ); ?></p>
				<p class="kb-signature__role"><?php echo esc_html( kb_mod( 'mayor_role' ) ); ?></p>
			</div>
			<?php kb_button( kb_mod( 'popup_button' ), get_permalink( $kb_page ), 'gold' ); ?>
		</div>
	</div>
</dialog>
