<?php
/**
 * Fonctions utilitaires : valeurs par défaut du Customizer, lecture des réglages,
 * page du Maire, liens de contact.
 *
 * @package Kasumbalesa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Valeurs par défaut de tous les réglages du thème.
 * Ce sont des valeurs de départ : chacune est modifiable dans le Customizer.
 *
 * @return array
 */
function kb_defaults() {
	static $defaults = null;
	if ( null !== $defaults ) {
		return $defaults;
	}

	$defaults = array(
		// Introduction & pop-up.
		'intro_enable'          => 1,
		'popup_enable'          => 1,
		'mayor_page'            => 0,
		'mayor_name'            => 'André KAPAMPA KAMWANYA',
		'mayor_role'            => 'Maire de la Ville de Kasumbalesa',
		'mayor_signature'       => 6417,
		'popup_eyebrow'         => 'Message du Maire',
		'popup_title'           => "Bienvenue\nVille de Kasumbalesa",
		'popup_button'          => 'Lire le mot du Maire',

		// Héros.
		'hero_eyebrow'          => 'République Démocratique du Congo · Haut-Katanga',
		'hero_title'            => 'Bienvenue sur le site officiel de la *Ville de Kasumbalesa*',
		'hero_subtitle'         => 'Kasumba ka Lesa — la ville-montagne de Dieu, porte de la RDC vers la Zambie.',
		'hero_btn1_label'       => 'Découvrir la ville',
		'hero_btn1_url'         => '#decouvrir',
		'hero_btn2_label'       => 'Actualités',
		'hero_btn2_url'         => '',
		'hero_overlay'          => 30,
		'hero_interval'         => 7,
		'hero_slide_1'          => 0,
		'hero_slide_2'          => 0,
		'hero_slide_3'          => 0,
		'hero_slide_4'          => 0,
		'hero_slide_5'          => 0,

		// Bande défilante.
		'ticker_display'        => 'all',
		'ticker_label'          => 'À la une',
		'ticker_speed'          => 'normal',

		// Section « Mot du Maire ».
		'mayor_section_enable'  => 1,
		'mayor_section_eyebrow' => 'Mot d’accueil',
		'mayor_readmore'        => 'Lire la suite',
		'mayor_readless'        => 'Réduire le texte',

		// Actualités.
		'news_eyebrow'          => 'Actualités de la Ville',
		'news_title'            => 'Les dernières *nouvelles*',
		'news_all_label'        => 'Toutes les actualités',

		// Accès rapides.
		'qa_eyebrow'            => 'Accès rapides',
		'qa_title'              => 'Vos services *en un clic*',
		'qa_image'              => 0,
		'qa_1_title'            => 'Démarches',
		'qa_1_text'             => 'État civil, documents administratifs et formalités.',
		'qa_1_url'              => '',
		'qa_1_slug'             => 'demarches',
		'qa_1_icon'             => 'document',
		'qa_2_title'            => 'Bureau du Maire',
		'qa_2_text'             => 'Le cabinet, l’agenda et les communiqués officiels.',
		'qa_2_url'              => '',
		'qa_2_slug'             => 'bureau-du-maire',
		'qa_2_icon'             => 'building',
		'qa_3_title'            => 'Douane & commerce',
		'qa_3_text'             => 'Le grand poste frontalier entre la RDC et la Zambie.',
		'qa_3_url'              => '',
		'qa_3_slug'             => 'douane-commerce',
		'qa_3_icon'             => 'customs',
		'qa_4_title'            => 'Tourisme',
		'qa_4_text'             => 'Paysages, culture et hospitalité du Haut-Katanga.',
		'qa_4_url'              => '',
		'qa_4_slug'             => 'tourisme',
		'qa_4_icon'             => 'compass',

		// Chiffres clés (vides par défaut : rien n'est affiché tant qu'ils ne sont pas renseignés).
		'figures_eyebrow'       => 'Kasumbalesa en chiffres',
		'figures_title'         => 'Une ville *en mouvement*',
		'figures_image'         => 0,
		'fig_1_number'          => '',
		'fig_1_suffix'          => '',
		'fig_1_label'           => '',
		'fig_2_number'          => '',
		'fig_2_suffix'          => '',
		'fig_2_label'           => '',
		'fig_3_number'          => '',
		'fig_3_suffix'          => '',
		'fig_3_label'           => '',
		'fig_4_number'          => '',
		'fig_4_suffix'          => '',
		'fig_4_label'           => '',

		// Toponymie & carte postale.
		'topo_eyebrow'          => 'Toponymie',
		'topo_title'            => 'Kasumba ka Lesa',
		'topo_text'             => "Le nom de Kasumbalesa vient de « Kasumba ka Lesa », « la montagne de Dieu ».\n\nVille-frontière entre la République Démocratique du Congo et la Zambie, elle porte dans son nom l’attachement de ses habitants à leur terre.",
		'topo_image'            => 0,
		'topo_link_label'       => 'Découvrir son histoire',
		'topo_link_url'         => '',

		// Carte retournable (avant le pied de page, sur toutes les pages).
		'flip_enable'           => 1,
		'flip_front_title'      => 'Ville de Kasumbalesa',
		'flip_front_text'       => 'Ville frontalière du Haut-Katanga',
		'flip_back_title'       => 'Town of Kasumbalesa',
		'flip_back_text'        => 'Border town of Haut-Katanga',
		'flip_image'            => 0,
		'flip_link_url'         => '',

		// Bandeau institutionnel du pied de page (armoiries, devise, photo de la montagne).
		'heritage_emblem_1'     => 6041,
		'heritage_emblem_2'     => 6044,
		'heritage_photo'        => 7153,
		'heritage_line'         => 'Ville de la République Démocratique du Congo · Province du Haut-Katanga',
		'heritage_title'        => 'Site officiel de la *Ville de Kasumbalesa*',
		'heritage_motto'        => 'Kasumbalesa, la ville-montagne de Dieu',
		'postcard_image'        => 0,
		'postcard_title'        => 'Souvenir de Kasumbalesa',
		'postcard_text'         => 'Haut-Katanga · République Démocratique du Congo',

		// Bande typographique.
		'marquee_enable'        => 1,
		'marquee_text'          => 'Kasumba ka Lesa · La ville-montagne de Dieu · Haut-Katanga · Porte de la Zambie',

		// Coordonnées & réseaux.
		'contact_phone'         => '',
		'contact_phone_2'       => '',
		'contact_email'         => 'contact@ville-de-kasumbalesa.cd',
		'contact_address'       => "Hôtel de Ville de Kasumbalesa\nProvince du Haut-Katanga\nRépublique Démocratique du Congo",
		'contact_hours'         => '',
		'social_facebook'       => '',
		'social_x'              => '',
		'social_youtube'        => '',
		'social_instagram'      => '',
		'social_linkedin'       => '',
		'social_tiktok'         => '',
		'social_whatsapp'       => '',

		// Pied de page.
		'footer_about'          => '',
		'footer_credit'         => '© {year} Ville de Kasumbalesa – Réalisation Translog SARL',

		// Langues.
		'lang_enable'           => 1,
		'lang_fr_url'           => '',
		'lang_en_url'           => '',

		// SEO & page d'accueil.
		'seo_description'       => '',
		'seo_image'             => 0,
		'front_show_content'    => 0,
	);

	return $defaults;
}

