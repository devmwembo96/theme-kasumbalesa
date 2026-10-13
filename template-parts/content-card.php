<?php
/**
 * Carte d'article (listes, articles liés, recherche).
 *
 * Arguments : featured (bool) pour la grande carte en tête de liste.
 *
 * @package Kasumbalesa
 */

$kb_featured = ! empty( $args['featured'] );
?>
<article <?php post_class( 'kb-card' . ( $kb_featured ? ' kb-card--featured' : '' ) ); ?> data-reveal>
	<a class="kb-card__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
		<?php kb_thumbnail( $kb_featured ? 'kb-wide' : 'kb-card', array( 'sizes' => $kb_featured ? '(min-width: 1000px) 60vw, 100vw' : '(min-width: 1000px) 33vw, (min-width: 640px) 50vw, 100vw' ) ); ?>
	</a>
	<div class="kb-card__body">
		<div class="kb-meta">
			<?php
			if ( 'post' === get_post_type() ) {
				kb_category_badge( null, false );
				kb_post_date();
			} else {
				$kb_type = get_post_type_object( get_post_type() );
				echo '<span class="kb-badge">' . esc_html( $kb_type ? $kb_type->labels->singular_name : '' ) . '</span>';
			}
			?>
		</div>
		<h2 class="kb-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
		<?php if ( has_excerpt() || get_the_content() ) : ?>
			<p class="kb-card__excerpt"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt() ) ); ?></p>
		<?php endif; ?>
		<span class="kb-link-arrow" aria-hidden="true"><?php esc_html_e( 'Lire', 'kasumbalesa' ); ?><?php echo kb_icon( 'arrow-right' ); // phpcs:ignore ?></span>
	</div>
</article>
