<?php
/**
 * Pied de page du site.
 *
 * @package RevNefty
 */

$rn_credit = trim( (string) rn_mod( 'footer_credit' ) );
$rn_credit = $rn_credit ? $rn_credit : '© ' . gmdate( 'Y' ) . ' ' . get_bloginfo( 'name' ) . '. ' . __( 'Tous droits réservés.', 'revnefty' );
?>
</main>

<footer class="rn-footer" id="contact">
	<div class="rn-footer__top rn-wrap">
		<div class="rn-footer__brand">
			<?php rn_brand( 'footer' ); ?>
			<?php if ( rn_mod( 'footer_about' ) ) : ?>
				<p class="rn-footer__about"><?php echo esc_html( rn_mod( 'footer_about' ) ); ?></p>
			<?php endif; ?>
		</div>

		<div class="rn-footer__col">
			<h2 class="rn-footer__title"><?php esc_html_e( 'Contact', 'revnefty' ); ?></h2>
			<?php rn_contact_block(); ?>
			<?php echo rn_button( rn_mod( 'cta_label' ), rn_contact_url(), 'ghost rn-btn--sm' ); // phpcs:ignore ?>
		</div>

		<nav class="rn-footer__col" aria-label="<?php esc_attr_e( 'Liens', 'revnefty' ); ?>">
			<h2 class="rn-footer__title"><?php esc_html_e( 'Liens', 'revnefty' ); ?></h2>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => has_nav_menu( 'footer' ) ? 'footer' : 'primary',
					'container'      => false,
					'menu_class'     => 'rn-footer__menu',
					'depth'          => 1,
					'fallback_cb'    => 'rn_menu_fallback',
				)
			);
			?>
		</nav>
	</div>

	<?php if ( rn_mod( 'risk_disclaimer' ) ) : ?>
		<div class="rn-footer__risk rn-wrap">
			<p><?php echo esc_html( rn_mod( 'risk_disclaimer' ) ); ?></p>
		</div>
	<?php endif; ?>

	<div class="rn-footer__bottom rn-wrap">
		<p><?php echo esc_html( $rn_credit ); ?></p>
		<?php
		if ( has_nav_menu( 'legal' ) ) {
			wp_nav_menu(
				array(
					'theme_location'       => 'legal',
					'container'            => 'nav',
					'container_aria_label' => __( 'Mentions légales', 'revnefty' ),
					'menu_class'           => 'rn-footer__legal',
					'depth'                => 1,
				)
			);
		}
		?>
		<a class="rn-totop" href="#main">
			<?php echo rn_icon( 'arrow-up' ); // phpcs:ignore ?>
			<span class="screen-reader-text"><?php esc_html_e( 'Retour en haut', 'revnefty' ); ?></span>
		</a>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
