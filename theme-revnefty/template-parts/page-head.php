<?php
/**
 * En-tête de page intérieure : sur-titre, titre, introduction.
 *
 * @package RevNefty
 */

$rn_args = wp_parse_args(
	$args ?? array(),
	array(
		'eyebrow' => '',
		'title'   => '',
		'lead'    => '',
	)
);
?>
<header class="rn-pagehead">
	<div class="rn-pagehead__glow" aria-hidden="true"></div>
	<div class="rn-wrap rn-wrap--narrow">
		<?php if ( $rn_args['eyebrow'] ) : ?>
			<p class="rn-eyebrow"><?php echo esc_html( $rn_args['eyebrow'] ); ?></p>
		<?php endif; ?>
		<h1 class="rn-pagehead__title"><?php echo esc_html( $rn_args['title'] ); ?></h1>
		<?php if ( $rn_args['lead'] ) : ?>
			<p class="rn-pagehead__lead"><?php echo esc_html( $rn_args['lead'] ); ?></p>
		<?php endif; ?>
	</div>
</header>
