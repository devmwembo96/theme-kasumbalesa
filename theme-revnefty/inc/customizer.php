<?php
/**
 * Customizer : tous les textes de l'accueil, contacts, réseaux et pied de page.
 *
 * @package RevNefty
 */

defined( 'ABSPATH' ) || exit;

/**
 * Case à cocher.
 *
 * @param mixed $value Valeur.
 * @return int
 */
function rn_sanitize_checkbox( $value ) {
	return empty( $value ) ? 0 : 1;
}

/**
 * Lien : URL complète, ancre (#…) ou chemin du site (/…).
 *
 * @param string $value Valeur.
 * @return string
 */
function rn_sanitize_link( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return '';
	}
	if ( '#' === $value[0] ) {
		return '#' . sanitize_title( substr( $value, 1 ) );
	}
	return esc_url_raw( $value );
}

/**
 * Enregistre sections et réglages.
 *
 * @param WP_Customize_Manager $wp_customize Customizer.
 */
function rn_customize_register( $wp_customize ) {
	$d   = rn_defaults();
	$add = static function ( $id, $section, $type, $label, $description = '' ) use ( $wp_customize, $d ) {
		$sanitize = array(
			'text'     => 'sanitize_text_field',
			'textarea' => 'sanitize_textarea_field',
			'url'      => 'rn_sanitize_link',
			'email'    => 'sanitize_email',
			'checkbox' => 'rn_sanitize_checkbox',
		);
		$wp_customize->add_setting(
			$id,
			array(
				'default'           => $d[ $id ] ?? '',
				'sanitize_callback' => $sanitize[ $type ],
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			$id,
			array(
				'section'     => $section,
				'type'        => 'url' === $type ? 'text' : $type,
				'label'       => $label,
				'description' => $description,
			)
		);
	};

	$wp_customize->add_panel(
		'rn_home',
		array(
			'title'    => __( 'Accueil RevNefty', 'revnefty' ),
			'priority' => 30,
		)
	);

	// Héros.
	$wp_customize->add_section( 'rn_hero', array( 'title' => __( 'Haut de page', 'revnefty' ), 'panel' => 'rn_home' ) );
	$add( 'hero_eyebrow', 'rn_hero', 'text', __( 'Sur-titre', 'revnefty' ) );
	$add( 'hero_title', 'rn_hero', 'text', __( 'Titre', 'revnefty' ), __( 'Entourez un mot d’astérisques pour le mettre en vert : *méthode*.', 'revnefty' ) );
	$add( 'hero_text', 'rn_hero', 'textarea', __( 'Texte', 'revnefty' ) );
	$add( 'hero_cta_label', 'rn_hero', 'text', __( 'Bouton principal : texte', 'revnefty' ) );
	$add( 'hero_cta_url', 'rn_hero', 'url', __( 'Bouton principal : lien', 'revnefty' ) );
	$add( 'hero_cta2_label', 'rn_hero', 'text', __( 'Bouton secondaire : texte', 'revnefty' ) );
	$add( 'hero_cta2_url', 'rn_hero', 'url', __( 'Bouton secondaire : lien', 'revnefty' ) );
	for ( $i = 1; $i <= 3; $i++ ) {
		/* translators: %d: numéro. */
		$add( "hero_point_$i", 'rn_hero', 'text', sprintf( __( 'Point fort %d', 'revnefty' ), $i ) );
	}
	$add( 'ticker_enable', 'rn_hero', 'checkbox', __( 'Bandeau des cours en direct (TradingView)', 'revnefty' ) );

	// Méthode.
	$wp_customize->add_section( 'rn_steps', array( 'title' => __( 'Méthode', 'revnefty' ), 'panel' => 'rn_home' ) );
	$add( 'steps_eyebrow', 'rn_steps', 'text', __( 'Sur-titre', 'revnefty' ) );
	$add( 'steps_title', 'rn_steps', 'text', __( 'Titre', 'revnefty' ) );
	for ( $i = 1; $i <= 3; $i++ ) {
		/* translators: %d: numéro. */
		$add( "step_{$i}_title", 'rn_steps', 'text', sprintf( __( 'Étape %d : titre', 'revnefty' ), $i ) );
		/* translators: %d: numéro. */
		$add( "step_{$i}_text", 'rn_steps', 'textarea', sprintf( __( 'Étape %d : texte', 'revnefty' ), $i ) );
	}

	// Outils.
	$wp_customize->add_section( 'rn_pillars', array( 'title' => __( 'Outils', 'revnefty' ), 'panel' => 'rn_home' ) );
	$add( 'pillars_eyebrow', 'rn_pillars', 'text', __( 'Sur-titre', 'revnefty' ) );
	$add( 'pillars_title', 'rn_pillars', 'text', __( 'Titre', 'revnefty' ) );
	for ( $i = 1; $i <= 4; $i++ ) {
		/* translators: %d: numéro. */
		$add( "pillar_{$i}_title", 'rn_pillars', 'text', sprintf( __( 'Outil %d : titre', 'revnefty' ), $i ) );
		/* translators: %d: numéro. */
		$add( "pillar_{$i}_text", 'rn_pillars', 'textarea', sprintf( __( 'Outil %d : texte', 'revnefty' ), $i ) );
	}

	// Cadre de risque.
	$wp_customize->add_section( 'rn_risk', array( 'title' => __( 'Règles de risque', 'revnefty' ), 'panel' => 'rn_home' ) );
	$add( 'risk_eyebrow', 'rn_risk', 'text', __( 'Sur-titre', 'revnefty' ) );
	$add( 'risk_title', 'rn_risk', 'text', __( 'Titre', 'revnefty' ) );
	$add( 'risk_text', 'rn_risk', 'textarea', __( 'Texte', 'revnefty' ) );
	for ( $i = 1; $i <= 6; $i++ ) {
		/* translators: %d: numéro. */
		$add( "risk_{$i}_value", 'rn_risk', 'text', sprintf( __( 'Règle %d : valeur', 'revnefty' ), $i ) );
		/* translators: %d: numéro. */
		$add( "risk_{$i}_label", 'rn_risk', 'text', sprintf( __( 'Règle %d : libellé', 'revnefty' ), $i ) );
	}

	// FAQ.
	$wp_customize->add_section( 'rn_faq', array( 'title' => __( 'Questions fréquentes', 'revnefty' ), 'panel' => 'rn_home' ) );
	$add( 'faq_eyebrow', 'rn_faq', 'text', __( 'Sur-titre', 'revnefty' ) );
	$add( 'faq_title', 'rn_faq', 'text', __( 'Titre', 'revnefty' ) );
	for ( $i = 1; $i <= 4; $i++ ) {
		/* translators: %d: numéro. */
		$add( "faq_{$i}_q", 'rn_faq', 'text', sprintf( __( 'Question %d', 'revnefty' ), $i ) );
		/* translators: %d: numéro. */
		$add( "faq_{$i}_a", 'rn_faq', 'textarea', sprintf( __( 'Réponse %d', 'revnefty' ), $i ) );
	}

	// Appel final.
	$wp_customize->add_section( 'rn_cta', array( 'title' => __( 'Appel final', 'revnefty' ), 'panel' => 'rn_home' ) );
	$add( 'cta_title', 'rn_cta', 'text', __( 'Titre', 'revnefty' ) );
	$add( 'cta_text', 'rn_cta', 'textarea', __( 'Texte', 'revnefty' ) );
	$add( 'cta_label', 'rn_cta', 'text', __( 'Bouton', 'revnefty' ) );

	// Contact et réseaux.
	$wp_customize->add_section( 'rn_contact', array( 'title' => __( 'Contact et réseaux', 'revnefty' ), 'priority' => 31 ) );
	$add( 'contact_email', 'rn_contact', 'email', __( 'E-mail', 'revnefty' ) );
	$add( 'contact_phone', 'rn_contact', 'text', __( 'Téléphone', 'revnefty' ) );
	$add( 'contact_whatsapp', 'rn_contact', 'text', __( 'WhatsApp (numéro international)', 'revnefty' ) );
	foreach ( array( 'telegram' => 'Telegram', 'x' => 'X', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'tiktok' => 'TikTok' ) as $key => $label ) {
		$add( 'social_' . $key, 'rn_contact', 'url', $label );
	}

	// Pied de page.
	$wp_customize->add_section( 'rn_footer', array( 'title' => __( 'Pied de page', 'revnefty' ), 'priority' => 32 ) );
	$add( 'footer_about', 'rn_footer', 'textarea', __( 'Présentation', 'revnefty' ) );
	$add( 'risk_disclaimer', 'rn_footer', 'textarea', __( 'Avertissement sur les risques', 'revnefty' ) );
	$add( 'footer_credit', 'rn_footer', 'text', __( 'Mention de copyright (vide = automatique)', 'revnefty' ) );
}
add_action( 'customize_register', 'rn_customize_register' );
