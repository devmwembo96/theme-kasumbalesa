<?php
/**
 * Les outils.
 *
 * @package RevNefty
 */

$rn_icons = array( 1 => 'calendar', 2 => 'shield', 3 => 'journal', 4 => 'bell' );
?>
<section class="rn-section rn-section--alt" id="outils">
	<div class="rn-wrap">
		<?php rn_section_heading( rn_mod( 'pillars_eyebrow' ), rn_mod( 'pillars_title' ) ); ?>
		<div class="rn-grid rn-grid--4">
			<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
				<?php
				if ( ! rn_mod( "pillar_{$i}_title" ) ) {
					continue;
				}
				?>
				<article class="rn-card rn-card--feature">
					<span class="rn-card__icon"><?php echo rn_icon( $rn_icons[ $i ] ); // phpcs:ignore ?></span>
					<h3 class="rn-card__title"><?php echo esc_html( rn_mod( "pillar_{$i}_title" ) ); ?></h3>
					<p class="rn-card__text"><?php echo esc_html( rn_mod( "pillar_{$i}_text" ) ); ?></p>
				</article>
			<?php endfor; ?>
		</div>
	</div>
</section>
