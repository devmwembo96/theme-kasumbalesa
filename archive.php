<?php
/**
 * Archives (catégories, étiquettes, dates, auteurs).
 *
 * @package Kasumbalesa
 */

get_header();

get_template_part(
	'template-parts/page-hero',
	null,
	array(
		'eyebrow'     => kb_mod( 'news_eyebrow' ),
		'title'       => get_the_archive_title(),
		'description' => get_the_archive_description(),
		'extra'       => 'kb_category_filter',
	)
);

get_template_part( 'template-parts/post-list' );

get_footer();
