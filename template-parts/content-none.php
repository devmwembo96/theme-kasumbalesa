<?php
/**
 * Aucun résultat.
 *
 * @package Kasumbalesa
 */

?>
<div class="kb-empty" data-reveal>
	<span class="kb-empty__icon" aria-hidden="true"><?php echo kb_icon( 'search' ); // phpcs:ignore ?></span>
	<h2 class="kb-empty__title">
		<?php is_search() ? esc_html_e( 'Aucun résultat pour cette recherche', 'kasumbalesa' ) : esc_html_e( 'Aucun contenu publié pour le moment', 'kasumbalesa' ); ?>
	</h2>
	<p><?php esc_html_e( 'Essayez avec d’autres mots-clés ou revenez à l’accueil.', 'kasumbalesa' ); ?></p>
	<div class="kb-empty__form"><?php get_search_form(); ?></div>
	<?php kb_button( __( 'Retour à l’accueil', 'kasumbalesa' ), home_url( '/' ), 'primary' ); ?>
</div>
