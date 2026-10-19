<?php
/**
 * Template Name: Application plein écran
 * Template Post Type: page
 *
 * Page sans en-tête ni pied de page du site : l'outil occupe tout l'écran, comme une
 * application. Pensé pour les pages installables depuis Chrome (« Ajouter à l'écran
 * d'accueil »), par exemple le carnet de trading.
 *
 * @package RevNefty
 */

?>
<!doctype html>
<html <?php language_attributes(); ?> class="no-js">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<main id="main" class="rn-app-main">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<h1 class="screen-reader-text"><?php the_title(); ?></h1>
		<?php
		the_content();
	endwhile;
	?>
</main>

<?php wp_footer(); ?>
</body>
</html>
