<?php
/**
 * « Le carnet en direct » : plan de Claude de la semaine, statistiques du journal et signaux
 * Claude, lus dans le plugin Carnet XAU. Rien ne s'affiche si le plugin est absent.
 *
 * @package RevNefty
 */

if ( ! rn_carnet_url() || ! class_exists( 'CX_Repo' ) || ! class_exists( 'CX_Live' ) ) {
	return;
}

$rn_nf = static function ( $v, $dec = 1 ) {
	return ( $v < 0 ? '−' : '' ) . number_format( abs( (float) $v ), $dec, ',', ' ' );
};
$rn_r  = static function ( $v ) use ( $rn_nf ) {
	return ( $v > 0 ? '+' : '' ) . $rn_nf( $v, 1 ) . 'R';
};

// Plan de la semaine (la suivante le week-end, comme dans le carnet).
$rn_week = CX_Live::week();
$rn_plan = CX_Repo::plan( $rn_week );
$rn_c    = is_array( $rn_plan['content'] ?? null ) ? $rn_plan['content'] : array();
$rn_pred = $rn_c['prediction'] ?? null;
$rn_bias = '';
if ( $rn_pred ) {
	$rn_probs = array(
		'haussier' => (int) $rn_pred['bull'],
		'baissier' => (int) $rn_pred['bear'],
		'range'    => (int) $rn_pred['range'],
	);
	arsort( $rn_probs );
	$rn_bias = (string) key( $rn_probs );
}
$rn_zones = array_slice( (array) ( $rn_c['zones'] ?? array() ), 0, 4 );
usort( $rn_zones, static fn( $a, $b ) => $b['high'] <=> $a['high'] );

// Journal du compte actif.
$rn_acc    = class_exists( 'CX_Telegram' ) ? CX_Telegram::active() : null;
$rn_trades = $rn_acc ? CX_Repo::trades( $rn_acc['id'] ) : array();
$rn_n      = count( $rn_trades );
$rn_rs     = array_map( static fn( $t ) => (float) $t['r'], $rn_trades );
$rn_wins   = count( array_filter( $rn_rs, static fn( $r ) => $r > 0 ) );
$rn_pos    = array_sum( array_filter( $rn_rs, static fn( $r ) => $r > 0 ) );
$rn_neg    = -array_sum( array_filter( $rn_rs, static fn( $r ) => $r < 0 ) );

// Signaux Claude (trades virtuels de l'EA).
$rn_sig    = CX_Live::list_signals()->get_data();
$rn_closed = array_values( array_filter( $rn_sig, static fn( $s ) => in_array( $s['status'], array( 'gagne', 'perdu', 'be' ), true ) ) );
$rn_swon   = count( array_filter( $rn_closed, static fn( $s ) => (float) $s['r'] > 0 ) );
$rn_sopen  = count( array_filter( $rn_sig, static fn( $s ) => in_array( $s['status'], array( 'attente', 'ouvert' ), true ) ) );

