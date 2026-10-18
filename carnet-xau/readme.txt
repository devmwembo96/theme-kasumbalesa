=== Carnet XAU ===
Requires at least: 6.2
Requires PHP: 8.0
Stable tag: 1.1.0

Carnet de trading XAUUSD pour les challenges de prop firm : calcul du jour, plan
hebdomadaire, journal, statistiques, comptes et retraits.

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

== Désinstallation ==

Faire d’abord une sauvegarde JSON (onglet Comptes). La suppression du plugin efface les
tables, la page, les options, la capacité et le rôle. Les captures restent dans la médiathèque.

== Fichiers ==

carnet-xau.php                 Chargement, activation
uninstall.php                  Désinstallation propre
includes/class-cx-install.php  Tables (dbDelta), capacité, rôle, page, compte par défaut
includes/class-cx-repo.php     Validation et requêtes préparées
includes/class-cx-rest.php     Routes REST, envoi des captures
includes/class-cx-export.php   CSV des trades, sauvegarde JSON
includes/class-cx-front.php    Shortcode [carnet_xau], page tenue hors du site, manifest, service worker
templates/app.php              Balisage des onglets
assets/                        CSS, JS, service worker, icônes
docs/                          Brief et prototype de référence
