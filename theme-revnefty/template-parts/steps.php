<?php
/**
 * La méthode en trois étapes.
 *
 * @package RevNefty
 */

$rn_icons = array( 1 => 'search-plan', 2 => 'target', 3 => 'refresh' );
?>
<section class="rn-section" id="methode">
	<div class="rn-wrap">
		<?php rn_section_heading( rn_mod( 'steps_eyebrow' ), rn_mod( 'steps_title' ) ); ?>
		<ol class="rn-steps">
			<?php for ( $i = 1; $i <= 3; $i++ ) : ?>
				<li class="rn-step">
					<span class="rn-step__num"><?php echo esc_html( sprintf( '%02d', $i ) ); ?></span>
					<span class="rn-step__icon"><?php echo rn_icon( $rn_icons[ $i ] ); // phpcs:ignore ?></span>
					<h3 class="rn-step__title"><?php echo esc_html( rn_mod( "step_{$i}_title" ) ); ?></h3>
					<p class="rn-step__text"><?php echo esc_html( rn_mod( "step_{$i}_text" ) ); ?></p>
				</li>
			<?php endfor; ?>
		</ol>
	</div>
</section>
