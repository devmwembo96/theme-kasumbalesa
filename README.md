# Thème WordPress « Kasumbalesa »

Thème officiel de la **Ville de Kasumbalesa** (Haut-Katanga, RDC) : *Kasumba ka Lesa*, la ville-montagne de Dieu.
C'est un thème classique en PHP, sans Elementor ni aucun page builder. Toute l'apparence est codée dans le thème. Le contenu s'édite avec Gutenberg et les réglages se font dans **Apparence › Personnaliser › Ville de Kasumbalesa**.

## Arborescence

```
kasumbalesa/
├── style.css                     En-tête du thème (nom, version…)
├── functions.php                 Charge les modules de inc/
├── screenshot.png                Aperçu dans Apparence › Thèmes
├── header.php / footer.php       En-tête (menu, bande défilante, menu mobile) · pied de page
├── front-page.php                Accueil : héros, Mot du Maire, actualités, accès rapides, chiffres, toponymie
├── home.php                      Liste des actualités (page des articles)
├── archive.php                   Catégories, étiquettes, dates
├── single.php                    Article
├── page.php                      Page standard
├── search.php · 404.php · index.php · comments.php · searchform.php
├── templates/
│   └── template-full-width.php   Modèle « Pleine largeur » (anciennes pages Elementor)
├── template-parts/
│   ├── intro.php                 Animation du logo (≤ 2 s, une fois par session)
│   ├── mayor-modal.php           Pop-up du Maire
│   ├── news-ticker.php           Bande défilante des 5 derniers articles
│   ├── hero.php                  Héros plein écran, diaporama Ken Burns
│   ├── mayor-message.php         Mot d'accueil complet
│   ├── news-carousel.php         Carrousel vedette + cartes
│   ├── quick-access.php · key-figures.php · toponymy.php
│   ├── page-hero.php · post-list.php · content-card.php · content-none.php
├── inc/
│   ├── helpers.php               Valeurs par défaut, page du Maire, liens tel:/mailto:
│   ├── customizer.php            Tous les réglages
│   ├── setup.php                 Menus, tailles d'images, Gutenberg
│   ├── enqueue.php               Polices, CSS, JS (defer)
│   ├── template-tags.php         Fonctions d'affichage (langues, réseaux, partage…)
│   ├── icons.php                 Pictogrammes SVG en ligne
│   └── seo.php                   Description, Open Graph, Twitter Card, schema.org
├── assets/css/main.css           Feuille de styles unique
├── assets/css/editor.css         Styles de l'éditeur Gutenberg
├── assets/js/main.js             JavaScript natif, sans dépendance (environ 15 ko)
└── languages/                    Traductions (domaine « kasumbalesa »)
```

## Identité visuelle

| Rôle | Code |
|---|---|
| Vert montagne (fonds sombres, en-tête au défilement) | `#0B2A1F` |
| Vert nuit (héros, pied de page) | `#06170F` |
| Vert forêt (liens, dégradés) | `#1F5C43` |
| Cuivre du Katanga (accents, lettrines) | `#B87333` |
| Cuivre foncé (petits textes sur fond clair) | `#8E4F1E` |
| Or (boutons, filets, surtitres sur fond sombre) | `#D4A853` |
| Ivoire (fond général) | `#FAF7F0` |
| Papier (sections alternées) | `#F2ECDF` |
| Blanc | `#FFFFFF` |
| Noir profond (titres) | `#0A0F0C` |

Contrastes vérifiés : tous les textes atteignent au moins 4,5:1 (par exemple or sur vert 7,0:1, cuivre foncé sur ivoire 6,0:1, noir sur bouton or 8,8:1).

**Polices (Google Fonts)**
- **Cormorant Garamond** (500, 600, 700, italique) pour les titres : une serif de prestige.
- **Manrope** (400 à 700, variable) pour le texte : une sans-serif moderne et très lisible.

## Mise en service

1. Copier le dossier dans `wp-content/themes/kasumbalesa`, puis l'activer.
2. **Identité du site** : choisir le logo, qui sert aussi à l'animation d'introduction et au timbre de la carte postale. Saisir le titre et le slogan.
3. **Réglages › Lecture** : définir une page d'accueil statique et la page « Actualités » comme page des articles.
4. **Page « Mot du Maire »** : saisir le texte intégral, une **image mise en avant** (photo du Maire) et, si besoin, un **extrait** qui sera repris dans le pop-up.
5. **Menus** : « Menu principal », « Pied de page – navigation », « Pied de page – mentions légales ».
6. **Personnaliser › Ville de Kasumbalesa** : images du héros, textes, accès rapides, chiffres clés, téléphone, e-mail et réseaux sociaux.
7. **Pages Elementor existantes** : dans l'éditeur, sous Attributs de page › Modèle, choisir **« Pleine largeur »**. Elles restent lisibles pendant la transition.

### Comportements à connaître
- L'intro du logo et le pop-up du Maire s'affichent sur la page d'accueil, **une fois par session**. On peut désactiver chacun d'eux dans le Customizer.
- Les **chiffres clés** sont vides par défaut. Seuls ceux qui ont un nombre supérieur à 0 et un libellé apparaissent. Si aucun n'est rempli, la section est masquée.
- Les accès rapides pointent par défaut vers les pages `demarches`, `bureau-du-maire`, `douane-commerce` et `tourisme` si elles existent. Sinon, renseignez un lien.
- Le bouton « Découvrir la ville » mène à la section Toponymie (`#decouvrir`).
- Le crédit du pied de page accepte `{year}` (année automatique).
- **SEO** : les balises sont désactivées automatiquement si Yoast, Rank Math, AIOSEO ou SEOPress est actif.
- **FR / EN** : avec Polylang ou WPML, le sélecteur utilise les langues de l'extension, et les textes du Customizer sont traduisibles dans Langues › Traductions des chaînes (Polylang). Sans extension, renseignez l'adresse de la version anglaise. Tant qu'elle est vide, « EN » apparaît comme « bientôt disponible ».

## Performance et accessibilité
- Une seule feuille CSS et un seul script chargé en `defer`, sans jQuery ni bibliothèque d'animation.
- Images en `srcset` + `loading="lazy"`. Seule la première image du héros est prioritaire, les suivantes sont chargées juste avant leur affichage.
- `prefers-reduced-motion` est respecté : pas d'intro, pas de Ken Burns ni de défilement automatique, et la bande d'actualités se fait défiler à la main.
- Navigation au clavier : lien d'évitement, focus visible, menu mobile avec piège de focus et touche Échap, pop-up en `<dialog>` natif, bouton pause sur chaque élément animé.
- Sans JavaScript, tout le contenu reste visible.