$rn_url = rn_carnet_url();
?>
<section class="rn-section rn-live-sec" id="carnet">
	<div class="rn-wrap">
		<div class="rn-live-sec__head">
			<?php rn_section_heading( __( 'Le carnet en direct', 'revnefty' ), __( 'Le plan, le journal et les signaux, *à jour*', 'revnefty' ), __( 'Les données du carnet de trading, telles qu’elles sont en ce moment : rien n’est retouché.', 'revnefty' ) ); ?>
			<?php echo rn_button( __( 'Ouvrir le carnet', 'revnefty' ), $rn_url, 'primary', 'journal' ); // phpcs:ignore ?>
		</div>

		<div class="rn-live">
			<a class="rn-live__card rn-live__card--plan" href="<?php echo esc_url( $rn_url ); ?>">
				<p class="rn-live__label"><?php echo rn_icon( 'calendar' ); // phpcs:ignore ?><?php esc_html_e( 'Plan de Claude', 'revnefty' ); ?>
					<span><?php echo esc_html( sprintf( /* translators: %s: date du lundi. */ __( 'semaine du %s', 'revnefty' ), wp_date( 'j F', strtotime( $rn_week ) ) ) ); ?></span>
				</p>
				<?php if ( $rn_pred ) : ?>
					<p class="rn-live__big rn-live__bias rn-live__bias--<?php echo esc_attr( $rn_bias ); ?>"><?php echo esc_html( ucfirst( $rn_bias ) ); ?></p>
					<div class="rn-probs" aria-label="<?php esc_attr_e( 'Probabilités', 'revnefty' ); ?>">
						<span class="up" style="flex:<?php echo (int) $rn_pred['bull']; ?>"><?php echo (int) $rn_pred['bull']; ?> %</span>
						<span class="mid" style="flex:<?php echo (int) $rn_pred['range']; ?>"><?php echo (int) $rn_pred['range']; ?> %</span>
						<span class="down" style="flex:<?php echo (int) $rn_pred['bear']; ?>"><?php echo (int) $rn_pred['bear']; ?> %</span>
					</div>
					<?php if ( ! empty( $rn_pred['invalidation'] ) ) : ?>
						<p class="rn-live__line"><?php esc_html_e( 'Invalidation', 'revnefty' ); ?> <b><?php echo esc_html( $rn_nf( $rn_pred['invalidation'], 2 ) ); ?></b></p>
					<?php endif; ?>
					<?php if ( $rn_zones ) : ?>
						<ul class="rn-live__zones">
							<?php foreach ( $rn_zones as $rn_z ) : ?>
								<li class="<?php echo 'vente' === $rn_z['side'] ? 'sell' : 'buy'; ?>"><?php echo esc_html( $rn_nf( $rn_z['low'], 1 ) . '–' . $rn_nf( $rn_z['high'], 1 ) . ' · ' . $rn_z['type'] . ' ' . $rn_z['tf'] ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				<?php else : ?>
					<p class="rn-live__empty"><?php esc_html_e( 'Le plan de la semaine sera publié dimanche.', 'revnefty' ); ?></p>
				<?php endif; ?>
			</a>

			<a class="rn-live__card" href="<?php echo esc_url( $rn_url ); ?>">
				<p class="rn-live__label"><?php echo rn_icon( 'journal' ); // phpcs:ignore ?><?php esc_html_e( 'Journal', 'revnefty' ); ?>
					<?php if ( $rn_acc ) : ?><span><?php echo esc_html( $rn_acc['name'] ); ?></span><?php endif; ?>
				</p>
				<?php if ( $rn_n ) : ?>
					<p class="rn-live__big"><?php echo esc_html( $rn_nf( $rn_wins / $rn_n * 100, 0 ) ); ?> %<small><?php esc_html_e( 'de réussite', 'revnefty' ); ?></small></p>
					<dl class="rn-live__kv">
						<div><dt><?php esc_html_e( 'Trades', 'revnefty' ); ?></dt><dd><?php echo esc_html( (string) $rn_n ); ?></dd></div>
						<div><dt><?php esc_html_e( 'Total', 'revnefty' ); ?></dt><dd class="<?php echo array_sum( $rn_rs ) >= 0 ? 'up' : 'down'; ?>"><?php echo esc_html( $rn_r( array_sum( $rn_rs ) ) ); ?></dd></div>
						<div><dt><?php esc_html_e( 'Facteur de profit', 'revnefty' ); ?></dt><dd><?php echo esc_html( $rn_neg ? $rn_nf( $rn_pos / $rn_neg, 2 ) : '—' ); ?></dd></div>
					</dl>
				<?php else : ?>
					<p class="rn-live__empty"><?php esc_html_e( 'Aucun trade pour le moment.', 'revnefty' ); ?></p>
				<?php endif; ?>
			</a>

			<a class="rn-live__card" href="<?php echo esc_url( $rn_url ); ?>">
				<p class="rn-live__label"><?php echo rn_icon( 'bolt' ); // phpcs:ignore ?><?php esc_html_e( 'Signaux Claude', 'revnefty' ); ?><span><?php esc_html_e( 'EA MT5', 'revnefty' ); ?></span></p>
				<?php if ( $rn_closed ) : ?>
					<p class="rn-live__big"><?php echo esc_html( $rn_nf( $rn_swon / count( $rn_closed ) * 100, 0 ) ); ?> %<small><?php esc_html_e( 'de réussite', 'revnefty' ); ?></small></p>
					<dl class="rn-live__kv">
						<div><dt><?php esc_html_e( 'Clôturés', 'revnefty' ); ?></dt><dd><?php echo esc_html( (string) count( $rn_closed ) ); ?></dd></div>
						<div><dt><?php esc_html_e( 'Total', 'revnefty' ); ?></dt><dd class="up"><?php echo esc_html( $rn_r( array_sum( array_map( static fn( $s ) => (float) $s['r'], $rn_closed ) ) ) ); ?></dd></div>
						<div><dt><?php esc_html_e( 'En cours', 'revnefty' ); ?></dt><dd><?php echo esc_html( (string) $rn_sopen ); ?></dd></div>
					</dl>
				<?php else : ?>
					<p class="rn-live__empty"><?php echo esc_html( $rn_sopen ? __( 'Premier signal en cours de suivi.', 'revnefty' ) : __( 'Les signaux apparaissent dès que le prix touche une zone du plan avec confirmation.', 'revnefty' ) ); ?></p>
				<?php endif; ?>
			</a>
		</div>
	</div>
</section>