/**
 * Réglages texte traduisibles (enregistrés auprès de Polylang si présent).
 *
 * @return string[]
 */
function kb_translatable_mods() {
	return array(
		'mayor_role', 'popup_eyebrow', 'popup_title', 'popup_button',
		'hero_eyebrow', 'hero_title', 'hero_subtitle', 'hero_btn1_label', 'hero_btn2_label',
		'ticker_label', 'mayor_section_eyebrow', 'mayor_readmore', 'mayor_readless',
		'news_eyebrow', 'news_title', 'news_all_label',
		'qa_eyebrow', 'qa_title',
		'qa_1_title', 'qa_1_text', 'qa_2_title', 'qa_2_text', 'qa_3_title', 'qa_3_text', 'qa_4_title', 'qa_4_text',
		'figures_eyebrow', 'figures_title',
		'fig_1_label', 'fig_2_label', 'fig_3_label', 'fig_4_label',
		'topo_eyebrow', 'topo_title', 'topo_text', 'topo_link_label',
		'flip_front_title', 'flip_front_text', 'flip_back_title', 'flip_back_text',
		'heritage_line', 'heritage_title', 'heritage_motto',
		'postcard_title', 'postcard_text', 'marquee_text',
		'contact_address', 'contact_hours', 'footer_about', 'footer_credit', 'seo_description',
	);
}

