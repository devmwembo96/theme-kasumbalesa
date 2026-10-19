<?php
/**
 * Dernières analyses (articles), seulement s'il y en a.
 *
 * @package RevNefty
 */

$rn_posts = new WP_Query(
	array(
		'posts_per_page'      => 3,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	)
);
if ( ! $rn_posts->have_posts() ) {
	return;
}
$rn_blog = get_option( 'page_for_posts' ) ? get_permalink( get_option( 'page_for_posts' ) ) : '';
?>
<section class="rn-section rn-section--alt" id="analyses">
	<div class="rn-wrap">
		<?php rn_section_heading( __( 'Analyses', 'revnefty' ), __( 'Les dernières publications', 'revnefty' ) ); ?>
		<div class="rn-grid rn-grid--3">
			<?php
			while ( $rn_posts->have_posts() ) :
				$rn_posts->the_post();
				rn_post_card();
			endwhile;
			wp_reset_postdata();
			?>
		</div>
		<?php if ( $rn_blog ) : ?>
			<p class="rn-center"><?php echo rn_button( __( 'Toutes les analyses', 'revnefty' ), $rn_blog, 'ghost' ); // phpcs:ignore ?></p>
		<?php endif; ?>
	</div>
</section>
