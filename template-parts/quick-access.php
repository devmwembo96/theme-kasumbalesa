<?php
/**
 * Accès rapides (Démarches, Bureau du Maire, Douane & commerce, Tourisme…).
 *
 * @package Kasumbalesa
 */

$kb_defaults = kb_defaults();
$kb_items    = array();

for ( $kb_i = 1; $kb_i <= 4; $kb_i++ ) {
	$kb_title = kb_mod( 'qa_' . $kb_i . '_title' );
	if ( '' === trim( (string) $kb_title ) ) {
		continue;
	}
	$kb_url = kb_mod( 'qa_' . $kb_i . '_url' );
	if ( ! $kb_url ) {
		$kb_page = get_page_by_path( $kb_defaults[ 'qa_' . $kb_i . '_slug' ] );
		$kb_url  = ( $kb_page && 'publish' === $kb_page->post_status ) ? get_permalink( $kb_page ) : '';
	}
	$kb_items[] = array(
		'title' => $kb_title,
		'text'  => kb_mod( 'qa_' . $kb_i . '_text' ),
		'url'   => kb_resolve_url( $kb_url ),
		'icon'  => kb_mod( 'qa_' . $kb_i . '_icon' ),
	);
}

if ( ! $kb_items ) {
	return;
}
?>
<?php $kb_qa_image = absint( kb_mod( 'qa_image' ) ); ?>
<section class="kb-section kb-section--dark kb-quick" aria-labelledby="kb-quick-title">
	<div class="kb-container kb-container--wide">
		<?php kb_section_heading( kb_mod( 'qa_eyebrow' ), kb_mod( 'qa_title' ), 'kb-quick-title', 'center' ); ?>
		<ul class="kb-bento kb-bento--<?php echo count( $kb_items ); ?>">
			<?php foreach ( $kb_items as $kb_n => $kb_item ) : ?>
				<li class="kb-bento__item kb-bento__item--<?php echo (int) $kb_n + 1; ?>" data-reveal>
					<?php
					$kb_tag = $kb_item['url'] ? 'a' : 'div';
					printf( '<%1$s class="kb-bento__card%2$s" data-spotlight%3$s>', $kb_tag, ( 0 === $kb_n && $kb_qa_image ) ? ' kb-bento__card--image' : '', $kb_item['url'] ? ' href="' . esc_url( $kb_item['url'] ) . '"' : '' ); // phpcs:ignore
					if ( 0 === $kb_n && $kb_qa_image ) {
						echo wp_get_attachment_image( $kb_qa_image, 'kb-wide', false, array( 'class' => 'kb-bento__img', 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async', 'sizes' => '(min-width: 1000px) 50vw, 100vw' ) );
					}
					?>
						<span class="kb-bento__index" aria-hidden="true"><?php echo esc_html( sprintf( '%02d', $kb_n + 1 ) ); ?></span>
						<span class="kb-bento__icon"><?php echo kb_icon( $kb_item['icon'] ); // phpcs:ignore ?></span>
						<span class="kb-bento__content">
							<h3 class="kb-bento__title"><?php echo esc_html( $kb_item['title'] ); ?></h3>
							<?php if ( $kb_item['text'] ) : ?>
								<span class="kb-bento__text"><?php echo esc_html( $kb_item['text'] ); ?></span>
							<?php endif; ?>
						</span>
						<?php if ( $kb_item['url'] ) : ?>
							<span class="kb-bento__arrow"><?php echo kb_icon( 'arrow-right' ); // phpcs:ignore ?></span>
						<?php endif; ?>
					<?php echo '</' . $kb_tag . '>'; // phpcs:ignore ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
