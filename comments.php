<?php
/**
 * Commentaires.
 *
 * @package Kasumbalesa
 */

if ( post_password_required() ) {
	return;
}
?>
<div id="comments" class="kb-comments">
	<?php if ( have_comments() ) : ?>
		<h2 class="kb-comments__title">
			<?php
			$kb_count = get_comments_number();
			/* translators: %s: nombre de commentaires. */
			printf( esc_html( _n( '%s commentaire', '%s commentaires', $kb_count, 'kasumbalesa' ) ), esc_html( number_format_i18n( $kb_count ) ) );
			?>
		</h2>
		<ol class="kb-comments__list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 56,
				)
			);
			?>
		</ol>
		<?php the_comments_navigation(); ?>
	<?php endif; ?>

	<?php if ( ! comments_open() && get_comments_number() ) : ?>
		<p class="kb-comments__closed"><?php esc_html_e( 'Les commentaires sont fermés.', 'kasumbalesa' ); ?></p>
	<?php endif; ?>

	<?php
	comment_form(
		array(
			'class_submit'       => 'kb-btn kb-btn--primary',
			'title_reply_before' => '<h2 id="reply-title" class="kb-comments__title">',
			'title_reply_after'  => '</h2>',
		)
	);
	?>
</div>
