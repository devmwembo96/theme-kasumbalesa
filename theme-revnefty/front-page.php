<?php
/**
 * Page d'accueil.
 *
 * @package RevNefty
 */

get_header();

get_template_part( 'template-parts/hero' );
if ( rn_mod( 'ticker_enable' ) ) {
	get_template_part( 'template-parts/ticker' );
}
get_template_part( 'template-parts/carnet-live' );
get_template_part( 'template-parts/steps' );
get_template_part( 'template-parts/pillars' );
get_template_part( 'template-parts/risk' );
get_template_part( 'template-parts/news' );
get_template_part( 'template-parts/faq' );

// Contenu de la page d'accueil choisie dans Réglages › Lecture, s'il y en a un.
if ( 'page' === get_option( 'show_on_front' ) ) :
	while ( have_posts() ) :
		the_post();
		if ( '' !== trim( get_the_content() ) ) :
			?>
			<section class="rn-section">
				<div class="rn-entry rn-wrap rn-wrap--narrow"><?php the_content(); ?></div>
			</section>
			<?php
		endif;
	endwhile;
endif;

get_template_part( 'template-parts/cta' );

get_footer();
