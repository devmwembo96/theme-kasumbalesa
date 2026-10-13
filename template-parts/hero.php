<?php
/**
 * Héros plein écran de l'accueil : diaporama en fondu + effet Ken Burns.
 * Seule la 1re image est chargée d'emblée ; les suivantes sont chargées juste avant leur affichage.
 *
 * @package Kasumbalesa
 */

$kb_slides  = kb_hero_slide_ids();
$kb_overlay = min( 70, absint( kb_mod( 'hero_overlay' ) ) ) / 100;
$kb_btn2    = kb_resolve_url( kb_mod( 'hero_btn2_url' ), kb_news_url() );
?>
<section class="kb-hero<?php echo $kb_slides ? '' : ' kb-hero--plain'; ?>" style="--kb-overlay: <?php echo esc_attr( $kb_overlay ); ?>" aria-labelledby="kb-hero-title" data-hero>
	<?php if ( $kb_slides ) : ?>
		<div class="kb-hero__slides">
			<?php foreach ( $kb_slides as $kb_i => $kb_id ) : ?>
				<figure class="kb-hero__slide<?php echo 0 === $kb_i ? ' is-active' : ''; ?>" aria-hidden="<?php echo 0 === $kb_i ? 'false' : 'true'; ?>">
					<?php
					if ( 0 === $kb_i ) {
						echo wp_get_attachment_image(
							$kb_id,
							'kb-hero',
							false,
							array(
								'class'         => 'kb-hero__img',
								'sizes'         => '100vw',
								'loading'       => 'eager',
								'fetchpriority' => 'high',
								'decoding'      => 'async',
							)
						);
					} else {
						printf(
							'<img class="kb-hero__img" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" data-src="%1$s" data-srcset="%2$s" sizes="100vw" alt="%3$s" width="2400" height="1350" decoding="async">',
							esc_url( wp_get_attachment_image_url( $kb_id, 'kb-hero' ) ),
							esc_attr( (string) wp_get_attachment_image_srcset( $kb_id, 'kb-hero' ) ),
							esc_attr( (string) get_post_meta( $kb_id, '_wp_attachment_image_alt', true ) )
						);
					}
					?>
				</figure>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<div class="kb-hero__veil" aria-hidden="true"></div>

	<div class="kb-hero__content kb-container kb-container--wide">
		<?php if ( kb_mod( 'hero_eyebrow' ) ) : ?>
			<p class="kb-eyebrow kb-eyebrow--light kb-hero__eyebrow"><?php echo esc_html( kb_mod( 'hero_eyebrow' ) ); ?></p>
		<?php endif; ?>
		<h1 class="kb-hero__title" id="kb-hero-title" data-split><?php echo kb_rich_title( kb_mod( 'hero_title' ) ); // phpcs:ignore ?></h1>
		<?php if ( kb_mod( 'hero_subtitle' ) ) : ?>
			<p class="kb-hero__subtitle"><?php echo esc_html( kb_mod( 'hero_subtitle' ) ); ?></p>
		<?php endif; ?>
		<div class="kb-hero__actions">
			<?php
			kb_button( kb_mod( 'hero_btn1_label' ), kb_resolve_url( kb_mod( 'hero_btn1_url' ) ), 'gold' );
			kb_button( kb_mod( 'hero_btn2_label' ), $kb_btn2, 'ghost' );
			?>
		</div>
	</div>

	<?php
	$kb_latest = kb_latest_posts();
	if ( $kb_latest ) :
		$kb_top = $kb_latest[0];
		?>
		<a class="kb-hero__card" href="<?php echo esc_url( get_permalink( $kb_top ) ); ?>">
			<span class="kb-hero__card-media">
				<?php kb_thumbnail( 'kb-thumb', array( 'sizes' => '96px', 'alt' => '' ), $kb_top ); ?>
			</span>
			<span class="kb-hero__card-body">
				<span class="kb-hero__card-label"><span class="kb-ticker__dot" aria-hidden="true"></span><?php echo esc_html( kb_mod( 'ticker_label' ) ); ?></span>
				<span class="kb-hero__card-title"><?php echo esc_html( get_the_title( $kb_top ) ); ?></span>
			</span>
			<span class="kb-hero__card-arrow"><?php echo kb_icon( 'arrow-right' ); // phpcs:ignore ?></span>
		</a>
	<?php endif; ?>

	<div class="kb-hero__footer kb-container kb-container--wide">
		<?php if ( count( $kb_slides ) > 1 ) : ?>
			<div class="kb-hero__controls">
				<ol class="kb-hero__progress" aria-hidden="true">
					<?php foreach ( $kb_slides as $kb_i => $kb_id ) : ?>
						<li class="<?php echo 0 === $kb_i ? 'is-active' : ''; ?>"><span></span></li>
					<?php endforeach; ?>
				</ol>
				<button type="button" class="kb-hero__toggle" aria-pressed="false">
					<span class="kb-hero__icon-pause"><?php echo kb_icon( 'pause' ); // phpcs:ignore ?></span>
					<span class="kb-hero__icon-play"><?php echo kb_icon( 'play' ); // phpcs:ignore ?></span>
					<span class="screen-reader-text"><?php esc_html_e( 'Mettre en pause le diaporama', 'kasumbalesa' ); ?></span>
				</button>
			</div>
		<?php endif; ?>
		<a class="kb-hero__scroll" href="#kb-after-hero">
			<span class="kb-hero__scroll-line" aria-hidden="true"></span>
			<span class="screen-reader-text"><?php esc_html_e( 'Descendre vers le contenu', 'kasumbalesa' ); ?></span>
		</a>
	</div>
</section>
<div id="kb-after-hero"></div>
