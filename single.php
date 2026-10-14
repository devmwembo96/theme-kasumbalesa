<?php
/**
 * Article : grande image d'en-tête, lecture éditoriale, partage, articles liés.
 *
 * @package Kasumbalesa
 */

get_header();

while ( have_posts() ) :
	the_post();
	$kb_image     = kb_post_image_id();
	$kb_image     = $kb_image ? $kb_image : kb_fallback_hero_image();
	$kb_has_image = (bool) $kb_image;
	?>
	<div class="kb-progress" aria-hidden="true"><span></span></div>
	<article <?php post_class( 'kb-article' ); ?>>
		<header class="kb-post-hero kb-post-hero--wh<?php echo $kb_has_image ? ' kb-post-hero--image' : ''; ?>">
			<?php
			if ( $kb_has_image ) {
				echo wp_get_attachment_image(
					$kb_image,
					'kb-hero',
					false,
					array(
						'class'         => 'kb-post-hero__img',
						'sizes'         => '100vw',
						'loading'       => 'eager',
						'fetchpriority' => 'high',
						'decoding'      => 'async',
					)
				);
			}
			?>
			<div class="kb-post-hero__inner kb-container">
				<?php if ( kb_mod( 'hero_eyebrow' ) ) : ?>
					<p class="kb-eyebrow kb-eyebrow--light kb-hero__eyebrow"><?php echo esc_html( kb_mod( 'hero_eyebrow' ) ); ?></p>
				<?php endif; ?>
				<?php
				if ( get_theme_mod( 'custom_logo' ) ) {
					echo wp_get_attachment_image( (int) get_theme_mod( 'custom_logo' ), 'medium', false, array( 'class' => 'kb-page-hero__emblem', 'alt' => '', 'loading' => 'eager' ) );
				}
				?>
				<div class="kb-meta"><?php kb_category_badge(); ?></div>
				<h1 class="kb-post-hero__title"><?php the_title(); ?></h1>
				<p class="kb-post-hero__meta">
					<?php kb_post_date(); ?>
					<span aria-hidden="true">·</span>
					<span>
						<?php
						/* translators: %d: minutes de lecture. */
						printf( esc_html( _n( '%d minute de lecture', '%d minutes de lecture', kb_reading_time(), 'kasumbalesa' ) ), (int) kb_reading_time() );
						?>
					</span>
				</p>
				<?php $kb_links = kb_hero_links( -1 ); ?>
				<?php if ( $kb_links ) : ?>
					<div class="kb-hero__actions kb-page-hero__actions">
						<?php
						foreach ( $kb_links as $kb_n => $kb_link ) {
							kb_button( $kb_link['label'], $kb_link['url'], 0 === $kb_n ? 'gold' : 'ghost' );
						}
						?>
					</div>
				<?php endif; ?>
			</div>
		</header>

		<?php if ( $kb_has_image && wp_get_attachment_caption( get_post_thumbnail_id() ) ) : ?>
			<p class="kb-post-caption kb-container"><?php echo esc_html( wp_get_attachment_caption( get_post_thumbnail_id() ) ); ?></p>
		<?php endif; ?>

		<div class="kb-article__body">
			<?php if ( has_excerpt() ) : ?>
				<p class="kb-lead kb-container kb-container--narrow" data-reveal><?php echo esc_html( get_the_excerpt() ); ?></p>
			<?php endif; ?>

			<div class="kb-entry">
				<?php
				the_content();
				wp_link_pages(
					array(
						'before' => '<nav class="kb-page-links">' . esc_html__( 'Pages :', 'kasumbalesa' ),
						'after'  => '</nav>',
					)
				);
				?>
			</div>

			<footer class="kb-article__footer kb-container kb-container--narrow">
				<?php
				$kb_tags = get_the_tag_list( '<ul class="kb-tags"><li>', '</li><li>', '</li></ul>' );
				if ( $kb_tags && ! is_wp_error( $kb_tags ) ) {
					echo $kb_tags; // phpcs:ignore WordPress.Security.EscapeOutput
				}
				kb_share_links();
				?>
			</footer>
		</div>

		<?php
		$kb_prev = get_previous_post();
		$kb_next = get_next_post();
		if ( $kb_prev || $kb_next ) :
			?>
			<nav class="kb-post-nav" aria-label="<?php esc_attr_e( 'Articles voisins', 'kasumbalesa' ); ?>">
				<div class="kb-container kb-container--wide kb-post-nav__grid<?php echo ( $kb_prev && $kb_next ) ? '' : ' kb-post-nav__grid--single'; ?>">
					<?php foreach ( array( 'prev' => $kb_prev, 'next' => $kb_next ) as $kb_dir => $kb_adj ) : ?>
						<?php if ( $kb_adj ) : ?>
							<a class="kb-post-nav__link kb-post-nav__link--<?php echo esc_attr( $kb_dir ); ?>" href="<?php echo esc_url( get_permalink( $kb_adj ) ); ?>">
								<span class="kb-post-nav__label">
									<?php
									if ( 'prev' === $kb_dir ) {
										echo kb_icon( 'arrow-left' ); // phpcs:ignore
										esc_html_e( 'Article précédent', 'kasumbalesa' );
									} else {
										esc_html_e( 'Article suivant', 'kasumbalesa' );
										echo kb_icon( 'arrow-right' ); // phpcs:ignore
									}
									?>
								</span>
								<span class="kb-post-nav__title"><?php echo esc_html( get_the_title( $kb_adj ) ); ?></span>
							</a>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			</nav>
		<?php endif; ?>
	</article>

	<?php
	$kb_cat     = kb_primary_category();
	$kb_related = new WP_Query(
		array(
			'post_type'           => 'post',
			'posts_per_page'      => 3,
			'post__not_in'        => array( get_the_ID() ),
			'cat'                 => $kb_cat ? $kb_cat->term_id : 0,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);
	if ( $kb_related->have_posts() ) :
		?>
		<section class="kb-section kb-section--paper kb-related" aria-labelledby="kb-related-title">
			<div class="kb-container kb-container--wide">
				<?php kb_section_heading( kb_mod( 'news_eyebrow' ), __( 'À lire aussi', 'kasumbalesa' ), 'kb-related-title' ); ?>
				<div class="kb-grid kb-grid--3">
					<?php
					while ( $kb_related->have_posts() ) :
						$kb_related->the_post();
						get_template_part( 'template-parts/content-card' );
					endwhile;
					wp_reset_postdata();
					?>
				</div>
			</div>
		</section>
		<?php
	endif;

	if ( comments_open() || get_comments_number() ) :
		?>
		<section class="kb-section kb-section--white">
			<div class="kb-container kb-container--narrow"><?php comments_template(); ?></div>
		</section>
		<?php
	endif;
endwhile;

get_footer();
