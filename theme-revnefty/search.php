<?php
/**
 * Résultats de recherche.
 *
 * @package RevNefty
 */

get_header();

get_template_part(
	'template-parts/page-head',
	null,
	array(
		'eyebrow' => __( 'Recherche', 'revnefty' ),
		/* translators: %s: termes recherchés. */
		'title'   => sprintf( __( 'Résultats pour « %s »', 'revnefty' ), get_search_query() ),
	)
);
?>
<section class="rn-section rn-section--tight">
	<div class="rn-wrap">
		<div class="rn-wrap--narrow rn-search-again"><?php get_search_form(); ?></div>
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
			<p class="rn-empty"><?php esc_html_e( 'Aucun résultat. Essayez avec d’autres mots.', 'revnefty' ); ?></p>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
