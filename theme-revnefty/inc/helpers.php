<?php
/**
 * Valeurs par défaut et lecture des réglages du Customizer.
 *
 * @package RevNefty
 */

defined( 'ABSPATH' ) || exit;

/**
 * Valeurs par défaut de tous les réglages (modifiables dans Apparence › Personnaliser).
 *
 * @return array
 */
function rn_defaults() {
	return array(
		// Accueil : héros.
		'hero_eyebrow'     => 'XAUUSD · Prop firm · Gestion du risque',
		'hero_title'       => 'Tradez l’or avec *méthode*, pas avec l’émotion.',
		'hero_text'        => 'RevNefty Capital Finance structure chaque trade sur l’or : un plan de semaine écrit à l’avance, un risque fixe par position et un journal qui ne ment pas. La discipline d’une prop firm, au service de vos challenges.',
		'hero_cta_label'   => 'Découvrir la méthode',
		'hero_cta_url'     => '#methode',
		'hero_cta2_label'  => 'Nous contacter',
		'hero_cta2_url'    => '#contact',
		'hero_point_1'     => 'Plan de la semaine',
		'hero_point_2'     => 'Risque fixe par trade',
		'hero_point_3'     => 'Journal et statistiques',

		// Bandeau des cours.
		'ticker_enable'    => 1,

		// Méthode : trois étapes.
		'steps_eyebrow'    => 'La méthode',
		'steps_title'      => 'Trois temps, chaque semaine',
		'step_1_title'     => 'Analyser',
		'step_1_text'      => 'Le dimanche, le plan de la semaine fixe le biais, les zones à surveiller, les niveaux de liquidité et l’invalidation. Les annonces économiques sont repérées à l’avance.',
		'step_2_title'     => 'Exécuter',
		'step_2_text'      => 'On n’entre que sur une zone du plan, après une confirmation claire. Stop placé avant l’entrée, lot calculé sur le risque, sortie partielle à 2R.',
		'step_3_title'     => 'Revoir',
		'step_3_text'      => 'Chaque soir, le plan est relu ; chaque vendredi, la semaine est jugée. Les chiffres du journal disent ce qui marche, et ce qu’il faut arrêter.',

		// Piliers.
		'pillars_eyebrow'  => 'Nos outils',
		'pillars_title'    => 'Ce qui fait la différence',
		'pillar_1_title'   => 'Plan hebdomadaire',
		'pillar_1_text'    => 'Prévision, zones, niveaux, scénarios et annonces de la semaine, mis à jour au fil des séances.',
		'pillar_2_title'   => 'Gestion du risque',
		'pillar_2_text'    => 'Risque fixe par trade, limite de pertes journalière, arrêt automatique à l’objectif du jour.',
		'pillar_3_title'   => 'Journal et statistiques',
		'pillar_3_text'    => 'Chaque trade noté : setup, niveau, émotion, respect des règles. Réussite, R moyen, courbe et simulations.',
		'pillar_4_title'   => 'Signaux et alertes',
		'pillar_4_text'    => 'Zones tracées sur le graphique, signaux d’exécution, alertes sur mobile avant chaque annonce.',

		// Cadre de risque (style prop firm).
		'risk_eyebrow'     => 'Le cadre',
		'risk_title'       => 'Des règles écrites avant le premier trade',
		'risk_text'        => 'Les mêmes limites qu’un challenge prop firm, appliquées sur chaque compte.',
		'risk_1_value'     => '8 %',
		'risk_1_label'     => 'Objectif phase 1',
		'risk_2_value'     => '5 %',
		'risk_2_label'     => 'Objectif phase 2',
		'risk_3_value'     => '5 %',
		'risk_3_label'     => 'Perte journalière max',
		'risk_4_value'     => '10 %',
		'risk_4_label'     => 'Perte totale max',
		'risk_5_value'     => '1,5–4 $',
		'risk_5_label'     => 'Stop sur l’or',
		'risk_6_value'     => '2R',
		'risk_6_label'     => 'Sortie partielle',

		// FAQ.
		'faq_eyebrow'      => 'Questions fréquentes',
		'faq_title'        => 'Bon à savoir',
		'faq_1_q'          => 'Pourquoi uniquement l’or (XAUUSD) ?',
		'faq_1_a'          => 'Un seul marché, étudié en profondeur : ses sessions, ses annonces, sa liquidité. La spécialisation rend les plans plus précis et les statistiques plus fiables.',
		'faq_2_q'          => 'Qu’est-ce qu’un challenge prop firm ?',
		'faq_2_a'          => 'Une évaluation où l’on trade le capital d’une société en respectant des règles strictes : objectif de gain, perte journalière et perte totale maximales. Réussie, elle donne accès à un compte financé.',
		'faq_3_q'          => 'Comment le risque est-il géré ?',
		'faq_3_a'          => 'Le risque est fixé en dollars avant chaque trade, le lot en découle. Après une série de pertes, le risque baisse ; à la limite journalière, la séance s’arrête.',
		'faq_4_q'          => 'Le trading est-il risqué ?',
		'faq_4_a'          => 'Oui. Le trading sur marge comporte un risque élevé de perte en capital. Les résultats passés ne préjugent pas des résultats futurs. Ne tradez que ce que vous pouvez vous permettre de perdre.',

		// Appel final et contact.
		'cta_title'        => 'Prêt à trader avec un plan ?',
		'cta_text'         => 'Écrivez-nous pour en savoir plus sur RevNefty Capital Finance.',
		'cta_label'        => 'Nous écrire',
		'contact_email'    => '',
		'contact_phone'    => '',
		'contact_whatsapp' => '',
		'social_telegram'  => '',
		'social_x'         => '',
		'social_instagram' => '',
		'social_youtube'   => '',
		'social_tiktok'    => '',

		// Pied de page.
		'footer_about'     => 'Trading de l’or, gestion du risque et accompagnement aux challenges prop firm.',
		'risk_disclaimer'  => 'Avertissement : le trading de produits à effet de levier (CFD, forex, matières premières) comporte un risque élevé de perte rapide en capital. Les contenus de ce site sont fournis à titre informatif et éducatif ; ils ne constituent ni un conseil en investissement ni une incitation à trader. Les performances passées ne préjugent pas des performances futures.',
		'footer_credit'    => '',
	);
}

