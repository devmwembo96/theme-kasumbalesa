<?php
/**
 * Commentaires.
 *
 * @package RevNefty
 */

if ( post_password_required() ) {
	return;
}
?>
<div class="rn-comments" id="comments">
	<?php if ( have_comments() ) : ?>
		<h2 class="rn-comments__title"><?php echo esc_html( get_comments_number_text( __( 'Aucun commentaire', 'revnefty' ), __( 'Un commentaire', 'revnefty' ), __( '% commentaires', 'revnefty' ) ) ); ?></h2>
		<ol class="rn-comments__list"><?php wp_list_comments( array( 'style' => 'ol', 'short_ping' => true, 'avatar_size' => 40 ) ); ?></ol>
		<?php the_comments_navigation(); ?>
	<?php endif; ?>
	<?php comment_form(); ?>
</div>
