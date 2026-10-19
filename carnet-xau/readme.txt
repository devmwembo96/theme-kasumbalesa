=== Carnet XAU ===
Requires at least: 6.2
Requires PHP: 8.0
Stable tag: 2.10.0

Carnet de trading XAUUSD pour les challenges de prop firm : calcul du jour, plan
hebdomadaire, journal, statistiques, comptes et retraits ; réception des trades MT5,
journal des alertes TradingView, alertes Telegram.

== Installation ==

1. Extensions › Ajouter › Téléverser une extension : envoyer carnet-xau.zip, puis activer.
2. L’activation crée les tables {prefix}cx_accounts, cx_trades, cx_plans, cx_payouts,
   un premier compte (FundedNext Stellar 2-Step, 6 000 $) et la page « Carnet XAU »
   (adresse /carnet-xau/), avec le modèle « Pleine largeur » du thème s’il existe.
3. Ouvrir /carnet-xau/ (pas besoin d’être connecté). Sur téléphone : « Ajouter à l’écran d’accueil ».
   Nom, couleurs et icônes de l’application installée : filtre cx_app_identity (thème).

== Visibilité ==

* La page /carnet-xau/ est ouverte à qui connaît l’adresse, sans connexion (phase de
  conception), mais tenue hors du site : jamais dans un menu ni une liste de pages, absente
  de la recherche, de l’API /wp/v2/pages, du plan du site WordPress et de Yoast, en noindex.
* Pour la réserver plus tard, une ligne suffit (mu-plugin ou functions.php) :
  add_filter( 'cx_can_use', fn() => current_user_can( 'manage_carnet_xau' ) );
* Page publique (filtre cx_page_visible, utilisé par le thème RevNefty) : plus aucune exclusion
  (menus, recherche, plans du site, indexation) et lecture seule pour les visiteurs (filtre
  cx_public_read_only) : routes en écriture, connexions et sauvegardes refusées sans la capacité
  manage_carnet_xau ; commandes de modification masquées, champs du calcul du jour et check-list figés,
  bandeau « Se connecter pour modifier ».
  Les points d’entrée MT5, TradingView, Telegram et Claude gardent leurs clés.
* Captures : pièces jointes privées au nom de fichier aléatoire.
* Pas de cache : la page appelle wpfc_exclude_current_page() (WP Fastest Cache) et définit
  DONOTCACHEPAGE ; son CSS et son JS sont insérés dans la page, car le cache du serveur LWS
  sert d’anciennes copies des fichiers statiques. Chaque mise à jour vide la copie en cache.

== Version 2 : connexions (onglet Comptes › Connexions) ==

MT5
* Bouton « Clé MT5 » sur la carte du compte : une clé secrète par compte (affichée une fois).
* EA : assets/mt5/CarnetXAU.mq5.txt, téléchargeable depuis l’onglet Comptes (/?cx_pwa=ea). Lecture seule, n’ouvre
  aucun ordre. Envoie POST /wp-json/carnet-xau/v1/mt5 avec l’en-tête X-CX-Key :
  positions fermées (sorties partielles additionnées, stop = SL de l’ordre d’entrée,
  heures en UTC) et, chaque minute, solde, équité et perte du jour.
* « Couper la liaison MT5 » invalide la clé et efface les soldes reçus pour ce compte (les
  trades importés restent). Pour un compte de démo, créer un compte à part dans le carnet.
* Les trades importés arrivent « MT5 · à compléter » (setup, niveau, règles…) et ne sont
  jamais importés deux fois (ticket). Le solde du matin est déduit du dernier instantané.
* MT5 : Outils › Options › Expert Advisors › autoriser WebRequest pour l’adresse du site.

TradingView
* « Générer l’adresse du webhook » : URL avec jeton (?token=…), à coller dans l’alerte.
* Message conseillé (JSON) : symbol, tf, signal, price, bar_time ({{time}}), fired ({{timenow}}).
  Le texte libre est aussi accepté. Chaque alerte est notée avec son heure de réception
  (Journal › Signaux TradingView reçus) pour repérer un indicateur qui repeint.

Telegram (mode webhook)
* Créer un bot avec @BotFather, coller son jeton, puis ouvrir le lien « /start CODE » :
  seul ce chat reçoit les alertes. Webhook protégé par l’en-tête secret de Telegram.
* Alertes : 80 % puis 100 % de la limite de perte journalière (instantanés MT5 ou trades du
  journal), annonce du plan dans moins de 15 min, rappel du plan samedi 10 h et dimanche
  18 h s’il n’est pas prêt. Commandes : /jour, /plan, /aide.
* WP-Cron toutes les 5 minutes. Sur LWS, si le site a peu de visites, ajouter une tâche cron
  qui appelle https://…/wp-cron.php toutes les 5 minutes.

