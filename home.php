<?php
/**
 * Liste des actualités (page des articles).
 *
 * @package Kasumbalesa
 */

get_header();

$kb_posts_page = (int) get_option( 'page_for_posts' );
get_template_part(
	'template-parts/page-hero',
	null,
	array(
		'eyebrow'     => kb_mod( 'news_eyebrow' ),
		'title'       => $kb_posts_page ? esc_html( get_the_title( $kb_posts_page ) ) : kb_rich_title( kb_mod( 'news_title' ) ),
		'description' => $kb_posts_page && has_excerpt( $kb_posts_page ) ? wpautop( get_the_excerpt( $kb_posts_page ) ) : '',
		'image'       => $kb_posts_page ? get_post_thumbnail_id( $kb_posts_page ) : 0,
		'extra'       => 'kb_category_filter',
	)
);

get_template_part( 'template-parts/post-list' );

get_footer();
