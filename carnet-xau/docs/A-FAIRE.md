# Carnet XAU · revnefty.store — à faire

Liste tenue à jour à chaque séance de travail. Le travail se fait désormais uniquement sur
revnefty.store (thème RevNefty, carnet en lecture seule pour les visiteurs). ville-de-kasumbalesa.cd
n’est plus utilisé : plus de copie des plans, Telegram et EA débranchés.

## En cours

- [ ] Compte en fonds propres (250 $) : choisir le courtier (Deriv, XM Global ou Exness), installer
      les deux EA sur deux graphiques XAUUSD du même compte : EA Carnet 2.9.0 (signaux) et
      Calculateur XAU 3.00 (exécution manuelle, touche C pour charger un signal). Compiler les deux
      dans MetaEditor et signaler toute erreur.
- [ ] Plus tard : copieur vers les comptes prop firm (lot recalculé avec le risque de la firme),
      après vérification des règles de copie de chaque firme.


- [ ] Accès durable de Claude au site : mot de passe d’application WordPress rangé
      dans les secrets de l’environnement cloud (variable `RN_WP_AUTH`,
      « identifiant:mot de passe »), au lieu du fichier temporaire de la séance.

## Ensuite

1. Coordonnées de contact du thème (e-mail, WhatsApp) dans le Customizer.
2. Réenregistrement automatique du webhook Telegram si le domaine change.
3. Tests datés à remettre à jour : test-plan, test-report (semaine du 12 octobre),
   test-cal (lecture ForexFactory).
4. EA sur les comptes Challenge 1 / FundedNext quand le compte FundedNext existera
   (une clé MT5 par compte ; seul « Démo Deriv » est relié aujourd’hui).

## Fait

- Fiabilité de Claude (2.19.0) : chaque analyse de séance est notée par le carnet sur les
  bougies (biais annoncé contre mouvement réel, seuil ± 0,2 × ATR), bilan dans l’onglet Suivi
  (`GET /claude-score`). Trades MT5 remplis seuls : setup du signal Claude correspondant, zone et
  niveau du plan, biais de la dernière analyse ; restent les règles et l’émotion.

- EA 2.8.0 installé sur le PC (20 octobre) : bougies M15, M5 et M1 reçues par le site.
- Onglet Suivi (2.18.0) : séances du jour, relectures et rejeu, réglages de l’EA.

- Analyses de séance et rejeu du soir (2.17.0, 20 octobre, installée automatiquement) : session
  Asie à 06:45, pré-marché à 08:33 New York, plan US à 09:15 New York, rejeu de la journée à 17:20
  New York avec occasions manquées et mauvais signaux ; réglages de l’EA ajustés par Claude, un
  par jour au plus, dans des bornes (`GET /ea-params`). Règle d’objectivité dans toutes les tâches.
  Le dimanche, une étude SSRN au plus, transformée en proposition testable.

- Plage de stop portée à 1,50–8,00 $ (2.16.0, 20 octobre), installée par la mise à jour
  automatique : première mise à jour sans l’écran Extensions réussie.

- Première relecture de Claude sur revnefty.store (19 octobre) et résumé Telegram de 7 h reçu.

- Cron serveur LWS activé sur revnefty.store (19 octobre 2026) : le carnet passe
  toutes les 5 minutes sans attendre les visites.

- Chaîne Telegram reliée (tous les messages du carnet), propositions d’amélioration de Claude
  (`ideas`) dans les deux tâches et dans l’onglet Plan.

## Mises à jour du plugin

- Depuis la 2.15.1, le plugin se met à jour sans l’écran Extensions (choix de l’utilisateur) :
  zip `carnet-xau-x.y.z.zip` envoyé dans la médiathèque, puis `POST /carnet-xau/v1/update`
  {version, url, sha256} avec le mot de passe d’application (administrateur). La tâche du
  carnet l’installe dans les 5 minutes (empreinte vérifiée, 3 essais) et prévient sur Telegram.
  État : `GET /carnet-xau/v1/update`.

## À valider sur le compte démo

- Réglages de l’EA ajustés par Claude : vérifier après deux semaines que chaque changement a
  amélioré le total en R (historique dans `GET /ea-params`, retour arrière sinon).
- EA 2.7 : setup 3 (iFVG), cassure sur le dernier sommet ou creux pivot, entrée « retour FVG »
  suivie en parallèle de l’entrée au marché. Toutes sessions, sans note ni filtre ajouté (choix
  de l’utilisateur : refuser un mauvais trade plutôt qu’en chercher un). Décision après une
  trentaine de signaux, d’après l’onglet Stats (setups 1, 2, 3 ; marché contre retour FVG).

## Plus tard

- Comptes utilisateurs pour la communauté (chacun son carnet).

## Tâches Claude programmées

- « Carnet XAU · plan de la semaine · revnefty.store » : dimanche 11:52 (Lubumbashi).
- « Carnet XAU · session Asie (06:45) » : du lundi au vendredi à 06:45 (Lubumbashi).
- « Carnet XAU · pré-marché US (08:33 New York) » : du lundi au vendredi (14:33 Lubumbashi
  en heure d’été américaine).
- « Carnet XAU · plan de la session US (09:15 New York) » : du lundi au vendredi (15:15).
- « Carnet XAU · relecture et rejeu de 17 h (New York) » : du lundi au vendredi à 17:20
  New York (23:20 Lubumbashi) ; rejeu, réglage de l’EA ; le vendredi, verdict et coaching.
- Elles publient sur revnefty.store seulement, avec un jeton réservé aux plans. S’il est régénéré dans
  Comptes › Connexions, il faut le remplacer dans les cinq tâches.
