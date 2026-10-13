<?php
/**
 * Toponymie (« Kasumba ka Lesa ») et carte postale de la ville.
 *
 * @package Kasumbalesa
 */

$kb_text     = kb_mod( 'topo_text' );
$kb_image    = absint( kb_mod( 'topo_image' ) );
$kb_postcard = absint( kb_mod( 'postcard_image' ) );
$kb_link     = kb_resolve_url( kb_mod( 'topo_link_url' ) );

if ( ! kb_mod( 'topo_title' ) && ! $kb_text && ! $kb_postcard ) {
	return;
}
?>
<section class="kb-section kb-section--paper kb-topo" id="decouvrir" aria-labelledby="kb-topo-title">
	<div class="kb-container kb-container--wide kb-topo__grid">
		<div class="kb-topo__text">
			<?php kb_section_heading( kb_mod( 'topo_eyebrow' ), kb_mod( 'topo_title' ), 'kb-topo-title' ); ?>
			<?php if ( $kb_text ) : ?>
				<div class="kb-topo__body" data-reveal>
					<?php echo wpautop( esc_html( $kb_text ) ); // phpcs:ignore ?>
				</div>
			<?php endif; ?>
			<?php if ( $kb_link ) : ?>
				<div data-reveal><?php kb_button( kb_mod( 'topo_link_label' ), $kb_link, 'primary' ); ?></div>
			<?php endif; ?>
			<?php if ( $kb_image ) : ?>
				<figure class="kb-topo__image" data-reveal>
					<?php echo wp_get_attachment_image( $kb_image, 'kb-wide', false, array( 'loading' => 'lazy', 'decoding' => 'async', 'sizes' => '(min-width: 1000px) 40vw, 100vw' ) ); ?>
				</figure>
			<?php endif; ?>
		</div>

		<?php if ( $kb_postcard ) : ?>
			<figure class="kb-postcard" data-reveal>
				<div class="kb-postcard__card">
					<div class="kb-postcard__photo">
						<?php echo wp_get_attachment_image( $kb_postcard, 'kb-wide', false, array( 'loading' => 'lazy', 'decoding' => 'async', 'sizes' => '(min-width: 1000px) 45vw, 100vw' ) ); ?>
						<?php if ( kb_mod( 'postcard_title' ) ) : ?>
							<span class="kb-postcard__greeting"><?php echo esc_html( kb_mod( 'postcard_title' ) ); ?></span>
						<?php endif; ?>
					</div>
					<div class="kb-postcard__stamp" aria-hidden="true">
						<?php
						$kb_logo = get_theme_mod( 'custom_logo' );
						echo $kb_logo ? wp_get_attachment_image( $kb_logo, 'thumbnail', false, array( 'alt' => '', 'loading' => 'lazy' ) ) : kb_icon( 'mountain' ); // phpcs:ignore
						?>
					</div>
				</div>
				<?php if ( kb_mod( 'postcard_text' ) ) : ?>
					<figcaption class="kb-postcard__caption"><?php echo esc_html( kb_mod( 'postcard_text' ) ); ?></figcaption>
				<?php endif; ?>
			</figure>
		<?php endif; ?>
	</div>
</section>
