<?php
/**
 * Bande typographique géante (texte du Customizer), défilement lent et continu.
 *
 * @package Kasumbalesa
 */

$kb_parts = array_filter( array_map( 'trim', explode( '·', (string) kb_mod( 'marquee_text' ) ) ) );
if ( ! $kb_parts ) {
	return;
}
?>
<section class="kb-marquee" aria-label="<?php echo esc_attr( implode( ' · ', $kb_parts ) ); ?>">
	<div class="kb-marquee__track" aria-hidden="true">
		<?php for ( $kb_pass = 0; $kb_pass < 2; $kb_pass++ ) : ?>
			<p class="kb-marquee__line">
				<?php foreach ( $kb_parts as $kb_i => $kb_part ) : ?>
					<span class="kb-marquee__word<?php echo $kb_i % 2 ? ' kb-marquee__word--outline' : ''; ?>"><?php echo esc_html( $kb_part ); ?></span>
					<span class="kb-marquee__sep"><?php echo kb_icon( 'mountain' ); // phpcs:ignore ?></span>
				<?php endforeach; ?>
			</p>
		<?php endfor; ?>
	</div>
</section>
