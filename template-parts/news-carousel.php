<?php
/**
 * Actualités : carrousel de l'article vedette (5 derniers articles) + cartes.
 *
 * @package Kasumbalesa
 */

$kb_posts = kb_latest_posts();
if ( ! $kb_posts ) {
	return;
}
$kb_count = count( $kb_posts );
?>
<section class="kb-section kb-news" aria-labelledby="kb-news-title">
	<div class="kb-container kb-container--wide">
		<div class="kb-news__head">
			<?php kb_section_heading( kb_mod( 'news_eyebrow' ), kb_mod( 'news_title' ), 'kb-news-title' ); ?>
			<div class="kb-news__all" data-reveal>
				<?php kb_button( kb_mod( 'news_all_label' ), kb_news_url(), 'outline' ); ?>
			</div>
		</div>

		<div class="kb-news__grid">
			<div class="kb-carousel" data-carousel data-interval="6500" role="region" aria-roledescription="<?php esc_attr_e( 'carrousel', 'kasumbalesa' ); ?>" aria-label="<?php esc_attr_e( 'Articles à la une', 'kasumbalesa' ); ?>" data-reveal>
				<div class="kb-carousel__viewport" aria-live="off">
					<?php foreach ( $kb_posts as $kb_i => $kb_post ) : ?>
						<article class="kb-carousel__slide<?php echo 0 === $kb_i ? ' is-active' : ''; ?>" role="group" aria-roledescription="<?php esc_attr_e( 'diapositive', 'kasumbalesa' ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: position, 2: total. */ __( '%1$d sur %2$d', 'kasumbalesa' ), $kb_i + 1, $kb_count ) ); ?>" aria-hidden="<?php echo 0 === $kb_i ? 'false' : 'true'; ?>">
							<a class="kb-carousel__media" href="<?php echo esc_url( get_permalink( $kb_post ) ); ?>" tabindex="-1" aria-hidden="true">
								<?php kb_thumbnail( 'kb-wide', array( 'sizes' => '(min-width: 1100px) 60vw, 100vw' ), $kb_post ); ?>
							</a>
							<div class="kb-carousel__body">
								<div class="kb-meta">
									<?php kb_category_badge( $kb_post ); ?>
									<?php kb_post_date( $kb_post ); ?>
								</div>
								<h3 class="kb-carousel__title"><a href="<?php echo esc_url( get_permalink( $kb_post ) ); ?>"><?php echo esc_html( get_the_title( $kb_post ) ); ?></a></h3>
								<p class="kb-carousel__excerpt"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt( $kb_post ) ) ); ?></p>
							</div>
						</article>
					<?php endforeach; ?>
				</div>

				<?php if ( $kb_count > 1 ) : ?>
					<div class="kb-carousel__controls">
						<div class="kb-carousel__dots">
							<?php foreach ( $kb_posts as $kb_i => $kb_post ) : ?>
								<button type="button" class="kb-carousel__dot"<?php echo 0 === $kb_i ? ' aria-current="true"' : ''; ?>>
									<span class="screen-reader-text"><?php echo esc_html( sprintf( /* translators: %d: numéro de l'article. */ __( 'Afficher l’article %d', 'kasumbalesa' ), $kb_i + 1 ) ); ?></span>
								</button>
							<?php endforeach; ?>
						</div>
						<div class="kb-carousel__arrows">
							<button type="button" class="kb-carousel__btn" data-carousel-toggle aria-pressed="false">
								<span class="kb-carousel__icon-pause"><?php echo kb_icon( 'pause' ); // phpcs:ignore ?></span>
								<span class="kb-carousel__icon-play"><?php echo kb_icon( 'play' ); // phpcs:ignore ?></span>
								<span class="screen-reader-text"><?php esc_html_e( 'Mettre en pause', 'kasumbalesa' ); ?></span>
							</button>
							<button type="button" class="kb-carousel__btn" data-carousel-prev>
								<?php echo kb_icon( 'arrow-left' ); // phpcs:ignore ?>
								<span class="screen-reader-text"><?php esc_html_e( 'Article précédent', 'kasumbalesa' ); ?></span>
							</button>
							<button type="button" class="kb-carousel__btn" data-carousel-next>
								<?php echo kb_icon( 'arrow-right' ); // phpcs:ignore ?>
								<span class="screen-reader-text"><?php esc_html_e( 'Article suivant', 'kasumbalesa' ); ?></span>
							</button>
						</div>
					</div>
					<div class="kb-carousel__timer" aria-hidden="true"><span></span></div>
				<?php endif; ?>
			</div>

			<?php if ( $kb_count > 1 ) : ?>
				<ul class="kb-news__list">
					<?php foreach ( array_slice( $kb_posts, 1 ) as $kb_post ) : ?>
						<li data-reveal>
							<article class="kb-mini-card">
								<a class="kb-mini-card__media" href="<?php echo esc_url( get_permalink( $kb_post ) ); ?>" tabindex="-1" aria-hidden="true">
									<?php kb_thumbnail( 'kb-card', array( 'sizes' => '(min-width: 1100px) 180px, 34vw' ), $kb_post ); ?>
								</a>
								<div class="kb-mini-card__body">
									<div class="kb-meta">
										<?php kb_category_badge( $kb_post, false ); ?>
										<?php kb_post_date( $kb_post ); ?>
									</div>
									<h3 class="kb-mini-card__title"><a href="<?php echo esc_url( get_permalink( $kb_post ) ); ?>"><?php echo esc_html( get_the_title( $kb_post ) ); ?></a></h3>
								</div>
							</article>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>
</section>
