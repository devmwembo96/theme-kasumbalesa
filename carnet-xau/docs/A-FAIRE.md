# Carnet XAU · revnefty.store — à faire

Liste tenue à jour à chaque séance de travail. Site principal : revnefty.store
(thème RevNefty, carnet en lecture seule pour les visiteurs). Ancien site :
ville-de-kasumbalesa.cd (garde une copie des plans, Telegram déconnecté).

## En cours

- [ ] Vérifier la première relecture de Claude publiée sur revnefty.store
      (lundi 19 octobre 2026, 23:20 heure de Lubumbashi) et le résumé Telegram de 7 h.
- [ ] Accès durable de Claude au site : mot de passe d’application WordPress rangé
      dans les secrets de l’environnement cloud (variable `RN_WP_AUTH`,
      « identifiant:mot de passe »), au lieu du fichier temporaire de la séance.

## Ensuite

1. Cron LWS pour revnefty.store : appeler `https://revnefty.store/wp-cron.php`
   toutes les 5 minutes (Telegram de 7 h, alertes, signaux), sans attendre les visites.
2. Coordonnées de contact du thème (e-mail, WhatsApp) dans le Customizer.
3. Réenregistrement automatique du webhook Telegram si le domaine change.
4. Tests datés à remettre à jour : test-plan, test-report (semaine du 12 octobre),
   test-cal (lecture ForexFactory).
5. EA sur les comptes Challenge 1 / FundedNext quand le compte FundedNext existera
   (une clé MT5 par compte ; seul « Démo Deriv » est relié aujourd’hui).
6. Ancien site : décider quand arrêter la copie des plans (étape « Copie sur l’ancien
   site » des deux tâches Claude) et retirer le carnet de ville-de-kasumbalesa.cd.

## Plus tard

- Comptes utilisateurs pour la communauté (chacun son carnet).

## Tâches Claude programmées

- « Carnet XAU · plan de la semaine · revnefty.store » : dimanche 11:52 (Lubumbashi).
- « Carnet XAU · relecture de 17 h (New York) · revnefty.store » : du lundi au
  vendredi à 17:20 New York (23:20 Lubumbashi) ; le vendredi, verdict et coaching.
- Elles publient avec un jeton réservé aux plans. S’il est régénéré dans
  Comptes › Connexions, il faut le remplacer dans les deux tâches.
