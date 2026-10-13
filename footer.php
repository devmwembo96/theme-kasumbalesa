<?php
/**
 * Pied de page du site.
 *
 * @package Kasumbalesa
 */

$kb_about = kb_mod( 'footer_about' );
$kb_about = $kb_about ? $kb_about : get_bloginfo( 'description' );
?>
</main>

<footer class="kb-footer">
	<div class="kb-footer__top kb-container kb-container--wide">
		<div class="kb-footer__brand">
			<?php kb_site_branding( 'footer' ); ?>
			<?php if ( $kb_about ) : ?>
				<p class="kb-footer__about"><?php echo nl2br( esc_html( $kb_about ) ); ?></p>
			<?php endif; ?>
			<?php kb_social_links( 'kb-social--footer' ); ?>
		</div>

		<div class="kb-footer__col">
			<h2 class="kb-footer__title"><?php esc_html_e( 'Nous contacter', 'kasumbalesa' ); ?></h2>
			<?php kb_contact_list(); ?>
		</div>

		<?php if ( has_nav_menu( 'footer' ) ) : ?>
			<nav class="kb-footer__col" aria-label="<?php esc_attr_e( 'Liens du pied de page', 'kasumbalesa' ); ?>">
				<h2 class="kb-footer__title"><?php echo esc_html( wp_get_nav_menu_name( 'footer' ) ); ?></h2>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'footer',
						'container'      => false,
						'menu_class'     => 'kb-footer__menu',
						'depth'          => 1,
					)
				);
				?>
			</nav>
		<?php endif; ?>

		<?php if ( is_active_sidebar( 'footer-1' ) ) : ?>
			<div class="kb-footer__col kb-footer__widgets">
				<?php dynamic_sidebar( 'footer-1' ); ?>
			</div>
		<?php endif; ?>
	</div>

	<div class="kb-footer__bottom">
		<div class="kb-container kb-container--wide kb-footer__bottom-inner">
			<p class="kb-footer__credit"><?php echo esc_html( kb_footer_credit() ); ?></p>
			<?php
			if ( has_nav_menu( 'legal' ) ) {
				wp_nav_menu(
					array(
						'theme_location'  => 'legal',
						'container'       => 'nav',
						'container_class' => 'kb-footer__legal',
						'container_aria_label' => __( 'Mentions légales', 'kasumbalesa' ),
						'menu_class'      => 'kb-footer__legal-menu',
						'depth'           => 1,
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