Sécurité des points d’entrée : jetons comparés en temps constant (empreintes SHA-256 seules
stockées pour MT5 et TradingView), 60 à 120 requêtes par minute et par IP, blocage 15 min
après 10 jetons invalides, refus journalisés (Comptes › Connexions).

Ménage automatique : instantanés de plus de 30 jours, signaux de plus de 90 jours.

== Version 3 ==

Simulateur de challenge (onglet Stats)
* Monte-Carlo dans le navigateur : 2 000 challenges par niveau de risque (25, 35, 50, 75,
  100 $ et le risque actuel), 60 jours de trading au plus, avec les règles du compte actif
  (objectif, perte journalière et maximale, jours minimum, trades et pertes max par jour,
  risque réduit, risque plafonné à la marge − 5 $, arrêt à l’objectif du jour).
* Résultats tirés au hasard parmi les trades du journal (10 au minimum, fiable à partir
  de 30), ou hypothèse : taux de réussite et gain moyen en R, perte de 1R.
* Affiche la réussite de l’étape, la perte du compte pendant l’étape, la réussite des deux
  phases et le nombre de jours médian.

Plan de semaine élargi, rédigé exclusivement par Claude (onglet Plan en lecture seule)
* Prévision (probabilités haussier / baissier / range, scénario principal et alternatif,
  invalidation, range attendu), repères de prix et ATR, structure W/D/H4/H1, zones (bornes,
  réaction, force, état), niveaux de liquidité, carte des niveaux, fondamentaux, annonces,
  plan jour par jour, scénarios détaillés, règles de la semaine, bilan noté sur 10.
* Publication : PUT /wp-json/carnet-xau/v1/claude/plans/{lundi}, en-tête X-CX-Key = jeton
  du plan (Comptes › Connexions). Format : docs/PLAN-CLAUDE.md. Résumé envoyé sur Telegram.
* L’ancienne route d’écriture du plan depuis l’interface est supprimée.
* « Où tracer » : chaque zone et niveau peut porter sa bougie d’origine et sa consigne de
  tracé ; section « À tracer sur le graphique » dans l’onglet Plan.
* Relecture quotidienne (17 h New York, lundi–vendredi) : « maintenu » par défaut, « mis à
  jour » seulement sur déclencheur (invalidation, événement majeur, zone consommée, range
  dépassé). La version du dimanche reste consultable ; historique daté des relectures.
* Verdict de fin de semaine : Bonne / Partielle / Mauvaise, critères mesurés (biais, range,
  invalidation, zones atteintes et réaction, scénarios), ce qui a marché ou non, leçon.
  Onglet Stats : fiabilité des prévisions de Claude au fil des semaines.
* Telegram : résumé de la relecture et verdict le lendemain à partir de 7 h (Lubumbashi).
* Le samedi et le dimanche, « Cette semaine », le résumé de l’onglet Jour et la commande
  Telegram /plan portent sur la semaine qui commence.

EA MT5 version 2 : plan dynamique et signaux d’exécution (signal seulement)
* L’EA lit le plan (GET /mt5/plan, texte) chaque minute et le trace : zones, niveaux,
  invalidation, range attendu ; redessine dès que le plan ou son état change.
* Surveillance M15 : invalidation touchée, zone testée ou cassée → POST /mt5/event, état
  « live » du plan (bandeau et zones dans l’onglet Plan), alerte Telegram immédiate.
* Signal : touche d’une zone de Claude, extrême dans la zone, cassure de structure M1 dans
  le sens de la zone ; setup 1 si balayage de liquidité, sinon setup 2 avec le biais.
  Stop derrière l’extrême ; BUY/SELL NOW si stop ≤ 4 $, sinon BUY/SELL LIMIT au prix où le
  stop revient à 4 $ (30 min). Filtres : 2R de place, annonces, spread, limites du jour.
  Flèche, position longue / courte dessinée, lot calculé ; alertes MT5, mobile, Telegram.
  Aucun ordre n’est passé par l’EA.
* PC éteint (version 2.6 de l’EA) : au redémarrage, l’EA relit les bougies manquées (7 jours
  au plus, jamais avant le lundi du plan) pour clôturer les signaux Claude en cours à l’heure
  réelle du stop ou de l’objectif et mettre à jour zones et invalidation ; le premier envoi
  reprend 30 jours de trades. Aucun signal n’est émis pour la période où MT5 était fermé.
* L’EA envoie aussi les bougies du courtier (D1, H4, H1) chaque heure : GET /candles, pour
  que Claude analyse aux prix exacts de FundedNext.

Signaux Claude : trades virtuels et taux de réussite (version 2.5)
* Chaque signal de l’EA devient un trade virtuel « Signal Claude », suivi par l’EA jusqu’à
  sa clôture, que vous l’ayez pris ou non : sortie de 50 % à 2R puis stop au prix d’entrée.
  Stop touché −1R ; objectif après 2R 1 + 0,5 × R de l’objectif ; retour à l’entrée après
  2R +1R ; limite non déclenchée en 30 min « annulé » (hors taux) ; clôture forcée à 24 h.
