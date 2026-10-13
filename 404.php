<?php
/**
 * Page introuvable.
 *
 * @package Kasumbalesa
 */

get_header();

$kb_slides = kb_hero_slide_ids();
?>
<section class="kb-404">
	<?php
	if ( $kb_slides ) {
		echo wp_get_attachment_image(
			$kb_slides[0],
			'kb-hero',
			false,
			array(
				'class'   => 'kb-404__bg',
				'alt'     => '',
				'sizes'   => '100vw',
				'loading' => 'eager',
			)
		);
	}
	?>
	<div class="kb-404__inner kb-container kb-container--narrow">
		<p class="kb-404__code" aria-hidden="true">404</p>
		<h1 class="kb-404__title"><?php esc_html_e( 'Cette page est introuvable', 'kasumbalesa' ); ?></h1>
		<p class="kb-404__text"><?php esc_html_e( 'Le lien est peut-être ancien ou la page a été déplacée. Utilisez la recherche ou revenez à l’accueil.', 'kasumbalesa' ); ?></p>
		<div class="kb-404__form"><?php get_search_form(); ?></div>
		<div class="kb-404__actions">
			<?php
			kb_button( __( 'Retour à l’accueil', 'kasumbalesa' ), home_url( '/' ), 'gold' );
			kb_button( kb_mod( 'news_all_label' ), kb_news_url(), 'ghost' );
			?>
		</div>
	</div>
</section>
<?php
get_footer();
