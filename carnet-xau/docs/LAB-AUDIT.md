# XAUUSD AI Intelligence Lab — étape 1 : audit

Date : 03/11/2026. Lecture seule : rien n'a été modifié dans le carnet, les tâches de Claude ni les EA.
Choix validés : Claude seul (pas de Grok ni de GPT), pas de replay, tout branché en direct, Claude lancé
par les tâches programmées (option B).

## 1. Le système existant

### Architecture
- **Site** : WordPress sur revnefty.store, plugin `carnet-xau` (PHP 8, aucune dépendance externe).
  API REST `carnet-xau/v1` (`includes/class-cx-rest.php` et une classe par domaine), interface en
  JavaScript sans framework (`assets/js/app.js`, `templates/app.php`), graphiques dessinés par le carnet
  (`class-cx-chart.php`), Telegram (`class-cx-telegram.php`).
- **Déploiement** : zip du plugin → médiathèque WordPress → `POST /update {version, url, sha256}` →
  installé par le cron du carnet (5 min). Pas d'environnement de préproduction.
- **Stockage** : tables du plugin (`class-cx-install.php` : plans, signaux, trades…) et options
  WordPress (`cx_*`).

### MetaTrader 5
- **EA Carnet XAU 2.19** (`assets/mt5/CarnetXAU.mq5.txt`), sur le VPS (compte de référence
  « Fundednext DEMO ») et sur le PC. Il envoie au carnet, avec la clé MT5 du compte :
  - les bougies (`POST /mt5/candles`, `class-cx-live.php::store_candles`) : D1 120, H4 300, H1 500,
    M15 400, M5 600, M1 1 500 bougies ; refusées si le prix n'est pas celui de l'or (2.36.0) ;
  - les signaux et l'état des zones (`POST /mt5/event`).
  Il lit le plan (`GET /mt5/plan`) pour tracer zones, niveaux et signaux.
- **Calculateur XAU 4.04** : outil position et ticket d'ordre ; c'est le seul composant qui ouvre des
  positions, sur action de l'utilisateur.
- Le carnet ne parle jamais directement à MT5 : il ne fait que recevoir. Pour le Lab, c'est déjà une
  connexion en lecture seule.

