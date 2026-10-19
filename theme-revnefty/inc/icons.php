<?php
/**
 * Pictogrammes SVG en ligne (trait fin, style unifié). Aucun fichier externe à charger.
 *
 * @package RevNefty
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tracés des pictogrammes (viewBox 0 0 24 24).
 *
 * @return array
 */
function rn_icon_paths() {
	return array(
		'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'arrow-up'    => '<path d="M12 19V5M6 11l6-6 6 6"/>',
		'chevron'     => '<path d="M6 9l6 6 6-6"/>',
		'close'       => '<path d="M6 6l12 12M18 6L6 18"/>',
		'menu'        => '<path d="M3 7h18M3 12h18M3 17h12"/>',
		'search'      => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
		'mail'        => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
		'phone'       => '<path d="M5 3h4l2 5-2.5 1.5a11 11 0 0 0 6 6L16 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 5a2 2 0 0 1 2-2z"/>',
		'calendar'    => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
		'clock'       => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'check'       => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
		'chart'       => '<path d="M4 20V4M4 20h16"/><path d="M8 16v-4M12 16V8M16 16v-6M20 16V6"/>',
		'candles'     => '<path d="M7 3v4M7 15v6M17 3v6M17 17v4"/><rect x="5" y="7" width="4" height="8" rx="1"/><rect x="15" y="9" width="4" height="8" rx="1"/>',
		'target'      => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.2"/>',
		'shield'      => '<path d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
		'journal'     => '<path d="M6 3h11a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6z"/><path d="M6 3v18M9.5 8h6M9.5 12h6M9.5 16h3.5"/>',
		'bell'        => '<path d="M6 16V11a6 6 0 0 1 12 0v5l2 2H4z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
		'search-plan' => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5M8 12.5l2-2 1.5 1.5 2.5-3"/>',
		'bolt'        => '<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>',
		'refresh'     => '<path d="M20 11a8 8 0 0 0-14.9-3.5M4 4v4h4M4 13a8 8 0 0 0 14.9 3.5M20 20v-4h-4"/>',
		'telegram'    => '<path d="M21 4L3 11l6 2 2 6 3-4 5 4z"/><path d="M9 13l12-9"/>',
		'x'           => '<path d="M4 4l16 16M20 4l-6.6 7.4M10.6 12.6L4 20"/><path d="M4 4h4.5L20 20h-4.5z"/>',
		'youtube'     => '<rect x="2.5" y="5.5" width="19" height="13" rx="4"/><path d="M10 9v6l5-3z"/>',
		'instagram'   => '<rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17 7v.01"/>',
		'tiktok'      => '<path d="M14 3v11.5a3.5 3.5 0 1 1-3.5-3.5"/><path d="M14 3c.4 2.6 2.2 4.4 5 4.6"/>',
		'whatsapp'    => '<path d="M4 20l1.3-4A8.5 8.5 0 1 1 8.6 19z"/><path d="M9 8.5c0 3.5 2.6 6.5 6.5 6.5l1-1.6-2-1-1 .8a4.6 4.6 0 0 1-2.7-2.7l.8-1-1-2z"/>',
	);
}

/**
 * Renvoie le SVG d'un pictogramme.
 *
 * @param string $name  Nom du pictogramme.
 * @param string $class Classe CSS supplémentaire.
 * @return string
 */
function rn_icon( $name, $class = '' ) {
	$paths = rn_icon_paths();
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return sprintf(
		'<svg class="rn-icon rn-icon--%1$s %2$s" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%3$s</svg>',
		esc_attr( $name ),
		esc_attr( $class ),
		$paths[ $name ] // Tracés internes, statiques.
	);
}
