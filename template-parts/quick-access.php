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
<section class="kb-section kb-section--dark kb-quick" aria-labelledby="kb-quick-title">
	<div class="kb-container kb-container--wide">
		<?php kb_section_heading( kb_mod( 'qa_eyebrow' ), kb_mod( 'qa_title' ), 'kb-quick-title', 'center' ); ?>
		<ul class="kb-quick__grid">
			<?php foreach ( $kb_items as $kb_item ) : ?>
				<li class="kb-quick__item" data-reveal>
					<?php
					$kb_tag = $kb_item['url'] ? 'a' : 'div';
					printf( '<%1$s class="kb-quick__card"%2$s>', $kb_tag, $kb_item['url'] ? ' href="' . esc_url( $kb_item['url'] ) . '"' : '' ); // phpcs:ignore
					?>
						<span class="kb-quick__icon"><?php echo kb_icon( $kb_item['icon'] ); // phpcs:ignore ?></span>
						<h3 class="kb-quick__title"><?php echo esc_html( $kb_item['title'] ); ?></h3>
						<?php if ( $kb_item['text'] ) : ?>
							<p class="kb-quick__text"><?php echo esc_html( $kb_item['text'] ); ?></p>
						<?php endif; ?>
						<?php if ( $kb_item['url'] ) : ?>
							<span class="kb-quick__arrow"><?php echo kb_icon( 'arrow-right' ); // phpcs:ignore ?></span>
						<?php endif; ?>
					<?php echo '</' . $kb_tag . '>'; // phpcs:ignore ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
