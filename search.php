<?php
/**
 * Résultats de recherche.
 *
 * @package Kasumbalesa
 */

get_header();

global $wp_query;

get_template_part(
	'template-parts/page-hero',
	null,
	array(
		'eyebrow'     => __( 'Recherche', 'kasumbalesa' ),
		/* translators: %s: termes recherchés. */
		'title'       => sprintf( __( 'Résultats pour « %s »', 'kasumbalesa' ), '<em>' . esc_html( get_search_query() ) . '</em>' ),
		/* translators: %d: nombre de résultats. */
		'description' => '<p>' . esc_html( sprintf( _n( '%d résultat', '%d résultats', (int) $wp_query->found_posts, 'kasumbalesa' ), (int) $wp_query->found_posts ) ) . '</p>',
		'extra'       => 'get_search_form',
	)
);

get_template_part( 'template-parts/post-list' );

get_footer();
