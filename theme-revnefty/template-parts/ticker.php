<?php
/**
 * Bandeau des cours en direct (widget TradingView « Ticker Tape », chargé après la page).
 *
 * @package RevNefty
 */

$rn_ticker = array(
	'symbols'      => array(
		array( 'proName' => 'OANDA:XAUUSD', 'title' => 'Or' ),
		array( 'proName' => 'OANDA:XAGUSD', 'title' => 'Argent' ),
		array( 'proName' => 'CAPITALCOM:DXY', 'title' => 'Dollar (DXY)' ),
		array( 'proName' => 'OANDA:EURUSD', 'title' => 'EUR/USD' ),
		array( 'proName' => 'FX:USDJPY', 'title' => 'USD/JPY' ),
		array( 'proName' => 'FOREXCOM:SPXUSD', 'title' => 'S&P 500' ),
		array( 'proName' => 'BITSTAMP:BTCUSD', 'title' => 'Bitcoin' ),
	),
	'colorTheme'   => 'dark',
	'isTransparent' => true,
	'displayMode'  => 'adaptive',
	'locale'       => 'fr',
);
?>
<div class="rn-ticker" aria-label="<?php esc_attr_e( 'Cours des marchés', 'revnefty' ); ?>">
	<div class="tradingview-widget-container" data-rn-ticker="<?php echo esc_attr( wp_json_encode( $rn_ticker ) ); ?>">
		<div class="tradingview-widget-container__widget"></div>
	</div>
</div>
