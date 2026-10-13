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
	)
);
?>
<header class="kb-page-hero<?php echo $kb_args['image'] ? ' kb-page-hero--image' : ''; ?>">
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
		<?php if ( $kb_args['eyebrow'] ) : ?>
			<p class="kb-eyebrow kb-eyebrow--light"><?php echo esc_html( $kb_args['eyebrow'] ); ?></p>
		<?php endif; ?>
		<h1 class="kb-page-hero__title"><?php echo wp_kses_post( $kb_args['title'] ); ?></h1>
		<?php if ( $kb_args['description'] ) : ?>
			<div class="kb-page-hero__desc"><?php echo wp_kses_post( $kb_args['description'] ); ?></div>
		<?php endif; ?>
		<?php
		if ( is_callable( $kb_args['extra'] ) ) {
			call_user_func( $kb_args['extra'] );
		}
		?>
	</div>
</header>
