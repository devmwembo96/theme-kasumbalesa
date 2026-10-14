<?php
/**
 * Carte retournable avant le pied de page (reprise de l'ancien site) :
 * recto en français, verso en anglais ; se retourne au survol, au clavier ou au toucher.
 *
 * @package Kasumbalesa
 */

if ( ! kb_mod( 'flip_enable' ) ) {
	return;
}

$kb_flip_img  = wp_attachment_is_image( absint( kb_mod( 'flip_image' ) ) ) ? absint( kb_mod( 'flip_image' ) ) : absint( kb_mod( 'topo_image' ) );
$kb_flip_link = kb_resolve_url( kb_mod( 'flip_link_url' ), home_url( '/historique/' ) );
?>
<section class="kb-flip" aria-label="<?php echo esc_attr( kb_mod( 'flip_front_title' ) ); ?>">
	<?php
	if ( $kb_flip_img ) {
		echo wp_get_attachment_image( $kb_flip_img, 'kb-hero', false, array( 'class' => 'kb-flip__bg', 'alt' => '', 'loading' => 'lazy', 'sizes' => '100vw' ) );
	}
	?>
	<div class="kb-flip__wrap kb-container">
		<div class="kb-flip__card" tabindex="0" role="button" aria-pressed="false" data-flip>
			<div class="kb-flip__face kb-flip__face--front">
				<span class="kb-flip__icon" aria-hidden="true"><?php echo kb_icon( 'mountain' ); // phpcs:ignore ?></span>
				<p class="kb-flip__title"><?php echo esc_html( kb_mod( 'flip_front_title' ) ); ?></p>
				<p class="kb-flip__text"><?php echo esc_html( kb_mod( 'flip_front_text' ) ); ?></p>
				<span class="kb-flip__hint" aria-hidden="true"><?php echo kb_icon( 'arrow-right' ); // phpcs:ignore ?></span>
			</div>
			<div class="kb-flip__face kb-flip__face--back" lang="en">
				<span class="kb-flip__icon" aria-hidden="true"><?php echo kb_icon( 'mountain' ); // phpcs:ignore ?></span>
				<p class="kb-flip__title"><?php echo esc_html( kb_mod( 'flip_back_title' ) ); ?></p>
				<p class="kb-flip__text"><?php echo esc_html( kb_mod( 'flip_back_text' ) ); ?></p>
				<?php if ( $kb_flip_link ) : ?>
					<a class="kb-flip__link" href="<?php echo esc_url( $kb_flip_link ); ?>" lang="fr"><?php esc_html_e( 'Découvrir la ville', 'kasumbalesa' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
