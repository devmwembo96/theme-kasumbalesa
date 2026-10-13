<?php
/**
 * Gabarit de repli.
 *
 * @package Kasumbalesa
 */

get_header();

get_template_part(
	'template-parts/page-hero',
	null,
	array(
		'eyebrow' => get_bloginfo( 'name' ),
		'title'   => is_singular() ? get_the_title() : wp_get_document_title(),
	)
);

get_template_part( 'template-parts/post-list' );

get_footer();
