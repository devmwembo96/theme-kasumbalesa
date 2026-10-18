=== Carnet XAU ===
Requires at least: 6.2
Requires PHP: 8.0
Stable tag: 2.0.0

Carnet de trading XAUUSD pour les challenges de prop firm : calcul du jour, plan
hebdomadaire, journal, statistiques, comptes et retraits ; réception des trades MT5,
journal des alertes TradingView, alertes Telegram.

== Installation ==

1. Extensions › Ajouter › Téléverser une extension : envoyer carnet-xau.zip, puis activer.
2. L’activation crée les tables {prefix}cx_accounts, cx_trades, cx_plans, cx_payouts,
   un premier compte (FundedNext Stellar 2-Step, 6 000 $) et la page « Carnet XAU »
   (adresse /carnet-xau/), avec le modèle « Pleine largeur » du thème s’il existe.
3. Ouvrir /carnet-xau/ (pas besoin d’être connecté). Sur téléphone : « Ajouter à l’écran d’accueil ».

== Visibilité ==

* La page /carnet-xau/ est ouverte à qui connaît l’adresse, sans connexion (phase de
  conception), mais tenue hors du site : jamais dans un menu ni une liste de pages, absente
  de la recherche, de l’API /wp/v2/pages, du plan du site WordPress et de Yoast, en noindex.
* Pour la réserver plus tard, une ligne suffit (mu-plugin ou functions.php) :
  add_filter( 'cx_can_use', fn() => current_user_can( 'manage_carnet_xau' ) );
* Captures : pièces jointes privées au nom de fichier aléatoire.

== Version 2 : connexions (onglet Comptes › Connexions) ==

MT5
* Bouton « Clé MT5 » sur la carte du compte : une clé secrète par compte (affichée une fois).
* EA : assets/mt5/CarnetXAU.mq5.txt (lien dans l’onglet Comptes). Lecture seule, n’ouvre
  aucun ordre. Envoie POST /wp-json/carnet-xau/v1/mt5 avec l’en-tête X-CX-Key :
  positions fermées (sorties partielles additionnées, stop = SL de l’ordre d’entrée,
  heures en UTC) et, chaque minute, solde, équité et perte du jour.
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
assets/mt5/CarnetXAU.mq5.txt   EA MetaTrader 5
templates/app.php              Balisage des onglets
assets/                        CSS, JS, service worker, icônes
docs/                          Brief et prototype de référence
