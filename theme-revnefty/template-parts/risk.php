<?php
/**
 * Cadre de risque, présenté comme les règles d'un challenge prop firm.
 *
 * @package RevNefty
 */

?>
<section class="rn-section" id="cadre">
	<div class="rn-wrap rn-risk">
		<div class="rn-risk__intro">
			<?php rn_section_heading( rn_mod( 'risk_eyebrow' ), rn_mod( 'risk_title' ), rn_mod( 'risk_text' ) ); ?>
		</div>
		<dl class="rn-rules">
			<?php for ( $i = 1; $i <= 6; $i++ ) : ?>
				<?php
				if ( ! rn_mod( "risk_{$i}_value" ) ) {
					continue;
				}
				?>
				<div class="rn-rule">
					<dt><?php echo esc_html( rn_mod( "risk_{$i}_label" ) ); ?></dt>
					<dd><?php echo esc_html( rn_mod( "risk_{$i}_value" ) ); ?></dd>
				</div>
			<?php endfor; ?>
		</dl>
	</div>
</section>