/**
 * Lit un réglage du thème (avec sa valeur par défaut, traduit par Polylang si actif).
 *
 * @param string $key Nom du réglage.
 * @return mixed
 */
function kb_mod( $key ) {
	$defaults = kb_defaults();
	$default  = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
	$value    = get_theme_mod( $key, $default );

	if ( is_string( $value ) && '' !== $value && function_exists( 'pll__' ) && in_array( $key, kb_translatable_mods(), true ) ) {
		$value = pll__( $value );
	}

	return $value;
}

/**
 * Enregistre les textes du Customizer dans Polylang (Langues > Traductions des chaînes).
 */
function kb_register_polylang_strings() {
	if ( ! function_exists( 'pll_register_string' ) ) {
		return;
	}
	foreach ( kb_translatable_mods() as $key ) {
		$value = get_theme_mod( $key, kb_defaults()[ $key ] );
		if ( is_string( $value ) && '' !== $value ) {
			pll_register_string( $key, $value, 'Kasumbalesa', false !== strpos( $value, "\n" ) );
		}
	}
}
add_action( 'init', 'kb_register_polylang_strings' );

/**
 * Renvoie la page du Mot du Maire : celle choisie dans le Customizer,
 * sinon la page « mot-du-maire » / « Mot du Maire ».
 *
 * @return WP_Post|null
 */
function kb_get_mayor_page() {
	static $page = false;
	if ( false !== $page ) {
		return $page;
	}

	$page = null;
	$id   = absint( get_theme_mod( 'mayor_page', 0 ) );

	if ( ! $id ) {
		$found = get_page_by_path( 'mot-du-maire' );
		if ( ! $found ) {
			$query = new WP_Query(
				array(
					'post_type'              => 'page',
					'title'                  => 'Mot du Maire',
					'posts_per_page'         => 1,
					'no_found_rows'          => true,
					'update_post_term_cache' => false,
				)
			);
			$found = $query->have_posts() ? $query->posts[0] : null;
		}
		$id = $found ? $found->ID : 0;
	}

	if ( $id && function_exists( 'pll_get_post' ) ) {
		$translated = pll_get_post( $id );
		$id         = $translated ? $translated : $id;
	}

	if ( $id && 'publish' === get_post_status( $id ) ) {
		$page = get_post( $id );
	}

	return $page;
}

/**
 * Extrait du Mot du Maire : l'extrait manuel s'il existe, sinon le début du texte.
 *
 * @param WP_Post $page  Page du Maire.
 * @param int     $words Nombre de mots.
 * @return string
 */
function kb_mayor_excerpt( $page, $words = 60 ) {
	if ( has_excerpt( $page ) ) {
		return get_the_excerpt( $page );
	}
	$text = strip_shortcodes( $page->post_content );
	$text = excerpt_remove_blocks( $text );
	$text = wp_strip_all_tags( $text );
	return wp_trim_words( $text, $words, '…' );
}

