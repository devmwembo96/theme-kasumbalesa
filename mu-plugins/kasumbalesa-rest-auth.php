<?php
/**
 * Plugin Name: Kasumbalesa — Authentification REST de secours
 * Description: Le cache Edge de LWS supprime l'en-tête HTTP Authorization. Ce mu-plugin accepte les mêmes identifiants (mot de passe d'application) dans l'en-tête X-Kasu-Auth, uniquement pour l'API REST et en HTTPS.
 * Version: 1.0.0
 *
 * À déposer dans wp-content/mu-plugins/ (pas dans le thème).
 * Format de l'en-tête : X-Kasu-Auth: Basic base64(utilisateur:mot-de-passe-d-application)
 *
 * Ne contient aucun identifiant. Seuls les mots de passe d'application sont
 * acceptés : WordPress refuse le mot de passe de connexion sur l'API REST.
 *
 * @package Kasumbalesa
 */

defined( 'ABSPATH' ) || exit;

( function () {
	// L'en-tête standard est arrivé : rien à faire.
	if ( ! empty( $_SERVER['PHP_AUTH_USER'] ) || empty( $_SERVER['HTTP_X_KASU_AUTH'] ) ) {
		return;
	}

	// HTTPS seulement (derrière le proxy LWS, Apache peut voir la requête en HTTP).
	$forwarded_https = isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === strtolower( (string) $_SERVER['HTTP_X_FORWARDED_PROTO'] );
	if ( ! is_ssl() && ! $forwarded_https ) {
		return;
	}

	// API REST seulement.
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
	if ( false === strpos( $uri, '/wp-json/' ) && false === strpos( $uri, 'rest_route=' ) ) {
		return;
	}

	$value = trim( (string) $_SERVER['HTTP_X_KASU_AUTH'] );
	if ( 0 === stripos( $value, 'Basic ' ) ) {
		$value = substr( $value, 6 );
	}

	$decoded = base64_decode( $value, true );
	if ( false === $decoded || false === strpos( $decoded, ':' ) ) {
		return;
	}

	list( $user, $password ) = explode( ':', $decoded, 2 );

	// WordPress lit ces deux valeurs pour valider le mot de passe d'application.
	$_SERVER['PHP_AUTH_USER'] = $user;
	$_SERVER['PHP_AUTH_PW']   = $password;
} )();
