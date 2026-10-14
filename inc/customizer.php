<?php
/**
 * Réglages du Customizer (Apparence > Personnaliser > Ville de Kasumbalesa).
 *
 * @package Kasumbalesa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Nettoyage : case à cocher.
 *
 * @param mixed $value Valeur.
 * @return int
 */
function kb_sanitize_checkbox( $value ) {
	return ( isset( $value ) && ( true === $value || '1' === $value || 1 === $value || 'on' === $value ) ) ? 1 : 0;
}

/**
 * Nettoyage : liste de choix.
 *
 * @param string               $value   Valeur.
 * @param WP_Customize_Setting $setting Réglage.
 * @return string
 */
function kb_sanitize_choice( $value, $setting ) {
	$control = $setting->manager->get_control( $setting->id );
	$choices = $control ? $control->choices : array();
	return array_key_exists( $value, $choices ) ? $value : $setting->default;
}

/**
 * Nettoyage : lien (URL absolue, relative ou ancre #).
 *
 * @param string $value Valeur.
 * @return string
 */
function kb_sanitize_link( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return '';
	}
	if ( 0 === strpos( $value, '#' ) ) {
		return '#' . sanitize_title( substr( $value, 1 ) );
	}
	return esc_url_raw( $value );
}

/**
 * Nettoyage : nombre saisi librement (chiffres, espaces, virgule ou point).
 *
 * @param string $value Valeur.
 * @return string
 */
function kb_sanitize_number_text( $value ) {
	return preg_replace( '/[^0-9.,\s]/u', '', (string) $value );
}

/**
 * Enregistre panneau, sections, réglages et contrôles.
 *
 * @param WP_Customize_Manager $wp_customize Gestionnaire.
 */
