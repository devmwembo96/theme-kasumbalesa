<?php
/**
 * Formulaire de recherche.
 *
 * @package Kasumbalesa
 */

$kb_uid = wp_unique_id( 'kb-search-' );
?>
<form role="search" method="get" class="kb-searchform" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="<?php echo esc_attr( $kb_uid ); ?>"><?php esc_html_e( 'Rechercher sur le site', 'kasumbalesa' ); ?></label>
	<input type="search" id="<?php echo esc_attr( $kb_uid ); ?>" class="kb-searchform__input" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'Rechercher une actualité, une démarche…', 'kasumbalesa' ); ?>" autocomplete="off">
	<button type="submit" class="kb-searchform__submit">
		<?php echo kb_icon( 'search' ); // phpcs:ignore ?>
		<span class="screen-reader-text"><?php esc_html_e( 'Lancer la recherche', 'kasumbalesa' ); ?></span>
	</button>
</form>
