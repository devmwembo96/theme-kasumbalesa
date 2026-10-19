# Carnet XAU · revnefty.store — à faire

Liste tenue à jour à chaque séance de travail. Le travail se fait désormais uniquement sur
revnefty.store (thème RevNefty, carnet en lecture seule pour les visiteurs). ville-de-kasumbalesa.cd
n’est plus utilisé : plus de copie des plans, Telegram et EA débranchés.

## En cours

- [ ] Vérifier la première relecture de Claude publiée sur revnefty.store
      (lundi 19 octobre 2026, 23:20 heure de Lubumbashi) et le résumé Telegram de 7 h.
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

- Cron serveur LWS activé sur revnefty.store (19 octobre 2026) : le carnet passe
  toutes les 5 minutes sans attendre les visites.

- Chaîne Telegram reliée (tous les messages du carnet), propositions d’amélioration de Claude
  (`ideas`) dans les deux tâches et dans l’onglet Plan.

## Proposé, en attente d’accord

- EA 2.7 : heures de session (09:00–12:00, 14:30–17:30), vraie cassure de structure (pivot M1 +
  bougie de déplacement), note A+/A/B par confluences, entrée au marché et entrée sur le FVG
  suivies en parallèle.

## Plus tard

- Comptes utilisateurs pour la communauté (chacun son carnet).

## Tâches Claude programmées

- « Carnet XAU · plan de la semaine · revnefty.store » : dimanche 11:52 (Lubumbashi).
- « Carnet XAU · relecture de 17 h (New York) · revnefty.store » : du lundi au
  vendredi à 17:20 New York (23:20 Lubumbashi) ; le vendredi, verdict et coaching.
- Elles publient sur revnefty.store seulement, avec un jeton réservé aux plans. S’il est régénéré dans
  Comptes › Connexions, il faut le remplacer dans les deux tâches.
