# Plan de test NAS100 (version 1.1, 13/11/2026)

Plan pré-enregistré : tout ce qui suit est écrit **avant** de regarder un seul résultat. Il est exécuté
par l'agent de code du dépôt `devmwembo96/shin` (`strategie-pa`), avec Bernard pour les passages dans le
Testeur MT5. Auteur : la session du Carnet NAS100. La version 1.1 coule le plan dans le circuit de
`strategie-pa` (réponse de l'agent du 13/11) : moteur de l'EA réutilisé, prompts pour l'assistant de
MT5, CSV, critères C0 à C4.

## 0. Question et règles du jeu

**Question unique.** Existe-t-il une règle mécanique, exécutable à la main par Bernard sur le Nasdaq 100
(CFD « NDX100 » chez FundedNext) pendant la séance de New York (09:30 → 16:00 New York), dont
l'espérance **nette de coûts** est positive et assez robuste pour un challenge FundedNext ?

1. On teste pour réfuter. Les labos de l'or et du BTC n'ont trouvé aucun avantage aux zones SMC/ICT :
   une règle sans preuve reste « non prouvée », jamais « prometteuse ».
2. Rien n'est ajouté en cours de route sans être compté. Toute variante née après avoir vu un résultat
   est marquée « post hoc » et ne peut pas être retenue sans repasser par un bloc de données neuf.
3. Trois blocs de données, jamais mélangés : Découverte, Confirmation, Test scellé (ouvert une fois).
4. Les « faits de confluence » (gap, zone de valeur, VWAP, 30 premières minutes, volatilité, annonces)
   sont **mesurés**, jamais imposés comme filtres d'avance.
5. Jamais de martingale, de moyenne à la baisse ni de position gardée la nuit. Risque fixe par trade.
6. Le temps de Bernard est la ressource rare : la recherche se fait sans lui (étape R), MT5 ne sert
   qu'aux finalistes (étape M) : **10 passages dans le Testeur au plus**, au lieu de 56 optimisations.

## 1. Instrument, horloge et données

- **Instrument** (mesuré le 13/11 sur le compte FundedNext du carnet) : symbole `NDX100`, 2 décimales,
  tick 0,01, valeur du tick 0,10 $ : **1 lot = 10 $ par point d'indice** ; lot minimum et pas 0,01
  (0,10 $ par point). Lot = risque ÷ (stop en points × 10). Prix ≈ 28 350 ; ATR journalier (14) ≈ 780
  points : période très volatile (−6,4 % le 05/11).
- **Horloge** : toutes les règles sont écrites en heure de New York (America/New_York, heure d'été
  américaine comprise). Serveur FundedNext = New York + 7 h toute l'année (bougie D1 ouverte à 17:00
  New York) : à vérifier sur les ticks (le pic de volume de 09:30 doit rester à 16:30 serveur pendant
  les semaines où l'Europe et les États-Unis ne sont pas à la même heure). Demi-séances (clôture
  13:00) et jours fériés américains exclus.
- **Source A (longue)** : bougies M1 bid et ask d'une source indépendante sur au moins 8 ans :
  Découverte et Confirmation, hors MT5, sans temps de Bernard. Piste : Dukascopy `USATECHIDXUSD`
  (`https://datafeed.dukascopy.com/datafeed/USATECHIDXUSD/AAAA/MM/JJ/BID_candles_min_1.bi5`, mois de
  00 à 11, fichier LZMA, enregistrements de 24 octets gros-boutistes : secondes depuis 00:00 UTC,
  ouverture, clôture, bas, haut en entiers, volume en flottant ; échelle des prix à vérifier sur un
  cours connu ; limite de débit sévère, erreur 429 : une requête toutes les 2 s, reprise progressive,
  cache disque). Autre piste : HistData `NSXUSD` en M1.
