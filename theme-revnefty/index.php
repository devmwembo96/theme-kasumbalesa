<?php
/**
 * Liste d'articles (blog, archives par défaut).
 *
 * @package RevNefty
 */

get_header();

get_template_part(
	'template-parts/page-head',
	null,
	array(
		'eyebrow' => __( 'Analyses', 'revnefty' ),
		'title'   => is_home() && get_option( 'page_for_posts' ) ? get_the_title( get_option( 'page_for_posts' ) ) : wp_strip_all_tags( get_the_archive_title() ? get_the_archive_title() : __( 'Publications', 'revnefty' ) ),
		'lead'    => wp_strip_all_tags( get_the_archive_description() ),
	)
);
?>
<section class="rn-section rn-section--tight">
	<div class="rn-wrap">
		<?php if ( have_posts() ) : ?>
			<div class="rn-grid rn-grid--3">
				<?php
				while ( have_posts() ) :
					the_post();
					rn_post_card();
				endwhile;
				?>
			</div>
			<?php rn_pagination(); ?>
		<?php else : ?>
			<p class="rn-empty"><?php esc_html_e( 'Aucune publication pour le moment.', 'revnefty' ); ?></p>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