function kb_customize_register( $wp_customize ) {
	$d = kb_defaults();

	$wp_customize->add_panel(
		'kb_panel',
		array(
			'title'       => __( 'Ville de Kasumbalesa', 'kasumbalesa' ),
			'description' => __( 'Tous les réglages du site officiel : accueil, Maire, actualités, coordonnées.', 'kasumbalesa' ),
			'priority'    => 20,
		)
	);

	$sections = array(
		'kb_intro'    => __( 'Introduction & pop-up du Maire', 'kasumbalesa' ),
		'kb_hero'     => __( 'Héros de la page d’accueil', 'kasumbalesa' ),
		'kb_ticker'   => __( 'Bande défilante d’actualités', 'kasumbalesa' ),
		'kb_mayor'    => __( 'Section « Mot du Maire »', 'kasumbalesa' ),
		'kb_news'     => __( 'Section actualités', 'kasumbalesa' ),
		'kb_quick'    => __( 'Accès rapides', 'kasumbalesa' ),
		'kb_figures'  => __( 'Chiffres clés', 'kasumbalesa' ),
		'kb_topo'     => __( 'Toponymie & carte postale', 'kasumbalesa' ),
		'kb_contact'  => __( 'Coordonnées & réseaux sociaux', 'kasumbalesa' ),
		'kb_footer'   => __( 'Pied de page', 'kasumbalesa' ),
		'kb_lang'     => __( 'Langues (FR / EN)', 'kasumbalesa' ),
		'kb_seo'      => __( 'Référencement & partage', 'kasumbalesa' ),
	);
	$priority = 10;
	foreach ( $sections as $id => $title ) {
		$wp_customize->add_section(
			$id,
			array(
				'title'    => $title,
				'panel'    => 'kb_panel',
				'priority' => $priority,
			)
		);
		$priority += 10;
	}

	$wp_customize->get_section( 'kb_figures' )->description = __( 'Seuls les chiffres renseignés (nombre supérieur à zéro ET libellé) sont affichés. Si aucun n’est rempli, la section est masquée.', 'kasumbalesa' );
	$wp_customize->get_section( 'kb_news' )->description    = __( 'Astuce : entourez un mot d’astérisques (*ainsi*) pour le mettre en italique doré dans les titres.', 'kasumbalesa' );
	$wp_customize->get_section( 'kb_hero' )->description    = __( 'Titres : entourez un mot d’astérisques (*ainsi*) pour le mettre en valeur. ', 'kasumbalesa' ) . __( 'Choisissez jusqu’à 5 images dans la Médiathèque (format paysage, 2400 px de large conseillé).', 'kasumbalesa' );

	/**
	 * Ajoute un réglage + son contrôle.
	 *
	 * @param string $id      Identifiant.
	 * @param string $section Section.
	 * @param string $type    text|textarea|url|link|email|checkbox|select|image|page|range|number.
	 * @param string $label   Libellé.
	 * @param array  $extra   Paramètres supplémentaires du contrôle.
	 */
	$add = static function ( $id, $section, $type, $label, $extra = array() ) use ( $wp_customize, $d ) {
		$sanitize = array(
			'text'     => 'sanitize_text_field',
			'textarea' => 'sanitize_textarea_field',
			'url'      => 'esc_url_raw',
			'link'     => 'kb_sanitize_link',
			'email'    => 'sanitize_email',
			'checkbox' => 'kb_sanitize_checkbox',
			'select'   => 'kb_sanitize_choice',
			'image'    => 'absint',
			'page'     => 'absint',
			'range'    => 'absint',
			'figure'   => 'kb_sanitize_number_text',
		);

		$wp_customize->add_setting(
			$id,
			array(
				'default'           => isset( $d[ $id ] ) ? $d[ $id ] : '',
				'sanitize_callback' => $sanitize[ $type ],
				'transport'         => 'refresh',
			)
		);

		$args = array_merge(
			array(
				'label'   => $label,
				'section' => $section,
			),
			$extra
		);

		if ( 'image' === $type ) {
			$args['mime_type'] = 'image';
			$wp_customize->add_control( new WP_Customize_Media_Control( $wp_customize, $id, $args ) );
			return;
		}

		$map = array(
			'link'   => 'text',
			'page'   => 'dropdown-pages',
			'figure' => 'text',
		);
		$args['type'] = isset( $map[ $type ] ) ? $map[ $type ] : $type;
		$wp_customize->add_control( $id, $args );
	};

	// Introduction & pop-up.
	$add( 'intro_enable', 'kb_intro', 'checkbox', __( 'Animation d’introduction du logo (une fois par session)', 'kasumbalesa' ) );
	$add( 'popup_enable', 'kb_intro', 'checkbox', __( 'Pop-up du Maire à l’ouverture (une fois par session)', 'kasumbalesa' ) );
	$add( 'mayor_page', 'kb_intro', 'page', __( 'Page source du Mot du Maire', 'kasumbalesa' ), array( 'description' => __( 'Photo = image mise en avant de la page. Extrait = extrait de la page (ou début du texte). Par défaut : la page « Mot du Maire ».', 'kasumbalesa' ) ) );
	$add( 'mayor_name', 'kb_intro', 'text', __( 'Nom du Maire (signature)', 'kasumbalesa' ) );
	$add( 'mayor_role', 'kb_intro', 'text', __( 'Fonction (signature)', 'kasumbalesa' ) );
	$add( 'mayor_signature', 'kb_intro', 'image', __( 'Signature manuscrite (image PNG transparente, facultatif)', 'kasumbalesa' ) );
	$add( 'popup_eyebrow', 'kb_intro', 'text', __( 'Surtitre du pop-up', 'kasumbalesa' ) );
	$add( 'popup_button', 'kb_intro', 'text', __( 'Texte du bouton du pop-up', 'kasumbalesa' ) );

	// Héros.
	$add( 'hero_eyebrow', 'kb_hero', 'text', __( 'Surtitre', 'kasumbalesa' ) );
	$add( 'hero_title', 'kb_hero', 'text', __( 'Titre', 'kasumbalesa' ) );
	$add( 'hero_subtitle', 'kb_hero', 'textarea', __( 'Sous-titre', 'kasumbalesa' ) );
	$add( 'hero_btn1_label', 'kb_hero', 'text', __( 'Bouton 1 – texte', 'kasumbalesa' ) );
	$add( 'hero_btn1_url', 'kb_hero', 'link', __( 'Bouton 1 – lien (URL ou #ancre)', 'kasumbalesa' ) );
	$add( 'hero_btn2_label', 'kb_hero', 'text', __( 'Bouton 2 – texte', 'kasumbalesa' ) );
	$add( 'hero_btn2_url', 'kb_hero', 'link', __( 'Bouton 2 – lien (vide = page des articles)', 'kasumbalesa' ) );
	for ( $i = 1; $i <= 5; $i++ ) {
		/* translators: %d: numéro de l'image. */
		$add( 'hero_slide_' . $i, 'kb_hero', 'image', sprintf( __( 'Image du diaporama n° %d', 'kasumbalesa' ), $i ) );
	}
	$add( 'hero_overlay', 'kb_hero', 'range', __( 'Intensité du voile sur les photos (%)', 'kasumbalesa' ), array( 'input_attrs' => array( 'min' => 0, 'max' => 70, 'step' => 5 ) ) );
	$add( 'hero_interval', 'kb_hero', 'range', __( 'Durée de chaque image (secondes)', 'kasumbalesa' ), array( 'input_attrs' => array( 'min' => 4, 'max' => 15, 'step' => 1 ) ) );

	// Bande défilante.
	$add(
		'ticker_display',
		'kb_ticker',
		'select',
		__( 'Affichage', 'kasumbalesa' ),
		array(
			'choices' => array(
				'all'   => __( 'Sur tout le site', 'kasumbalesa' ),
				'front' => __( 'Page d’accueil uniquement', 'kasumbalesa' ),
				'none'  => __( 'Masquée', 'kasumbalesa' ),
			),
		)
	);
	$add( 'ticker_label', 'kb_ticker', 'text', __( 'Étiquette', 'kasumbalesa' ) );
	$add(
		'ticker_speed',
		'kb_ticker',
		'select',
		__( 'Vitesse', 'kasumbalesa' ),
		array(
			'choices' => array(
				'slow'   => __( 'Lente', 'kasumbalesa' ),
				'normal' => __( 'Normale', 'kasumbalesa' ),
				'fast'   => __( 'Rapide', 'kasumbalesa' ),
			),
		)
	);

	// Section Mot du Maire.
	$add( 'mayor_section_enable', 'kb_mayor', 'checkbox', __( 'Afficher le Mot du Maire sur l’accueil', 'kasumbalesa' ) );
	$add( 'mayor_section_eyebrow', 'kb_mayor', 'text', __( 'Surtitre', 'kasumbalesa' ) );
	$add( 'mayor_readmore', 'kb_mayor', 'text', __( 'Bouton « Lire la suite » (mobile)', 'kasumbalesa' ) );
	$add( 'mayor_readless', 'kb_mayor', 'text', __( 'Bouton « Réduire » (mobile)', 'kasumbalesa' ) );

	// Actualités.
	$add( 'news_eyebrow', 'kb_news', 'text', __( 'Surtitre', 'kasumbalesa' ) );
	$add( 'news_title', 'kb_news', 'text', __( 'Titre', 'kasumbalesa' ) );
	$add( 'news_all_label', 'kb_news', 'text', __( 'Bouton vers toutes les actualités', 'kasumbalesa' ) );

	// Accès rapides.
	$add( 'qa_eyebrow', 'kb_quick', 'text', __( 'Surtitre', 'kasumbalesa' ) );
	$add( 'qa_image', 'kb_quick', 'image', __( 'Photo de la grande tuile (accès 1)', 'kasumbalesa' ) );
	$add( 'qa_title', 'kb_quick', 'text', __( 'Titre', 'kasumbalesa' ) );
	for ( $i = 1; $i <= 4; $i++ ) {
		/* translators: %d: numéro de l'accès rapide. */
		$n = sprintf( __( 'Accès %d', 'kasumbalesa' ), $i );
		$add( 'qa_' . $i . '_title', 'kb_quick', 'text', $n . ' – ' . __( 'titre', 'kasumbalesa' ) );
		$add( 'qa_' . $i . '_text', 'kb_quick', 'text', $n . ' – ' . __( 'description', 'kasumbalesa' ) );
		$add(
			'qa_' . $i . '_url',
			'kb_quick',
			'link',
			$n . ' – ' . __( 'lien', 'kasumbalesa' ),
			/* translators: %s: identifiant (slug) de page. */
			array( 'description' => sprintf( __( 'Vide = page « %s » si elle existe.', 'kasumbalesa' ), $d[ 'qa_' . $i . '_slug' ] ) )
		);
		$add( 'qa_' . $i . '_icon', 'kb_quick', 'select', $n . ' – ' . __( 'pictogramme', 'kasumbalesa' ), array( 'choices' => kb_quick_access_icons() ) );
	}

	// Chiffres clés.
	$add( 'figures_eyebrow', 'kb_figures', 'text', __( 'Surtitre', 'kasumbalesa' ) );
	$add( 'figures_title', 'kb_figures', 'text', __( 'Titre', 'kasumbalesa' ) );
	$add( 'figures_image', 'kb_figures', 'image', __( 'Image de fond (facultatif)', 'kasumbalesa' ) );
	for ( $i = 1; $i <= 4; $i++ ) {
		/* translators: %d: numéro du chiffre clé. */
		$n = sprintf( __( 'Chiffre %d', 'kasumbalesa' ), $i );
		$add( 'fig_' . $i . '_number', 'kb_figures', 'figure', $n . ' – ' . __( 'nombre', 'kasumbalesa' ) );
		$add( 'fig_' . $i . '_suffix', 'kb_figures', 'text', $n . ' – ' . __( 'suffixe (ex. +, km, %)', 'kasumbalesa' ) );
		$add( 'fig_' . $i . '_label', 'kb_figures', 'text', $n . ' – ' . __( 'libellé', 'kasumbalesa' ) );
	}

	// Toponymie & carte postale.
	$add( 'topo_eyebrow', 'kb_topo', 'text', __( 'Surtitre', 'kasumbalesa' ) );
	$add( 'topo_title', 'kb_topo', 'text', __( 'Titre', 'kasumbalesa' ) );
	$add( 'topo_text', 'kb_topo', 'textarea', __( 'Texte (une ligne vide = nouveau paragraphe)', 'kasumbalesa' ) );
	$add( 'topo_image', 'kb_topo', 'image', __( 'Image d’illustration', 'kasumbalesa' ) );
	$add( 'topo_link_label', 'kb_topo', 'text', __( 'Lien – texte', 'kasumbalesa' ) );
	$add( 'topo_link_url', 'kb_topo', 'link', __( 'Lien – adresse (vide = pas de lien)', 'kasumbalesa' ) );

	// Carte retournable avant le pied de page.
	$add( 'flip_enable', 'kb_topo', 'checkbox', __( 'Carte retournable avant le pied de page', 'kasumbalesa' ) );
	$add( 'flip_front_title', 'kb_topo', 'text', __( 'Carte – titre (recto)', 'kasumbalesa' ) );
	$add( 'flip_front_text', 'kb_topo', 'text', __( 'Carte – texte (recto)', 'kasumbalesa' ) );
	$add( 'flip_back_title', 'kb_topo', 'text', __( 'Carte – titre (verso)', 'kasumbalesa' ) );
	$add( 'flip_back_text', 'kb_topo', 'text', __( 'Carte – texte (verso)', 'kasumbalesa' ) );
	$add( 'flip_image', 'kb_topo', 'image', __( 'Carte – photo de fond (vide = photo de la toponymie)', 'kasumbalesa' ) );
	$add( 'flip_link_url', 'kb_topo', 'link', __( 'Carte – lien « Découvrir la ville » (vide = page Portail)', 'kasumbalesa' ) );

	// Bandeau institutionnel du pied de page.
	$add( 'heritage_emblem_1', 'kb_topo', 'image', __( 'Pied de page – armoiries de la RDC', 'kasumbalesa' ) );
	$add( 'heritage_emblem_2', 'kb_topo', 'image', __( 'Pied de page – logo de la province du Haut-Katanga', 'kasumbalesa' ) );
	$add( 'heritage_photo', 'kb_topo', 'image', __( 'Pied de page – photo de la montagne', 'kasumbalesa' ) );
	$add( 'heritage_line', 'kb_topo', 'text', __( 'Pied de page – appartenance (pays, province)', 'kasumbalesa' ) );
	$add( 'heritage_title', 'kb_topo', 'text', __( 'Pied de page – titre (*mot* = accent doré)', 'kasumbalesa' ) );
	$add( 'heritage_motto', 'kb_topo', 'text', __( 'Pied de page – devise', 'kasumbalesa' ) );
	$add( 'postcard_image', 'kb_topo', 'image', __( 'Carte postale – photo', 'kasumbalesa' ) );
	$add( 'postcard_title', 'kb_topo', 'text', __( 'Carte postale – titre', 'kasumbalesa' ) );
	$add( 'postcard_text', 'kb_topo', 'text', __( 'Carte postale – légende', 'kasumbalesa' ) );

	// Bande typographique.
	$add( 'marquee_enable', 'kb_topo', 'checkbox', __( 'Bande typographique géante (accueil)', 'kasumbalesa' ) );
	$add( 'marquee_text', 'kb_topo', 'text', __( 'Texte de la bande (séparer par « · »)', 'kasumbalesa' ) );

	// Coordonnées.
	$add( 'contact_phone', 'kb_contact', 'text', __( 'Téléphone principal', 'kasumbalesa' ), array( 'description' => __( 'Format international conseillé : +243 …', 'kasumbalesa' ) ) );
	$add( 'contact_phone_2', 'kb_contact', 'text', __( 'Téléphone secondaire', 'kasumbalesa' ) );
	$add( 'contact_email', 'kb_contact', 'email', __( 'Adresse e-mail', 'kasumbalesa' ) );
	$add( 'contact_address', 'kb_contact', 'textarea', __( 'Adresse postale', 'kasumbalesa' ) );
	$add( 'contact_hours', 'kb_contact', 'textarea', __( 'Horaires d’ouverture', 'kasumbalesa' ) );
	foreach ( kb_social_networks() as $key => $label ) {
		/* translators: %s: nom du réseau social. */
		$add( 'social_' . $key, 'kb_contact', 'url', sprintf( __( 'Lien %s', 'kasumbalesa' ), $label ) );
	}

	// Pied de page.
	$add( 'footer_about', 'kb_footer', 'textarea', __( 'Texte de présentation (vide = slogan du site)', 'kasumbalesa' ) );
	$add( 'footer_credit', 'kb_footer', 'text', __( 'Mention de copyright', 'kasumbalesa' ), array( 'description' => __( '{year} est remplacé automatiquement par l’année en cours.', 'kasumbalesa' ) ) );

	// Langues.
	$add( 'lang_enable', 'kb_lang', 'checkbox', __( 'Afficher le sélecteur FR / EN', 'kasumbalesa' ), array( 'description' => __( 'Avec Polylang ou WPML, les langues sont détectées automatiquement. Sinon, renseignez les adresses ci-dessous.', 'kasumbalesa' ) ) );
	$add( 'lang_fr_url', 'kb_lang', 'url', __( 'Adresse de la version française (vide = accueil)', 'kasumbalesa' ) );
	$add( 'lang_en_url', 'kb_lang', 'url', __( 'Adresse de la version anglaise (vide = « bientôt disponible »)', 'kasumbalesa' ) );

	// SEO.
	$add( 'seo_description', 'kb_seo', 'textarea', __( 'Description par défaut (balise meta)', 'kasumbalesa' ), array( 'description' => __( 'Utilisée pour l’accueil et les pages sans extrait. 150 à 160 caractères.', 'kasumbalesa' ) ) );
	$add( 'seo_image', 'kb_seo', 'image', __( 'Image de partage par défaut (1200 × 630 px)', 'kasumbalesa' ) );
	$add( 'front_show_content', 'kb_hero', 'checkbox', __( 'Afficher aussi le contenu de la page d’accueil statique', 'kasumbalesa' ), array( 'description' => __( 'À laisser décoché tant que l’ancienne page d’accueil Elementor existe.', 'kasumbalesa' ) ) );
}
add_action( 'customize_register', 'kb_customize_register' );
