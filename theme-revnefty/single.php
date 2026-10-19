<?php
/**
 * Article.
 *
 * @package RevNefty
 */

get_header();

while ( have_posts() ) :
	the_post();
	$rn_cat = get_the_category();
	get_template_part(
		'template-parts/page-head',
		null,
		array(
			'eyebrow' => ( $rn_cat ? $rn_cat[0]->name . ' · ' : '' ) . get_the_date(),
			'title'   => get_the_title(),
			'lead'    => has_excerpt() ? get_the_excerpt() : '',
		)
	);
	?>
	<article <?php post_class( 'rn-section rn-section--tight' ); ?>>
		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="rn-wrap rn-wrap--narrow rn-single__media"><?php the_post_thumbnail( 'large' ); ?></figure>
		<?php endif; ?>
		<div class="rn-entry rn-wrap rn-wrap--narrow">
			<?php
			the_content();
			wp_link_pages();
			?>
		</div>
		<nav class="rn-wrap rn-wrap--narrow rn-postnav">
			<?php
			the_post_navigation(
				array(
					'prev_text' => '<span>' . esc_html__( 'Précédent', 'revnefty' ) . '</span>%title',
					'next_text' => '<span>' . esc_html__( 'Suivant', 'revnefty' ) . '</span>%title',
				)
			);
			?>
		</nav>
	</article>
	<?php
	if ( comments_open() || get_comments_number() ) {
		echo '<section class="rn-section rn-section--tight"><div class="rn-wrap rn-wrap--narrow">';
		comments_template();
		echo '</div></section>';
	}
endwhile;

get_footer();
