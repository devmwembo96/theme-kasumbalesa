<?php
/**
 * Page standard.
 *
 * @package Kasumbalesa
 */

get_header();

while ( have_posts() ) :
	the_post();

	$kb_parent = wp_get_post_parent_id( get_the_ID() );
	get_template_part(
		'template-parts/page-hero',
		null,
		array(
			'eyebrow'     => $kb_parent ? get_the_title( $kb_parent ) : get_bloginfo( 'name' ),
			'title'       => get_the_title(),
			'description' => has_excerpt() ? wpautop( esc_html( get_the_excerpt() ) ) : '',
			'image'       => get_post_thumbnail_id(),
		)
	);
	?>
	<div class="kb-page-body kb-section kb-section--white">
		<div class="kb-entry">
			<?php
			the_content();
			wp_link_pages(
				array(
					'before' => '<nav class="kb-page-links">' . esc_html__( 'Pages :', 'kasumbalesa' ),
					'after'  => '</nav>',
				)
			);
			?>
		</div>
	</div>
	<?php
	if ( comments_open() || get_comments_number() ) :
		?>
		<section class="kb-section kb-section--paper">
			<div class="kb-container kb-container--narrow"><?php comments_template(); ?></div>
		</section>
		<?php
	endif;
endwhile;

get_footer();
