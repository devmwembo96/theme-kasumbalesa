<?php
/**
 * Page introuvable.
 *
 * @package RevNefty
 */

get_header();
?>
<section class="rn-section rn-404">
	<div class="rn-wrap rn-wrap--narrow rn-center">
		<p class="rn-404__code">404</p>
		<h1 class="rn-pagehead__title"><?php esc_html_e( 'Cette page n’existe pas', 'revnefty' ); ?></h1>
		<p class="rn-pagehead__lead"><?php esc_html_e( 'Le lien est peut-être ancien, ou la page a été déplacée.', 'revnefty' ); ?></p>
		<p><?php echo rn_button( __( 'Retour à l’accueil', 'revnefty' ), home_url( '/' ), 'primary' ); // phpcs:ignore ?></p>
	</div>
</section>
<?php
get_footer();
