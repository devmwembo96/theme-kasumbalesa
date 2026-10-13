<?php
/**
 * Gabarit de repli.
 *
 * @package Kasumbalesa
 */

get_header();
?>
<section class="kb-section">
	<div class="kb-container">
		<?php
		while ( have_posts() ) :
			the_post();
			the_title( '<h2><a href="' . esc_url( get_permalink() ) . '">', '</a></h2>' );
			the_excerpt();
		endwhile;
		?>
	</div>
</section>
<?php
get_footer();
