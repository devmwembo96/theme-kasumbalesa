<?php
/**
 * Template Name: Pleine largeur
 * Template Post Type: page, post
 *
 * Pleine largeur, sans en-tête de page : le contenu occupe toute la largeur.
 * Idéal pour les anciennes pages Elementor pendant la transition.
 *
 * @package Kasumbalesa
 */

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article <?php post_class( 'kb-fullwidth' ); ?>>
		<h1 class="screen-reader-text"><?php the_title(); ?></h1>
		<div class="kb-fullwidth__content">
			<?php the_content(); ?>
		</div>
	</article>
	<?php
endwhile;

get_footer();
