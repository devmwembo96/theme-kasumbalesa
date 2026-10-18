# Plan de semaine rédigé par Claude — format de publication

Le plan de la semaine du Carnet XAU est rédigé **exclusivement** par une tâche planifiée Claude. L'onglet Plan l'affiche en lecture seule.

## Publication

```
PUT https://ville-de-kasumbalesa.cd/wp-json/carnet-xau/v1/claude/plans/{lundi}
En-tête  X-CX-Key: <jeton du plan>          (Comptes › Connexions › Plan de la semaine)
Corps    JSON ci-dessous
```

- `{lundi}` : date du lundi de la semaine, `AAAA-MM-JJ`.
- Les clés envoyées remplacent celles du plan existant ; les autres sont conservées. Pour compléter le **bilan** d'une semaine passée, envoyer seulement `{"review": {...}}` sur le lundi de cette semaine-là.
- Un envoi qui contient `prediction` publie le plan et envoie son résumé sur Telegram (ajouter `?silent=1` pour ne rien envoyer).
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
    { "type": "OB", "tf": "H4", "low": 4140.0, "high": 4148.5, "side": "achat", "strength": 3, "state": "vierge", "note": "Sous l’EQL 4 135 balayé = setup 1" },
    { "type": "FVG", "tf": "H1", "low": 4224.0, "high": 4231.0, "side": "vente", "strength": 2, "state": "vierge", "note": "" }
  ],
  "levels": [
    { "price": 4212.4, "type": "Plus haut semaine passée", "note": "Liquidité au-dessus" },
    { "price": 4135.0, "type": "EQL", "note": "Double bas H1" }
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
  "review": { "text": "Bilan de la semaine (rempli la semaine suivante).", "score": 7 },
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

Limites : 6 lignes de structure, 12 zones, 15 niveaux, 8 fondamentaux, 25 annonces, 5 jours, 8 scénarios, 10 règles. Les valeurs hors format sont ignorées.

## Ce que le carnet en déduit

- Biais d'origine = la plus forte des trois probabilités.
- Niveaux, annonces et scénarios d'origine : résumé de l'onglet Jour, alertes Telegram 15 minutes avant chaque annonce, commande `/plan`.
