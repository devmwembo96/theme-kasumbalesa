<?php
/**
 * Appel final.
 *
 * @package RevNefty
 */

if ( ! rn_mod( 'cta_title' ) ) {
	return;
}
?>
<section class="rn-section rn-section--cta">
	<div class="rn-wrap">
		<div class="rn-cta">
			<img class="rn-cta__ring" src="<?php echo esc_url( RN_URI . '/assets/img/ring-256.png' ); ?>" width="120" height="120" alt="" loading="lazy">
			<div class="rn-cta__copy">
				<h2 class="rn-cta__title"><?php echo rn_rich_title( rn_mod( 'cta_title' ) ); // phpcs:ignore ?></h2>
				<?php if ( rn_mod( 'cta_text' ) ) : ?>
					<p><?php echo esc_html( rn_mod( 'cta_text' ) ); ?></p>
				<?php endif; ?>
			</div>
			<?php echo rn_button( rn_mod( 'cta_label' ), rn_contact_url(), 'primary' ); // phpcs:ignore ?>
		</div>
	</div>
</section>
