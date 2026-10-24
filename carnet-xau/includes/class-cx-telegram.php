<?php
/**
 * Bot Telegram (mode webhook) et tâches planifiées (WP-Cron, toutes les 5 minutes) :
 * - alerte à 80 % puis à 100 % de la limite de perte journalière ;
 * - annonce économique du plan dans moins de 15 minutes ;
 * - rappel du plan le week-end (samedi 10 h, dimanche 18 h) s'il n'est pas prêt ;
 * - commandes /jour, /plan, /rapport, /aide.
 *
 * Liaison sécurisée : seul le chat qui envoie « /start CODE » (code affiché dans le carnet)
 * reçoit les alertes ; les requêtes du webhook doivent porter l'en-tête secret de Telegram.
 *
 * @package CarnetXAU
 */

defined( 'ABSPATH' ) || exit;

/**
 * Telegram et WP-Cron.
 */
class CX_Telegram {

	const CRON = 'cx_cron';

	/**
	 * Accroches.
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval
		add_action( self::CRON, array( __CLASS__, 'run_cron' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Intervalle de 5 minutes.
	 *
	 * @param array $s Intervalles.
	 * @return array
	 */
	public static function schedules( $s ) {
		$s['cx_5min'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => 'Toutes les 5 minutes (Carnet XAU)',
		);
		return $s;
	}

