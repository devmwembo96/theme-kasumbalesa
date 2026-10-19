<?php
/**
 * Formulaire de recherche.
 *
 * @package RevNefty
 */

?>
<form role="search" method="get" class="rn-searchform" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="rn-s"><?php esc_html_e( 'Rechercher', 'revnefty' ); ?></label>
	<input type="search" id="rn-s" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'Rechercher…', 'revnefty' ); ?>">
	<button type="submit"><?php echo rn_icon( 'search' ); // phpcs:ignore ?><span class="screen-reader-text"><?php esc_html_e( 'Rechercher', 'revnefty' ); ?></span></button>
</form>
