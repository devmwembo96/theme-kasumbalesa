<?php
/**
 * Bande défilante : titres des 5 derniers articles.
 *
 * @package Kasumbalesa
 */

$kb_posts    = kb_latest_posts();
$kb_speeds   = array(
	'slow'   => 12,
	'normal' => 8,
	'fast'   => 5,
);
$kb_speed    = kb_mod( 'ticker_speed' );
$kb_duration = count( $kb_posts ) * ( isset( $kb_speeds[ $kb_speed ] ) ? $kb_speeds[ $kb_speed ] : 8 );
?>
<div class="kb-ticker" role="region" aria-label="<?php esc_attr_e( 'Dernières actualités', 'kasumbalesa' ); ?>">
	<div class="kb-ticker__inner kb-container kb-container--wide">
		<?php if ( kb_mod( 'ticker_label' ) ) : ?>
			<p class="kb-ticker__label"><span class="kb-ticker__dot" aria-hidden="true"></span><?php echo esc_html( kb_mod( 'ticker_label' ) ); ?></p>
		<?php endif; ?>
		<div class="kb-ticker__viewport">
			<div class="kb-ticker__track" style="--kb-ticker-duration: <?php echo esc_attr( $kb_duration ); ?>s">
				<?php for ( $kb_pass = 0; $kb_pass < 2; $kb_pass++ ) : ?>
					<ul class="kb-ticker__list"<?php echo $kb_pass ? ' aria-hidden="true"' : ''; ?>>
						<?php foreach ( $kb_posts as $kb_post ) : ?>
							<li class="kb-ticker__item">
								<a href="<?php echo esc_url( get_permalink( $kb_post ) ); ?>"<?php echo $kb_pass ? ' tabindex="-1"' : ''; ?>>
									<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $kb_post ) ); ?>"><?php echo esc_html( get_the_date( 'd.m', $kb_post ) ); ?></time>
									<?php echo esc_html( get_the_title( $kb_post ) ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endfor; ?>
			</div>
		</div>
		<button type="button" class="kb-ticker__toggle" aria-pressed="false">
			<span class="kb-ticker__icon-pause"><?php echo kb_icon( 'pause' ); // phpcs:ignore ?></span>
			<span class="kb-ticker__icon-play"><?php echo kb_icon( 'play' ); // phpcs:ignore ?></span>
			<span class="screen-reader-text"><?php esc_html_e( 'Mettre en pause le défilement des actualités', 'kasumbalesa' ); ?></span>
		</button>
	</div>
</div>
