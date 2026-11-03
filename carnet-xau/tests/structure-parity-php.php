<?php
define( 'ABSPATH', '/' );
require __DIR__ . '/../includes/class-cx-structure.php';
$d   = json_decode( file_get_contents( $argv[1] ), true );
$now = (int) $argv[2];
$out = array();
foreach ( CX_Structure::TF as $tf => $sec ) {
	$rows = array_values( array_filter( $d['tf'][ $tf ], fn( $r ) => (int) $r[0] + $sec <= $now ) );
	$x    = CX_Structure::compute( $rows, $sec );
	$ev   = array();
	foreach ( array( 'pivots', 'equal', 'sweeps', 'displacement', 'structure', 'sessions' ) as $k ) {
		foreach ( $x[ $k ] as $e ) {
			$ev[] = array( $e['type'], $e['dir'], $e['t_origin'], $e['t_confirm'], round( $e['price'], 2 ) );
		}
	}
	foreach ( array( 'fvg', 'ob' ) as $k ) {
		foreach ( $x[ $k ] as $e ) {
			$ev[] = array( $e['type'], $e['dir'], $e['t_origin'], $e['t_confirm'], round( $e['price'], 2 ) );
			foreach ( $e['states'] as $s ) {
				$ev[] = array( $s['type'], $s['dir'], $s['t_origin'], $s['t_confirm'], round( $s['price'], 2 ) );
			}
		}
	}
	$out[ $tf ] = $ev;
}
echo json_encode( $out );