### Données de marché et calculs
- `GET /candles` : bougies du compte de référence ; `at` = fraîcheur ; heure serveur = New York + 7 h.
- `class-cx-calendar.php` (annonces USD), `class-cx-news.php` (réaction passée de l'or aux annonces),
  `class-cx-edge.php` (R par setup, séance, heure), `class-cx-score.php` (rejeu des fiches, scénarios et
  zones sur les bougies, leçons), `class-cx-coach.php` (coach, évaluation de Claude).
- **Aucune détection SMC dans le site** : BOS, CHoCH, FVG, OB, liquidité sont trouvés par Claude à la
  lecture des bougies, et par l'EA en MQL5 (touche de zone, pivot, iFVG). Les détecteurs testés existent
  seulement dans le labo hors ligne (`/home/user/research-lab/engine/objects.py`, Python, 246 tests
  anti-lookahead).

### L'IA aujourd'hui
- **Un seul Claude par tâche, un seul long prompt** (tâches programmées, aucune clé d'API dans le site) :
  plan de la semaine (dimanche), plan de Londres, plan de New York, relecture et rejeu du soir, coach du
  soir, coach de la semaine, note du système sur 100.
- **Données transmises** : le prompt demande à Claude de lire lui-même, en GET, `candles`, `plans`,
  `calendar`, `claude-signals`, `claude-score`, `edge`, `news-playbook`, `report`, `ea-params`. Claude
  reçoit donc les bougies brutes et doit trouver seul les structures.
- **Réponses** : JSON publié par `PUT /claude/plans/{lundi}` (clé `session` pour Londres et New York,
  `prediction`/`zones`/`scenarios` pour la semaine), validé et nettoyé par `class-cx-plan.php`.
- **Vérification existante** : un sous-agent « relecteur » relit les fiches de Londres et de New York
  sur les bougies avant publication ; le coach note Claude chaque soir.

## 2. Diagnostic

### Ce qui fonctionne et se garde
- Le flux de bougies du VPS, le garde-fou « prix de l'or », l'heure serveur maîtrisée.
- La publication JSON validée côté serveur, le jeton de publication, Telegram, le dessin des graphiques.
- La mesure objective : rejeu des fiches et des zones (objectif ou stop, sans gestion), leçons, coach.
- Le relecteur indépendant : c'est déjà l'idée de la « couche de validation ».

### Ce qui est limité
1. **Un seul raisonnement** : le même Claude trouve les zones, choisit le biais, écrit les fiches et
   les défend ; personne ne construit le scénario opposé.
2. **Structures non calculées** : Claude doit repérer BOS, FVG, OB dans des centaines de bougies ;
   rien ne garantit qu'un BOS cité est une clôture au-delà d'un pivot confirmé.
3. **Pas de WAIT explicite** : une séance sans fiche est possible mais n'est pas une décision tracée.
4. **Prompts dans les tâches** : longs, non versionnés dans le dépôt, impossibles à comparer.
5. **Mesure** : le backtest (labos BTC et or) n'a trouvé aucun avantage aux zones et setups actuels sur
   M15 ; le Lab ne doit pas promettre un avantage, il doit le mesurer.

## 3. Les trois dépôts de référence (lus dans `/home/user/refs`)

### TradingAgents (TauricResearch) — à reprendre : l'organisation
- Graphe LangGraph : analystes → débat **Bull / Bear** à tours alternés (`conditional_logic.py`,
  `max_debate_rounds`) → **Research Manager** qui tranche → trader → débat de risque → gestionnaire.
- Bon : chaque chercheur reçoit le dernier argument adverse et doit y répondre ; le manager a une règle
  explicite (« le conflit seul n'est pas une raison de tenir ; Hold seulement si les preuves restent
  équilibrées »). Sorties structurées (Pydantic) avec nettoyage des champs mal remplis (`schemas.py`).
- Pas pour nous : conçu pour des actions (fondamentaux, réseaux sociaux), prompts en prose, LangGraph
  et ses dépendances. Je reprends le schéma du débat et la règle de décision, pas le code.

### gold-analysis-v4 — à reprendre : la préparation du contexte
- Séparation nette : prompt système en fichier (`prompts/system_prompt.md`, base de connaissances
  Al Brooks en chinois) ; prompt utilisateur construit par le code (`llm_analyzer.py::_build_user_prompt`)
  avec résumé journalier, 24 h de bougies M5 annotées (corps, mèches, « bougie de tendance »), swings
  calculés ; schéma JSON imposé (`schema.py`) ; extraction du JSON tolérante (blocs ```json, virgules).
- Je reprends : prompts versionnés en fichiers, contexte préparé par le code, annotations des bougies,
  JSON validé. Je remplace Al Brooks par SMC/ICT, et je limite le nombre de bougies envoyées.

### Gold-MultiAgent-Analyzer — à ne pas reprendre tel quel
- Agents surtout algorithmiques, Claude seulement pour la synthèse ; connexion MT5 par le paquet Python
  `MetaTrader5` (`tools/mt5_tools.py`).
- **Défauts vérifiés dans `tools/structure_tools.py`** : swing = maximum d'une fenêtre centrée de 5
  bougies, sans heure de confirmation (connu seulement 5 bougies plus tard) ; « BOS » = un sommet de
  swing plus haut que le précédent, donc une mèche suffit, sans clôture au-delà ; CHoCH déduit de
  l'ordre des swings. C'est exactement ce que le prompt interdit.
- **`execute_and_monitor.py` envoie des ordres** (`mt5.order_send`) : exclu, le Lab n'envoie rien.
- Je ne reprends que des idées de présentation ; nos détecteurs testés du labo restent la référence.

## 4. Ce qu'il faut construire (étapes suivantes, chacune validée avant la suivante)

1. **Module isolé dans le plugin** : `includes/lab/`, routes `carnet-xau-lab/v1`, options et table
   `cx_lab_*`, interrupteur (éteint par défaut), page réservée à l'administrateur, mention
   « Environnement expérimental ». Aucun changement au carnet actuel.
2. **Snapshot** (`GET carnet-xau-lab/v1/snapshot`) construit par le site à partir des bougies du VPS :
   D1, H4, H1, M15, M5, bougies closes séparées de la bougie en cours, spread, ATR, swings confirmés,
   BOS/CHoCH (clôture au-delà d'un pivot confirmé), FVG, OB, EQH/EQL, sweeps, premium/discount, niveaux
   de séance, état des données (fraîcheur, trous). Détecteurs portés en PHP depuis le labo Python, avec
   un test de parité (mêmes bougies → mêmes structures). Le snapshot est figé avec un identifiant.
3. **Cinq agents + validation du risque**, lancés par une tâche programmée avant Londres et New York
   (à la demande : bouton « Lancer » de la tâche dans l'application Claude, pas depuis le site) : chaque agent est un sous-agent
   Claude avec son prompt versionné (`prompts/lab/*.md`), qui ne reçoit que le snapshot et les rapports
   prévus ; Bull et Bear indépendants puis un tour de réponse ; Chief Analyst BUY/SELL/WAIT ; validation
   du risque en code sur le site au moment de la publication (`POST carnet-xau-lab/v1/analysis`), qui
   peut rejeter la proposition.
4. **Tableau de bord du Lab** : état des données et des tâches, cartes des agents, débat Bull/Bear,
   décision du Chief Analyst, verdict de la validation, historique (snapshot, versions des prompts,
   réponses, durée).
5. **Mesure en direct** : chaque proposition est suivie par le rejeu du carnet (déclenchée, objectif,
   stop, invalidation respectée) et chaque WAIT est comparé au mouvement réel ; statistiques par séance
   et par version de prompt. Aucun score de confiance présenté comme une probabilité.
6. **Documentation** : routes, prompts, tâches, interrupteur.

### Limites connues
- Option B : le Lab consomme la même limite d'utilisation que les plans actuels ; une analyse = 5 agents.
  Le site ne peut pas lancer Claude lui-même sans clé d'API : une analyse à la demande se lance depuis
  l'application Claude (tâche « Lab », bouton Lancer) ; la page du Lab affiche la dernière analyse.
- Le snapshot dépend des bougies envoyées par le VPS (fenêtres listées plus haut) : si le VPS se tait,
  le snapshot le dit et les agents ne publient pas de trade.
- Pas de replay, comme demandé : la mesure commence le jour de la mise en service.
