<?php
/**
 * Page standard.
 *
 * @package RevNefty
 */

get_header();

while ( have_posts() ) :
	the_post();
	get_template_part(
		'template-parts/page-head',
		null,
		array(
			'eyebrow' => get_bloginfo( 'name' ),
			'title'   => get_the_title(),
			'lead'    => has_excerpt() ? get_the_excerpt() : '',
		)
	);
	?>
	<section class="rn-section rn-section--tight">
		<div class="rn-entry rn-wrap rn-wrap--narrow">
			<?php
			the_content();
			wp_link_pages();
			?>
		</div>
	</section>
	<?php
	if ( comments_open() || get_comments_number() ) {
		echo '<section class="rn-section rn-section--tight"><div class="rn-wrap rn-wrap--narrow">';
		comments_template();
		echo '</div></section>';
	}
endwhile;

get_footer();
