<?php
/**
 * En-tête du site.
 *
 * @package Kasumbalesa
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

<a class="kb-skip-link" href="#main"><?php esc_html_e( 'Aller au contenu', 'kasumbalesa' ); ?></a>

<?php
if ( is_front_page() && kb_mod( 'intro_enable' ) ) {
	get_template_part( 'template-parts/intro' );
}
?>

<header class="kb-header kb-header--wh" id="kb-header">
	<?php
	if ( kb_ticker_is_visible() ) {
		get_template_part( 'template-parts/news-ticker' );
	}
	?>
	<div class="kb-header__bar">
		<div class="kb-header__inner kb-container kb-container--wide">
			<div class="kb-header__side kb-header__side--start">
				<button type="button" class="kb-icon-btn kb-menu-toggle" aria-controls="kb-mobile-menu" aria-expanded="false">
					<?php echo kb_icon( 'menu' ); // phpcs:ignore ?>
					<span class="screen-reader-text"><?php esc_html_e( 'Ouvrir le menu', 'kasumbalesa' ); ?></span>
				</button>
			</div>
			<?php kb_site_branding( 'header' ); ?>
			<div class="kb-header__side kb-header__side--end">
				<button type="button" class="kb-icon-btn kb-search-toggle" aria-controls="kb-search" aria-expanded="false">
					<?php echo kb_icon( 'search' ); // phpcs:ignore ?>
					<span class="screen-reader-text"><?php esc_html_e( 'Rechercher', 'kasumbalesa' ); ?></span>
				</button>
			</div>
		</div>
	</div>
	<nav class="kb-header__nav" aria-label="<?php esc_attr_e( 'Menu principal', 'kasumbalesa' ); ?>">
		<div class="kb-header__nav-inner kb-container kb-container--wide">
			<div class="kb-header__nav-scroll" data-nav-scroll>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'container'      => false,
						'menu_class'     => 'kb-nav__menu',
						'menu_id'        => 'kb-primary-menu',
						'depth'          => 1,
						'fallback_cb'    => 'kb_menu_fallback',
					)
				);
				?>
			</div>
			<button type="button" class="kb-header__nav-more" data-nav-more tabindex="-1" aria-hidden="true">
				<?php echo kb_icon( 'chevron-right' ); // phpcs:ignore ?>
			</button>
		</div>
	</nav>
</header>

<div class="kb-search" id="kb-search" hidden>
	<div class="kb-search__inner kb-container">
		<?php get_search_form(); ?>
		<button type="button" class="kb-icon-btn kb-search__close" data-close-search>
			<?php echo kb_icon( 'close' ); // phpcs:ignore ?>
			<span class="screen-reader-text"><?php esc_html_e( 'Fermer la recherche', 'kasumbalesa' ); ?></span>
		</button>
	</div>
</div>

<div class="kb-mobile-menu" id="kb-mobile-menu" hidden>
	<div class="kb-mobile-menu__top">
		<?php kb_site_branding( 'mobile' ); ?>
		<button type="button" class="kb-icon-btn kb-mobile-menu__close" data-close-menu>
			<?php echo kb_icon( 'close' ); // phpcs:ignore ?>
			<span class="screen-reader-text"><?php esc_html_e( 'Fermer le menu', 'kasumbalesa' ); ?></span>
		</button>
	</div>
	<nav class="kb-mobile-menu__nav" aria-label="<?php esc_attr_e( 'Menu principal', 'kasumbalesa' ); ?>">
		<?php
		wp_nav_menu(
			array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'kb-mobile-menu__list',
				'menu_id'        => 'kb-mobile-menu-list',
				'depth'          => 2,
				'fallback_cb'    => 'kb_menu_fallback',
			)
		);
		?>
	</nav>
	<div class="kb-mobile-menu__footer">
		<?php kb_lang_switcher( 'mobile' ); ?>
		<?php kb_contact_list(); ?>
		<?php kb_social_links(); ?>
	</div>
</div>

<main id="main" class="kb-main" tabindex="-1">
