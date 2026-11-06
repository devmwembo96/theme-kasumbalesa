# Plan de semaine rédigé par Claude — format de publication

Le plan de la semaine du Carnet NAS100 (Nasdaq 100, séance de New York) est rédigé **exclusivement** par une tâche planifiée Claude. L'onglet Plan l'affiche en lecture seule.

## Publication

```
PUT https://revnefty.store/wp-json/carnet-xau/v1/claude/plans/{lundi}
En-tête  X-CX-Key: <jeton du plan>          (Comptes › Connexions › Plan de la semaine)
Corps    JSON ci-dessous
```

- `{lundi}` : date du lundi de la semaine, `AAAA-MM-JJ`.
- Les clés envoyées remplacent celles du plan existant ; les autres sont conservées.
- **Publication du dimanche** : envoi complet avec `prediction`, sans `update`. L'historique des relectures repart de zéro et le résumé part aussitôt sur Telegram (`?silent=1` pour ne rien envoyer).
- **Relecture quotidienne** (lundi à vendredi, 17 h heure de New York, clôture de la bougie journalière) : envoyer `update` (voir plus bas). Si le plan est maintenu, n'envoyer que `update`. S'il est mis à jour, envoyer aussi les clés modifiées (`prediction`, `zones`, `levels`, `scenarios`, `days`…) : la version du dimanche est conservée et reste consultable. Le résumé part sur Telegram **le lendemain à 7 h** (Lubumbashi).
- **Verdict** : à la relecture du vendredi (ou avec le plan suivant), envoyer `review` sur le lundi de la semaine jugée. Il part sur Telegram le lendemain matin à 7 h.

## Sources à lire avant d'écrire

```
GET https://revnefty.store/wp-json/carnet-xau/v1/calendar?week={lundi}
GET https://revnefty.store/wp-json/carnet-xau/v1/candles
```

- `calendar?week=` : annonces USD à fort et moyen impact de la semaine (ForexFactory, relu
  toutes les 6 h) : `t` (Unix), `date` et `time` (heure de Lubumbashi), `impact`, `title`,
  `fr` (nom français), `forecast`, `previous`. Reprendre ces annonces dans `calendar` (heures
  de Lubumbashi) avec leur incidence probable sur le Nasdaq 100 dans `fund_notes`. Le carnet, Telegram
  et l'EA lisent déjà ce calendrier : `calendar` sert à ajouter ce qui n'y figure pas
  (discours, annonce surprise) et à commenter. Une annonce de `calendar` sans équivalent à
  10 minutes près dans le calendrier automatique bloque les signaux de l'EA et déclenche
  l'alerte Telegram 15 minutes avant.
- `candles` : bougies D1, H4, H1 du courtier (prix FundedNext), envoyées par l'EA.

## Analyse du journal (clé `coaching`, avec le bilan du vendredi)

