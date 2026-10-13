<?php
/**
 * Plugin Name: Kasumbalesa — Authentification REST (correctif et diagnostic)
 * Description: Fait valider les mots de passe d'application sur l'API REST même si une extension lit l'utilisateur courant trop tôt, et ajoute un en-tête de diagnostic X-Kasu-Auth-Etat aux réponses de l'API.
 * Version: 2.1.0
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
	$determine   = 0;
	$retry       = 'non';

	add_filter(
		'determine_current_user',
		static function ( $user ) use ( &$determine ) {
			++$determine;
			return $user;
		},
		0
	);

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

	// Une extension a fixé l'utilisateur (à 0) avant que WordPress ne vérifie le mot de passe
	// d'application : on relance la vérification de WordPress une fois la requête REST connue.
	add_action(
		'rest_api_init',
		static function () use ( &$result, &$retry ) {
			if ( 'non-tente' !== $result || get_current_user_id() || empty( $_SERVER['PHP_AUTH_USER'] ) || ! isset( $_SERVER['PHP_AUTH_PW'] ) ) {
				return;
			}
			$retry = 'oui';
			$user  = wp_authenticate_application_password( null, (string) $_SERVER['PHP_AUTH_USER'], (string) $_SERVER['PHP_AUTH_PW'] );
			if ( $user instanceof WP_User ) {
				wp_set_current_user( $user->ID );
			}
		},
		0
	);

	// Diagnostic sans donnée sensible, ajouté à la réponse de l'API.
	add_filter(
		'rest_pre_dispatch',
		static function ( $response ) use ( &$result, &$determined, &$determine, &$retry, $auth_header ) {
			$first    = $determined;
			$user_id  = get_current_user_id();
			$priority = has_filter( 'determine_current_user', 'wp_validate_application_password' );

			if ( ! headers_sent() ) {
				header(
					sprintf(
						'X-Kasu-Auth-Etat: entete=%s; dispo=%d; utilise=%d; filtre=%s; utilisateur-lu=%s; determine=%d; rattrapage=%s; resultat=%s; id=%d',
						$auth_header,
						wp_is_application_passwords_available() ? 1 : 0,
						WP_Application_Passwords::is_in_use() ? 1 : 0,
						false === $priority ? 'absent' : (string) $priority,
						$first,
						$determine,
						$retry,
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
