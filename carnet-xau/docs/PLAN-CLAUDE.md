# Plan de semaine rédigé par Claude — format de publication

Le plan de la semaine du Carnet XAU est rédigé **exclusivement** par une tâche planifiée Claude. L'onglet Plan l'affiche en lecture seule.

## Publication

```
PUT https://ville-de-kasumbalesa.cd/wp-json/carnet-xau/v1/claude/plans/{lundi}
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
GET https://ville-de-kasumbalesa.cd/wp-json/carnet-xau/v1/calendar?week={lundi}
GET https://ville-de-kasumbalesa.cd/wp-json/carnet-xau/v1/candles
```

- `calendar?week=` : annonces USD à fort et moyen impact de la semaine (ForexFactory, relu
  toutes les 6 h) : `t` (Unix), `date` et `time` (heure de Lubumbashi), `impact`, `title`,
  `fr` (nom français), `forecast`, `previous`. Reprendre ces annonces dans `calendar` (heures
  de Lubumbashi) avec leur incidence probable sur l'or dans `fund_notes`. Le carnet, Telegram
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
    "summary": "Biais haussier tant que 4 120 tient : …",
    "main": "Balayage de l’EQL 4 135 lundi-mardi puis poussée vers 4 230.",
    "alt": "Cassure sous 4 120 (ChoCh H4) : retour vers la demande 4 060–4 075.",
    "invalidation": 4118.0,
    "range_low": 4110.0, "range_high": 4245.0
  },
  "refs": {
    "price": 4181.1, "week_open": 4178.0,
    "prev_week_high": 4212.4, "prev_week_low": 4126.8,
    "prev_month_high": 4260.0, "prev_month_low": 3985.5,
    "atr_d": 38.5, "atr_w": 112.0
  },
  "structure": [
    { "tf": "W",  "trend": "h", "last": "BoS haussier 4 160 (semaine du 5/10)", "pd": "premium",   "note": "" },
    { "tf": "D",  "trend": "h", "last": "BoS 4 205 (15/10)",                     "pd": "equilibre", "note": "" },
    { "tf": "H4", "trend": "r", "last": "ChoCh baissier 4 190 (16/10)",          "pd": "discount",  "note": "" },
    { "tf": "H1", "trend": "b", "last": "BoS baissier 4 172",                    "pd": "discount",  "note": "" }
  ],
  "zones": [
    { "type": "OB", "tf": "H4", "low": 4140.0, "high": 4148.5, "side": "achat", "strength": 3, "state": "vierge", "note": "Sous l’EQL 4 135 balayé = setup 1",
      "origin": "Dernière bougie baissière H4 du jeudi 16/10, 08:00", "draw": "Rectangle du bas de la mèche (4 140) au haut du corps (4 148,5), à partir de cette bougie, prolongé à droite." },
    { "type": "FVG", "tf": "H1", "low": 4224.0, "high": 4231.0, "side": "vente", "strength": 2, "state": "vierge", "note": "",
      "origin": "Déséquilibre H1 du mercredi 15/10, 14:00–16:00", "draw": "Rectangle entre le bas de la bougie de 14:00 et le haut de celle de 16:00." }
  ],
  "levels": [
    { "price": 4212.4, "type": "Plus haut semaine passée", "note": "Liquidité au-dessus", "origin": "Plus haut du jeudi 16/10", "draw": "Ligne horizontale sur le plus haut de la semaine passée." },
    { "price": 4135.0, "type": "EQL", "note": "Double bas H1", "origin": "Bas H1 du 15/10 10:00 et du 16/10 13:00", "draw": "Ligne horizontale reliant les deux bas." }
  ],
  "fundamentals": [
    { "key": "dxy",   "label": "Dollar (DXY)",  "effect": "up",      "value": "101,8 (−0,6 %)", "note": "" },
    { "key": "rates", "label": "Taux réels US", "effect": "neutral", "value": "10 ans 5,10 %",   "note": "" },
    { "key": "fed",   "label": "Attentes Fed",  "effect": "up",      "value": "",                "note": "" },
    { "key": "geo",   "label": "Géopolitique",  "effect": "up",      "value": "",                "note": "" }
  ],
  "fund_notes": "Ce qui pousse l’or cette semaine…",
  "calendar": [
    { "day": "mercredi", "time": "14:30", "event": "CPI US", "impact": "high", "forecast": "0,3 %", "previous": "0,4 %" }
  ],
  "days": [
    { "day": "lundi", "bias": "h", "session": "Londres", "focus": "Attendre le balayage de l’EQL 4 135, pas de vente." }
  ],
  "scenarios": [
    { "if": "le prix balaie l’EQL 4 135 puis donne un gros triangle d’achat", "then": "setup 1 en achat dans l’OB H4",
      "setup": "1", "side": "achat", "entry": "4 140–4 148", "sl": 4132.0, "tp": 4205.0, "prob": 45,
      "invalidation": "Clôture H4 sous 4 118" }
  ],
  "rules": [ "Pas de trade mercredi de 14:15 à 15:00 (CPI).", "Vendredi : demi-risque après 16 h." ],
  "review": {
    "verdict": "partielle", "score": 6,
    "bias_ok": true, "range_held": false, "invalidation_hit": false,
    "zones": [ { "label": "OB H4 4 140–4 148,5", "reached": true, "reacted": true }, { "label": "FVG H1 4 224–4 231", "reached": false, "reacted": null } ],
    "scenarios": [ { "label": "Balayage EQL puis achat", "triggered": true, "result": "+3,1R jusqu’à 4 205" } ],
    "worked": [ "Biais haussier et OB H4" ], "failed": [ "Range sous-estimé après le CPI" ],
    "lesson": "Élargir le range attendu les semaines de CPI.",
    "text": "Commentaire libre."
  },
  "update": {
    "date": "2026-10-21", "status": "maintenu",
    "reason": "Aucun déclencheur : clôture au-dessus de l’invalidation, OB H4 intact.",
    "summary": "Balayage de l’EQL 4 135 à Londres puis rebond dans l’OB H4.",
    "changes": [],
    "trades": "2 trades, tous deux sur l’OB H4 et dans le sens du biais : règles respectées.",
    "tomorrow": "Mercredi : CPI à 14:30, pas de trade de 14:15 à 15:00 ; viser le plus haut 4 212 si l’OB tient."
  },
  "sources": "Bougies or (contrat à terme GC=F, écart avec le courtier ≈ +x $), DXY, US10Y, calendrier ForexFactory, actualité."
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
| `effect` | `up` (soutient l’or), `down` (pèse sur l’or), `neutral` |
| `impact` | `high`, `medium`, `low` |
| `days[].bias` | `h`, `b`, `n` |
| `setup` | `1` (liquidité), `2` (continuation) |
| `review.score` | 0 à 10 |
| `review.verdict` | `bonne`, `partielle`, `mauvaise` |
| `bias_ok`, `range_held`, `invalidation_hit`, `reached`, `reacted`, `triggered` | `true`, `false` ou `null` (inconnu) |
| `update.status` | `maintenu`, `mis_a_jour` (avec `update.date` = jour relu, `AAAA-MM-JJ`) |
| `origin`, `draw` (zones et niveaux) | où tracer : bougie d’origine et consigne de tracé |

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

`area` : entrée, zones, horaires, sorties, discipline, outil. Affichage : carte « Propositions de
Claude » de l’onglet Plan ; les deux premières partent aussi sur Telegram.
