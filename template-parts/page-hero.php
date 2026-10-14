<?php
/**
 * En-tête de page (pages, listes, recherche).
 *
 * Arguments : title, eyebrow, description (HTML autorisé), image (ID), extra (callable).
 *
 * @package Kasumbalesa
 */

$kb_args = wp_parse_args(
	$args,
	array(
		'title'       => '',
		'eyebrow'     => '',
		'description' => '',
		'image'       => 0,
		'extra'       => null,
		'links'       => null,
	)
);
$kb_links = is_array( $kb_args['links'] ) ? $kb_args['links'] : kb_hero_links( is_singular() ? get_queried_object_id() : (int) ( is_home() ? get_option( 'page_for_posts' ) : 0 ) );
if ( ! $kb_args['image'] ) {
	$kb_args['image'] = kb_fallback_hero_image();
}
$kb_logo = (int) get_theme_mod( 'custom_logo' );
?>
<header class="kb-page-hero kb-page-hero--wh<?php echo $kb_args['image'] ? ' kb-page-hero--image' : ''; ?>">
	<?php
	if ( $kb_args['image'] ) {
		echo wp_get_attachment_image(
			$kb_args['image'],
			'kb-hero',
			false,
			array(
				'class'         => 'kb-page-hero__img',
				'alt'           => '',
				'sizes'         => '100vw',
				'loading'       => 'eager',
				'fetchpriority' => 'high',
				'decoding'      => 'async',
			)
		);
	}
	?>
	<div class="kb-page-hero__inner kb-container kb-container--wide">
		<?php if ( kb_mod( 'hero_eyebrow' ) ) : ?>
			<p class="kb-eyebrow kb-eyebrow--light kb-hero__eyebrow"><?php echo esc_html( kb_mod( 'hero_eyebrow' ) ); ?></p>
		<?php endif; ?>
		<?php
		if ( $kb_logo ) {
			echo wp_get_attachment_image( $kb_logo, 'medium', false, array( 'class' => 'kb-page-hero__emblem', 'alt' => '', 'loading' => 'eager' ) );
		}
		?>
		<h1 class="kb-page-hero__title"><?php echo wp_kses_post( $kb_args['title'] ); ?></h1>
		<?php if ( $kb_args['description'] ) : ?>
			<div class="kb-page-hero__desc"><?php echo wp_kses_post( $kb_args['description'] ); ?></div>
		<?php endif; ?>
		<?php
		if ( is_callable( $kb_args['extra'] ) ) {
			call_user_func( $kb_args['extra'] );
		}
		?>
		<?php if ( $kb_links ) : ?>
			<div class="kb-hero__actions kb-page-hero__actions">
				<?php
				foreach ( $kb_links as $kb_n => $kb_link ) {
					kb_button( $kb_link['label'], $kb_link['url'], 0 === $kb_n ? 'gold' : 'ghost' );
				}
				?>
			</div>
		<?php endif; ?>
	</div>
</header>