* L’EA garde son suivi dans le fichier CarnetXAU_signaux.csv (redémarrage de MT5 sans perte)
  et envoie chaque changement (POST /mt5/event, type signal_update) ; Telegram annonce la
  clôture. Un signal renvoyé n’est jamais compté deux fois (référence unique).
* Onglet Stats › Signaux Claude (EA MT5) : taux de réussite, R moyen et total, 2R atteint,
  en cours, limites annulées, part des signaux pris par vous (trade du journal dans le même
  sens à 15 min près), détail par setup, type et sens, 30 derniers signaux.
  GET /claude-signals ; inclus dans la sauvegarde JSON.

Calendrier économique automatique (version 2.6)
* Le flux public de ForexFactory (nfs.faireconomy.media) est lu toutes les 6 heures par
  WP-Cron (1 heure après un échec) ; seules les annonces USD à fort et moyen impact sont
  gardées (option cx_calendar). Les jours passés de la semaine restent 21 jours.
* Onglet Jour › Annonces économiques USD : annonces du jour à l’heure de Lubumbashi, impact,
  prévision et précédent, nom français des principales annonces, compte à rebours ; bandeau
  « pas de trade » de 15 min avant à 30 min après une annonce à fort impact ; prochaine
  annonce si la journée est vide ; reste de la semaine. GET /calendar.

Calendrier branché sur Telegram, l’EA et Claude (version 2.7)
* Telegram : alerte 15 min avant chaque annonce du calendrier (fort et moyen impact) et
  de celles du plan de Claude absentes du calendrier ; annonces publiées à la même minute
  groupées en un message, prévision et précédent ; /jour liste les annonces du jour.
* EA : les annonces à fort impact du calendrier s’ajoutent à celles du plan (lignes N de
  GET /mt5/plan) ; pas de signal de 15 min avant à 30 min après. Aucune mise à jour de l’EA.
* Claude : GET /calendar?week={lundi} donne les annonces de la semaine pour rédiger le plan
  (docs/PLAN-CLAUDE.md).

Rapport hebdomadaire (version 2.8)
* Onglet Stats › Rapport de la semaine : trades, réussite, R, résultat, discipline (règles,
  niveau clé, biais, série de pertes, limites du jour), avancement de l’étape (solde MT5 ou
  journal), analyse du journal par Claude ; semaines précédentes avec ‹ ›.
* Page imprimable /?cx_report={lundi}&account={id} (bouton « Imprimer / Enregistrer en
  PDF », mise en page A4) : résultats, jour par jour, setups, discipline, émotions, analyse
  de Claude, plan et verdict, signaux Claude, annonces, liste des trades. noindex, sans cache.
* Analyse du journal rédigée par Claude : clé « coaching » du plan (résumé, note de
  discipline, points forts, à corriger, actions), publiée avec le jeton du plan.
  GET /report?week= donne les chiffres à Claude (docs/PLAN-CLAUDE.md).
* Telegram : résumé le samedi à partir de 9 h (case « Rapport de la semaine ») avec le lien
  du rapport ; commande /rapport.

À venir : tâche planifiée Claude
qui rédige le plan (après l’EA MT5, pour des niveaux aux prix du courtier).

== Désinstallation ==

Faire d’abord une sauvegarde JSON (onglet Comptes). La suppression du plugin efface les
tables, la page, les options et réglages des connexions, la tâche planifiée, la capacité
et le rôle. Les captures restent dans la médiathèque.

== Fichiers ==

carnet-xau.php                 Chargement, activation
uninstall.php                  Désinstallation propre
includes/class-cx-install.php  Tables (dbDelta), capacité, rôle, page, compte par défaut
includes/class-cx-repo.php     Validation et requêtes préparées
includes/class-cx-rest.php     Routes REST, envoi des captures
includes/class-cx-export.php   CSV des trades, sauvegarde JSON
includes/class-cx-front.php    Shortcode [carnet_xau], page tenue hors du site, manifest, service worker
includes/class-cx-guard.php    Réglages des connexions, jetons, limite de débit, journal des refus
includes/class-cx-ingest.php   Points d’entrée MT5 et TradingView
includes/class-cx-telegram.php Bot Telegram, alertes, WP-Cron
includes/class-cx-plan.php     Plan élargi rédigé par Claude : format, publication protégée
includes/class-cx-live.php     Plan pour l’EA, événements et signaux, bougies du courtier
includes/class-cx-calendar.php Calendrier économique (ForexFactory)
includes/class-cx-report.php   Rapport hebdomadaire, page imprimable, envoi du samedi
assets/mt5/CarnetXAU.mq5.txt   EA MetaTrader 5
templates/app.php              Balisage des onglets
assets/                        CSS, JS, service worker, icônes
docs/                          Brief et prototype de référence
