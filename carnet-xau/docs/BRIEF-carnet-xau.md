# Carnet XAU — brief de construction (plugin WordPress)

Ce document sert de point de départ pour Claude Code. L’interface de référence est le fichier `carnet-xau.html` (prototype fonctionnel en HTML/JS vanilla, à reprendre comme base visuelle et logique).

## Contexte

- Trader particulier, basé à Lubumbashi (RDC), heure locale UTC+2 (CAT), trade uniquement l’or (XAUUSD) en M1 avec un indicateur SMC (signaux triangles, EQH/EQL, 2 Top/Bottom Liq, BoS, ChoCh, MSB).
- Objectif : réussir des challenges de prop firm (FundedNext Stellar 2-Step, compte de 6 000 $), puis gérer les comptes financés.
- Hébergement : LWS, formule WordPress. Construire l’outil comme un **plugin WordPress autonome**, sur un site personnel (jamais sur un site client).
- Langue de l’interface : français. Typographie : apostrophe ’ et guillemets « » dans les textes affichés.

## Architecture

- Plugin `carnet-xau` : tables MySQL propres (préfixe `$wpdb->prefix . 'cx_'`), API REST sous `/wp-json/carnet-xau/v1/`, interface affichée par un shortcode `[carnet_xau]` sur une page dédiée, ou une page plein écran sans thème.
- Accès réservé à l’utilisateur connecté (capability `manage_options` ou rôle dédié). Nonce REST (`wp_rest`) pour toutes les requêtes depuis l’interface.
- Front-end : reprendre le HTML/CSS/JS du prototype, remplacer la couche `store` (actuellement `claude.use("db")` ou `localStorage`) par des appels `fetch` vers l’API REST.
- PWA : `manifest.json` + service worker simple (cache de l’interface, données toujours en ligne).
- PHP 8.0 minimum. Pas de processus permanent (hébergement mutualisé) : tâches planifiées via WP-Cron, ou via une tâche cron LWS qui appelle `wp-cron.php` si elle est disponible.

## Modèle de données

- `cx_accounts` : id, nom, prop_firm, modèle, taille, prix_challenge, statut (phase1, phase2, financé, échoué, retiré), date_achat, paramètres de risque en JSON (objectifs %, perte journalière %, perte max %, jours minimum, risque, risque réduit, seuil de réduction, trades max/jour, pertes max/jour, spread max % du stop, contrat or = 100).
- `cx_trades` : id, account_id, date, heure, sens, setup (1, 2, hors), niveau (EQL, EQH, 2 Bottom Liq, 2 Top Liq, PDH, PDL, session fermée, BoS, autre), stop_usd, lot, risque_usd, resultat_r, resultat_usd, sur_niveau_cle (bool), avec_biais (bool), regles_respectees (bool), emotion, capture_id, lien, notes, source (manuel, mt5), ticket_mt5 (unique, pour éviter les doublons), created_at.
- `cx_plans` : semaine (date du lundi, unique par compte ou global), biais, plus haut / plus bas hebdo, structure, fondamentaux (JSON : dxy, taux réels, fed, géopolitique), niveaux (JSON), annonces (JSON), scénarios (JSON), bilan.
- `cx_payouts` : id, account_id, date, montant_brut, part_trader, frais, moyen (USDT TRC20, Rise…), conversion (notes Binance P2P).
- `cx_signals` (version 2) : id, recu_le, symbole, unité de temps, type de signal, prix, payload brut — reçus depuis les alertes TradingView.
- `cx_snapshots` (version 2) : account_id, horodatage, solde, équité, perte du jour — envoyés par l’EA MT5.
- Captures : médiathèque WordPress (`media_handle_upload`), stocker l’ID de pièce jointe.

## Règles de calcul (onglet Jour)

- Objectif de l’étape = taille × objectif % (phase 1 : 8 %, phase 2 : 5 %, cycle financé : 4,5 %).
- Plancher = taille × (1 − perte max %). Marge = solde − plancher.
- Jours restants = max(1, jours minimum − jours tradés) ; en financé, 1.
- Objectif du jour = (objectif − (solde − taille)) ÷ jours restants.
- Risque = risque normal, ou risque réduit si (solde − taille) ≤ −seuil ; jamais plus que (marge − 5 $).
- Lot = arrondi inférieur au centième de risque ÷ (stop × contrat). Risque réel = lot × stop × contrat.
- Take-profit du jour (en $ de mouvement) = objectif du jour ÷ (lot × contrat). Sortie partielle : 50 % à 2R puis stop au prix d’entrée.
- Alertes : stop hors de 1,50–4,00 $ ; spread > spread max % du stop ; trades du jour ≥ max ; pertes du jour ≥ max ; perte du jour ≥ 80 % de la limite journalière ; objectif du jour atteint ; objectif de l’étape atteint (micro-trades 0,01 lot jusqu’au jour minimum) ; marge ≤ un risque.
- Check-list de six points obligatoire avant le bouton « Noter ce trade » (voir prototype).

## Statistiques

Taux de réussite, R moyen, total en R, facteur de profit, résultat en $, courbe des R cumulés ; ventilation par setup, niveau, tranche horaire (Asie 0–9 h, Londres 9–14 h, New York 14–18 h, soir 18–24 h, heure de Lubumbashi), jour de la semaine, sur niveau clé ou non, avec le biais ou non, règles respectées ou non, émotion. Repère de rentabilité : 33 % à 2R.

## Feuille de route

**Version 1**
1. Squelette du plugin, création des tables à l’activation, désinstallation propre.
2. API REST (CRUD comptes, trades, plans, retraits ; upload de captures) avec contrôle des droits et validation.
3. Interface : onglets Jour, Plan, Journal, Stats (repris du prototype) + onglet Comptes (multi-challenges, coût, statut, retraits, rentabilité : coût total des challenges, comptes financés, coût moyen d’un compte financé, retraits cumulés).
4. Champs ajoutés au journal : règles respectées, émotion (calme, pressé, revanche, fatigue), capture uploadée.
5. PWA installable. Export CSV des trades.

**Version 2**
- Point d’entrée `POST /mt5` protégé par une clé secrète par compte : reçoit les trades fermés (ticket, symbole, sens, lots, prix, SL, TP, profit, heures) et des instantanés solde/équité. EA MQL5 à fournir (WebRequest ; l’URL du site doit être ajoutée dans MT5 : Outils > Options > Expert Advisors). Livrer le code MQL5 en .txt.
- Point d’entrée `POST /tradingview` protégé par un jeton dans l’URL ou le corps : journalise chaque alerte de l’indicateur avec l’heure de réception (sert à vérifier si l’indicateur repeint).
- Bot Telegram (mode webhook) : alertes limite journalière, annonce dans 15 min, rappel du plan du week-end.

**Version 3**
- Calendrier économique (annonces USD à fort impact) chargé automatiquement en zones de non-trade.
- Simulateur Monte-Carlo à partir des statistiques réelles (probabilité de réussir un challenge selon le risque).
- Rapport hebdomadaire PDF. Analyse IA hebdomadaire du journal (clé API Anthropic stockée côté serveur, jamais dans le navigateur).

## Sécurité

- Toutes les routes REST de l’interface : `permission_callback` vérifiant l’utilisateur connecté et ses droits.
- Routes MT5 / TradingView / Telegram : jeton secret, comparaison en temps constant (`hash_equals`), limitation de débit simple, journalisation des refus.
- Requêtes SQL préparées (`$wpdb->prepare`), échappement des sorties (`esc_html`, `esc_url`), validation des types et des plages.
- Sauvegarde : export JSON complet depuis l’interface.