- **Source B (courtier)** : ticks réels FundedNext dans le Testeur MT5 (« Chaque tick basé sur des ticks
  réels ») : parité et test scellé, avec le spread réel du courtier. Profondeur à vérifier (l'or ne
  commence qu'en 2024-01 chez Bernard).
- **Contrôle de qualité** (rapport d'étape 0) : trous, doublons, changements d'heure, et comparaison
  A contre B sur 20 séances communes (corrélation des rendements M5 ≥ 0,99 ; écart médian des prix
  d'ouverture de 09:30 ≤ 0,05 %). Si A et B divergent, on le dit et on s'arrête.

| Bloc | Avec la source A | Repli : source B seule (depuis 2024) |
|---|---|---|
| Découverte | 2015-01 → 2021-12 | 2024-01 → 2025-03 |
| Confirmation | 2022-01 → 2024-12 | 2025-04 → 2026-02 |
| Test scellé | 2025-01 → 2026-10 (MT5, source B) | 2026-03 → 2026-10 |

Dans le repli, la puissance est faible (≈ 300 trades en Découverte pour une règle à un trade par jour :
seule une espérance ≥ 0,2R est détectable) : aucun verdict ne peut alors dépasser « À CONFIRMER ».

## 2. Définitions communes (calculées sans regard vers l'avenir)

- `O` : ouverture de la bougie M1 de 09:30. `Cprev` : clôture de la bougie M1 de 15:59 de la séance
  précédente.
- `ATRj` : moyenne des étendues de séance (haut − bas de 09:30 à 16:00) des 14 séances précédentes.
- `VWAP(t)` : Σ(prix typique × volume de ticks) ÷ Σ(volume de ticks) des bougies M1 de 09:30 à t.
- `ONH`, `ONL` : extrêmes de 18:00 (la veille) à 09:29. `PDH`, `PDL` : extrêmes de la séance précédente.
- `IB` : haut et bas de 09:30 à 10:29. `OR(n)` : haut et bas des n premières minutes.
- Zone de valeur de la veille : profil du volume de ticks M1 de la séance précédente (tranches de
  0,02 % du prix) ; `POC` = tranche la plus chargée ; `VAH`, `VAL` = bornes des 70 % autour du POC.
- Une bougie n'est connue qu'à sa clôture. Ordre stop ou au marché : exécuté à l'ouverture de la bougie
  M1 suivante, plus le glissement. Stop et objectif touchés dans la même bougie M1 : on compte le stop.
- Une seule position à la fois par règle, un seul trade par jour et par règle (sauf mention), tout est
  fermé à 15:55 au plus tard.

## 3. Coûts

- Spread : celui de la source (ask − bid), plancher 1,0 point ; doublé de 09:30 à 09:35.
- Glissement : 0,5 point par ordre stop ou au marché ; 0 pour une limite, mais une limite n'est remplie
  que si le prix la dépasse d'au moins le spread.
- Commission : celle du compte FundedNext (à lire dans MT5). Pas de swap : rien ne passe la nuit.
- **Stress** : chaque résultat est aussi donné avec des coûts doublés. Une règle qui devient négative
  sous stress est « fragile ».

## 4. Unité de mesure

`R` = distance entrée → stop au moment de l'entrée. Chaque trade est noté en R **net de coûts** (comme
`result_R` de l'EA actuel). Les règles sans stop naturel reçoivent un stop de référence défini
ci-dessous, pour rester compatibles avec un compte de prop firm. Chaque résultat est donné pour
l'ensemble, puis **achats et ventes séparés** (l'indice a une pente haussière de fond).

## 5. Familles et variantes pré-enregistrées (69 variantes)

### Témoins (à calculer en premier)
- **T0, dérive** : achat à 09:30, sortie à 15:55, chaque jour (stop de référence 0,5 × ATRj). Toute
  règle acheteuse doit faire mieux que sa part de dérive.
- **T1, hasard apparié** : pour chaque variante, 1 000 répliques où le sens est tiré au hasard (même
  proportion d'achats) et l'heure d'entrée décalée au hasard dans la même fenêtre, avec les mêmes stop,
  objectif et sortie. La variante doit dépasser le 95ᵉ centile de ces répliques.

### A. Cassure du range d'ouverture (référence connue) — 27 variantes
- **A1, sens de la première bougie** (Zarattini et Aziz, 2023) : n ∈ {5, 15, 30} min. Si la clôture de
  l'OR(n) est au-dessus de `O` : achat à la fin de l'OR ; en dessous : vente ; égale : rien. Stop : extrême
  opposé de l'OR. Sortie ∈ {15:55, 2R, 3R}. → 9 variantes.
- **A2, cassure classique** : n ∈ {5, 15, 30}. Ordres stop au-dessus du haut et sous le bas de l'OR,
  valables jusqu'à 12:00 ; le premier touché est pris, l'autre annulé. Stop ∈ {bord opposé, milieu de
  l'OR}. Sortie ∈ {15:55, 2R, 3R}. → 18 variantes.

### B. Momentum hors de la zone de bruit (Zarattini, Aziz et Barbon, 2024) — 6 variantes
- Pour chaque minute m de la séance : σ(m) = moyenne, sur les 14 séances précédentes, de
  |P(m) ÷ O − 1|. Bornes : `Haut(m)` = max(O, Cprev) × (1 + k·σ(m)) ; `Bas(m)` = min(O, Cprev) × (1 − k·σ(m)).
- Décision toutes les 30 minutes de 10:00 à 15:30 : prix au-dessus de `Haut` → achat ; sous `Bas` →
  vente (si à plat).
- Sortie d'un achat : prix sous max(`Haut`, `VWAP`) (symétrique pour une vente), ou 15:55.
- Stop de référence et unité R : distance entre l'entrée et max(`Haut`, `VWAP`) à l'entrée, avec un
  plancher de 0,15 × ATRj ; ce stop est un vrai stop.
- Grille : k ∈ {0,8 ; 1,0 ; 1,2} × contrôle de la sortie ∈ {aux décisions de 30 min, à chaque clôture
  M1}. → 6 variantes.

### C. Dernière demi-heure (Gao, Han, Li et Zhou, 2018) — 8 variantes
- `r1` = P(10:00) ÷ Cprev − 1 ; `r12` = P(15:30) ÷ P(15:00) − 1 ; `rj` = P(15:30) ÷ Cprev − 1.
- À 15:30, entrée dans le sens du signal, sortie à 15:59 :
  C1 = signe de `r1` ; C2 = `r1` et `r12` de même signe, sinon rien ; C3 = signe de `r1` seulement si
  |`r1`| dépasse sa médiane des 60 séances précédentes ; C4 = signe de `rj`.
- Stop de référence ∈ {0,5 × étendue moyenne de 15:30 → 16:00 des 20 séances précédentes (vrai stop),
  aucun stop (R = cette même distance, mesure seulement)}. → 8 variantes.

### D. Théorie des enchères : IB, VWAP, zone de valeur — 14 variantes
- **D1, cassure de l'IB** : de 10:30 à 14:00, première clôture M5 au-dessus du haut de l'IB → achat (sous
  le bas → vente). Stop ∈ {milieu de l'IB, bord opposé}. Sortie ∈ {1 × étendue de l'IB projetée, 15:55}.
  → 4 variantes.
- **D2, fausse cassure de l'IB** : le prix dépasse un bord de l'IB d'au moins 0,1 × son étendue, puis une
  bougie M5 clôture de retour dans l'IB avant 14:00 → entrée vers l'intérieur. Stop : extrême de
  l'excursion + 0,05 × étendue de l'IB. Objectif ∈ {milieu de l'IB, bord opposé}. → 2 variantes.
- **D3, repli sur le VWAP dans le sens de l'ouverture** : sens = signe de P(10:00) − O si l'écart dépasse
  0,25 × ATRj. Premier contact du VWAP de 10:00 à 13:00 : ordre limite au VWAP. Stop ∈ {0,10 ; 0,15 ;
  0,20} × ATRj. Objectif ∈ {2R, extrême du jour au moment de l'entrée}. → 6 variantes.
- **D4, règle des 80 %** : `O` hors de la zone de valeur de la veille ; le prix y rentre et deux clôtures
  de 30 minutes consécutives restent dedans → entrée vers le bord opposé de la zone. Stop ∈ {extrême du
  jour côté entrée + 0,02 × ATRj, bord d'entrée ± 0,25 × largeur de la zone}. Objectif : bord opposé ;
  sinon 15:55. → 2 variantes. À donner aussi : la fréquence brute « le bord opposé est touché »,
  comparée à la même fréquence sans la condition.

### E. Prises de liquidité (ICT/SMC) — 14 variantes
- **E1, balayage du haut ou du bas de la nuit ou de la veille** : de 09:30 à 11:30, une bougie dépasse
  `ONH` ou `PDH` (de 0,05 à 1 ATR(14) de son unité de temps) et clôture de retour en dessous → vente à
  la clôture (symétrique en bas). Stop : extrême de la bougie + 0,1 ATR. Une entrée par niveau et par
  jour. Bougie ∈ {M5, M15} × objectif ∈ {2R, 3R, VWAP (seulement s'il est à 1R au moins, sinon pas de
  trade)}. → 6 variantes.
- **E2, Silver Bullet (10:00 → 11:00)** : dans l'heure, après un balayage (dans les 6 bougies
  précédentes, une bougie a dépassé l'extrême des 20 bougies puis clôturé de retour), premier FVG de
  sens opposé d'au moins 0,1 ATR. Ordre limite au bord proche du FVG, valable 10 bougies. Stop : extrême
  du balayage + 0,1 ATR. Unité ∈ {M1, M5} × objectif ∈ {2R, 3R}. → 4 variantes.
- **E3, setup 4 de l'EA Carnet NAS100 tel qu'il est codé** : balayage M15 (plus haut ou plus bas de 20
  bougies, nuit, veille, égaux ; mèche de 0,05 à 1 ATR M15, clôture de retour) → CHoCH M5 (clôture
  au-delà du dernier pivot, 24 bougies au plus, sans nouvel extrême) → FVG M5 ≥ 0,1 ATR M5 dans
  l'impulsion → limite au premier tiers du FVG, valable 60 min ; stop : mèche + 0,1 ATR M15 + spread ;
  fenêtre 09:30 → 12:00. FVG ∈ {déjà formé à la clôture du CHoCH (code actuel), accepté jusqu'à 3
  bougies M5 après} × objectif ∈ {3R (code actuel), 2R}. → 4 variantes.
  Constat du 13/11 sur les bougies du carnet : 6 balayages en 4 séances, aucun signal (3 nouveaux
  extrêmes, 1 sans CHoCH, 1 CHoCH sans FVG, 1 sans données M5). Le code de référence est
  `assets/mt5/CarnetNAS100.mq5.txt` du dépôt `devmwembo96/theme-kasumbalesa` (fonctions
  `SfDetectSweep` et `SfCheckChoch`).

## 6. Statistiques et verdicts (critères C0 à C6, dans le vocabulaire de `strategie-pa`)

**Découverte.** Une variante est retenue si elle remplit **tout** ceci :
- **C0** : au moins 200 trades (100 dans le repli MT5 seul) ;
- **C1, plateau** : espérance nette ≥ +0,08R, et au moins 70 % des variantes voisines dans la grille
  ont une espérance positive ;
- **C2, significativité** : t ≥ 2,5 (bootstrap par blocs d'une semaine, 10 000 tirages) **et** q ≤ 0,10
  après correction de Benjamini-Hochberg sur les 69 variantes (plus les « post hoc ») ;
- **C4, hasard** : elle dépasse le 95ᵉ centile du témoin T1 ;
- **C5, stress** : espérance encore positive avec les coûts doublés.

Cinq variantes au plus passent en Confirmation (une par famille de préférence), réglages gelés.

**Confirmation (C3).** Réglages gelés, aucun ajustement : espérance > 0 avec t ≥ 1,65 ; au moins 40 %
de l'espérance de Découverte ; perte maximale cumulée ≤ 15R ; aucune année ne fait à elle seule plus
de 60 % du gain. On ne choisit jamais un réglage sur la Confirmation.

**Test scellé (C6).** Trois règles au plus, une seule ouverture, dans MT5 sur les ticks FundedNext.

| Verdict | Condition |
|---|---|
| EDGE PROUVÉ | C0 à C5 vrais, et test scellé : espérance > 0, t ≥ 1,65, simulation de challenge favorable |
| À CONFIRMER | C0 à C5 vrais, test scellé positif sans atteindre t ≥ 1,65 (ou repli MT5 seul) |
| PAS D'EDGE | tout le reste |

**Parité.** Avant le test scellé, chaque règle finaliste codée en MQL5 est comparée à la version de
recherche sur 20 séances communes : au moins 90 % des trades identiques (même sens, entrée à 2 points
près).

## 7. Faits de confluence (mesurés pour chaque variante retenue)

Espérance en R par classe, sur la Découverte puis la Confirmation :
1. ouverture par rapport à la zone de valeur de la veille (au-dessus, dedans, en dessous) ;
2. ouverture par rapport au range de la veille (hors de PDH–PDL ou dedans) et taille du gap (terciles) ;
3. côté du VWAP à l'entrée ; 4. accord avec le sens des 30 premières minutes ;
5. régime de volatilité (terciles d'ATRj) ; 6. largeur de l'IB (terciles) ;
7. jour d'annonce USD à fort impact (CPI, NFP, FOMC, PCE) ou non, et entrée à ±15 min de l'annonce ;
8. jour de la semaine.

Un fait ne devient un filtre que si son effet a le même signe en Découverte et en Confirmation, avec
q ≤ 0,10. Sinon il reste une information affichée, sans rien bloquer.

## 8. Simulation de challenge (pour chaque règle finaliste)

FundedNext Stellar 2-Step, compte de 6 000 $ : objectif +8 % puis +5 % ; perte journalière maximale
5 % ; perte totale maximale 10 % ; 5 jours tradés au minimum. Règles du carnet : risque 0,5 % par
trade, 3 trades par jour au plus, arrêt après 2 pertes dans la journée. Bootstrap par blocs de journées
(10 000 parcours) : probabilité de réussir les deux phases, probabilité d'échec, durée médiane.
« Favorable » = probabilité de réussite au moins deux fois celle de l'échec.

## 9. Exécution dans le circuit `strategie-pa`

### Étape R : recherche, sans Bernard (conteneur de l'agent)
- Moteur de recherche en Python sur la source A (le script d'analyse et la méthode du plateau de
  `analyse_module1.py` se réutilisent). Découverte puis Confirmation des 69 variantes, témoins,
  registre, journaux de trades, faits de confluence.
- **Tests anti-regard vers l'avenir** obligatoires : pour chaque détecteur, données tronquées à T contre
  données complètes, signaux identiques ; un détecteur volontairement tricheur doit être attrapé.

### Étape M : MT5, avec Bernard (finalistes seulement)
- Le moteur de `PA_M1_Structure_EA` est gardé tel quel (exécution, SL/TP en R, MFE/MAE, coûts, frames,
  CSV, mode aléatoire, garde-fou `_EN_COURS`). Un module de signal `.mqh` par famille finaliste et un
  `InpSignalType` remplacent `InpTradeEvents` ; chaque signal porte sa règle de stop.
- **Heures** : règles en heure de New York, converties dans l'EA par « serveur − 7 h ». Ne jamais
  utiliser `TimeGMT()` dans le Testeur (il y vaut l'heure du serveur).
- **Prompts** pour l'assistant de MT5 : même gabarit que 01 à 30, un par passage, produits par le
  générateur ; modèle « Every tick based on real ticks », dépôt 10 000 USD, délai 50 ms.
- **Nom des CSV** : `NAS_<famille><variante>_<bloc>_<AAAAMMJJ_HHMM>.csv`, bloc ∈ {PARITE, RND, SCELLE} ;
  jamais lu tant qu'il porte `_EN_COURS`.
- **Passages demandés à Bernard, 10 au plus** : pour chacune des 3 règles finalistes au plus, un test
  simple de parité (20 séances), un contrôle aléatoire de 30 graines, un test scellé unique ; plus un
  passage de réserve.

### Repli : source A introuvable (tout dans MT5)
Même catalogue, blocs de la colonne « repli », grilles passées comme inputs à optimiser (une
optimisation par sous-famille : 11 passages de Découverte de 2 à 18 passes), puis Confirmation et test
scellé en tests simples : environ 24 passages. Verdict plafonné à « À CONFIRMER ». À ne lancer qu'avec
l'accord de Bernard sur la charge.

### Ordre et points d'arrêt

| Étape | Contenu | Qui | Livrable |
|---|---|---|---|
| 0 | Arrêt confirmé de la campagne XAUUSD/GER40 ; profondeur des ticks NDX100 et commission dans MT5 | Bernard | deux réponses dans le chat |
| 1 | Données A, contrôle de qualité, modèle de coûts | agent | rapport d'étape |
| 2 | Témoins T0 et T1 ; familles B, A puis C (règles horaires simples) | agent | registre + journaux |
| 3 | Famille D (IB, VWAP, zone de valeur) | agent | idem |
| 4 | Famille E (liquidité, setup 4) ; gel des variantes retenues | agent | idem |
| 5 | Confirmation (5 variantes au plus) ; gel des finalistes (3 au plus) | agent | rapport de confirmation |
| 6 | Modules MQL5 des finalistes, parité, contrôle aléatoire, test scellé | Bernard + agent | CSV du Testeur |
| 7 | Rapport final, simulation de challenge, recommandation pour l'EA du carnet | agent | rapport final |

- **Registre** (`registre.csv`) : une ligne par variante testée, y compris les « post hoc » : famille,
  réglages, bloc, trades, espérance nette (ensemble, achats, ventes), t, q, part de voisines positives,
  centile du témoin, stress, verdict.
- **Journal des trades** (un CSV par variante, colonnes de l'EA actuel) plus les 8 faits de confluence.
- Rapport court après chaque étape, envoyé à la session du carnet (`send_message` vers
  `session_01Nf7aFSrkHodwiWzoMBAGfZ`) et montré à Bernard. On ne touche pas à la Confirmation avant le
  gel de l'étape 4, ni au test scellé avant le gel de l'étape 5.
- Si aucune variante ne passe la Découverte : le dire tel quel. C'est un résultat, pas un échec du plan.

## 10. Ce que le plan ne teste pas

Les zones et fiches rédigées par Claude (plans de la semaine et de New York) ne sont pas mécaniques :
le carnet les rejoue lui-même chaque soir (`/claude-score`). Les règles validées ici serviront de base
objective à l'EA (signal seulement) et aux plans.