/**
 * Titre avec mise en valeur : les mots entourés d'astérisques (*ainsi*) passent en italique doré.
 *
 * @param string $text Texte saisi.
 * @return string HTML sûr.
 */
function kb_rich_title( $text ) {
	return preg_replace( '/\*([^*]+)\*/u', '<em>$1</em>', esc_html( (string) $text ) );
}

/**
 * Texte brut d'un titre enrichi (sans astérisques).
 *
 * @param string $text Texte saisi.
 * @return string
 */
function kb_plain_title( $text ) {
	return str_replace( '*', '', (string) $text );
}

/**
 * Lien tel: à partir d'un numéro saisi librement.
 *
 * @param string $phone Numéro.
 * @return string
 */
function kb_tel_href( $phone ) {
	return 'tel:' . preg_replace( '/[^0-9+]/', '', (string) $phone );
}

/**
 * Résout une URL saisie dans le Customizer : URL absolue, ancre, ou vide (=> repli).
 *
 * @param string $url      Valeur saisie.
 * @param string $fallback URL de repli.
 * @return string
 */
function kb_resolve_url( $url, $fallback = '' ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return $fallback;
	}
	if ( 0 === strpos( $url, '#' ) && ! is_front_page() ) {
		return home_url( '/' ) . $url;
	}
	return $url;
}

/**
 * URL de la page des articles (ou de l'archive par défaut).
 *
 * @return string
 */
function kb_news_url() {
	$posts_page = (int) get_option( 'page_for_posts' );
	if ( $posts_page ) {
		return get_permalink( $posts_page );
	}
	return home_url( '/' );
}

/**
 * Identifiants des images du diaporama du héros.
 *
 * @return int[]
 */
function kb_hero_slide_ids() {
	$ids = array();
	for ( $i = 1; $i <= 5; $i++ ) {
		$id = absint( get_theme_mod( 'hero_slide_' . $i, 0 ) );
		if ( $id && wp_attachment_is_image( $id ) ) {
			$ids[] = $id;
		}
	}
	return $ids;
}

/**
 * Réseaux sociaux pris en charge.
 *
 * @return array clé => libellé
 */
function kb_social_networks() {
	return array(
		'facebook'  => 'Facebook',
		'x'         => 'X',
		'youtube'   => 'YouTube',
		'instagram' => 'Instagram',
		'linkedin'  => 'LinkedIn',
		'tiktok'    => 'TikTok',
		'whatsapp'  => 'WhatsApp',
	);
}

/**
 * Chiffres clés renseignés (les entrées vides ou nulles sont ignorées).
 *
 * @return array
 */
function kb_key_figures() {
	$figures = array();
	for ( $i = 1; $i <= 4; $i++ ) {
		$raw   = trim( (string) get_theme_mod( 'fig_' . $i . '_number', '' ) );
		$label = trim( (string) kb_mod( 'fig_' . $i . '_label' ) );
		$num   = (float) str_replace( array( ' ', "\u{00A0}", "\u{202F}", ',' ), array( '', '', '', '.' ), $raw );
		if ( '' === $raw || '' === $label || $num <= 0 ) {
			continue;
		}
		$figures[] = array(
			'number'   => $num,
			'decimals' => ( floor( $num ) === $num ) ? 0 : 1,
			'suffix'   => (string) get_theme_mod( 'fig_' . $i . '_suffix', '' ),
			'label'    => $label,
		);
	}
	return $figures;
}

/**
 * Temps de lecture estimé (minutes).
 *
 * @param int|WP_Post|null $post Article.
 * @return int
 */
function kb_reading_time( $post = null ) {
	$post  = get_post( $post );
	$words = str_word_count( wp_strip_all_tags( (string) $post->post_content ) );
	return max( 1, (int) ceil( $words / 220 ) );
}

/**
 * Texte du crédit de pied de page, avec l'année automatique.
 *
 * @return string
 */
function kb_footer_credit() {
	return str_replace( '{year}', wp_date( 'Y' ), (string) kb_mod( 'footer_credit' ) );
}

