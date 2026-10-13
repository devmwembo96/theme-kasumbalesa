<?php
/**
 * Plugin Name: Kasumbalesa — Authentification REST (correctif et diagnostic)
 * Description: Fait valider les mots de passe d'application sur l'API REST même si une extension lit l'utilisateur courant trop tôt, et ajoute un en-tête de diagnostic X-Kasu-Auth-Etat aux réponses de l'API.
 * Version: 2.0.0
 *
 * À déposer dans wp-content/mu-plugins/ (pas dans le thème).
 * Ne contient aucun identifiant et n'ajoute aucune méthode de connexion :
 * seul l'en-tête Authorization standard est utilisé.
 *
 * @package Kasumbalesa
 */

defined( 'ABSPATH' ) || exit;

( function () {
	// API REST seulement.
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
	if ( false === strpos( $uri, '/wp-json/' ) && false === strpos( $uri, 'rest_route=' ) ) {
		return;
	}

	// Une extension peut lire l'utilisateur courant avant que WordPress ne sache qu'il
	// s'agit d'une requête REST : le mot de passe d'application serait alors ignoré sans erreur.
	add_filter( 'application_password_is_api_request', '__return_true' );

	$result      = 'non-tente';
	$determined  = 'jamais';
	$auth_header = ! empty( $_SERVER['PHP_AUTH_USER'] ) ? 'recu' : 'absent';

	add_action(
		'set_current_user',
		static function () use ( &$determined ) {
			if ( 'jamais' === $determined ) {
				$determined = defined( 'REST_REQUEST' ) && REST_REQUEST ? 'pendant-rest' : 'avant-rest';
			}
		},
		0
	);
	add_action(
		'application_password_did_authenticate',
		static function () use ( &$result ) {
			$result = 'reussi';
		}
	);
	add_action(
		'application_password_failed_authentication',
		static function ( $error ) use ( &$result ) {
			$result = 'echec-' . ( is_wp_error( $error ) ? sanitize_key( $error->get_error_code() ) : 'inconnu' );
		}
	);

	// Diagnostic sans donnée sensible, ajouté à la réponse de l'API.
	add_filter(
		'rest_pre_dispatch',
		static function ( $response ) use ( &$result, &$determined, $auth_header ) {
			$first    = $determined;
			$user_id  = get_current_user_id();
			$priority = has_filter( 'determine_current_user', 'wp_validate_application_password' );

			if ( ! headers_sent() ) {
				header(
					sprintf(
						'X-Kasu-Auth-Etat: entete=%s; dispo=%d; filtre=%s; utilisateur-lu=%s; resultat=%s; id=%d',
						$auth_header,
						wp_is_application_passwords_available() ? 1 : 0,
						false === $priority ? 'absent' : (string) $priority,
						$first,
						$result,
						$user_id
					)
				);
			}
			return $response;
		},
		0
	);
} )();
