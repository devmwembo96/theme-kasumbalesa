<?php
/**
 * En-tête du site.
 *
 * @package RevNefty
 */

?>
<!doctype html>
<html <?php language_attributes(); ?> class="no-js">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="rn-skip" href="#main"><?php esc_html_e( 'Aller au contenu', 'revnefty' ); ?></a>

<header class="rn-header" id="rn-header">
	<div class="rn-header__inner rn-wrap">
		<?php rn_brand( 'header' ); ?>
		<nav class="rn-nav" aria-label="<?php esc_attr_e( 'Menu principal', 'revnefty' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'rn-nav__menu',
					'depth'          => 1,
					'fallback_cb'    => 'rn_menu_fallback',
				)
			);
			?>
		</nav>
		<div class="rn-header__actions">
			<?php
			echo rn_carnet_url()
				? rn_button( __( 'Carnet', 'revnefty' ), rn_carnet_url(), 'primary rn-btn--sm rn-header__cta', 'journal' ) // phpcs:ignore
				: rn_button( rn_mod( 'cta_label' ), rn_contact_url(), 'primary rn-btn--sm rn-header__cta rn-hide-sm', '' ); // phpcs:ignore
			?>
			<button type="button" class="rn-icon-btn rn-menu-toggle" aria-controls="rn-drawer" aria-expanded="false">
				<?php echo rn_icon( 'menu' ); // phpcs:ignore ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Ouvrir le menu', 'revnefty' ); ?></span>
			</button>
		</div>
	</div>
</header>

<div class="rn-drawer" id="rn-drawer" hidden>
	<div class="rn-drawer__top rn-wrap">
		<?php rn_brand( 'drawer' ); ?>
		<button type="button" class="rn-icon-btn" data-close-menu>
			<?php echo rn_icon( 'close' ); // phpcs:ignore ?>
			<span class="screen-reader-text"><?php esc_html_e( 'Fermer le menu', 'revnefty' ); ?></span>
		</button>
	</div>
	<nav class="rn-drawer__nav rn-wrap" aria-label="<?php esc_attr_e( 'Menu principal', 'revnefty' ); ?>">
		<?php
		wp_nav_menu(
			array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'rn-drawer__menu',
				'depth'          => 2,
				'fallback_cb'    => 'rn_menu_fallback',
			)
		);
		?>
	</nav>
	<div class="rn-drawer__foot rn-wrap">
		<?php if ( rn_carnet_url() ) : ?>
			<?php echo rn_button( __( 'Ouvrir le carnet', 'revnefty' ), rn_carnet_url(), 'primary rn-btn--block', 'journal' ); // phpcs:ignore ?>
		<?php endif; ?>
		<?php echo rn_button( rn_mod( 'cta_label' ), rn_contact_url(), 'ghost rn-btn--block' ); // phpcs:ignore ?>
		<?php rn_contact_block(); ?>
	</div>
</div>

<main id="main" class="rn-main" tabindex="-1">
