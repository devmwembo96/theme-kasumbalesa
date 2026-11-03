<?php
/**
 * Prompts des cinq rôles de l'analyse (contexte, SMC, haussier, baissier, Chief Analyst) et règles
 * communes, versionnés avec le plugin (dossier prompts/). Les tâches de Claude les lisent par
 * GET /prompts et notent la version dans le plan (`session.prompts`).
 *
 * @package CarnetXAU
 */

defined( 'ABSPATH' ) || exit;

class CX_Prompts {

	const ROLES = array( 'shared', 'context', 'smc', 'bull', 'bear', 'chief' );

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			CX_Rest::NS,
			'/prompts',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest' ),
				'permission_callback' => array( 'CX_Rest', 'can' ),
			)
		);
	}

	/** @return array {version, roles: {rôle: texte}} */
	public static function all() {
		$roles = array();
		foreach ( self::ROLES as $r ) {
			$f           = CX_DIR . 'prompts/' . $r . '.md';
			$roles[ $r ] = is_readable( $f ) ? (string) file_get_contents( $f ) : '';
		}
		return array(
			// Version : plugin + empreinte des textes (change dès qu'un prompt change).
			'version' => CX_VERSION . '-' . substr( md5( implode( '|', $roles ) ), 0, 8 ),
			'roles'   => $roles,
		);
	}

	/** @return WP_REST_Response */
	public static function rest() {
		return new WP_REST_Response( self::all(), 200 );
	}
}
