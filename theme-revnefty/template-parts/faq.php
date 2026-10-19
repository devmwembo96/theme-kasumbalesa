<?php
/**
 * Questions fréquentes (accordéon natif <details>).
 *
 * @package RevNefty
 */

?>
<section class="rn-section" id="faq">
	<div class="rn-wrap rn-wrap--narrow">
		<?php rn_section_heading( rn_mod( 'faq_eyebrow' ), rn_mod( 'faq_title' ) ); ?>
		<div class="rn-faq">
			<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
				<?php
				if ( ! rn_mod( "faq_{$i}_q" ) ) {
					continue;
				}
				?>
				<details class="rn-faq__item" <?php echo 1 === $i ? 'open' : ''; ?>>
					<summary><?php echo esc_html( rn_mod( "faq_{$i}_q" ) ); ?><?php echo rn_icon( 'chevron' ); // phpcs:ignore ?></summary>
					<p><?php echo esc_html( rn_mod( "faq_{$i}_a" ) ); ?></p>
				</details>
			<?php endfor; ?>
		</div>
	</div>
</section>