/**
 * La page affichée utilise-t-elle le modèle pleine largeur ?
 *
 * @return bool
 */
function kb_is_full_width() {
	return is_page_template( 'templates/template-full-width.php' );
}

/**
 * Photo de secours pour les en-têtes de page : la 1re image du diaporama d'accueil.
 *
 * @return int
 */
function kb_fallback_hero_image() {
	$ids = kb_hero_slide_ids();
	return $ids ? (int) $ids[0] : absint( kb_mod( 'topo_image' ) );
}

/**
 * L'en-tête du site se pose en transparence sur une photo (accueil, pages, articles, listes).
 *
 * @return bool
 */
function kb_header_overlays() {
	if ( is_404() || kb_is_full_width() ) {
		return false;
	}
	return is_front_page() || is_singular() || is_home() || is_archive() || is_search();
}

/**
 * Deux boutons de l'en-tête d'une page, vers d'autres pages du site.
 * Correspondances choisies par la Mairie (identifiant de page => pages cibles),
 * sinon les deux pages suivantes du menu principal.
 *
 * @param int $current ID de la page affichée (0 pour une liste, -1 pour un article).
 * @return array<int, array{label:string,url:string}>
 */
function kb_hero_links( $current = 0 ) {
	$by_slug = array(
		'bureau-du-maire'    => array( 'historique', '@news' ),
		'historique'         => array( 'bureau-du-maire', 'ressources-projets' ),
		'ressources-projets' => array( 'bureau-du-maire', '@news' ),
		'mot-du-maire'       => array( 'bureau-du-maire', '@news' ),
		'@news'              => array( 'historique', 'bureau-du-maire' ),
		'@post'              => array( '@news', 'historique' ),
	);
	$posts_page = (int) get_option( 'page_for_posts' );
	$key        = -1 === $current ? '@post' : '@news';
	if ( $current > 0 && $current !== $posts_page ) {
		$key = (string) get_post_field( 'post_name', $current );
	}

	$resolve = static function ( $slug ) use ( $posts_page ) {
		if ( '@news' === $slug ) {
			return $posts_page ? array( 'label' => html_entity_decode( get_the_title( $posts_page ), ENT_QUOTES, 'UTF-8' ), 'url' => get_permalink( $posts_page ) ) : null;
		}
		$page = get_page_by_path( $slug );
		return ( $page && 'publish' === $page->post_status ) ? array( 'label' => html_entity_decode( get_the_title( $page ), ENT_QUOTES, 'UTF-8' ), 'url' => get_permalink( $page ) ) : null;
	};

	$links = array();
	if ( isset( $by_slug[ $key ] ) ) {
		foreach ( $by_slug[ $key ] as $slug ) {
			$link = $resolve( $slug );
			if ( $link ) {
				$links[] = $link;
			}
		}
	}

	// Repli : pages suivantes du menu principal (hors accueil et page courante).
	if ( count( $links ) < 2 ) {
		$locations = get_nav_menu_locations();
		$items     = empty( $locations['primary'] ) ? array() : (array) wp_get_nav_menu_items( $locations['primary'] );
		$items     = array_values(
			array_filter(
				$items,
				static function ( $item ) use ( $current ) {
					return ! $item->menu_item_parent && (int) $item->object_id !== (int) $current && (int) $item->object_id !== (int) get_option( 'page_on_front' ) && untrailingslashit( $item->url ) !== untrailingslashit( home_url() );
				}
			)
		);
		foreach ( $items as $item ) {
			if ( count( $links ) >= 2 ) {
				break;
			}
			if ( ! in_array( $item->url, wp_list_pluck( $links, 'url' ), true ) ) {
				$links[] = array( 'label' => html_entity_decode( $item->title, ENT_QUOTES, 'UTF-8' ), 'url' => $item->url );
			}
		}
	}
	return array_slice( $links, 0, 2 );
}
