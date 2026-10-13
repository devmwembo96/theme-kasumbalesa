<?php
/**
 * Grille d'articles avec pagination (la 1re carte de la 1re page est mise en avant).
 *
 * @package Kasumbalesa
 */

?>
<section class="kb-section kb-listing">
	<div class="kb-container kb-container--wide">
		<?php if ( have_posts() ) : ?>
			<div class="kb-grid">
				<?php
				$kb_index = 0;
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/content-card', null, array( 'featured' => 0 === $kb_index && ! is_paged() && ! is_search() ) );
					++$kb_index;
				endwhile;
				?>
			</div>
			<?php kb_pagination(); ?>
		<?php else : ?>
			<?php get_template_part( 'template-parts/content-none' ); ?>
		<?php endif; ?>
	</div>
</section>
