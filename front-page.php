<?php
/**
 * Page d'accueil.
 *
 * @package Kasumbalesa
 */

get_header();

get_template_part( 'template-parts/hero' );

if ( kb_mod( 'mayor_section_enable' ) && kb_get_mayor_page() ) {
	get_template_part( 'template-parts/mayor-message' );
}

get_template_part( 'template-parts/news-carousel' );
get_template_part( 'template-parts/quick-access' );
get_template_part( 'template-parts/key-figures' );
get_template_part( 'template-parts/toponymy' );

if ( kb_mod( 'front_show_content' ) && 'page' === get_option( 'show_on_front' ) ) :
	while ( have_posts() ) :
		the_post();
		if ( '' !== trim( get_the_content() ) ) :
			?>
			<section class="kb-section kb-section--white">
				<div class="kb-entry kb-container">
					<?php the_content(); ?>
				</div>
			</section>
			<?php
		endif;
	endwhile;
endif;

get_footer();
