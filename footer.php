<?php
/**
 * Pied de page du site.
 *
 * @package Kasumbalesa
 */

// Texte de présentation : jamais la devise seule, déjà affichée sous le nom.
$kb_about = trim( (string) kb_mod( 'footer_about' ) );
$kb_about = ( get_bloginfo( 'description' ) === $kb_about ) ? '' : $kb_about;
?>
</main>

<?php get_template_part( 'template-parts/flip-card' ); ?>

<footer class="kb-footer">
	<div class="kb-footer__top kb-container kb-container--wide">
		<div class="kb-footer__brand">
			<?php get_template_part( 'template-parts/footer-heritage' ); ?>
			<?php if ( $kb_about ) : ?>
				<p class="kb-footer__about"><?php echo nl2br( esc_html( $kb_about ) ); ?></p>
			<?php endif; ?>
		</div>

		<div class="kb-footer__col kb-footer__col--contact">
			<h2 class="kb-footer__title"><?php esc_html_e( 'Nous contacter', 'kasumbalesa' ); ?></h2>
			<?php kb_contact_list(); ?>
		</div>

		<?php
		$kb_links_location = has_nav_menu( 'footer' ) ? 'footer' : ( has_nav_menu( 'primary' ) ? 'primary' : '' );
		if ( $kb_links_location ) :
			?>
			<nav class="kb-footer__col kb-footer__col--links" aria-label="<?php esc_attr_e( 'Liens utiles', 'kasumbalesa' ); ?>">
				<h2 class="kb-footer__title"><?php esc_html_e( 'Liens utiles', 'kasumbalesa' ); ?></h2>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => $kb_links_location,
						'container'      => false,
						'menu_class'     => 'kb-footer__menu',
						'depth'          => 1,
					)
				);
				?>
			</nav>
		<?php endif; ?>
	</div>

	<div class="kb-footer__bottom">
		<div class="kb-container kb-container--wide kb-footer__bottom-inner">
			<p class="kb-footer__credit"><?php echo esc_html( kb_footer_credit() ); ?></p>
			<?php
			if ( has_nav_menu( 'legal' ) ) {
				wp_nav_menu(
					array(
						'theme_location'       => 'legal',
						'container'            => 'nav',
						'container_class'      => 'kb-footer__legal',
						'container_aria_label' => __( 'Mentions légales', 'kasumbalesa' ),
						'menu_class'           => 'kb-footer__legal-menu',
						'depth'                => 1,
					)
				);
			}
			?>
			<a class="kb-back-to-top" href="#main">
				<?php echo kb_icon( 'arrow-up' ); // phpcs:ignore ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Retour en haut de page', 'kasumbalesa' ); ?></span>
			</a>
		</div>
	</div>
</footer>

<?php
if ( is_front_page() && kb_mod( 'popup_enable' ) && kb_get_mayor_page() ) {
	get_template_part( 'template-parts/mayor-modal' );
}

wp_footer();
?>
</body>
</html>