Lire d'abord les chiffres de la semaine : `GET /wp-json/carnet-xau/v1/report?week={lundi}`
(trades, réussite, R, discipline, émotions, avancement de l'étape, signaux Claude). Puis
publier sur le même lundi, avec le même jeton :

```json
{
  "coaching": {
    "summary": "Bilan en 3 à 5 phrases : ce qui a rapporté, ce qui a coûté, et pourquoi.",
    "discipline": 7.5,
    "strengths": ["Stop respecté sur tous les trades"],
    "weaknesses": ["Deux ventes contre le biais du plan"],
    "actions": ["Une action concrète et mesurable pour la semaine prochaine"]
  }
}
```

- `discipline` : note sur 10 (règles respectées, limites du jour, trades sur niveau clé et
  avec le biais, émotions « revanche » ou « pressé »).
- 6 points forts et 6 points à corriger au plus, 5 actions au plus ; la première action est
  reprise dans le rapport Telegram du samedi 9 h.
- Le rapport imprimable (PDF) : `/?cx_report={lundi}` ; aucun message n'est envoyé à la
  publication de `coaching`.

## Règles de la relecture quotidienne

Par défaut, le plan est **maintenu**. Mise à jour (`mis_a_jour`) seulement sur un déclencheur précis, cité dans `reason` :
1. clôture journalière au-delà de l'invalidation ;
2. nouvel événement majeur (annonce surprise, Fed, géopolitique) ;
3. zone consommée ou cassée ;
4. range attendu dépassé.

Jamais de changement de biais sans l'un de ces déclencheurs.

## Règles du verdict

Le verdict s'appuie d'abord sur des faits mesurés sur les prix de la semaine, puis sur l'explication :
- `bias_ok` : la semaine a clôturé dans le sens du biais dominant ;
- `range_held` : plus haut et plus bas de la semaine dans `range_low`–`range_high` ;
- `invalidation_hit` : le prix a touché l'invalidation ;
- `zones[]` : chaque zone atteinte ou non, et si elle a réagi (rebond d'au moins 0,5 × ATR journalier) ;
- `scenarios[]` : déclenchés ou non, avec le résultat.

`verdict` : `bonne` si le biais était juste et que la majorité des zones atteintes ont réagi ; `mauvaise` si le biais était faux et l'invalidation touchée ; `partielle` sinon.
- Réponse : `{"ok": true, "week": "...", "url": "..."}`. Jeton faux : 401. Trop d'essais : 429.

Prix en dollars, avec deux décimales au plus. Heures en heure de Lubumbashi (UTC+2), au format `HH:MM`. Jours en minuscules : `lundi` … `vendredi`. Textes en français, avec l'apostrophe ’ et les guillemets « ».

## Corps

```json
{
  "prediction": {
    "bull": 55, "bear": 30, "range": 15,
    "summary": "Biais haussier tant que 21 010 tient : …",
    "main": "Balayage de l’EQL 21 090 lundi-mardi puis poussée vers 21 575.",
    "alt": "Cassure sous 21 010 (ChoCh H4) : retour vers la demande 20 705–20 780.",
    "invalidation": 21000.0,
    "range_low": 20960.0, "range_high": 21650.0
  },
  "refs": {
    "price": 21325.0, "week_open": 21310.0,
    "prev_week_high": 21485.0, "prev_week_low": 21045.0,
    "prev_month_high": 21725.0, "prev_month_low": 20325.0,
    "atr_d": 38.5, "atr_w": 112.0
  },
  "structure": [
    { "tf": "W",  "trend": "h", "last": "BoS haussier 21 215 (semaine du 5/10)", "pd": "premium",   "note": "" },
    { "tf": "D",  "trend": "h", "last": "BoS 21 445 (15/10)",                     "pd": "equilibre", "note": "" },
    { "tf": "H4", "trend": "r", "last": "ChoCh baissier 21 370 (16/10)",          "pd": "discount",  "note": "" },
    { "tf": "H1", "trend": "b", "last": "BoS baissier 21 275",                    "pd": "discount",  "note": "" }
  ],
  "zones": [
    { "type": "OB", "tf": "H4", "low": 21115.0, "high": 21155.0, "side": "achat", "strength": 3, "state": "vierge", "note": "Sous l’EQL 21 090 balayé = setup 1",
      "origin": "Dernière bougie baissière H4 du jeudi 16/10, 08:00", "draw": "Rectangle du bas de la mèche (21 115) au haut du corps (21 155), à partir de cette bougie, prolongé à droite." },
    { "type": "FVG", "tf": "H1", "low": 21540.0, "high": 21580.0, "side": "vente", "strength": 2, "state": "vierge", "note": "",
      "origin": "Déséquilibre H1 du mercredi 15/10, 14:00–16:00", "draw": "Rectangle entre le bas de la bougie de 14:00 et le haut de celle de 16:00." }
  ],
  "levels": [
    { "price": 21485.0, "type": "Plus haut semaine passée", "note": "Liquidité au-dessus", "origin": "Plus haut du jeudi 16/10", "draw": "Ligne horizontale sur le plus haut de la semaine passée." },
    { "price": 21090.0, "type": "EQL", "note": "Double bas H1", "origin": "Bas H1 du 15/10 10:00 et du 16/10 13:00", "draw": "Ligne horizontale reliant les deux bas." }
  ],
  "fundamentals": [
    { "key": "dxy",   "label": "Dollar (DXY)",  "effect": "up",      "value": "101,8 (−0,6 %)", "note": "" },
    { "key": "rates", "label": "Taux réels US", "effect": "neutral", "value": "10 ans 5,10 %",   "note": "" },
    { "key": "fed",   "label": "Attentes Fed",  "effect": "up",      "value": "",                "note": "" },
    { "key": "geo",   "label": "Géopolitique",  "effect": "up",      "value": "",                "note": "" }
  ],
  "fund_notes": "Ce qui pousse le Nasdaq 100 cette semaine (annonces, résultats des Big Tech, taux 10 ans, VIX)…",
  "calendar": [
    { "day": "mercredi", "time": "14:30", "event": "CPI US", "impact": "high", "forecast": "0,3 %", "previous": "0,4 %" }
  ],
  "days": [
    { "day": "lundi", "bias": "h", "session": "New York", "focus": "Attendre le balayage du bas de la nuit 21 035, pas de vente." }
  ],
  "scenarios": [
    { "if": "le prix balaie l’EQL 21 090 et revient dans l’OB H4 21 115–21 155", "then": "setup 1 en achat dans l’OB H4",
      "trigger": "Clôture M1 au-dessus du dernier sommet pivot après la mèche",
      "setup": "1", "side": "achat", "entry": "21 115–21 155", "sl": 21075.0, "tp": 21445.0, "prob": 45,
      "invalidation": "Clôture H4 sous 21 000" }
  ],
  "rules": [ "Pas de trade mercredi de 14:15 à 15:00 (CPI).", "Vendredi : demi-risque après 16 h." ],
  "review": {
    "verdict": "partielle", "score": 6,
    "bias_ok": true, "range_held": false, "invalidation_hit": false,
    "zones": [ { "label": "OB H4 21 115–21 155", "reached": true, "reacted": true }, { "label": "FVG H1 21 540–21 580", "reached": false, "reacted": null } ],
    "scenarios": [ { "label": "Balayage EQL puis achat", "triggered": true, "result": "+3,1R jusqu’à 21 445" } ],
    "worked": [ "Biais haussier et OB H4" ], "failed": [ "Range sous-estimé après le CPI" ],
    "lesson": "Élargir le range attendu les semaines de CPI.",
    "text": "Commentaire libre."
  },
  "update": {
    "date": "2026-10-21", "status": "maintenu",
    "reason": "Aucun déclencheur : clôture au-dessus de l’invalidation, OB H4 intact.",
    "summary": "Balayage du bas de la nuit 21 035 à l’ouverture puis rebond dans l’OB H4.",
    "changes": [],
    "trades": "2 trades, tous deux sur l’OB H4 et dans le sens du biais : règles respectées.",
    "tomorrow": "Mercredi : CPI à 14:30, pas de trade de 14:15 à 15:00 ; viser le plus haut 21 480 si l’OB tient."
  },
  "sources": "Bougies NAS100 du courtier (compte de référence), US10Y, VIX, résultats d’entreprises, calendrier ForexFactory, actualité."
}
```

## Valeurs permises

| Champ | Valeurs |
|---|---|
| `bull`, `bear`, `range`, `prob` | entiers 0–100 (`bull + bear + range` = 100) |
| `structure[].tf`, `zones[].tf` | `W`, `D`, `H4`, `H1`, `M15` |
| `trend` | `h` haussière, `b` baissière, `r` range |
| `pd` | `premium`, `discount`, `equilibre` |
| `side` | `achat`, `vente` |
| `strength` | 1 à 3 |
| `state` | `vierge`, `testee` |
| `effect` | `up` (soutient l’indice), `down` (pèse sur l’indice), `neutral` |
| `impact` | `high`, `medium`, `low` |
| `days[].bias` | `h`, `b`, `n` |
| `setup` | `1` (liquidité), `2` (continuation), `3` (iFVG) |
| `review.score` | 0 à 10 |
| `review.verdict` | `bonne`, `partielle`, `mauvaise` |
| `bias_ok`, `range_held`, `invalidation_hit`, `reached`, `reacted`, `triggered` | `true`, `false` ou `null` (inconnu) |
| `update.status` | `maintenu`, `mis_a_jour` (avec `update.date` = jour relu, `AAAA-MM-JJ`) |
| `origin` (zones et niveaux) | bougie d’origine « jj/mm hh:mm », heure de Lubumbashi, « jj/mm » seul pour une bougie D1 : point de départ du tracé (le graphique et l’EA dessinent eux-mêmes ; le carnet la convertit en heure du serveur MT5 pour l’EA) |

Limites : 6 lignes de structure, 12 zones, 15 niveaux, 8 fondamentaux, 25 annonces, 5 jours, 8 scénarios, 10 règles. Les valeurs hors format sont ignorées.

## Ce que le carnet en déduit

- Biais d'origine = la plus forte des trois probabilités.
- Niveaux, annonces et scénarios d'origine : résumé de l'onglet Jour, alertes Telegram 15 minutes avant chaque annonce, commande `/plan`.

## Amélioration continue (`ideas`)

À chaque publication (plan du dimanche, relecture, verdict), Claude cherche une faille dans la
méthode à partir des faits (bougies, signaux de l’EA, trades du journal) et propose une solution
concrète et testable, sans contredire ce qui existe. Liste complète renvoyée à chaque fois
(anciennes propositions conservées, 5 au plus) :

```json
"ideas": [
  { "area": "entrée", "title": "Entrer sur le FVG M1",
    "flaw": "La faille constatée et le fait qui la prouve.",
    "solution": "La solution précise.",
    "test": "Comment la valider : nombre de signaux ou de trades, critère chiffré." }
]
```

`area` : entrée, zones, horaires, sorties, discipline, outil. Affichage : « Améliorations proposées
par Claude » dans l’onglet Journal ; les deux premières partent aussi sur Telegram.

## Bougies

`GET /candles` renvoie les bougies du **compte de référence de l’analyse** (Comptes › Connexions,
par défaut le compte actif), avec `account`, `firm`, `at` et la liste `sources` des comptes dont l’EA
envoie des bougies. Chaque courtier a ses propres prix et ses propres bougies D1 et H4 (heure de son
serveur) : tous les prix d’un plan viennent d’un seul courtier, jamais d’un mélange.
`GET /candles?account=ID` lit celles d’un autre compte.

## Analyses de séance (`session`)

Publiées dans le plan de la semaine, envoyées sur Telegram dès réception. `kind` : `us` seulement
(plan de New York du Nasdaq 100, 08:15 à New York, 1 h 15 avant l’ouverture de 09:30). Une analyse
par jour. L’EA surveille les zones du plan de New York du jour comme les zones du plan.

```json
"session": { "date": "2026-10-20", "kind": "us", "bias": "b",
  "summary": "Deux phrases chiffrées : ce que le marché a fait, ce qu’on attend.",
  "facts": ["Ouverture 09:30 à 21 120,50, gap de −0,4 % sous la VAL de la veille 21 180,25"],
  "liquidity": ["Bas de la nuit 21 035,75 pris", "Bas de la veille 20 990,00 intact"],
  "execution": ["Pas de vente sous 21 010 : place insuffisante jusqu’au bas de la veille"],
  "zones": [ { "type": "FVG M15", "low": 21150, "high": 21170, "side": "vente", "note": "…", "origin": "20/10 17:15" } ],
  "levels": [ { "price": 20990.0, "type": "Bas de la veille (RTH)", "origin": "19/10 21:40" } ],
  "positions": [ { "side": "vente", "setup": "1", "label": "retour FVG M15 de 13:15",
    "wait": "Le prix revient dans 21 150–21 170 après avoir pris le haut de l’IB 21 148,50",
    "trigger": "Clôture M1 sous le dernier creux pivot après la mèche",
    "entry": 21155.0, "sl": 21185.0, "tp": 21095.0,
    "cancel": "Clôture M15 au-dessus de 21 185,00" } ],
  "invalid": "Clôture M15 au-dessus de 21 185",
  "idea": "Facultatif : une idée de trade, de setup ou de gestion née de l’analyse." }
```

Chaque position devient une **fiche d’exécution** (onglet Aujourd’hui, Plan, Telegram) :
attendre (`wait`) → déclencheur (`trigger`) → ordre (`entry`, `sl`, `tp`, écart et R calculés) → lot
du compte actif (calculé par le carnet avec son risque du jour) → annulé si (`cancel`).
`execution` ne garde que les consignes générales (ce qu’il ne faut pas faire, annonces) ; jamais de
BE, de trailing ni de sortie partielle imposés : l’utilisateur gère sa position, une remarque de
gestion va dans `idea`, chiffrée sur les bougies.

## Rejeu du soir (`update.backtest`) et réglages de l’EA

```json
"backtest": { "summary": "…", "ea_check": "…",
  "missed": [ { "time": "16:42", "side": "vente", "setup": "3", "entry": 21272.5, "sl": 21305.0, "tp": 21165.0, "r": 3.3, "why": "…" } ],
  "wrong": [], "tuning": "…" }
```

`PUT /claude/ea-params` (jeton du plan) : `{ "key": "fvg", "value": 0.5, "reason": "faits" }` ou
`{ "key": "fvg", "revert": true, "reason": "…" }`. Réglages : touch (10–60), swing (10–40),
fvg (0,10–1,50 $), slbuf (0,10–1,00 $), limitmin (15–120), cooldown (15–180). Un changement par jour.

## Coach (`/claude/coach`)

`POST /claude/coach` (jeton du plan, en-tête `X-CX-Key`) :
`{"kind": "day"|"week", "date": "AAAA-MM-JJ", "lines": ["…"], "focus": "objectif", "score": 0-10}`.
10 lignes au plus, chiffrées ; `focus` = un seul objectif ; `score` = discipline (règles du compte).
Envoyé en privé sur Telegram (pas sur la chaîne). Une note par jour et par type : la dernière
remplace la précédente. `GET /coach` : les 60 dernières notes (relire l’objectif précédent).

## Playbook des annonces (`/news-playbook`)

Chaque annonce USD à fort impact est mesurée par le carnet sur les bougies du compte de référence
(M1, sinon M5, sinon M15), 65 minutes après sa sortie : amplitude haute et basse des 15 premières
minutes, clôture à +15 et +60 min (en points d’indice depuis l’ouverture de la bougie de l’annonce), sens de
la première impulsion d’au moins 1 $ et retournement à +60 min. Les 300 dernières sont gardées.
`GET /news-playbook` donne le résumé par annonce (`n`, `range15`, `abs60`, `reverse_pc`) ;
`?title=CPI m/m` filtre une annonce. Le plan de New York s’en sert pour chiffrer
la réaction attendue d’une annonce du jour (jamais pour assouplir la fenêtre sans trade).

## Statisticien (`/edge`)

`GET /edge` : résultats en R (`n`, `win` %, `avg`, `sum`) des trades du journal (`journal`) et des
signaux Claude soldés (`claude`), par `setup`, `session` (ny : 09:30 → 16:00 New York, apres :
16:00 → 18:00, nuit : 18:00 → 09:30), ère NAS100 seulement, `hour` (heure de Lubumbashi) et `side`, sur tout
l’historique (`total`) et sur 30 jours (`30d`) ; `journal.rules` compare les trades complétés où
les règles sont respectées ou non. Les plans de séance mettent en premier la fiche du setup et de la
séance qui gagnent (au moins 5 trades pour conclure) ; le coach s’en sert pour ses bilans.

## Fiabilité des fiches (`/claude-score`)

Après la fin de chaque séance, le carnet rejoue chaque fiche (`positions`) sur les bougies du compte
de référence, sans gestion : entrée touchée ou non (fiche annulée si la condition « Clôture M15
au-dessus / sous de … » de `cancel` est clôturée avant), puis stop (vérifié en premier) ou objectif,
clôture au dernier cours au bout de 24 h ; avec le temps jusqu’à l’entrée et jusqu’à l’issue. Les
scénarios du plan de la semaine sont rejoués de la même façon du lundi au vendredi (`invalidation`).
`GET /claude-score` : `cards.all`, `cards.kinds` (london, us), `cards.recent`, `scenarios` (publiées,
déclenchées, objectif, stop, réussite, R total et moyen, minutes moyennes jusqu’à l’objectif). La
relecture du soir s’en sert pour corriger ce qui rate : une fiche jamais déclenchée est une zone mal
placée, un stop touché vite est un stop mal placé.

## Zones rejouées et leçons apprises

Chaque zone donnée à l’EA (`zones` du plan de la semaine, versions du dimanche et mise à jour, et
`zones` des plans de séance) est rejouée par le carnet comme l’EA la trade : entrée au bord touché en
premier, stop 1 $ derrière l’autre bord (1,50 $ au moins) ; `reacted` = 2R avant le stop, `broken` =
stop d’abord, `not_reached`, `wrong_side` (prix déjà de l’autre côté à la publication), `touched`.
Résultats dans `content.zone_score` et `sessions[].zone_score`. `GET /claude-score` donne `zones`
(par famille « OB H4 achat »…, par source) et `lessons` : dès 8 cas d’une même famille (fiches par
séance, setup et sens ; zones par type, unité et sens), « à éviter » sous 34 % de réussite,
« à privilégier » au-dessus de 50 %. Claude ne publie jamais une famille « à éviter » et préfère
celles « à privilégier ». Bulletin de fiabilité sur Telegram le samedi à partir de 9 h.
