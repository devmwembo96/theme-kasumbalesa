<?php
/**
 * Pictogrammes SVG en ligne (trait fin, style unifié). Aucun fichier externe à charger.
 *
 * @package Kasumbalesa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tracés des pictogrammes (viewBox 0 0 24 24).
 *
 * @return array
 */
function kb_icon_paths() {
	return array(
		'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'arrow-left'  => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
		'arrow-up'    => '<path d="M12 19V5M6 11l6-6 6 6"/>',
		'arrow-down'  => '<path d="M12 5v14M6 13l6 6 6-6"/>',
		'chevron'     => '<path d="M6 9l6 6 6-6"/>',
		'close'       => '<path d="M6 6l12 12M18 6L6 18"/>',
		'menu'        => '<path d="M3 7h18M3 12h18M3 17h12"/>',
		'search'      => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
		'phone'       => '<path d="M5 3h4l2 5-2.5 1.5a11 11 0 0 0 6 6L16 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 5a2 2 0 0 1 2-2z"/>',
		'mail'        => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
		'map-pin'     => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
		'clock'       => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'calendar'    => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
		'pause'       => '<path d="M9 5v14M15 5v14"/>',
		'play'        => '<path d="M7 4.5v15l12-7.5z"/>',
		'link'        => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
		'share'       => '<circle cx="18" cy="5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="19" r="2.5"/><path d="M8.2 10.8l7.6-4.4M8.2 13.2l7.6 4.4"/>',
		'globe'       => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
		'quote'       => '<path d="M10 7H6a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h3v1a3 3 0 0 1-3 3M20 7h-4a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h3v1a3 3 0 0 1-3 3"/>',
		'document'    => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
		'building'    => '<path d="M3 21h18M5 21V10M19 21V10M9 21v-6h6v6M2 10l10-6 10 6"/><path d="M12 7.5v.01"/>',
		'customs'     => '<path d="M3 17V7h11v10M14 10h4l3 3.5V17h-7"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/>',
		'compass'     => '<circle cx="12" cy="12" r="9"/><path d="M15.5 8.5l-2 5-5 2 2-5z"/>',
		'mountain'    => '<path d="M2 20l7-12 4 6 2.5-3.5L22 20z"/><path d="M7.5 10.5L9 12l1.5-1.5"/>',
		'users'       => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6.5 6.5 0 0 1 3.5 6"/>',
		'heart'       => '<path d="M12 20s-7.5-4.6-7.5-10A4.3 4.3 0 0 1 12 7.5 4.3 4.3 0 0 1 19.5 10c0 5.4-7.5 10-7.5 10z"/>',
		'shield'      => '<path d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
		'facebook'    => '<path d="M14.5 8H17V4.5h-2.5C11.9 4.5 10.5 6 10.5 8.6V10H8v3.5h2.5V21H14v-7.5h2.6L17 10h-3V8.8c0-.5.2-.8.5-.8z"/>',
		'x'           => '<path d="M4 4l16 16M20 4l-6.6 7.4M10.6 12.6L4 20"/><path d="M4 4h4.5L20 20h-4.5z"/>',
		'youtube'     => '<rect x="2.5" y="5.5" width="19" height="13" rx="4"/><path d="M10 9v6l5-3z"/>',
		'instagram'   => '<rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17 7v.01"/>',
		'linkedin'    => '<rect x="3.5" y="3.5" width="17" height="17" rx="2.5"/><path d="M8 10.5V16M8 7.8v.01M11.5 16v-5.5M11.5 13c0-1.6 1-2.6 2.3-2.6s2.2.9 2.2 2.6v3"/>',
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
function kb_icon( $name, $class = '' ) {
	$paths = kb_icon_paths();
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	$fill = ( 'play' === $name ) ? 'currentColor' : 'none';
	return sprintf(
		'<svg class="kb-icon kb-icon--%1$s %2$s" viewBox="0 0 24 24" width="24" height="24" fill="%3$s" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%4$s</svg>',
		esc_attr( $name ),
		esc_attr( $class ),
		esc_attr( $fill ),
		$paths[ $name ] // Tracés internes, statiques.
	);
}

/**
 * Pictogrammes proposés pour les accès rapides.
 *
 * @return array
 */
function kb_quick_access_icons() {
	return array(
		'document' => __( 'Document', 'kasumbalesa' ),
		'building' => __( 'Bâtiment officiel', 'kasumbalesa' ),
		'customs'  => __( 'Camion / douane', 'kasumbalesa' ),
		'compass'  => __( 'Boussole', 'kasumbalesa' ),
		'mountain' => __( 'Montagne', 'kasumbalesa' ),
		'users'    => __( 'Population', 'kasumbalesa' ),
		'shield'   => __( 'Sécurité', 'kasumbalesa' ),
		'heart'    => __( 'Social / santé', 'kasumbalesa' ),
		'calendar' => __( 'Agenda', 'kasumbalesa' ),
		'phone'    => __( 'Téléphone', 'kasumbalesa' ),
	);
}
