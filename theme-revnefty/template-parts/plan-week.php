<?php
/**
 * Résumé du plan de la semaine (biais, probabilités, invalidation), lu dans le plugin Carnet XAU.
 * Rien ne s'affiche si le plugin est absent.
 *
 * @package RevNefty
 */

if ( ! rn_carnet_url() || ! class_exists( 'CX_Repo' ) || ! class_exists( 'CX_Live' ) ) {
	return;
}

// Plan de la semaine (la suivante le week-end, comme dans le carnet).
$rn_week = CX_Live::week();
$rn_plan = CX_Repo::plan( $rn_week );
$rn_c    = is_array( $rn_plan['content'] ?? null ) ? $rn_plan['content'] : array();
$rn_pred = $rn_c['prediction'] ?? null;
$rn_bias = '';
if ( $rn_pred ) {
	$rn_probs = array(
		'haussier' => (int) ( $rn_pred['bull'] ?? 0 ),
		'baissier' => (int) ( $rn_pred['bear'] ?? 0 ),
		'range'    => (int) ( $rn_pred['range'] ?? 0 ),
	);
	arsort( $rn_probs );
	$rn_bias = (string) key( $rn_probs );
}
$rn_text = trim( (string) ( $rn_pred['summary'] ?? '' ) );
?>
<section class="rn-section rn-wplan-sec" id="plan">
	<div class="rn-wrap rn-wrap--narrow">
		<a class="rn-wplan" href="<?php echo esc_url( rn_carnet_url() ); ?>">
			<p class="rn-wplan__label"><?php echo rn_icon( 'calendar' ); // phpcs:ignore ?><?php esc_html_e( 'Plan de la semaine', 'revnefty' ); ?>
				<span><?php echo esc_html( sprintf( /* translators: %s: date du lundi. */ __( 'du %s', 'revnefty' ), wp_date( 'j F', strtotime( $rn_week ) ) ) ); ?></span>
			</p>
			<?php if ( $rn_bias ) : ?>
				<p class="rn-wplan__bias rn-wplan__bias--<?php echo esc_attr( $rn_bias ); ?>"><?php echo esc_html( ucfirst( $rn_bias ) . ' ' . $rn_probs[ $rn_bias ] . ' %' ); ?></p>
				<p class="rn-wplan__probs">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: haussier, 2: range, 3: baissier (en %). */
							__( 'Haussier %1$d %% · Range %2$d %% · Baissier %3$d %%', 'revnefty' ),
							$rn_probs['haussier'],
							$rn_probs['range'],
							$rn_probs['baissier']
						)
					);
					?>
				</p>
				<?php if ( $rn_text ) : ?>
					<p class="rn-wplan__text"><?php echo esc_html( wp_trim_words( $rn_text, 40, '…' ) ); ?></p>
				<?php endif; ?>
				<?php if ( isset( $rn_pred['invalidation'] ) && '' !== $rn_pred['invalidation'] ) : ?>
					<p class="rn-wplan__inv"><?php esc_html_e( 'Invalidation', 'revnefty' ); ?> <b><?php echo esc_html( number_format( (float) $rn_pred['invalidation'], 2, ',', ' ' ) ); ?></b></p>
				<?php endif; ?>
			<?php else : ?>
				<p class="rn-wplan__text"><?php esc_html_e( 'Le plan de la semaine sera publié dimanche.', 'revnefty' ); ?></p>
			<?php endif; ?>
			<span class="rn-wplan__more"><?php esc_html_e( 'Voir le plan complet', 'revnefty' ); ?> <?php echo rn_icon( 'arrow-right' ); // phpcs:ignore ?></span>
		</a>
	</div>
</section>