/**
 * Lit un réglage, avec sa valeur par défaut.
 *
 * @param string $key Clé.
 * @return mixed
 */
function rn_mod( $key ) {
	$d = rn_defaults();
	return get_theme_mod( $key, $d[ $key ] ?? '' );
}

/**
 * Titre avec mise en valeur : *mot* devient <em>mot</em> (texte échappé).
 *
 * @param string $text Texte.
 * @return string HTML.
 */
function rn_rich_title( $text ) {
	return preg_replace( '/\*([^*]+)\*/u', '<em>$1</em>', esc_html( $text ) );
}

/**
 * Titre sans marqueurs de mise en valeur.
 *
 * @param string $text Texte.
 * @return string
 */
function rn_plain_title( $text ) {
	return str_replace( '*', '', (string) $text );
}

/**
 * Le modèle « Application plein écran » est-il utilisé ?
 *
 * @return bool
 */
function rn_is_app() {
	return is_page_template( 'templates/template-app.php' );
}

/**
 * Lien de contact par défaut : e-mail, sinon WhatsApp, sinon ancre du pied de page.
 *
 * @return string
 */
function rn_contact_url() {
	$email = sanitize_email( rn_mod( 'contact_email' ) );
	if ( $email ) {
		return 'mailto:' . antispambot( $email );
	}
	$wa = preg_replace( '/\D+/', '', (string) rn_mod( 'contact_whatsapp' ) );
	if ( $wa ) {
		return 'https://wa.me/' . $wa;
	}
	return '#contact';
}

/**
 * Réseaux sociaux renseignés.
 *
 * @return array Liste de [clé, libellé, url].
 */
function rn_social_networks() {
	$out = array();
	foreach ( array(
		'telegram'  => 'Telegram',
		'x'         => 'X',
		'instagram' => 'Instagram',
		'youtube'   => 'YouTube',
		'tiktok'    => 'TikTok',
	) as $key => $label ) {
		$url = esc_url( rn_mod( 'social_' . $key ) );
		if ( $url ) {
			$out[] = array( $key, $label, $url );
		}
	}
	return $out;
}