	/**
	 * Programme la tâche si besoin.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'cx_5min', self::CRON );
		}
	}

	/**
	 * Route du webhook (publique, protégée par l'en-tête secret).
	 */
	public static function routes() {
		register_rest_route(
			CX_Rest::NS,
			'/telegram',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Réglages et API
	 * ------------------------------------------------------------------ */

	/**
	 * Réglages Telegram.
	 *
	 * @return array {token, bot, secret, code, chat, alerts{daily,news,weekend}}
	 */
	public static function cfg() {
		$c = CX_Guard::settings()['telegram'] ?? array();
		$c = is_array( $c ) ? $c : array();
		$c['alerts'] = array_merge(
			array(
				'daily'   => true,
				'news'    => true,
				'weekend' => true,
				'signals' => true,
				'report'  => true,
			),
			is_array( $c['alerts'] ?? null ) ? $c['alerts'] : array()
		);
		return $c;
	}

	/**
	 * Appel de l'API Telegram.
	 *
	 * @param string $method Méthode (sendMessage, getMe…).
	 * @param array  $params Paramètres.
	 * @param string $token  Jeton du bot (par défaut celui enregistré).
	 * @return array|WP_Error Champ « result » de la réponse.
	 */
	public static function api( $method, $params = array(), $token = null ) {
		$token = $token ?? ( self::cfg()['token'] ?? '' );
		if ( '' === (string) $token ) {
			return new WP_Error( 'cx_tg', 'Bot Telegram non configuré.' );
		}
		$res = wp_remote_post(
			'https://api.telegram.org/bot' . $token . '/' . $method,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $params ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'cx_tg', 'Telegram injoignable : ' . $res->get_error_message() );
		}
		$j = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( empty( $j['ok'] ) ) {
			return new WP_Error( 'cx_tg', 'Telegram : ' . ( $j['description'] ?? 'réponse invalide' ) );
		}
		return $j['result'] ?? array();
	}

	/**
	 * Envoie un message au chat lié et, s'il y en a une, à la chaîne Telegram.
	 *
	 * @param string $text  Texte (HTML simple de Telegram).
	 * @param bool   $reply Réponse à une commande : chat privé seulement.
	 * @return bool|WP_Error
	 */
	public static function send( $text, $reply = false ) {
		$c = self::cfg();
		if ( empty( $c['token'] ) || empty( $c['chat'] ) ) {
			return new WP_Error( 'cx_tg', 'Bot non relié à un chat : envoyez /start et le code au bot.' );
		}
		$text = self::nb( $text );
		$to = array( $c['chat'] );
		if ( ! $reply && ! empty( $c['channel'] ) ) {
			$to[] = $c['channel'];
		}
		$err = null;
		foreach ( $to as $chat ) {
			$r = self::post( $chat, $text );
			if ( is_wp_error( $r ) ) {
				$err = $r;
			}
		}
		return $err ?? true;
	}

	/**
	 * Espace des milliers insécable : « 4 044,70 » n'est jamais coupé en fin de ligne sur le téléphone.
	 *
	 * @param string $s Texte.
	 * @return string
	 */
	private static function nb( $s ) {
		return (string) preg_replace( '/(\d)[ \x{00A0}](?=\d{3}(?!\d))/u', "\$1\u{202F}", (string) $s );
	}

	/**
	 * Envoie une image (graphique de Claude) au chat lié et à la chaîne.
	 *
	 * @param string $file    Chemin du fichier PNG.
	 * @param string $caption Légende (HTML simple, 1 024 caractères au plus).
	 * @return bool|WP_Error
	 */
	public static function photo( $file, $caption ) {
		$c = self::cfg();
		if ( empty( $c['token'] ) || empty( $c['chat'] ) ) {
			return new WP_Error( 'cx_tg', 'Bot non relié à un chat.' );
		}
		$img = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$err = null;
		foreach ( array_filter( array( $c['chat'], $c['channel'] ?? '' ) ) as $chat ) {
			$b    = 'cx' . wp_generate_password( 20, false );
			$body = '';
			foreach ( array(
				'chat_id'    => (string) $chat,
				'caption'    => mb_substr( self::nb( $caption ), 0, 1024 ),
				'parse_mode' => 'HTML',
			) as $k => $v ) {
				$body .= "--$b\r\nContent-Disposition: form-data; name=\"$k\"\r\n\r\n$v\r\n";
			}
			$body .= "--$b\r\nContent-Disposition: form-data; name=\"photo\"; filename=\"graphique.png\"\r\nContent-Type: image/png\r\n\r\n" . $img . "\r\n--$b--\r\n";
			$res  = wp_remote_post(
				'https://api.telegram.org/bot' . $c['token'] . '/sendPhoto',
				array(
					'timeout' => 30,
					'headers' => array( 'Content-Type' => 'multipart/form-data; boundary=' . $b ),
					'body'    => $body,
				)
			);
			$j = is_wp_error( $res ) ? null : json_decode( wp_remote_retrieve_body( $res ), true );
			if ( empty( $j['ok'] ) ) {
				$err = new WP_Error( 'cx_tg', 'Telegram : ' . ( is_wp_error( $res ) ? $res->get_error_message() : ( $j['description'] ?? 'réponse invalide' ) ) );
			}
		}
		return $err ?? true;
	}

	/**
	 * Légende du graphique d'une séance : titre, biais, scénarios d'exécution.
	 *
	 * @param array $s Séance.
	 * @return string
	 */
	public static function chart_caption( $s ) {
		$t = array(
			'asia'      => '🌏 <b>Session Asie</b>',
			'london'    => '🇬🇧 <b>Plan de Londres</b>',
			'premarket' => '🧭 <b>Pré-marché US</b>',
			'us'        => '🇺🇸 <b>Plan de New York</b>',
		)[ $s['kind'] ] ?? '<b>Séance</b>';
		$l = array( $t . ' — graphique' );
		if ( ! empty( $s['bias'] ) ) {
			$l[] = 'Biais : ' . array( 'h' => 'haussier', 'b' => 'baissier', 'n' => 'neutre' )[ $s['bias'] ];
		}
		// Rappel court de chaque fiche ; la fiche complète est dans le message du plan.
		foreach ( array_values( (array) ( $s['positions'] ?? array() ) ) as $i => $p ) {
			$sell = 'vente' === ( $p['side'] ?? '' );
			$l[]  = '';
			$l[]  = sprintf( '%s <b>%d. %s</b>%s', $sell ? '🔴' : '🟢', $i + 1, $sell ? 'VENTE' : 'ACHAT', empty( $p['label'] ) ? '' : ' — ' . esc_html( $p['label'] ) );
			$l[]  = '• Entrée : ' . self::usd( (float) $p['entry'] );
			$l[]  = '• Stop : ' . self::usd( (float) $p['sl'] );
			$l[]  = '• Objectif : ' . self::usd( (float) $p['tp'] );
		}
		if ( ! empty( $s['invalid'] ) ) {
			$l[] = '';
			$l[] = '⛔ <b>Annule le plan</b>';
			$l[] = esc_html( $s['invalid'] );
		}
		// Légende de photo : 1 024 caractères au plus ; on retire des lignes entières, jamais une balise.
		while ( count( $l ) > 1 && mb_strlen( implode( "\n", $l ) ) > 1000 ) {
			array_pop( $l );
		}
		return implode( "\n", $l );
	}

	/**
	 * Risque par trade du compte actif, calculé comme dans l'onglet Jour, avec le contrat et la commission.
	 *
	 * @return array {risk, contract, commission}
	 */
	public static function exec_risk() {
		$a = self::active();
		if ( ! $a ) {
			return array(
				'risk'       => 0.0,
				'contract'   => 100.0,
				'commission' => 0.0,
			);
		}
		$pa   = $a['params'];
		$snap = ( (array) CX_Ingest::latest_snapshots() )[ $a['id'] ] ?? null;
		$risk = 'propre' === ( $pa['kind'] ?? 'propfirm' )
			? CX_Repo::own_risk( $snap ? (float) $snap['balance'] : (float) $a['size'], $pa )['risk']
			: CX_Repo::prop_limits( $a, $snap )['risk'];
		return array(
			'risk'       => (float) $risk,
			'contract'   => max( 1.0, (float) $pa['contract'] ),
			'commission' => (float) ( $pa['commission'] ?? 0 ),
		);
	}

	/**
	 * Fiche d'exécution d'un scénario : attendre, déclencheur, ordre avec le lot du compte actif, annulé si.
	 *
	 * @param array $p  Scénario {side, entry, sl, tp, label, setup, wait, trigger, cancel}.
	 * @param int   $n  Numéro.
	 * @param array $rk Risque du compte actif (exec_risk).
	 * @return string[]
	 */
	public static function card_lines( $p, $n, $rk ) {
		$e    = fn( $v ) => esc_html( (string) $v );
		$stop = abs( (float) $p['entry'] - (float) $p['sl'] );
		$rr   = $stop > 0 ? abs( (float) $p['tp'] - (float) $p['entry'] ) / $stop : 0;
		$cost = $stop * $rk['contract'] + $rk['commission'];
		$lot  = $cost > 0 && $rk['risk'] > 0 ? floor( $rk['risk'] / $cost * 100 + 1e-9 ) / 100 : 0;
		$sell = 'vente' === ( $p['side'] ?? '' );
		$out  = array( sprintf( '%s <b>%d. %s</b>%s', $sell ? '🔴' : '🟢', $n, $sell ? 'VENTE' : 'ACHAT', empty( $p['setup'] ) ? '' : ' · setup ' . $e( $p['setup'] ) ) );
		if ( ! empty( $p['label'] ) ) {
			$out[] = '<i>' . $e( $p['label'] ) . '</i>';
		}
		if ( ! empty( $p['wait'] ) ) {
			$out[] = '';
			$out[] = '⏳ <b>Attendre</b>';
			$out[] = $e( $p['wait'] );
		}
		if ( ! empty( $p['trigger'] ) ) {
			$out[] = '';
			$out[] = '🎯 <b>Déclencheur</b>';
			$out[] = $e( $p['trigger'] );
		}
		$out[] = '';
		$out[] = '📌 <b>Ordre</b>';
		$out[] = '• Entrée : ' . self::usd( (float) $p['entry'] );
		$out[] = '• Stop : ' . self::usd( (float) $p['sl'] ) . ' (' . number_format( $stop, 2, ',', ' ' ) . ' $)';
		$out[] = '• Objectif : ' . self::usd( (float) $p['tp'] ) . ' (' . number_format( $rr, 1, ',', '' ) . 'R)';
		$out[] = $lot >= 0.01 ? '• Lot : ' . number_format( $lot, 2, ',', '' ) . ' (risque ' . self::usd( $rk['risk'] ) . ')' : '• Lot : stop trop large pour le risque du compte';
		if ( ! empty( $p['cancel'] ) ) {
			$out[] = '';
			$out[] = '✖ <b>Annulé si</b>';
			$out[] = $e( $p['cancel'] );
		}
		return $out;
	}

	/**
	 * Un message vers un chat précis.
	 *
	 * @param string $chat Identifiant du chat ou de la chaîne.
	 * @param string $text Texte.
	 * @return array|WP_Error
	 */
	private static function post( $chat, $text ) {
		// Telegram refuse plus de 4 096 caractères : découpage entre deux lignes, jamais dans une balise.
		$parts = array( '' );
		foreach ( explode( "\n", (string) $text ) as $line ) {
			$cur = &$parts[ count( $parts ) - 1 ];
			if ( '' !== $cur && mb_strlen( $cur ) + mb_strlen( $line ) + 1 > 3900 ) {
				unset( $cur );
				$parts[] = $line;
				continue;
			}
			$cur = '' === $cur ? $line : $cur . "\n" . $line;
			unset( $cur );
		}
		$r = null;
		foreach ( $parts as $part ) {
			$r = self::api(
				'sendMessage',
				array(
					'chat_id'                  => $chat,
					'text'                     => $part,
					'parse_mode'               => 'HTML',
					'disable_web_page_preview' => true,
				)
			);
			if ( is_wp_error( $r ) ) {
				return $r;
			}
		}
		return $r;
	}

	/**
	 * Installe le webhook (messages du bot et ajout du bot à une chaîne).
	 *
	 * @param string $token  Jeton du bot.
	 * @param string $secret Secret attendu dans l'en-tête de Telegram.
	 * @return array|WP_Error
	 */
	private static function set_hook( $token, $secret ) {
		return self::api(
			'setWebhook',
			array(
				'url'                  => rest_url( CX_Rest::NS . '/telegram' ),
				'secret_token'         => $secret,
				'allowed_updates'      => array( 'message', 'my_chat_member' ),
				'drop_pending_updates' => false,
			),
			$token
		);
	}

	/**
	 * Webhook d'une version précédente (sans l'ajout aux chaînes) : réinstallé une fois.
	 */
	private static function upgrade_hook() {
		$c = self::cfg();
		if ( empty( $c['token'] ) || empty( $c['secret'] ) || ( $c['hook'] ?? 0 ) >= 2 ) {
			return;
		}
		if ( ! is_wp_error( self::set_hook( $c['token'], $c['secret'] ) ) ) {
			$c['hook'] = 2;
			CX_Guard::set( 'telegram', $c );
		}
	}

	/**
	 * Retire la chaîne : les messages ne partent plus que dans le chat privé.
	 *
	 * @return array État.
	 */
	public static function channel_off() {
		$c = self::cfg();
		unset( $c['channel'], $c['channel_title'] );
		CX_Guard::set( 'telegram', $c );
		return self::status();
	}

	/**
	 * Connecte un bot : vérifie le jeton, installe le webhook, crée le code de liaison.
	 *
	 * @param string $token Jeton donné par @BotFather.
	 * @return array|WP_Error
	 */
	public static function connect( $token ) {
		$token = trim( (string) $token );
		if ( ! preg_match( '/^\d{5,}:[A-Za-z0-9_-]{30,}$/', $token ) ) {
			return new WP_Error( 'cx_invalid', 'Jeton de bot invalide (format 123456:ABC…).', array( 'status' => 400 ) );
		}
		$me = self::api( 'getMe', array(), $token );
		if ( is_wp_error( $me ) ) {
			$me->add_data( array( 'status' => 400 ) );
			return $me;
		}
		$secret = CX_Guard::secret( 48 );
		$hook   = self::set_hook( $token, $secret );
		if ( is_wp_error( $hook ) ) {
			$hook->add_data( array( 'status' => 400 ) );
			return $hook;
		}
		$old = self::cfg();
		CX_Guard::set(
			'telegram',
			array(
				'token'  => $token,
				'bot'    => (string) ( $me['username'] ?? '' ),
				'secret' => $secret,
				'code'   => strtoupper( CX_Guard::secret( 8 ) ),
				'chat'   => null,
				'hook'   => 2,
				'alerts' => $old['alerts'],
			)
		);
		return self::status();
	}

	/**
	 * Déconnecte le bot (retire le webhook).
	 */
	public static function disconnect() {
		if ( ! empty( self::cfg()['token'] ) ) {
			self::api( 'deleteWebhook' );
		}
		CX_Guard::set( 'telegram', null );
	}

	/**
	 * État pour l'interface (jamais le jeton ni le secret).
	 *
	 * @return array
	 */
	public static function status() {
		$c   = self::cfg();
		$bot = (string) ( $c['bot'] ?? '' );
		return array(
			'connected' => ! empty( $c['token'] ),
			'bot'       => $bot,
			'linked'    => ! empty( $c['chat'] ),
			'code'      => empty( $c['chat'] ) ? (string) ( $c['code'] ?? '' ) : '',
			'startLink' => ( $bot && empty( $c['chat'] ) && ! empty( $c['code'] ) ) ? 'https://t.me/' . $bot . '?start=' . $c['code'] : '',
			'channel'   => empty( $c['channel'] ) ? '' : (string) ( $c['channel_title'] ?? $c['channel'] ),
			'alerts'    => $c['alerts'],
		);
	}

	/**
	 * Active ou coupe des alertes.
	 *
	 * @param array $alerts {daily, news, weekend}.
	 * @return array État.
	 */
	public static function set_alerts( $alerts ) {
		$c = self::cfg();
		foreach ( array( 'daily', 'news', 'weekend', 'signals', 'report' ) as $k ) {
			if ( isset( $alerts[ $k ] ) ) {
				$c['alerts'][ $k ] = (bool) $alerts[ $k ];
			}
		}
		CX_Guard::set( 'telegram', $c );
		return self::status();
	}

	/* ------------------------------------------------------------------
	 * Webhook
	 * ------------------------------------------------------------------ */

	/**
	 * Messages reçus par le bot.
	 *
	 * @param WP_REST_Request $req Requête de Telegram.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function webhook( WP_REST_Request $req ) {
		$limit = CX_Guard::throttle( 'telegram', 60 );
		if ( $limit ) {
			return $limit;
		}
		$c = self::cfg();
		if ( empty( $c['secret'] ) || ! hash_equals( (string) $c['secret'], (string) $req->get_header( 'x_telegram_bot_api_secret_token' ) ) ) {
			return CX_Guard::refuse( 'telegram', 'en-tête secret absent ou faux' );
		}
		$upd = (array) $req->get_json_params();
		if ( isset( $upd['my_chat_member'] ) ) {
			self::channel_member( (array) $upd['my_chat_member'], $c );
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}
		$msg  = $upd['message'] ?? array();
		$chat = isset( $msg['chat']['id'] ) ? (string) $msg['chat']['id'] : '';
		$text = trim( (string) ( $msg['text'] ?? '' ) );
		$ok   = new WP_REST_Response( array( 'ok' => true ), 200 );
		if ( '' === $chat ) {
			return $ok;
		}

		// Liaison : /start CODE (lien t.me/bot?start=CODE).
		if ( empty( $c['chat'] ) ) {
			if ( preg_match( '/^\/start\s+([A-Za-z0-9]+)/', $text, $m ) && ! empty( $c['code'] ) && hash_equals( (string) $c['code'], strtoupper( $m[1] ) ) ) {
				$c['chat'] = $chat;
				$c['code'] = '';
				CX_Guard::set( 'telegram', $c );
				self::send( "✅ <b>Carnet XAU</b> est relié à ce chat.\n\n" . self::help(), true );
			}
			return $ok;
		}
		if ( $chat !== (string) $c['chat'] ) {
			return $ok; // Un autre chat : ignoré.
		}

		$cmd = strtolower( strtok( $text, " @\n" ) );
		if ( '/jour' === $cmd ) {
			self::send( self::day_summary(), true );
		} elseif ( '/plan' === $cmd ) {
			// Le week-end, le plan utile est celui de la semaine qui commence.
			$now = new DateTimeImmutable( 'now', CX_Repo::tz() );
			self::send( self::plan_summary( (int) $now->format( 'N' ) >= 6 ? $now->modify( 'next monday' )->format( 'Y-m-d' ) : self::monday( $now ) ), true );
		} elseif ( '/rapport' === $cmd ) {
			$a = self::active();
			self::send( $a ? CX_Report::summary( CX_Report::data( $a, CX_Report::week() ) ) : 'Aucun compte.', true );
		} else {
			self::send( self::help(), true );
		}
		return $ok;
	}

	/**
	 * Le bot est ajouté à une chaîne (ou en est retiré). La chaîne n'est retenue que si c'est
	 * l'utilisateur relié au carnet qui en a fait le bot administrateur avec le droit de publier.
	 *
	 * @param array $m Mise à jour « my_chat_member ».
	 * @param array $c Réglages Telegram.
	 */
	private static function channel_member( $m, $c ) {
		$chat = (array) ( $m['chat'] ?? array() );
		if ( 'channel' !== ( $chat['type'] ?? '' ) || empty( $c['chat'] ) ) {
			return;
		}
		$id     = (string) ( $chat['id'] ?? '' );
		$title  = (string) ( $chat['title'] ?? $id );
		$member = (array) ( $m['new_chat_member'] ?? array() );
		$admin  = 'administrator' === ( $member['status'] ?? '' ) && ! empty( $member['can_post_messages'] );
		if ( $admin && (string) ( $m['from']['id'] ?? '' ) === (string) $c['chat'] ) {
			if ( (string) ( $c['channel'] ?? '' ) === $id ) {
				return;
			}
			$c['channel']       = $id;
			$c['channel_title'] = $title;
			CX_Guard::set( 'telegram', $c );
			self::post( $id, '📣 Cette chaîne recevra désormais les messages du <b>Carnet XAU</b> : plan de la semaine, relectures, annonces, signaux et rapports.' );
			self::post( $c['chat'], sprintf( '✅ Chaîne « %s » reliée : tous les messages du carnet y sont aussi publiés. Vos commandes (/jour, /plan, /rapport) restent ici.', esc_html( $title ) ) );
		} elseif ( (string) ( $c['channel'] ?? '' ) === $id && ! $admin ) {
			unset( $c['channel'], $c['channel_title'] );
			CX_Guard::set( 'telegram', $c );
			self::post( $c['chat'], sprintf( '⚠️ Le bot n’est plus administrateur de la chaîne « %s » : les messages ne partent plus que dans ce chat.', esc_html( $title ) ) );
		}
	}

	/**
	 * Aide des commandes.
	 *
	 * @return string
	 */
	private static function help() {
		return "Commandes :\n/jour — trades et résultat du jour\n/plan — plan de la semaine\n/rapport — rapport de la semaine\n/aide — cette aide\n\nAlertes : limite journalière, annonce dans 15 min, rappel du plan le week-end, rapport le samedi.";
	}

	/* ------------------------------------------------------------------
	 * Résumés
	 * ------------------------------------------------------------------ */

	/**
	 * Lundi de la semaine en cours (ou d'une date), fuseau du site.
	 *
	 * @param DateTimeImmutable|null $d Date.
	 * @return string AAAA-MM-JJ.
	 */
	public static function monday( $d = null ) {
		$d = $d ?? new DateTimeImmutable( 'now', CX_Repo::tz() );
		return $d->modify( '-' . ( (int) $d->format( 'N' ) - 1 ) . ' days' )->format( 'Y-m-d' );
	}

	/**
	 * Montant en dollars.
	 *
	 * @param float $v Montant.
	 * @return string
	 */
	public static function usd( $v ) {
		return ( $v < 0 ? '−' : '' ) . number_format( abs( $v ), 2, ',', ' ' ) . ' $';
	}

	/**
	 * Compte actif.
	 *
	 * @return array|null
	 */
	public static function active() {
		foreach ( CX_Repo::accounts() as $a ) {
			if ( $a['active'] ) {
				return $a;
			}
		}
		return null;
	}

	/**
	 * Résumé du jour du compte actif.
	 *
	 * @return string
	 */
	public static function day_summary() {
		$a = self::active();
		if ( ! $a ) {
			return 'Aucun compte.';
		}
		$today = CX_Repo::clock( 'Y-m-d' );
		$tt    = array_filter( CX_Repo::trades( $a['id'] ), fn( $t ) => $t['date'] === $today );
		$pnl   = array_sum( array_map( fn( $t ) => (float) $t['pnl'], $tt ) );
		$loss  = count( array_filter( $tt, fn( $t ) => $t['r'] < 0 ) );
		$sn    = ( (array) CX_Ingest::latest_snapshots() )[ $a['id'] ] ?? null;
		$lim   = 'propre' === ( $a['params']['kind'] ?? 'propfirm' )
			? $a['params']['daystopr'] * CX_Repo::own_risk( $sn ? (float) $sn['balance'] : (float) $a['size'], $a['params'] )['risk']
			: CX_Repo::prop_limits( $a, $sn )['firm_daily'];
		$lines = array(
			'<b>' . esc_html( $a['name'] ) . '</b>',
			wp_date( 'l j F', null, CX_Repo::tz() ),
			'',
			sprintf( '• Trades : %d/%d', count( $tt ), $a['params']['maxtrades'] ),
			sprintf( '• Pertes : %d/%d', $loss, $a['params']['maxloss'] ),
			'• Résultat : ' . self::usd( $pnl ),
			'• Limite journalière : ' . self::usd( $lim ),
		);
		$snap = CX_Ingest::latest_snapshots()->{$a['id']} ?? null;
		if ( $snap ) {
			$lines[] = '';
			$lines[] = '<b>MT5</b> (' . substr( $snap['at'], 11, 5 ) . ')';
			$lines[] = '• Solde : ' . self::usd( $snap['balance'] );
			$lines[] = '• Équité : ' . self::usd( $snap['equity'] );
		}
		$news    = CX_Calendar::day( new DateTimeImmutable( 'now', CX_Repo::tz() ) );
		$lines[] = '';
		if ( $news ) {
			$lines[] = '<b>Annonces USD</b>';
			foreach ( $news as $x ) {
				$lines[] = ( 'high' === $x['impact'] ? '🔴 ' : '🟠 ' ) . $x['time'] . ' — ' . esc_html( $x['name'] );
			}
		} else {
			$lines[] = 'Aucune annonce USD importante aujourd’hui.';
		}
		return implode( "\n", $lines );
	}

	/**
	 * Résumé d'un plan hebdomadaire.
	 *
	 * @param string $week Lundi.
	 * @return string
	 */
	public static function plan_summary( $week ) {
		$p = CX_Repo::plan( $week );
		if ( ! $p ) {
			return 'Pas encore de plan pour la semaine du ' . wp_date( 'j F', strtotime( $week ), CX_Repo::tz() ) . '.';
		}
		if ( is_array( $p['content'] ) && ! empty( $p['content']['prediction'] ) ) {
			return self::rich_summary( $week, $p['content'] );
		}
		$bias  = array(
			'h' => 'haussier',
			'b' => 'baissier',
			'n' => 'neutre / range',
		)[ $p['bias'] ] ?? 'non défini';
		$lines = array( '<b>Plan de la semaine du ' . wp_date( 'j F', strtotime( $week ), CX_Repo::tz() ) . '</b>', 'Biais : ' . $bias );
		$lv    = array_filter( $p['levels'], fn( $l ) => '' !== $l['price'] );
		usort( $lv, fn( $x, $y ) => (float) $y['price'] <=> (float) $x['price'] );
		foreach ( $lv as $l ) {
			$lines[] = '• ' . esc_html( $l['price'] . ' ' . $l['type'] . ( $l['note'] ? ' — ' . $l['note'] : '' ) );
		}
		foreach ( $p['cal'] as $e ) {
			$lines[] = '📅 ' . esc_html( $e['day'] . ' ' . $e['time'] . ' ' . $e['event'] );
		}
		foreach ( $p['sc'] as $s ) {
			$lines[] = '→ ' . esc_html( $s['text'] );
		}
		return implode( "\n", $lines );
	}

	/**
	 * Résumé du plan élargi rédigé par Claude.
	 *
	 * @param string $week Lundi.
	 * @param array  $c    Contenu du plan.
	 * @return string
	 */
	private static function rich_summary( $week, $c ) {
		$e     = fn( $s ) => esc_html( (string) $s );
		$p     = $c['prediction'];
		$num   = fn( $v ) => null === $v ? '?' : number_format( (float) $v, 2, ',', ' ' );
		$lines = array(
			'🧭 <b>Plan de la semaine du ' . wp_date( 'j F', strtotime( $week ), CX_Repo::tz() ) . '</b> (Claude)',
			'',
			'• Haussier : ' . (int) $p['bull'] . ' %',
			'• Baissier : ' . (int) $p['bear'] . ' %',
			'• Range : ' . (int) $p['range'] . ' %',
		);
		if ( null !== $p['range_low'] && null !== $p['range_high'] ) {
			$lines[] = '• Range attendu : ' . $num( $p['range_low'] ) . ' – ' . $num( $p['range_high'] );
		}
		if ( null !== $p['invalidation'] ) {
			$lines[] = '• Invalidation : ' . $num( $p['invalidation'] );
		}
		if ( $p['summary'] ) {
			$lines[] = '';
			$lines[] = $e( $p['summary'] );
		}
		$zones = (array) ( $c['zones'] ?? array() );
		usort( $zones, fn( $x, $y ) => (float) $y['high'] <=> (float) $x['high'] );
		if ( $zones ) {
			$lines[] = '';
			$lines[] = '<b>Zones</b>';
			foreach ( $zones as $z ) {
				$lines[] = ( 'vente' === $z['side'] ? '🔴 ' : '🟢 ' ) . $num( $z['low'] ) . ' – ' . $num( $z['high'] ) . ' ' . $e( $z['type'] . ' ' . $z['tf'] ) . ( (int) $z['strength'] > 0 ? ' ' . str_repeat( '★', (int) $z['strength'] ) : '' );
			}
		}
		// Scénarios de la semaine, dans l'ordre : condition, déclencheur, ordre, ce qui annule.
		$sc = array_slice( (array) ( $c['scenarios'] ?? array() ), 0, 3 );
		if ( $sc ) {
			$lines[] = '';
			$lines[] = '<b>Scénarios</b>';
			foreach ( $sc as $i => $x ) {
				if ( $i ) {
					$lines[] = '';
				}
				$lines[] = ( 'vente' === $x['side'] ? '🔴 ' : '🟢 ' ) . '<b>' . ( $i + 1 ) . '. ' . ( 'vente' === $x['side'] ? 'Vente' : 'Achat' ) . '</b>' . ( $x['setup'] ? ' · setup ' . $e( $x['setup'] ) : '' ) . ( null !== $x['prob'] ? ' · ' . (int) $x['prob'] . ' %' : '' );
				$lines[] = '⏳ Si : ' . $e( $x['if'] );
				if ( $x['then'] ) {
					$lines[] = '➡️ Alors : ' . $e( $x['then'] );
				}
				if ( ! empty( $x['trigger'] ) ) {
					$lines[] = '🎯 Déclencheur : ' . $e( $x['trigger'] );
				}
				$lines[] = '• Entrée : ' . $e( $x['entry'] );
				if ( null !== $x['sl'] ) {
					$lines[] = '• Stop : ' . $num( $x['sl'] );
				}
				if ( null !== $x['tp'] ) {
					$lines[] = '• Objectif : ' . $num( $x['tp'] );
				}
				if ( $x['invalidation'] ) {
					$lines[] = '✖ Annulé si : ' . $e( $x['invalidation'] );
				}
			}
		}
		$today = self::DAYS_FR[ (int) ( new DateTimeImmutable( 'now', CX_Repo::tz() ) )->format( 'N' ) ] ?? '';
		foreach ( (array) ( $c['days'] ?? array() ) as $d ) {
			if ( $d['day'] === $today && $d['focus'] ) {
				$lines[] = '';
				$lines[] = '<b>Aujourd’hui</b>';
				$lines[] = $e( $d['focus'] );
			}
		}
		$news = array_filter( (array) ( $c['calendar'] ?? array() ), fn( $x ) => 'high' === $x['impact'] );
		if ( $news ) {
			$lines[] = '';
			$lines[] = '<b>Annonces à fort impact</b>';
			foreach ( $news as $x ) {
				$lines[] = '📅 ' . $e( $x['day'] . ' ' . $x['time'] . ' ' . $x['event'] );
			}
		}
		$lines   = array_merge( $lines, self::ideas_lines( $c, $e ) );
		$lines[] = '';
		$lines[] = 'Plan complet : ' . get_permalink( CX_Front::page_id() );
		return implode( "\n", $lines );
	}

	const DAYS_FR = array( 1 => 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche' );

	/**
	 * À partir de 7 h (Lubumbashi) : relectures du soir non encore envoyées, puis verdict de
	 * la semaine. Chaque message ne part qu'une fois.
	 *
	 * @param DateTimeImmutable|null $now Instant (par défaut maintenant).
	 */
	public static function morning( $now = null ) {
		$now = $now ?? new DateTimeImmutable( 'now', CX_Repo::tz() );
		$h   = (int) $now->format( 'G' );
		if ( $h < 7 || $h >= 12 ) {
			return;
		}
		$today = $now->format( 'Y-m-d' );
		$weeks = array( self::monday( $now ), self::monday( $now->modify( '-7 days' ) ) );
		foreach ( $weeks as $week ) {
			$p = CX_Repo::plan( $week );
			$c = is_array( $p['content'] ?? null ) ? $p['content'] : array();
			foreach ( (array) ( $c['updates'] ?? array() ) as $u ) {
				// Relecture d'un jour passé, de moins de 4 jours.
				if ( $u['date'] < $today && $u['date'] >= $now->modify( '-4 days' )->format( 'Y-m-d' ) && CX_Guard::once( 'upd|' . $u['date'] ) ) {
					self::send( self::update_summary( $u, $c ) );
				}
			}
			if ( ! empty( $c['review']['verdict'] ) && $week < self::monday( $now ) && CX_Guard::once( 'verdict|' . $week ) ) {
				self::send( self::verdict_summary( $week, $c['review'] ) );
			}
		}
	}

	/**
	 * Message d'une relecture quotidienne.
	 *
	 * @param array $u Relecture.
	 * @param array $c Contenu du plan (zones à tracer).
	 * @return string
	 */
	public static function update_summary( $u, $c ) {
		$e     = fn( $s ) => esc_html( (string) $s );
		$day   = wp_date( 'l j F', strtotime( $u['date'] . ' 12:00 UTC' ), CX_Repo::tz() );
		$lines = array(
			'🌅 <b>Relecture de ' . $e( $day ) . '</b>',
			'',
			'mis_a_jour' === $u['status'] ? '🔄 <b>Plan mis à jour</b>' : '✅ <b>Plan maintenu</b>',
		);
		if ( $u['reason'] ) {
			$lines[] = $e( $u['reason'] );
		}
		if ( $u['summary'] ) {
			$lines[] = '';
			$lines[] = $e( $u['summary'] );
		}
		foreach ( (array) $u['changes'] as $ch ) {
			$lines[] = '• ' . $e( $ch );
		}
		if ( $u['trades'] ) {
			$lines[] = '';
			$lines[] = '<b>Vos trades</b>';
			$lines[] = $e( $u['trades'] );
		}
		if ( $u['tomorrow'] ) {
			$lines[] = '';
			$lines[] = '<b>Aujourd’hui</b>';
			$lines[] = $e( $u['tomorrow'] );
		}
		if ( 'mis_a_jour' === $u['status'] ) {
			$draw = array_filter( (array) ( $c['zones'] ?? array() ), fn( $z ) => '' !== $z['draw'] );
			if ( $draw ) {
				$lines[] = '';
				$lines[] = '<b>À tracer</b>';
				foreach ( $draw as $z ) {
					$lines[] = ( 'vente' === $z['side'] ? '🔴 ' : '🟢 ' ) . $e( $z['type'] . ' ' . $z['tf'] . ' : ' . $z['draw'] );
				}
			}
		}
		$lines = array_merge( $lines, self::card_results( $c, $u['date'] ) );
		$bt    = (array) ( $u['backtest'] ?? array() );
		if ( ! empty( $bt['summary'] ) || ! empty( $bt['missed'] ) || ! empty( $bt['wrong'] ) ) {
			$lines[] = '';
			$lines[] = '🔁 <b>Rejeu de la journée</b>';
			if ( ! empty( $bt['summary'] ) ) {
				$lines[] = $e( $bt['summary'] );
			}
			foreach ( (array) ( $bt['missed'] ?? array() ) as $x ) {
				$lines[] = '';
				$lines[] = '➕ <b>Manqué</b> ' . self::trade_line( $x, $e );
			}
			foreach ( (array) ( $bt['wrong'] ?? array() ) as $x ) {
				$lines[] = '';
				$lines[] = '➖ <b>À éviter</b> ' . self::trade_line( $x, $e );
			}
			if ( ! empty( $bt['tuning'] ) ) {
				$lines[] = '';
				$lines[] = '🛠 ' . $e( $bt['tuning'] );
			}
		}
		$lines   = array_merge( $lines, self::ideas_lines( $c, $e ) );
		$lines[] = '';
		$lines[] = 'Plan : ' . get_permalink( CX_Front::page_id() );
		return implode( "\n", $lines );
	}

	/**
	 * Fiches des plans de séance d'un jour, rejouées par le carnet sur les bougies (objectif ou stop).
	 *
	 * @param array  $c    Contenu du plan.
	 * @param string $date Jour (AAAA-MM-JJ).
	 * @return string[]
	 */
	public static function card_results( $c, $date ) {
		$out  = array();
		$name = array(
			'london' => '🇬🇧 Londres',
			'us'     => '🇺🇸 New York',
		);
		foreach ( (array) ( $c['sessions'] ?? array() ) as $s ) {
			if ( $s['date'] !== $date || empty( $s['cards'] ) ) {
				continue;
			}
			foreach ( $s['cards'] as $x ) {
				$side  = 'vente' === $x['side'] ? 'VENTE' : 'ACHAT';
				$r     = isset( $x['r'] ) ? ( $x['r'] > 0 ? '+' : ( $x['r'] < 0 ? '−' : '' ) ) . number_format( abs( (float) $x['r'] ), 1, ',', '' ) . 'R' : '';
				$what  = array(
					'tp'         => '✅ objectif ' . $r . ' en ' . CX_Live::duration( 60 * (int) ( $x['to_out'] ?? 0 ) ),
					'sl'         => '❌ stop ' . $r . ' en ' . CX_Live::duration( 60 * (int) ( $x['to_out'] ?? 0 ) ),
					'24h'        => '⏱ clôturée après 24 h ' . $r,
					'not_filled' => '➖ entrée jamais touchée',
					'cancelled'  => '✖ annulée avant l’entrée',
					'no_data'    => '… bougies manquantes',
					'incomplete' => '… prix incomplets',
				)[ $x['status'] ] ?? $x['status'];
				$out[] = ( $name[ $s['kind'] ] ?? $s['kind'] ) . ' ' . $x['n'] . '. ' . $side . ' : ' . $what;
			}
		}
		return $out ? array_merge( array( '', '📏 <b>Fiches rejouées sur les bougies</b>' ), $out ) : array();
	}

	/**
	 * Une opportunité du rejeu, en une ligne.
	 *
	 * @param array    $x Opportunité.
	 * @param callable $e Échappement.
	 * @return string
	 */
	private static function trade_line( $x, $e ) {
		$p = static fn( $v ) => null === $v ? '?' : number_format( (float) $v, 2, ',', ' ' );
		$r = null === ( $x['r'] ?? null ) ? '' : ' → ' . ( $x['r'] > 0 ? '+' : '' ) . number_format( (float) $x['r'], 1, ',', '' ) . 'R';
		return $e( trim( ( $x['time'] ?? '' ) . ' ' . ( 'vente' === ( $x['side'] ?? '' ) ? 'vente' : 'achat' ) . ( empty( $x['setup'] ) ? '' : ' S' . $x['setup'] ) ) ) . $r
			. "\n• Entrée : " . $p( $x['entry'] ?? null ) . "\n• Stop : " . $p( $x['sl'] ?? null ) . "\n• Objectif : " . $p( $x['tp'] ?? null )
			. ( empty( $x['why'] ) ? '' : "\n" . $e( $x['why'] ) );
	}

	/**
	 * Analyse de séance (plan de Londres, plan de New York), envoyée dès sa publication.
	 *
	 * @param array $s Séance.
	 * @return string
	 */
	public static function session_summary( $s ) {
		$e     = fn( $v ) => esc_html( (string) $v );
		$title = array(
			'asia'      => '🌏 <b>Session Asie</b>',
			'london'    => '🇬🇧 <b>Plan de Londres</b>',
			'premarket' => '🧭 <b>Pré-marché US</b> (Asie et Londres)',
			'us'        => '🇺🇸 <b>Plan de New York</b>',
		)[ $s['kind'] ] ?? '<b>Séance</b>';
		$bias  = array(
			'h' => 'haussier',
			'b' => 'baissier',
			'n' => 'neutre',
		)[ $s['bias'] ] ?? '';
		$lines = array( $title, $e( wp_date( 'l j F', strtotime( $s['date'] . ' 12:00 UTC' ), CX_Repo::tz() ) ) . ( $bias ? ' · biais ' . $bias : '' ) );
		if ( $s['summary'] ) {
			$lines[] = '';
			$lines[] = $e( $s['summary'] );
		}
		// Ce qu'il faut exécuter, dans l'ordre : une fiche par scénario, séparées par un trait.
		$lines[] = '';
		$lines[] = '<b>▶ À EXÉCUTER</b>';
		$rk      = self::exec_risk();
		foreach ( array_values( (array) ( $s['positions'] ?? array() ) ) as $i => $p ) {
			$lines = array_merge( $lines, array( '━━━━━━━━━━━━' ), self::card_lines( $p, $i + 1, $rk ) );
		}
		if ( ! empty( $s['positions'] ) ) {
			$lines[] = '━━━━━━━━━━━━';
		}
		if ( empty( $s['positions'] ) ) {
			$lines[] = 'Aucun scénario exécutable pour l’instant : on ne force pas de trade.';
		}
		if ( ! empty( $s['execution'] ) ) {
			$lines[] = '';
			$lines[] = '<b>Consignes</b>';
			foreach ( $s['execution'] as $x ) {
				$lines[] = '• ' . $e( $x );
			}
		}
		if ( ! empty( $s['liquidity'] ) ) {
			$lines[] = '';
			$lines[] = '<b>Liquidité visée</b>';
			foreach ( array_slice( $s['liquidity'], 0, 4 ) as $x ) {
				$lines[] = '• ' . $e( $x );
			}
		}
		if ( $s['invalid'] ) {
			$lines[] = '';
			$lines[] = '⛔ <b>Annule le plan</b>';
			$lines[] = $e( $s['invalid'] );
		}
		if ( ! empty( $s['idea'] ) ) {
			$lines[] = '';
			$lines[] = '💡 <b>Idée de Claude</b>';
			$lines[] = $e( $s['idea'] );
		}
		$lines[] = '';
		$lines[] = 'Carnet : ' . get_permalink( CX_Front::page_id() );
		return implode( "\n", $lines );
	}

	/** Statut d'une proposition de Claude. */
	const IDEA_STATES = array(
		'proposee'   => 'proposée',
		'test'       => 'en test',
		'appliquee'  => 'appliquée',
		'abandonnee' => 'abandonnée',
	);

	/**
	 * Fin des messages : la job list de Claude, en lignes courtes ; à défaut, le titre et le statut de
	 * ses propositions (le détail est dans le Journal).
	 *
	 * @param array    $c Contenu du plan.
	 * @param callable $e Échappement HTML.
	 * @return string[]
	 */
	private static function ideas_lines( $c, $e ) {
		$jobs = array_slice( (array) ( $c['jobs'] ?? array() ), 0, 5 );
		if ( $jobs ) {
			return array_merge( array( '', '📋 <b>Job list de Claude</b>' ), array_map( fn( $j ) => '• ' . $e( $j ), $jobs ) );
		}
		$out = array();
		foreach ( array_slice( (array) ( $c['ideas'] ?? array() ), 0, 3 ) as $x ) {
			$out[] = '💡 ' . $e( $x['title'] ?? 'Proposition' ) . ( empty( $x['status'] ) ? '' : ' (' . self::IDEA_STATES[ $x['status'] ] . ')' );
		}
		return $out ? array_merge( array( '' ), $out ) : array();
	}

	/**
	 * Message du verdict de la semaine.
	 *
	 * @param string $week Lundi.
	 * @param array  $r    Bilan.
	 * @return string
	 */
	public static function verdict_summary( $week, $r ) {
		$e     = fn( $s ) => esc_html( (string) $s );
		$icon  = array(
			'bonne'     => '🟢 Bonne',
			'partielle' => '🟡 Partielle',
			'mauvaise'  => '🔴 Mauvaise',
		)[ $r['verdict'] ] ?? '';
		$yn    = fn( $v ) => null === $v ? '—' : ( $v ? 'oui' : 'non' );
		$lines = array(
			'📊 <b>Verdict de la semaine du ' . wp_date( 'j F', strtotime( $week ), CX_Repo::tz() ) . '</b>',
			$icon . ( null !== $r['score'] ? ' (' . $r['score'] . '/10)' : '' ),
			'',
			'• Biais juste : ' . $yn( $r['bias_ok'] ),
			'• Range respecté : ' . $yn( $r['range_held'] ),
			'• Invalidation touchée : ' . $yn( $r['invalidation_hit'] ),
		);
		if ( $r['worked'] ) {
			$lines[] = '';
			foreach ( (array) $r['worked'] as $w ) {
				$lines[] = '✔️ ' . $e( $w );
			}
		}
		if ( $r['failed'] ) {
			$lines[] = '';
			foreach ( (array) $r['failed'] as $f ) {
				$lines[] = '✖️ ' . $e( $f );
			}
		}
		if ( $r['lesson'] ) {
			$lines[] = '';
			$lines[] = '<b>Leçon</b>';
			$lines[] = $e( $r['lesson'] );
		}
		return implode( "\n", $lines );
	}

	/* ------------------------------------------------------------------
	 * Alertes
	 * ------------------------------------------------------------------ */

	/**
	 * Alerte de limite journalière (80 % puis 100 %), une fois par niveau et par jour.
	 *
	 * @param int   $account_id Compte.
	 * @param float $loss       Perte du jour en $ (positive = perte).
	 */
	public static function check_daily( $account_id, $loss ) {
		$c = self::cfg();
		$a = CX_Repo::account( $account_id );
		if ( empty( $c['chat'] ) || empty( $c['alerts']['daily'] ) || ! $a || $loss <= 0 ) {
			return;
		}
		$pa  = $a['params'];
		if ( 'propre' === ( $pa['kind'] ?? 'propfirm' ) ) {
			// Fonds propres : perte du jour en R du palier (solde du jour = solde actuel + perte du jour).
			$snap = ( (array) CX_Ingest::latest_snapshots() )[ $account_id ] ?? null;
			$cap  = $snap ? (float) $snap['balance'] + (float) $loss : (float) $a['size'];
			$lim  = $pa['daystopr'] * CX_Repo::own_risk( $cap, $pa )['risk'];
		} else {
			// Prop firm : perte journalière de la firme (taille ou solde du début de journée).
			$lim = CX_Repo::prop_limits( $a, ( (array) CX_Ingest::latest_snapshots() )[ $account_id ] ?? null )['firm_daily'];
		}
		if ( $lim <= 0 ) {
			return;
		}
		$day = CX_Repo::clock( 'Y-m-d' );
		foreach ( array(
			100 => '🛑 <b>Limite de perte journalière atteinte</b>',
			80  => '⚠️ <b>Vous approchez de la limite de perte journalière</b>',
		) as $level => $title ) {
			if ( $loss >= $lim * $level / 100 ) {
				if ( CX_Guard::once( "daily|{$account_id}|{$day}|{$level}" ) ) {
					self::send( sprintf( "%s\n%s\n\n• Perte du jour : %s\n• Autorisé : %s\n\nArrêtez de trader aujourd’hui.", $title, esc_html( $a['name'] ), self::usd( $loss ), self::usd( $lim ) ) );
				}
				return;
			}
		}
	}

	/**
	 * Même alerte à partir des trades du jour notés dans le journal.
	 *
	 * @param int $account_id Compte.
	 */
	public static function check_daily_from_trades( $account_id ) {
		$today = CX_Repo::clock( 'Y-m-d' );
		$pnl   = 0;
		foreach ( CX_Repo::trades( $account_id ) as $t ) {
			if ( $t['date'] === $today ) {
				$pnl += (float) $t['pnl'];
			}
		}
		self::check_daily( $account_id, -$pnl );
	}

	/**
	 * Tâche toutes les 5 minutes.
	 */
	public static function run_cron() {
		self::upgrade_hook();
		$c = self::cfg();
		if ( ! empty( $c['chat'] ) ) {
			if ( ! empty( $c['alerts']['news'] ) ) {
				self::news();
			}
			if ( ! empty( $c['alerts']['weekend'] ) ) {
				self::weekend();
			}
			self::morning();
		}
		CX_Live::settle_orphans();
		self::prune();
	}

	/**
	 * Annonces qui tombent dans les 15 prochaines minutes : calendrier automatique (USD, fort
	 * et moyen impact) et annonces du plan de Claude absentes du calendrier.
	 *
	 * @param DateTimeImmutable|null $now Instant (par défaut maintenant).
	 */
	public static function news( $now = null ) {
		$tz  = CX_Repo::tz();
		$now = $now ?? new DateTimeImmutable( 'now', $tz );
		$due = array();
		foreach ( CX_Calendar::day( $now ) as $e ) {
			$delta = $e['t'] - $now->getTimestamp();
			if ( $delta > 0 && $delta <= 15 * MINUTE_IN_SECONDS && CX_Guard::once( 'news|' . $e['t'] . '|' . $e['name'] ) ) {
				$due[ $e['t'] ][] = $e;
			}
		}
		// Un message par heure d'annonce (plusieurs chiffres publiés à la même minute).
		foreach ( $due as $t => $group ) {
			$high  = (bool) array_filter( $group, fn( $e ) => 'high' === $e['impact'] );
			$from  = ( new DateTimeImmutable( '@' . ( $t - 15 * MINUTE_IN_SECONDS ) ) )->setTimezone( $tz )->format( 'H:i' );
			$to    = ( new DateTimeImmutable( '@' . ( $t + 30 * MINUTE_IN_SECONDS ) ) )->setTimezone( $tz )->format( 'H:i' );
			$lines = array( sprintf( '📅 <b>Annonce à %s</b> (dans %d min)', $group[0]['time'], max( 1, (int) round( ( $t - $now->getTimestamp() ) / 60 ) ) ) );
			foreach ( $group as $e ) {
				$lines[] = '';
				$lines[] = ( 'high' === $e['impact'] ? '🔴 ' : '🟠 ' ) . '<b>' . esc_html( $e['name'] ? $e['name'] : 'Annonce' ) . '</b>';
				if ( $e['fp'] ) {
					$lines[] = esc_html( $e['fp'] );
				}
			}
			$lines[] = '';
			$lines[] = $high ? '⛔ Pas de trade de ' . $from . ' à ' . $to . '.' : '⚠️ Prudence de ' . $from . ' à ' . $to . '.';
			self::send( implode( "\n", $lines ) );
		}
	}

	/**
	 * Rappel du plan le week-end, s'il n'est pas prêt pour lundi.
	 *
	 * @param DateTimeImmutable|null $now Instant (par défaut maintenant, fuseau du site).
	 */
	public static function weekend( $now = null ) {
		$now = $now ?? new DateTimeImmutable( 'now', CX_Repo::tz() );
		$dow = (int) $now->format( 'N' );
		$h   = (int) $now->format( 'G' );
		if ( ! ( 6 === $dow && $h >= 10 ) && ! ( 7 === $dow && $h >= 18 ) ) {
			return;
		}
		$next = $now->modify( 'next monday' )->format( 'Y-m-d' );
		if ( CX_Repo::plan( $next ) ) {
			return;
		}
		if ( CX_Guard::once( 'weekend|' . $next . '|' . $dow ) ) {
			self::send( sprintf( "📝 Le plan de la semaine du %s n’est pas encore publié par Claude.\nCarnet : %s", wp_date( 'j F', strtotime( $next ), CX_Repo::tz() ), get_permalink( CX_Front::page_id() ) ) );
		}
	}

	/**
	 * Ménage quotidien : instantanés de plus de 30 jours, signaux de plus de 90 jours.
	 */
	public static function prune() {
		global $wpdb;
		$day = CX_Repo::clock( 'Y-m-d' );
		if ( get_option( 'cx_last_prune' ) === $day ) {
			return;
		}
		update_option( 'cx_last_prune', $day, false );
		$snaps = CX_Install::table( 'snapshots' );
		$sigs  = CX_Install::table( 'signals' );
		$wpdb->query( $wpdb->prepare( "DELETE FROM $snaps WHERE horodatage < %s", gmdate( 'Y-m-d H:i:s', strtotime( CX_Repo::clock() ) - 30 * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		$wpdb->query( $wpdb->prepare( "DELETE FROM $sigs WHERE recu_le < %s", gmdate( 'Y-m-d H:i:s', strtotime( CX_Repo::clock() ) - 90 * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}
}
