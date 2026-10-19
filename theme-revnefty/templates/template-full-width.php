<?php
/**
 * Template Name: Pleine largeur
 * Template Post Type: page, post
 *
 * Pleine largeur, sans en-tête de page : le contenu occupe toute la largeur, sous
 * l'en-tête du site.
 *
 * @package RevNefty
 */

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article <?php post_class( 'rn-fullwidth' ); ?>>
		<h1 class="screen-reader-text"><?php the_title(); ?></h1>
		<?php the_content(); ?>
	</article>
	<?php
endwhile;

get_footer();
