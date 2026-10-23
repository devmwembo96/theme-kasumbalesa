//+------------------------------------------------------------------+
//|                                               CalculateurXAU.mq5 |
//|   Outil position et ticket d'ordre façon TradingView, risque     |
//|   et arrêts pilotés par le Carnet XAU                             |
//+------------------------------------------------------------------+
//| Version 4.01 : affichage façon TradingView                       |
//|  - Police Trebuchet MS (celle de TradingView sous Windows), Arial |
//|    pour les symboles ▲ ▼ ○ ↔ qu'elle n'a pas.                     |
//|  - Mise à l'échelle de l'écran : avec Windows à 125 % ou 150 %,  |
//|    les cadres grandissent comme le texte, plus de débordement.   |
//|  - Étiquettes de l'outil : texte centré dans son cadre.          |
//+------------------------------------------------------------------+
//| Version 4.00                                                     |
//|  - Sell / Buy (ou S / W) : l'outil position court ou long se     |
//|    trace au prix actuel, stop par défaut 4,00 $, objectif 2R.    |
//|  - Il se règle à la souris comme sur TradingView : poignée du    |
//|    stop, de l'objectif, de l'entrée (ordre Limite ou Stop), de   |
//|    la largeur (carré de droite), ou tout l'outil en le saisissant|
//|    au milieu. Les prix se saisissent aussi dans le ticket.       |
//|  - Ticket d'ordre : Marché / Limite / Stop, risque en $ ou en %  |
//|    du compte ⇄ lot, valeur du trade, marge disponible, valeur du |
//|    tick, objectif et stop en prix ⇄ ticks, ratio risque /        |
//|    récompense, Fermer et bouton d'envoi.                         |
//|  - Le SL n'est plus placé derrière une bougie : il est là où     |
//|    l'outil le montre, ou celui du signal du Carnet XAU (touche C)|
//|  - Flèche sur le prix d'entrée : là où est le prix avant         |
//|    d'entrer, puis là où l'ordre a été exécuté.                   |
//+------------------------------------------------------------------+
//| Risque et limites du carnet (par défaut) : l'EA Carnet XAU, sur  |
//| le même compte, transmet ce que le carnet calcule pour ce        |
//| compte : risque normal ou réduit, trades et pertes du jour,      |
//| perte du jour, plancher, plage de stop, étape et objectif.       |
//|  - Le risque du ticket est celui du carnet : on peut le baisser  |
//|    (en $ ou en %), jamais le dépasser.                           |
//|  - Une perte qui entame la limite du jour réduit le trade        |
//|    suivant : une perte pleine ne touche jamais la limite.        |
//|  - Arrêt : pertes ou trades du jour atteints, limite du jour,    |
//|    plancher, compte échoué ou retiré dans le carnet.             |
//|  - Objectif de l'étape atteint : plus de trade normal ; micro-   |
//|    trades (lot minimum) jusqu'au nombre de jours minimum, puis   |
//|    arrêt jusqu'au passage à l'étape suivante.                    |
//|  - Nouvelle étape ou nouvelle taille dans le carnet : le risque  |
//|    et le lot suivent tout seuls.                                 |
//| Sans l'EA Carnet XAU, l'envoi reste bloqué (ou désactiver        |
//| « Risque et limites du carnet » : modèles ci-dessous).           |
//+------------------------------------------------------------------+
//| Modèles sans le carnet :                                         |
//|  - Fonds propres : capital de départ = budget de 12 pertes,      |
//|    paliers 4 %, 2 % et 1 %, limites du jour et de la semaine,    |
//|    plancher.                                                     |
//|  - Prop firm : risque fixe réduit sous -3 % et -6 %, limites de  |
//|    la firme.                                                     |
//+------------------------------------------------------------------+
//| Clavier (graphique au premier plan) :                            |
//|     W ou B  outil long (achat)      S  outil court (vente)       |
//|     Espace  envoyer l'ordre         Q ou Échap  fermer l'outil   |
//|     C  charger le dernier signal du Carnet XAU                   |
//|     A  BE     D  trailing, palier suivant     E E  fermer        |
//| Les boutons et le calendrier économique ne fonctionnent pas      |
//| dans le testeur de stratégie : tester l'outil sur compte démo.   |
//+------------------------------------------------------------------+
#property version     "4.01"
#property description "Carnet XAU : outil position et ticket d'ordre façon TradingView, risque et arrêts du carnet."

#include <Trade\Trade.mqh>

//=== Préfixes et couleurs ===========================================
#define PFX          "CN_"
#define PFXM         "CNmk_"
#define PFXT         "CNt_"
#define GV_PREFIX    "CN"
#define TAG          "[CalculateurXAU] "
#define CLR_BG       C'19,23,34'
#define CLR_HDR      C'19,23,34'
#define CLR_ACCENT   C'41,98,255'
#define CLR_BORDER   C'54,58,69'
#define CLR_TEXT     C'219,222,229'
#define CLR_MUTED    C'134,139,152'
#define CLR_GREEN    C'8,153,129'
#define CLR_RED      C'242,54,69'
#define CLR_ORANGE   C'238,164,52'
#define CLR_EDIT_BG  C'30,34,45'
#define CLR_BTN      C'42,46,57'
#define CLR_DISABLED C'34,38,48'
#define CLR_INFO_BG  C'30,34,45'
#define CLR_TINT_G   C'16,48,42'
#define CLR_TINT_R   C'64,24,30'
#define CLR_TINT_O   C'60,46,22'
#define CLR_TINT_N   C'30,34,45'
#define CLR_SELL_ON  C'84,28,36'
#define CLR_SELL_TX  C'247,82,95'
#define CLR_BUY_ON   C'20,44,100'
#define CLR_BUY_TX   C'91,156,246'
#define CLR_SIDE_OFF C'30,34,45'
#define CLR_TV_LINE  C'120,123,134'
#define FONT_TV      "Trebuchet MS"        // police de TradingView sous Windows
#define FONT_TVB     "Trebuchet MS Bold"
#define FONT_SYM     "Arial"               // symboles ▲ ▼ ○ ↔ : absents de Trebuchet MS

//=== Tes règles personnelles : bloquer le bouton ou seulement avertir ===
enum ENUM_RULE_MODE
  {
   RULE_AVERTIR = 0, // Avertir seulement (le bouton reste actif)
   RULE_BLOQUER = 1  // Bloquer le bouton
  };

//=== Comportement du TP une fois le trade en cours ===
enum ENUM_TPMODE
  {
   TPM_FIXE    = 0, // Fixe : le TP ne bouge pas
   TPM_PALIERS = 1, // Paliers : le TP recule d'un R quand le prix s'en approche avec élan
   TPM_LIBERE  = 2  // Libéré : le TP est retiré, la sortie se fait par le trailing
  };

//=== Modèle de risque sans le carnet ===
enum ENUM_ACC_MODE
  {
   MODE_FONDS = 0, // Fonds propres : budget de 12 pertes, paliers, plancher
   MODE_PROP  = 1  // Prop firm : risque fixe, limites de la firme
  };

//=== Poignées de l'outil position ===
enum ENUM_DRAG
  {
   DRAG_NONE  = 0,
   DRAG_SL    = 1,
   DRAG_TP    = 2,
   DRAG_ENTRY = 3,
   DRAG_WIDTH = 4,
   DRAG_BODY  = 5
  };

//=== Entrées ========================================================
input group "=== Carnet XAU ==="
input bool   InpUseCarnet        = true;  // Risque et limites du carnet (EA Carnet XAU sur ce compte)

input group "=== Modèle de risque sans le carnet ==="
input ENUM_ACC_MODE InpMode      = MODE_FONDS; // Type de compte

input group "=== Fonds propres ==="
input double InpStartCapital     = 250.0;  // Capital de départ = budget de pertes ($)
input int    InpLossBudget       = 12;     // Nombre de pertes pleines du budget (risque = capital / N)
input double InpTier1Max         = 500.0;  // Palier 1 jusqu'à X $ : risque = capital / N
input double InpTier2Pct         = 4.0;    // Palier 2 : risque en % du capital
input double InpTier2Max         = 1000.0; // Palier 2 jusqu'à X $
input double InpTier3Pct         = 2.0;    // Palier 3 : risque en % du capital
input double InpTier3Max         = 2500.0; // Palier 3 jusqu'à X $
input double InpTier4Pct         = 1.0;    // Palier 4 (au-delà) : risque en % du capital
input double InpFloorUsd         = 125.0;  // Plancher : plus aucun trade réel sous X $ d'équité
input double InpWithdrawPct      = 50.0;   // Retrait conseillé en fin de mois (% du gain du mois)

input group "=== Prop firm ==="
input double InpInitialBalance   = 50000.0; // Capital initial du compte ($)
input double InpPropRiskUsd      = 50.0;    // Risque fixe par trade ($)
input double InpTier1DDPct       = 3.0;     // Palier 1 : équité sous -X % du capital initial
input double InpTier1Mult        = 0.50;    // Palier 1 : multiplicateur du risque
input double InpTier2DDPct       = 6.0;     // Palier 2 : équité sous -X % du capital initial
input double InpTier2Mult        = 0.25;    // Palier 2 : multiplicateur du risque
input double InpFirmDailyPct     = 3.0;     // Perte journalière max de la firme (% du capital initial)
input double InpFirmMaxPct       = 10.0;    // Perte totale max de la firme (% du capital initial)

input group "=== Outil position et stop ==="
input double InpDefStopUsd       = 4.00;  // Stop par défaut de l'outil ($ de prix)
input double InpRTarget          = 2.0;   // Objectif par défaut de l'outil (R)
input int    InpToolBars         = 40;    // Largeur de l'outil (bougies)
input double InpStopMin          = 1.50;  // Écart entrée -> stop minimum ($ de prix, sans le carnet)
input double InpStopMax          = 8.00;  // Écart entrée -> stop maximum ($ de prix, sans le carnet)
input bool   InpLimitOver        = true;  // Marché avec stop > max : ordre limite où le stop revient au max
input int    InpLimitMin         = 30;    // Validité de cet ordre limite (minutes)
input int    InpPendingMin       = 0;     // Validité des ordres Limite et Stop du ticket (minutes, 0 = jusqu'à annulation)
input double InpMinR             = 2.0;   // R minimum à l'objectif (règle du carnet)

input group "=== Règles du jour et de la semaine (sans le carnet) ==="
input int    InpMaxTradesDay     = 3;    // Trades maximum par jour
input int    InpMaxLossesDay     = 2;    // Pertes maximum par jour
input double InpDayStopR         = 2.0;  // Perte maximum du jour (en R du palier)
input int    InpMaxLossesWeek    = 4;    // Pertes maximum par semaine (fonds propres)
input double InpWeekStopR        = 4.0;  // Perte maximum de la semaine (en R, fonds propres)
input double InpLossThresholdR   = 0.5;  // Une perte = résultat net inférieur à -X R
input int    InpResetHourServer  = 0;    // Heure de remise à zéro, heure serveur (= 17 h New York)
input int    InpMaxOpenPositions = 1;    // Positions ouvertes simultanées (tout le compte)
input ENUM_RULE_MODE InpSelfRules = RULE_AVERTIR; // Positions ouvertes (et trades max sans le carnet) : bloquer ou avertir

input group "=== Exécution ==="
input double InpMaxCostPct       = 35.0;     // Coûts max (spread + commission) en % du stop
input double InpCommissionPerLot = 0.0;      // Commission aller-retour par lot ($), 0 sur compte standard
input double InpMaxMarginPct     = 80.0;     // Marge max utilisée après le trade (% de l'équité)
input bool   InpSellSpreadAdjust = true;     // Ventes : stop et objectif déclenchés au niveau du graphique (spread ajouté)
input bool   InpBEIncludeCosts   = true;     // BE : décaler le stop des coûts pour sortir vraiment à zéro
input int    InpMaxSlippagePts   = 30;       // Glissement maximum accepté (points)
input long   InpMagic            = 20260916; // Numéro magique

input group "=== Automatique après l'entrée ==="
input bool          InpAutoOn      = true;       // Gestion automatique active au démarrage
input ENUM_TIMEFRAMES InpAutoTF    = PERIOD_M1;  // Unité des clôtures surveillées
input double        InpBETriggerR  = 1.0;        // BE déclenché par une clôture au-delà de X R
input bool          InpAutoTrail   = true;       // Trailing structurel après le BE
input int           InpSwingLen    = 2;          // Creux/sommet confirmé par X bougies de chaque côté
input int           InpAtrPeriod   = 14;         // Période de l'ATR (garde-fou anti-bruit)
input double        InpMinAtrDist  = 1.0;        // Stop jamais à moins de X ATR du prix
input ENUM_TPMODE   InpTPMode2     = TPM_PALIERS; // Comportement du TP
input double        InpTPStepR     = 1.0;        // Paliers : extension du TP par pas de X R
input int           InpTPMaxSteps  = 3;          // Paliers : nombre maximum d'extensions
input double        InpTPBufferR   = 0.25;       // Paliers : extension déclenchée à X R du TP
input double        InpTPFreeR     = 1.5;        // Libéré : TP retiré au-delà de X R

input group "=== Clavier ==="
input bool   InpKeysOn           = true;  // Raccourcis clavier (W B S Espace Q Échap C A D E E)

input group "=== Filtre news ==="
input bool   InpNewsFilter       = true;  // Activer le filtre news
input int    InpNewsWindowMin    = 5;     // Fenêtre avant et après l'annonce (minutes)
input string InpNewsCurrencies   = "USD"; // Devises surveillées (séparées par des virgules)

input group "=== Flèches sur le graphique ==="
input bool   InpShowMarks        = true;          // Afficher les flèches
input int    InpMarkSize         = 8;             // Taille des flèches (points de police)
input color  InpMarkBuyClr       = C'8,153,129';  // Flèche d'achat
input color  InpMarkSellClr      = C'242,54,69';  // Flèche de vente
input int    InpMarkDays         = 7;             // Historique des flèches d'entrée (jours)

input group "=== Journal et affichage ==="
input bool   InpWriteJournal     = true;                          // Écrire le journal CSV
input string InpJournalFile      = "CalculateurXAU_journal.csv";  // Fichier du journal (dossier Common\Files)
input int    InpPanelX           = 10;   // Position X du ticket (pixels)
input int    InpPanelY           = 30;   // Position Y du ticket (pixels)
input int    InpPanelWidth       = 330;  // Largeur du ticket (pixels)
input double InpPanelScale       = 1.0;  // Échelle du ticket (1.0 = normal)

//=== Structures =====================================================
// Résultat complet d'un calcul
struct SCalc
  {
   bool     valid;          // les chiffres du trade sont calculés
   bool     allowed;        // tous les contrôles sont passés
   bool     hint;           // reason est une simple indication (outil non tracé)
   string   reason;         // première raison de blocage ou indication
   string   warn;           // avertissement : tes règles perso en mode Avertir
   int      dir;            // +1 achat, -1 vente, 0 indéterminé
   int      kind;           // 0 marché, 1 limite, 2 stop
   bool     limit;          // marché avec stop > max : ordre limite automatique
   int      tier;           // fonds propres : palier 1 à 4 ; prop firm : 0, 1 ou 2
   double   tierMult;       // prop firm : multiplicateur du palier
   double   budget;         // risque maximum de ce trade ($)
   double   capRef;         // taille du compte ou capital de référence ($)
   bool     micro;          // objectif de l'étape atteint : lot minimum seulement
   double   stopRaw;        // écart entrée -> stop au prix de l'outil (avant ordre limite)
   double   bid;
   double   ask;
   double   spread;         // ask - bid (prix)
   double   entry;          // prix d'entrée de l'ordre
   double   slEff;          // SL réellement envoyé
   double   stopDist;       // distance entrée -> SL (prix)
   double   valuePerPrice;  // valeur d'une unité de prix pour 1 lot ($)
   double   lossPerLot;     // perte d'un lot au SL, commission incluse ($)
   double   costPct;        // spread + commission en % du stop
   double   spreadPct;      // spread seul en % du stop
   double   lots;
   double   risk;           // risque réel après arrondi des lots ($)
   double   tp;             // TP réellement envoyé
   double   tpDist;         // distance entrée -> TP (prix)
   double   tpR;            // R net au TP
   double   tpGain;         // gain net au TP ($)
   double   marginPct;      // marge utilisée après le trade (% de l'équité)
   double   marginReq;      // marge demandée par ce trade ($)

   void Reset()
     {
      valid = false; allowed = false; hint = false; reason = ""; warn = "";
      dir = 0; kind = 0; limit = false; tier = 0; tierMult = 1.0; budget = 0.0; capRef = 0.0; micro = false;
      stopRaw = 0.0; bid = 0.0; ask = 0.0; spread = 0.0; entry = 0.0; slEff = 0.0; stopDist = 0.0;
      valuePerPrice = 0.0; lossPerLot = 0.0; costPct = 0.0; spreadPct = 0.0; lots = 0.0; risk = 0.0;
      tp = 0.0; tpDist = 0.0; tpR = 0.0; tpGain = 0.0; marginPct = 0.0; marginReq = 0.0;
     }
  };

// Limites du compte : celles du carnet, ou celles des paramètres sans le carnet
struct SLimits
  {
   bool     carnet;      // valeurs du carnet
   bool     own;         // fonds propres
   double   size;        // taille du compte (prop firm) ou capital de référence (fonds propres)
   double   budget;      // risque du trade avant le plafond de la marge restante ($)
   bool     reduced;     // risque réduit (compte sous le seuil)
   int      tier;
   double   tierMult;
   double   dayLimit;    // perte maximum du jour ($)
   double   dayFloor;    // équité sous laquelle la journée est finie
   double   floorUsd;    // plancher : perte maximum du compte ou plancher du capital
   int      maxTrades;
   int      maxLoss;
   int      weekLoss;    // pertes maximum de la semaine (0 = aucune limite)
   double   weekStop;    // perte maximum de la semaine en $ (0 = aucune limite)
   double   stopMin;
   double   stopMax;
   double   spreadMax;   // spread maximum en % du stop (0 = sans)
   double   goal;        // objectif de l'étape ($), 0 = aucun
   double   gain;        // avancement de l'étape ($)
   int      days;        // jours tradés dans l'étape
   int      minDays;     // jours minimum de l'étape
   int      phase;       // 1 phase 1, 2 phase 2, 3 financé, 4 échoué, 5 retiré, 0 inconnu
  };

// Ordre envoyé en attente de confirmation d'exécution
struct SPending
  {
   bool     active;
   ulong    order;
   datetime sent;
   int      dir;
   double   slEff;
   double   rCfg;
   bool     keepTp;      // TP choisi sur l'outil : il ne bouge pas après l'exécution
   double   riskPlanned;
   double   spread;
   double   tierMult;
   double   poc;
  };

// Ordre en attente (Limite, Stop, ou limite automatique quand le stop dépasse le maximum)
struct SLimit
  {
   bool     active;
   ulong    order;
   datetime placed;
   int      dir;
   int      kind;        // 1 limite, 2 stop
   int      minutes;     // validité (0 = jusqu'à annulation)
   double   slEff;
   double   rCfg;
   bool     keepTp;
   double   riskPlanned;
   double   spread;
   double   tierMult;
   double   poc;
  };

// Outil position tracé sur le graphique
struct STool
  {
   bool     on;
   int      dir;         // +1 long, -1 court
   int      kind;        // 0 marché, 1 limite, 2 stop
   double   entry;       // entrée des ordres Limite et Stop (en Marché, l'entrée suit le prix)
   double   sl;          // stop sur le graphique
   double   tp;          // objectif sur le graphique
   datetime t1;          // bord gauche
   int      bars;        // largeur en bougies
   datetime created;     // bougie où l'outil a été tracé (flèche)
   bool     raw;         // stop d'un signal du Carnet : déjà décalé du spread
   long     sig;         // signal du Carnet chargé (référence), 0 sinon
   int      setup;
   datetime sigExp;      // fin de validité du signal (heure locale)
  };

// Valeurs transmises par l'EA Carnet XAU
struct SCarnet
  {
   bool     on;
   bool     own;
   double   risk;
   double   riskred;
   double   redat;
   double   contract;
   double   spreadmax;
   double   stopmin;
   double   stopmax;
   double   daylim;
   double   size;
   double   floorUsd;
   double   goal;
   double   gain;
   double   wkstopr;
   int      maxtrades;
   int      maxloss;
   int      phase;
   int      days;
   int      mindays;
   int      wkloss;
   datetime at;          // dernière lecture du carnet par l'EA Carnet (heure locale)
  };

//=== Variables globales =============================================
CTrade   g_trade;
SLimit   g_limit;
SCalc    g_calc;
SPending g_pending;
STool    g_tool;
SCarnet  g_cx;

double   g_rTarget         = 0.0;   // objectif par défaut de l'outil (R)
double   g_riskUser        = 0.0;   // risque saisi dans le ticket ($), 0 = celui du carnet
double   g_lotsUser        = 0.0;   // lot saisi dans le ticket, 0 = calculé
int      g_riskPct         = 0;     // 1 : risque affiché en % du compte
string   g_editing         = "";    // champ du ticket en cours de saisie (pas réécrit)
string   g_lastSymbol      = "";
bool     g_loaded          = false;

datetime g_dayStart        = 0;
double   g_dayRef          = 0.0; // max(balance, équité) à la remise à zéro
bool     g_dayRefEstimated = false;
int      g_tradesToday     = 0;
int      g_lossesToday     = 0;
double   g_dayPnlClosed    = 0.0; // résultat des trades fermés depuis la remise à zéro
int      g_lossesWeek      = 0;
double   g_weekPnlClosed   = 0.0; // résultat des trades fermés depuis lundi
double   g_monthPnlClosed  = 0.0; // résultat des trades fermés depuis le 1er du mois
int      g_openPositions   = 0;
bool     g_openWithoutSL   = false;
double   g_openRiskToSL    = 0.0; // perte supplémentaire si les positions touchent leur SL (<= 0)
bool     g_statsDirty      = true;
uint     g_lastStatsTick   = 0;

datetime g_newsTimes[];
string   g_newsNames[];
bool     g_calendarOk      = false;
uint     g_lastNewsTick    = 0;

uint     g_lastRenderTick  = 0;
string   g_flashText       = "";
uint     g_flashUntil      = 0;
uint     g_lastExecTick    = 0;

// Pont avec l'EA Carnet XAU
struct SSignal
  {
   long     ref;     // référence du signal (heure de sa bougie)
   int      dir;
   double   entry;
   double   sl;
   double   tp;
   bool     limit;
   int      setup;
   datetime expires; // heure locale
  };
SSignal  g_sig;
uint     g_lastBridge      = 0;

// Gestion automatique et statistiques
bool     g_autoOn          = true;
int      g_atrHandle       = INVALID_HANDLE;
datetime g_lastAutoBar     = 0;
uint     g_lastKeyE        = 0;
string   g_statsTxt        = "Stats : -";
uint     g_lastStatsCalc   = 0;

// Souris : déplacement des poignées de l'outil
int      g_drag            = DRAG_NONE;
bool     g_mouseDown       = false;
bool     g_scrollWas       = true;
int      g_dragX0          = 0;
int      g_dragY0          = 0;
double   g_dragP0          = 0.0;
datetime g_dragT0          = 0;
double   g_dragEn0         = 0.0;
double   g_dragSl0         = 0.0;
double   g_dragTp0         = 0.0;
datetime g_dragT10         = 0;
int      g_dragKind0       = 0;
uint     g_lastDragTick    = 0;

// Mise en page : largeur d'un caractère mesurée, pour ne jamais déborder du cadre
int      g_charPx          = 7;
int      g_fs              = 9;     // taille du texte du ticket (points)
int      g_panInner        = 300;
int      g_colL            = 170;
int      g_colR            = 130;
int      g_panX            = 0;
int      g_panY            = 0;
int      g_panW            = 0;
int      g_panH            = 0;
int      g_tabW            = 100;
int      g_tabY            = 0;
double   g_scale           = 1.0;
double   g_k               = 1.0;   // échelle de l'écran : DPI / 96 (Windows à 125 % = 1,25)

//+------------------------------------------------------------------+
//| Outils : prix, lots, texte                                       |
//+------------------------------------------------------------------+
double TickSize()
  {
   double ts = SymbolInfoDouble(_Symbol, SYMBOL_TRADE_TICK_SIZE);
   if(ts <= 0.0)
      ts = SymbolInfoDouble(_Symbol, SYMBOL_POINT);
   return ts;
  }

// Arrondi au tick : mode +1 vers le haut, -1 vers le bas, 0 au plus proche
double RoundToTick(const double price, const int mode)
  {
   double ts = TickSize();
   if(ts <= 0.0)
      return NormalizeDouble(price, _Digits);
   double n = price / ts;
   double k = MathRound(n);
   if(mode > 0)
      k = MathCeil(n - 1e-7);
   else if(mode < 0)
      k = MathFloor(n + 1e-7);
   return NormalizeDouble(k * ts, _Digits);
  }

int LotDigits()
  {
   double step = SymbolInfoDouble(_Symbol, SYMBOL_VOLUME_STEP);
   if(step <= 0.0)
      return 2;
   return (int)MathMax(0.0, MathCeil(-MathLog10(step) - 1e-9));
  }

// Lots arrondis vers le bas au pas du symbole, plafonnés au maximum
double NormalizeLotsDown(const double lots)
  {
   double step = SymbolInfoDouble(_Symbol, SYMBOL_VOLUME_STEP);
   double vmax = SymbolInfoDouble(_Symbol, SYMBOL_VOLUME_MAX);
   if(step <= 0.0)
      step = 0.01;
   double v = MathFloor(lots / step + 1e-7) * step;
   if(vmax > 0.0 && v > vmax)
      v = MathFloor(vmax / step + 1e-7) * step;
   if(v < 0.0)
      v = 0.0;
   return NormalizeDouble(v, LotDigits());
  }

string Px(const double price)       { return DoubleToString(price, _Digits); }

// Pixels à l'échelle de l'écran : le texte (en points) grandit avec la mise à l'échelle de Windows,
// les cadres (en pixels) doivent grandir autant, sinon le texte déborde
int Dp(const double px)             { return (int)MathRound(px * g_k); }
string Usd(const double v)          { return DoubleToString(v, 2) + " $"; }
string SignedUsd(const double v)    { return (v >= 0.0 ? "+" : "") + DoubleToString(v, 2) + " $"; }
string LotsStr(const double v)      { return DoubleToString(v, LotDigits()); }

// Nombre au format français pour le CSV (virgule décimale)
string Num(const double v, const int digits)
  {
   string s = DoubleToString(v, digits);
   StringReplace(s, ".", ",");
   return s;
  }

string Truncate(const string s, const int maxLen)
  {
   if(StringLen(s) <= maxLen)
      return s;
   return StringSubstr(s, 0, maxLen - 3) + "...";
  }

// Lit un nombre saisi (accepte la virgule), 0 si vide ou invalide
double ParseNumber(string text)
  {
   StringReplace(text, ",", ".");
   StringReplace(text, " ", "");
   StringTrimLeft(text);
   StringTrimRight(text);
   if(StringLen(text) == 0)
      return 0.0;
   double v = StringToDouble(text);
   return (v > 0.0) ? v : 0.0;
  }

void Block(string &block, const string msg)
  {
   if(block == "")
      block = msg;
  }

void Flash(const string text, const uint ms = 4000)
  {
   g_flashText  = text;
   g_flashUntil = GetTickCount() + ms;
   Print(TAG, text);
  }

void AddUnique(ulong &arr[], const ulong v)
  {
   int n = ArraySize(arr);
   for(int i = 0; i < n; i++)
      if(arr[i] == v)
         return;
   ArrayResize(arr, n + 1);
   arr[n] = v;
  }

ENUM_ORDER_TYPE_FILLING SymbolFilling()
  {
   int mode = (int)SymbolInfoInteger(_Symbol, SYMBOL_FILLING_MODE);
   if((mode & SYMBOL_FILLING_FOK) == SYMBOL_FILLING_FOK)
      return ORDER_FILLING_FOK;
   if((mode & SYMBOL_FILLING_IOC) == SYMBOL_FILLING_IOC)
      return ORDER_FILLING_IOC;
   return ORDER_FILLING_RETURN;
  }

bool IsHedging()
  {
   return (AccountInfoInteger(ACCOUNT_MARGIN_MODE) == ACCOUNT_MARGIN_MODE_RETAIL_HEDGING);
  }

// Sélectionne la position d'identifiant posId et renvoie son ticket (0 si fermée)
ulong SelectPositionById(const ulong posId)
  {
   int total = PositionsTotal();
   for(int i = 0; i < total; i++)
     {
      ulong ticket = PositionGetTicket(i);
      if(ticket == 0)
         continue;
      if((ulong)PositionGetInteger(POSITION_IDENTIFIER) == posId)
         return ticket;
     }
   return 0;
  }

//+------------------------------------------------------------------+
//| Registre des trades (variables globales du terminal)             |
//+------------------------------------------------------------------+
string GvBase()                                   { return GV_PREFIX + IntegerToString(AccountInfoInteger(ACCOUNT_LOGIN)) + "_"; }
string GvName(const ulong posId, const string key) { return GvBase() + IntegerToString((long)posId) + "_" + key; }
bool   GvHas(const ulong posId, const string key)  { return GlobalVariableCheck(GvName(posId, key)); }

void GvSet(const ulong posId, const string key, const double v)
  {
   GlobalVariableSet(GvName(posId, key), v);
  }

double GvGet(const ulong posId, const string key, const double def = 0.0)
  {
   string n = GvName(posId, key);
   if(GlobalVariableCheck(n))
      return GlobalVariableGet(n);
   return def;
  }

// Journalise les trades du registre fermés mais pas encore écrits
void ScanRegistry()
  {
   string base  = GvBase();
   int    bLen  = StringLen(base);
   int    total = GlobalVariablesTotal();
   ulong  ids[];
   ArrayResize(ids, 0);
   for(int i = 0; i < total; i++)
     {
      string name = GlobalVariableName(i);
      if(StringFind(name, base) != 0)
         continue;
      int n = StringLen(name);
      if(n <= bLen + 5 || StringSubstr(name, n - 5) != "_risk")
         continue;
      ulong id = (ulong)StringToInteger(StringSubstr(name, bLen, n - bLen - 5));
      if(id > 0)
         AddUnique(ids, id);
     }
   for(int k = 0; k < ArraySize(ids); k++)
     {
      if(GvHas(ids[k], "done"))
         continue;
      if(SelectPositionById(ids[k]) != 0)
         continue;
      JournalClosed(ids[k]);
     }
  }

// Supprime les entrées journalisées depuis plus de 30 jours
void CleanupRegistry()
  {
   string   base  = GvBase();
   datetime limit = TimeCurrent() - 30 * 86400;
   int      total = GlobalVariablesTotal();
   string   prefixes[];
   ArrayResize(prefixes, 0);
   for(int i = 0; i < total; i++)
     {
      string name = GlobalVariableName(i);
      if(StringFind(name, base) != 0)
         continue;
      int n = StringLen(name);
      if(n > 5 && StringSubstr(name, n - 5) == "_done" && GlobalVariableTime(name) < limit)
        {
         int m = ArraySize(prefixes);
         ArrayResize(prefixes, m + 1);
         prefixes[m] = StringSubstr(name, 0, n - 4); // garde "CNlogin_position_"
        }
     }
   for(int k = 0; k < ArraySize(prefixes); k++)
      GlobalVariablesDeleteAll(prefixes[k]);
  }

//+------------------------------------------------------------------+
//| Statistiques du jour                                             |
//+------------------------------------------------------------------+
datetime DayStartFor(const datetime now)
  {
   MqlDateTime dt;
   TimeToStruct(now, dt);
   dt.hour = InpResetHourServer;
   dt.min  = 0;
   dt.sec  = 0;
   datetime t = StructToTime(dt);
   if(t > now)
      t -= 86400;
   return t;
  }

// Début de la semaine de trading : lundi, à l'heure de remise à zéro
datetime WeekStartFor(const datetime now)
  {
   datetime d   = DayStartFor(now);
   datetime ref = (InpResetHourServer >= 12) ? d + 86400 : d; // journée qui commence la veille au soir
   MqlDateTime dt;
   TimeToStruct(ref, dt);
   int back = (dt.day_of_week + 6) % 7; // lundi = 0
   return d - (datetime)(back * 86400);
  }

datetime MonthStartFor(const datetime now)
  {
   MqlDateTime dt;
   TimeToStruct(now, dt);
   dt.day  = 1;
   dt.hour = 0;
   dt.min  = 0;
   dt.sec  = 0;
   datetime t = StructToTime(dt) + InpResetHourServer * 3600;
   if(InpResetHourServer >= 12)
      t -= 86400; // la journée du 1er commence la veille au soir
   return t;
  }

// Résultat net et nombre de pertes des positions fermées depuis « from »
void ClosedSince(const datetime from, double &pnl, int &losses)
  {
   pnl    = 0.0;
   losses = 0;
   ulong closed[];
   ArrayResize(closed, 0);
   if(!HistorySelect(from, TimeTradeServer() + 86400))
      return;
   int total = HistoryDealsTotal();
   for(int i = 0; i < total; i++)
     {
      ulong d = HistoryDealGetTicket(i);
      if(d == 0)
         continue;
      long dtype = HistoryDealGetInteger(d, DEAL_TYPE);
      if(dtype != DEAL_TYPE_BUY && dtype != DEAL_TYPE_SELL)
         continue;
      if((datetime)HistoryDealGetInteger(d, DEAL_TIME) < from)
         continue;
      long de = HistoryDealGetInteger(d, DEAL_ENTRY);
      if(de == DEAL_ENTRY_OUT || de == DEAL_ENTRY_OUT_BY || de == DEAL_ENTRY_INOUT)
         AddUnique(closed, (ulong)HistoryDealGetInteger(d, DEAL_POSITION_ID));
     }
   for(int k = 0; k < ArraySize(closed); k++)
     {
      if(SelectPositionById(closed[k]) != 0)
         continue;
      double net  = PositionNetFromHistory(closed[k]);
      double risk = GvGet(closed[k], "risk", 0.0);
      pnl += net;
      if((risk > 0.0) ? (net < -InpLossThresholdR * risk) : (net < 0.0))
         losses++;
     }
  }

// Risque du palier pour un capital donné (fonds propres) ; tier = 1 à 4
double RiskForCapital(const double cap, int &tier)
  {
   if(cap < InpTier1Max)
     {
      tier = 1;
      // Sous le capital de départ, le risque baisse avec le capital ; au-dessus, il reste à départ / N
      return MathMin(cap, InpStartCapital) / (double)MathMax(1, InpLossBudget);
     }
   if(cap < InpTier2Max)
     {
      tier = 2;
      return cap * InpTier2Pct / 100.0;
     }
   if(cap < InpTier3Max)
     {
      tier = 3;
      return cap * InpTier3Pct / 100.0;
     }
   tier = 4;
   return cap * InpTier4Pct / 100.0;
  }

void SaveDayRef()
  {
   GlobalVariableSet(GvBase() + "daykey", (double)g_dayStart);
   GlobalVariableSet(GvBase() + "dayref", g_dayRef);
   GlobalVariableSet(GvBase() + "dayest", g_dayRefEstimated ? 1.0 : 0.0);
  }

// Balance au début de la journée = balance actuelle - opérations depuis
double EstimateBalanceAt(const datetime fromTime, bool &hadDeals)
  {
   hadDeals = false;
   double balance = AccountInfoDouble(ACCOUNT_BALANCE);
   if(!HistorySelect(fromTime, TimeTradeServer() + 86400))
      return balance;
   double sum   = 0.0;
   int    total = HistoryDealsTotal();
   for(int i = 0; i < total; i++)
     {
      ulong d = HistoryDealGetTicket(i);
      if(d == 0)
         continue;
      if((datetime)HistoryDealGetInteger(d, DEAL_TIME) < fromTime)
         continue;
      long dtype = HistoryDealGetInteger(d, DEAL_TYPE);
      if(dtype == DEAL_TYPE_BALANCE || dtype == DEAL_TYPE_CREDIT || dtype == DEAL_TYPE_BONUS)
         continue;   // dépôt (compte financé aujourd'hui), retrait de gains, crédit : la référence du jour reste le solde
      sum += HistoryDealGetDouble(d, DEAL_PROFIT) + HistoryDealGetDouble(d, DEAL_COMMISSION)
           + HistoryDealGetDouble(d, DEAL_SWAP)   + HistoryDealGetDouble(d, DEAL_FEE);
      hadDeals = true;
     }
   return balance - sum;
  }

double PositionNetFromHistory(const ulong posId)
  {
   if(!HistorySelectByPosition(posId))
      return 0.0;
   double net   = 0.0;
   int    total = HistoryDealsTotal();
   for(int i = 0; i < total; i++)
     {
      ulong d = HistoryDealGetTicket(i);
      if(d == 0)
         continue;
      net += HistoryDealGetDouble(d, DEAL_PROFIT) + HistoryDealGetDouble(d, DEAL_COMMISSION)
           + HistoryDealGetDouble(d, DEAL_SWAP)   + HistoryDealGetDouble(d, DEAL_FEE);
     }
   return net;
  }

void RefreshDayStats()
  {
   datetime now      = TimeTradeServer();
   datetime dayStart = DayStartFor(now);

   //--- Référence du jour, comme la firme : max(balance, équité) à la remise à zéro
   if(dayStart != g_dayStart)
     {
      bool liveCross = (g_dayStart != 0);
      g_dayStart = dayStart;
      string keyName = GvBase() + "daykey";
      string refName = GvBase() + "dayref";
      string estName = GvBase() + "dayest";

      if(GlobalVariableCheck(keyName) && GlobalVariableCheck(refName) &&
         (datetime)GlobalVariableGet(keyName) == dayStart)
        {
         g_dayRef          = GlobalVariableGet(refName);
         g_dayRefEstimated = (GlobalVariableCheck(estName) && GlobalVariableGet(estName) > 0.5);
        }
      else if(liveCross)
        {
         g_dayRef          = MathMax(AccountInfoDouble(ACCOUNT_BALANCE), AccountInfoDouble(ACCOUNT_EQUITY));
         g_dayRefEstimated = false;
         SaveDayRef();
        }
      else
        {
         bool   hadDeals = false;
         double balStart = EstimateBalanceAt(dayStart, hadDeals);
         g_dayRefEstimated = (hadDeals || PositionsTotal() > 0);
         g_dayRef = g_dayRefEstimated ? balStart : MathMax(balStart, AccountInfoDouble(ACCOUNT_EQUITY));
         SaveDayRef();
        }
     }

   //--- Trades ouverts et positions fermées depuis la remise à zéro
   ulong opened[];
   ulong closed[];
   ArrayResize(opened, 0);
   ArrayResize(closed, 0);
   if(HistorySelect(dayStart, now + 86400))
     {
      int total = HistoryDealsTotal();
      for(int i = 0; i < total; i++)
        {
         ulong d = HistoryDealGetTicket(i);
         if(d == 0)
            continue;
         long dtype = HistoryDealGetInteger(d, DEAL_TYPE);
         if(dtype != DEAL_TYPE_BUY && dtype != DEAL_TYPE_SELL)
            continue;
         if((datetime)HistoryDealGetInteger(d, DEAL_TIME) < dayStart)
            continue;
         long  dentry = HistoryDealGetInteger(d, DEAL_ENTRY);
         ulong pos    = (ulong)HistoryDealGetInteger(d, DEAL_POSITION_ID);
         if(dentry == DEAL_ENTRY_IN)
            AddUnique(opened, pos);
         else if(dentry == DEAL_ENTRY_OUT || dentry == DEAL_ENTRY_OUT_BY || dentry == DEAL_ENTRY_INOUT)
            AddUnique(closed, pos);
        }
     }
   g_tradesToday = ArraySize(opened);

   //--- Pertes : trades fermés sous -X R (un break-even ne compte pas)
   int    losses = 0;
   double dayPnl = 0.0;
   for(int k = 0; k < ArraySize(closed); k++)
     {
      if(SelectPositionById(closed[k]) != 0)
         continue; // encore partiellement ouverte
      double net  = PositionNetFromHistory(closed[k]);
      double risk = GvGet(closed[k], "risk", 0.0);
      bool   loss = (risk > 0.0) ? (net < -InpLossThresholdR * risk) : (net < 0.0);
      dayPnl += net;
      if(loss)
         losses++;
     }
   g_lossesToday  = losses;
   g_dayPnlClosed = dayPnl;

   //--- Semaine (depuis lundi) et mois (depuis le 1er), positions fermées seulement :
   //    recalculés après un trade, sinon une fois par minute
   static uint lastLong = 0;
   if(g_statsDirty || lastLong == 0 || GetTickCount() - lastLong > 60000)
     {
      lastLong = GetTickCount();
      int lw = 0, lm = 0;
      ClosedSince(WeekStartFor(now), g_weekPnlClosed, lw);
      ClosedSince(MonthStartFor(now), g_monthPnlClosed, lm);
      g_lossesWeek = lw;
     }

   g_statsDirty    = false;
   g_lastStatsTick = GetTickCount();
  }

// Positions ouvertes : nombre, SL manquant, perte supplémentaire jusqu'aux SL
void ScanOpenPositions()
  {
   g_openPositions = 0;
   g_openWithoutSL = false;
   g_openRiskToSL  = 0.0;
   int total = PositionsTotal();
   for(int i = 0; i < total; i++)
     {
      ulong ticket = PositionGetTicket(i);
      if(ticket == 0)
         continue;
      g_openPositions++;
      double sl = PositionGetDouble(POSITION_SL);
      if(sl <= 0.0)
        {
         g_openWithoutSL = true;
         continue;
        }
      string sym = PositionGetString(POSITION_SYMBOL);
      double vol = PositionGetDouble(POSITION_VOLUME);
      double cur = PositionGetDouble(POSITION_PRICE_CURRENT);
      ENUM_ORDER_TYPE otype = (PositionGetInteger(POSITION_TYPE) == POSITION_TYPE_BUY) ? ORDER_TYPE_BUY : ORDER_TYPE_SELL;
      double p = 0.0;
      if(OrderCalcProfit(otype, sym, vol, cur, sl, p))
         g_openRiskToSL += MathMin(0.0, p);
     }
  }

//+------------------------------------------------------------------+
//| Filtre news (calendrier économique MT5, heure serveur)           |
//+------------------------------------------------------------------+
void RefreshNews()
  {
   ArrayResize(g_newsTimes, 0);
   ArrayResize(g_newsNames, 0);
   g_calendarOk   = false;
   g_lastNewsTick = GetTickCount();
   if(!InpNewsFilter || MQLInfoInteger(MQL_TESTER))
      return;

   datetime now = TimeTradeServer();
   string   parts[];
   int      n = StringSplit(InpNewsCurrencies, ',', parts);
   for(int k = 0; k < n; k++)
     {
      string ccy = parts[k];
      StringTrimLeft(ccy);
      StringTrimRight(ccy);
      StringToUpper(ccy);
      if(StringLen(ccy) == 0)
         continue;

      MqlCalendarValue values[];
      ResetLastError();
      bool got = (bool)CalendarValueHistory(values, now - 3600, now + 86400, NULL, ccy);
      int  cnt = ArraySize(values);
      if(!got && cnt == 0 && GetLastError() != 0)
         continue;
      g_calendarOk = true;

      for(int i = 0; i < cnt; i++)
        {
         MqlCalendarEvent ev;
         if(!CalendarEventById(values[i].event_id, ev))
            continue;
         if(ev.importance != CALENDAR_IMPORTANCE_HIGH)
            continue;
         int m = ArraySize(g_newsTimes);
         ArrayResize(g_newsTimes, m + 1);
         ArrayResize(g_newsNames, m + 1);
         g_newsTimes[m] = values[i].time;
         g_newsNames[m] = ccy + " " + ev.name;
        }
     }
  }

// true si une annonce forte est dans la fenêtre ; sinon name/when = prochaine annonce
bool NewsCheck(const datetime now, string &name, datetime &when, bool &inWindow)
  {
   inWindow = false;
   name     = "";
   when     = 0;
   long win = (long)(InpNewsWindowMin > 0 ? InpNewsWindowMin : 0) * 60;
   int  best = -1;
   int  n = ArraySize(g_newsTimes);
   for(int i = 0; i < n; i++)
     {
      long diff  = (long)g_newsTimes[i] - (long)now;
      long adiff = (diff < 0) ? -diff : diff;
      if(adiff <= win)
        {
         inWindow = true;
         name     = g_newsNames[i];
         when     = g_newsTimes[i];
         return true;
        }
      if(diff > 0 && (best < 0 || g_newsTimes[i] < g_newsTimes[best]))
         best = i;
     }
   if(best >= 0)
     {
      name = g_newsNames[best];
      when = g_newsTimes[best];
     }
   return false;
  }

//+------------------------------------------------------------------+
//| Pont avec l'EA Carnet XAU : variables globales du terminal       |
//+------------------------------------------------------------------+
string Bridge(const string key)
  {
   return "CXB_" + IntegerToString(AccountInfoInteger(ACCOUNT_LOGIN)) + "_" + key;
  }

// Lit le dernier signal publié par le Carnet ; true s'il est nouveau
bool ReadSignal()
  {
   string n = Bridge("sig_ref");
   if(!GlobalVariableCheck(n))
      return false;
   long ref = (long)GlobalVariableGet(n);
   if(ref <= 0 || ref == g_sig.ref)
      return false;
   g_sig.ref     = ref;
   g_sig.dir     = (int)GlobalVariableGet(Bridge("sig_dir"));
   g_sig.entry   = GlobalVariableGet(Bridge("sig_entry"));
   g_sig.sl      = GlobalVariableGet(Bridge("sig_sl"));
   g_sig.tp      = GlobalVariableGet(Bridge("sig_tp"));
   g_sig.limit   = (GlobalVariableGet(Bridge("sig_limit")) > 0.5);
   g_sig.setup   = (int)GlobalVariableGet(Bridge("sig_setup"));
   g_sig.expires = (datetime)(long)GlobalVariableGet(Bridge("sig_exp"));
   if((long)GlobalVariableGet(n) != ref)
     {
      g_sig.ref = 0; // le Carnet publiait pendant la lecture : relu au prochain passage
      return false;
     }
   return true;
  }

bool SignalValid()
  {
   return g_sig.ref > 0 && g_sig.dir != 0 && g_sig.sl > 0.0 && TimeLocal() < g_sig.expires;
  }

//+------------------------------------------------------------------+
//| Valeurs du carnet transmises par l'EA Carnet XAU                  |
//+------------------------------------------------------------------+
double CxGet(const string key, const double def)
  {
   string n = Bridge(key);
   return GlobalVariableCheck(n) ? GlobalVariableGet(n) : def;
  }

// Lit les valeurs du carnet ; on = false si l'EA Carnet XAU ne tourne pas (signe de vie de plus de 3 min)
void ReadCarnet()
  {
   g_cx.on = false;
   string n = Bridge("cx_on");
   if(!GlobalVariableCheck(n) || (long)TimeLocal() - (long)GlobalVariableGet(n) > 180)
      return;
   g_cx.risk      = CxGet("cx_risk", 0.0);
   g_cx.riskred   = CxGet("cx_riskred", 0.0);
   g_cx.redat     = CxGet("cx_redat", 1e7);
   g_cx.contract  = CxGet("cx_contract", 100.0);
   g_cx.maxtrades = (int)CxGet("cx_maxtrades", 3.0);
   g_cx.maxloss   = (int)CxGet("cx_maxloss", 2.0);
   g_cx.spreadmax = CxGet("cx_spreadmax", 0.0);
   g_cx.stopmin   = CxGet("cx_stopmin", InpStopMin);
   g_cx.stopmax   = CxGet("cx_stopmax", InpStopMax);
   g_cx.daylim    = CxGet("cx_daylim", 0.0);
   g_cx.size      = CxGet("cx_size", 0.0);
   g_cx.floorUsd     = CxGet("cx_floor", 0.0);
   g_cx.phase     = (int)CxGet("cx_phase", 0.0);
   g_cx.goal      = CxGet("cx_goal", 0.0);
   g_cx.gain      = CxGet("cx_gain", 0.0);
   g_cx.days      = (int)CxGet("cx_days", 0.0);
   g_cx.mindays   = (int)CxGet("cx_mindays", 0.0);
   g_cx.wkloss    = (int)CxGet("cx_wkloss", 0.0);
   g_cx.wkstopr   = CxGet("cx_wkstopr", 0.0);
   g_cx.own       = (CxGet("cx_own", 0.0) > 0.5);
   g_cx.at        = (datetime)(long)CxGet("cx_at", 0.0);
   g_cx.on        = (g_cx.size > 0.0 && g_cx.risk > 0.0 && g_cx.stopmax > g_cx.stopmin);
  }

string PhaseName(const int phase)
  {
   if(phase == 1)
      return "phase 1";
   if(phase == 2)
      return "phase 2";
   if(phase == 3)
      return "financé";
   if(phase == 4)
      return "échoué";
   if(phase == 5)
      return "retiré";
   return "étape inconnue";
  }

// Limites du compte : celles du carnet (EA Carnet XAU relié) ou celles des paramètres.
// false : le carnet est demandé mais l'EA Carnet XAU ne transmet rien (envoi bloqué).
bool GetLimits(SLimits &l)
  {
   double balance = AccountInfoDouble(ACCOUNT_BALANCE);
   double equity  = AccountInfoDouble(ACCOUNT_EQUITY);
   double ref     = (g_dayRef > 0.0) ? g_dayRef : MathMin(balance, equity);
   l.carnet    = false;
   l.own       = false;
   l.reduced   = false;
   l.tier      = 0;
   l.tierMult  = 1.0;
   l.weekLoss  = 0;
   l.weekStop  = 0.0;
   l.spreadMax = 0.0;
   l.goal      = 0.0;
   l.gain      = 0.0;
   l.days      = 0;
   l.minDays   = 0;
   l.phase     = 0;
   l.stopMin   = InpStopMin;
   l.stopMax   = InpStopMax;

   if(InpUseCarnet)
     {
      if(!g_cx.on)
        {
         // Valeurs neutres pour l'affichage : l'envoi est bloqué tant que le carnet n'est pas relié
         l.size      = ref;
         l.budget    = 0.0;
         l.dayLimit  = 0.0;
         l.dayFloor  = 0.0;
         l.floorUsd     = 0.0;
         l.maxTrades = 0;
         l.maxLoss   = 0;
         return false;
        }
      l.carnet    = true;
      l.own       = g_cx.own;
      l.size      = g_cx.size;
      // Prop firm : risque réduit quand le compte du matin est à -seuil de la taille (comme l'onglet Jour)
      l.reduced   = (!l.own && ref - l.size <= -g_cx.redat);
      l.budget    = l.reduced ? g_cx.riskred : g_cx.risk;
      l.tier      = l.reduced ? 1 : 0;
      l.tierMult  = (g_cx.risk > 0.0) ? l.budget / g_cx.risk : 1.0;
      l.dayLimit  = g_cx.daylim;
      l.dayFloor  = g_dayRef - l.dayLimit;
      l.floorUsd     = g_cx.floorUsd;
      l.maxTrades = g_cx.maxtrades;
      l.maxLoss   = g_cx.maxloss;
      l.stopMin   = g_cx.stopmin;
      l.stopMax   = g_cx.stopmax;
      l.spreadMax = g_cx.spreadmax;
      if(l.own)
        {
         l.weekLoss = g_cx.wkloss;
         l.weekStop = g_cx.wkstopr * l.budget;
        }
      else
        {
         l.goal = g_cx.goal;
         l.gain = balance - l.size;
        }
      l.days    = g_cx.days;
      l.minDays = g_cx.mindays;
      l.phase   = g_cx.phase;
      return true;
     }

   //--- Sans le carnet : modèles des paramètres
   l.maxTrades = InpMaxTradesDay;
   l.maxLoss   = InpMaxLossesDay;
   if(InpMode == MODE_FONDS)
     {
      //--- Palier selon le capital du début de la journée : risque stable dans la journée
      int tierNo = 1;
      l.own      = true;
      l.size     = ref;
      l.budget   = RiskForCapital(ref, tierNo);
      l.tier     = tierNo;
      l.tierMult = (ref > 0.0) ? l.budget / ref : 0.0;
      l.dayLimit = InpDayStopR * l.budget;
      l.floorUsd    = InpFloorUsd;
      l.weekLoss = InpMaxLossesWeek;
      l.weekStop = InpWeekStopR * l.budget;
     }
   else
     {
      //--- Prop firm : risque fixe, réduit quand l'équité baisse sous le capital initial
      double initial = InpInitialBalance;
      double lowest  = MathMin(balance, equity);
      double ddPct   = (initial > 0.0) ? (initial - lowest) / initial * 100.0 : 0.0;
      if(ddPct >= InpTier2DDPct)
        {
         l.tier     = 2;
         l.tierMult = InpTier2Mult;
        }
      else if(ddPct >= InpTier1DDPct)
        {
         l.tier     = 1;
         l.tierMult = InpTier1Mult;
        }
      l.reduced  = (l.tier > 0);
      l.size     = initial;
      l.budget   = InpPropRiskUsd * l.tierMult;
      l.dayLimit = MathMin(InpDayStopR * l.budget, InpFirmDailyPct / 100.0 * initial);
      l.floorUsd    = initial * (1.0 - InpFirmMaxPct / 100.0);
     }
   l.dayFloor = g_dayRef - l.dayLimit;
   return true;
  }

// État publié pour le Carnet : 0 prêt, 1 pertes du jour, 2 perte du jour, 3 pertes de la semaine,
// 4 perte de la semaine, 5 plancher, 6 objectif de l'étape atteint, 7 compte échoué ou retiré,
// 8 trades du jour atteints
int BlockCode()
  {
   SLimits l;
   if(!GetLimits(l))
      return 0;
   double equity  = AccountInfoDouble(ACCOUNT_EQUITY);
   double weekPnl = g_weekPnlClosed + (equity - AccountInfoDouble(ACCOUNT_BALANCE));
   if(l.phase == 4 || l.phase == 5)
      return 7;
   if(equity <= l.floorUsd)
      return 5;
   if(g_lossesToday >= l.maxLoss)
      return 1;
   if(l.dayLimit > 0.0 && equity <= l.dayFloor)
      return 2;
   if(l.weekLoss > 0 && g_lossesWeek >= l.weekLoss)
      return 3;
   if(l.weekStop > 0.0 && weekPnl <= -l.weekStop)
      return 4;
   if(l.goal > 0.0 && l.gain >= l.goal && l.days >= l.minDays)
      return 6;
   if(l.carnet && g_tradesToday >= l.maxTrades)
      return 8;
   return 0;
  }

// Signe de vie, risque du palier et état, toutes les 2 secondes
void PublishBridge()
  {
   if(g_lastBridge != 0 && GetTickCount() - g_lastBridge < 2000)
      return;
   g_lastBridge = GetTickCount();
   GlobalVariableSet(Bridge("calc_risk"), g_calc.budget);
   GlobalVariableSet(Bridge("calc_block"), (double)BlockCode());
   GlobalVariableSet(Bridge("calc_on"), (double)TimeLocal());
  }

// C : trace l'outil depuis le dernier signal du Carnet (entrée, SL et TP du signal)
void LoadSignal()
  {
   ReadSignal();
   if(!SignalValid())
     {
      Flash("Aucun signal du Carnet XAU en cours");
      return;
     }
   if(!NewTool(g_sig.dir))
      return;
   g_tool.raw = true;               // SL du signal : déjà décalé du spread par le Carnet
   g_tool.sl  = g_sig.sl;
   if(g_sig.tp > 0.0)
      g_tool.tp = g_sig.tp;
   if(g_sig.limit && g_sig.entry > 0.0)
     {
      g_tool.kind  = 1;
      g_tool.entry = g_sig.entry;
     }
   g_tool.sig    = g_sig.ref;
   g_tool.setup  = g_sig.setup;
   g_tool.sigExp = g_sig.expires;
   SaveTool();
   Flash(StringFormat("Signal S%d %s chargé : vérifie l'outil, Espace pour envoyer", g_sig.setup,
                      (g_sig.dir > 0) ? "ACHAT" : "VENTE"), 5000);
  }

//+------------------------------------------------------------------+
//| Moteur de calcul                                                 |
//+------------------------------------------------------------------+
void ComputeCalc(SCalc &c)
  {
   c.Reset();
   string block = "";   // blocage dur : carnet, firme, broker ou terminal
   string soft  = "";   // avertissement : tes règles personnelles

   double balance = AccountInfoDouble(ACCOUNT_BALANCE);
   double equity  = AccountInfoDouble(ACCOUNT_EQUITY);

   //--- 1. Risque et limites du compte
   SLimits lim;
   bool linked = GetLimits(lim);
   c.tier     = lim.tier;
   c.tierMult = lim.tierMult;
   c.capRef   = lim.size;

   //--- 2. Contrôles du compte, indépendants de l'outil
   ScanOpenPositions();
   double weekPnl = g_weekPnlClosed + (equity - balance);
   long   symMode = SymbolInfoInteger(_Symbol, SYMBOL_TRADE_MODE);

   string   newsName = "";
   datetime newsTime = 0;
   bool     inNews   = false;
   if(InpNewsFilter)
      NewsCheck(TimeTradeServer(), newsName, newsTime, inNews);

   if(!linked)
      Block(block, "Carnet XAU non relié : lance l'EA Carnet XAU sur ce compte");
   if(!TerminalInfoInteger(TERMINAL_TRADE_ALLOWED) || !MQLInfoInteger(MQL_TRADE_ALLOWED))
      Block(block, "Algo Trading désactivé");
   if(!AccountInfoInteger(ACCOUNT_TRADE_ALLOWED) || !AccountInfoInteger(ACCOUNT_TRADE_EXPERT))
      Block(block, "Trading refusé par le compte");
   if(symMode == SYMBOL_TRADE_MODE_DISABLED || symMode == SYMBOL_TRADE_MODE_CLOSEONLY)
      Block(block, "Symbole non négociable");
   if(linked)
     {
      if(lim.phase == 4 || lim.phase == 5)
         Block(block, (lim.phase == 4) ? "Compte « échoué » dans le carnet : plus aucun trade"
                                       : "Compte « retiré » dans le carnet : plus aucun trade");
      if(g_lossesToday >= lim.maxLoss)
         Block(block, StringFormat("%d pertes aujourd'hui : arrêt jusqu'à demain", g_lossesToday));
      if(g_tradesToday >= lim.maxTrades)
        {
         if(lim.carnet)
            Block(block, StringFormat("%d trades aujourd'hui : limite du carnet atteinte", g_tradesToday));
         else
            Block(soft, StringFormat("%d trades aujourd'hui (max %d)", g_tradesToday, lim.maxTrades));
        }
      if(lim.dayLimit > 0.0 && equity <= lim.dayFloor)
         Block(block, "Perte maximum du jour atteinte (" + Usd(lim.dayLimit) + ") : arrêt jusqu'à demain");
      if(lim.weekLoss > 0 && g_lossesWeek >= lim.weekLoss)
         Block(block, StringFormat("%d pertes cette semaine : arrêt jusqu'à lundi", g_lossesWeek));
      if(lim.weekStop > 0.0 && weekPnl <= -lim.weekStop)
         Block(block, "Perte maximum de la semaine atteinte : arrêt jusqu'à lundi");
      if(equity <= lim.floorUsd)
         Block(block, lim.own ? "Plancher de " + Usd(lim.floorUsd) + " atteint : retour en démo" : "Plancher de perte maximum atteint");
      if(lim.goal > 0.0 && lim.gain >= lim.goal)
        {
         if(lim.days >= lim.minDays)
            Block(block, StringFormat("Objectif de l'étape atteint et %d/%d jours : demande l'étape suivante", lim.days, lim.minDays));
         else
            c.micro = true;      // jours minimum : micro-trades seulement
        }
     }
   if(g_limit.active)
      Block(block, "Ordre en attente (Q pour l'annuler)");
   if(g_openPositions >= InpMaxOpenPositions)
      Block(soft, StringFormat("%d position(s) déjà ouverte(s)", g_openPositions));
   if(g_openWithoutSL)
      Block(soft, "Une position ouverte n'a pas de SL");
   if(inNews)
      Block(soft, "News " + newsName + " à " + TimeToString(newsTime, TIME_MINUTES));
   c.warn = soft;

   //--- Risque du trade : jamais plus que la marge restante du jour et du compte
   //    (10 % de marge pour le glissement et le spread, comme l'onglet Jour du carnet)
   double room = equity + g_openRiskToSL - lim.floorUsd;
   if(lim.dayLimit > 0.0)
      room = MathMin(room, equity + g_openRiskToSL - lim.dayFloor);
   c.budget = MathMax(0.0, MathMin(lim.budget, 0.9 * room));

   //--- 3. Cotation
   MqlTick tick;
   if(!SymbolInfoTick(_Symbol, tick) || tick.bid <= 0.0 || tick.ask <= 0.0)
     {
      c.reason = (block != "") ? block : "Pas de cotation";
      return;
     }
   c.bid    = tick.bid;
   c.ask    = tick.ask;
   c.spread = tick.ask - tick.bid;

   //--- 4. Outil position : entrée, stop et objectif
   if(!g_tool.on)
     {
      c.hint   = (block == "");
      c.reason = (block != "") ? block : "Sell ou Buy (S / W) : trace l'outil position";
      return;
     }
   c.dir   = g_tool.dir;
   c.kind  = g_tool.kind;
   c.entry = (c.kind == 0) ? ((c.dir > 0) ? c.ask : c.bid) : RoundToTick(g_tool.entry, 0);
   if(c.kind == 1 && ((c.dir > 0 && c.entry >= c.ask) || (c.dir < 0 && c.entry <= c.bid)))
      Block(block, "Limite : le prix a déjà passé l'entrée (Marché, ou déplace l'entrée)");
   if(c.kind == 2 && ((c.dir > 0 && c.entry <= c.ask) || (c.dir < 0 && c.entry >= c.bid)))
      Block(block, "Stop : le prix a déjà passé l'entrée (Marché, ou déplace l'entrée)");

   // Vente : stop et objectif déclenchés sur l'ask ; le spread ajouté les fait toucher au niveau du graphique
   double adjSl = (c.dir < 0 && InpSellSpreadAdjust && !g_tool.raw) ? c.spread : 0.0;
   double adjTp = (c.dir < 0 && InpSellSpreadAdjust) ? c.spread : 0.0;
   c.slEff = (c.dir > 0) ? RoundToTick(g_tool.sl, -1) : RoundToTick(g_tool.sl + adjSl, 1);
   c.tp    = RoundToTick((c.dir > 0) ? g_tool.tp : g_tool.tp + adjTp, 0);
   if((c.dir > 0 && (c.slEff >= c.entry || c.tp <= c.entry)) || (c.dir < 0 && (c.slEff <= c.entry || c.tp >= c.entry)))
     {
      c.reason = (block != "") ? block : "Stop et objectif de part et d'autre de l'entrée";
      return;
     }
   if(g_tool.sig > 0 && TimeLocal() >= g_tool.sigExp)
      Block(block, "Signal du Carnet expiré : retrace l'outil");

   //--- Plage de stop (carnet) ; au Marché au-delà du maximum : ordre limite où le stop revient au maximum
   c.stopDist = MathAbs(c.entry - c.slEff);
   c.stopRaw  = c.stopDist;
   if(c.stopDist < lim.stopMin - 1e-9)
      Block(block, StringFormat("Stop %.2f $ : sous le minimum de %.2f $", c.stopDist, lim.stopMin));
   else if(c.stopDist > lim.stopMax + 1e-9)
     {
      if(c.kind == 0 && InpLimitOver)
        {
         c.limit    = true;
         c.entry    = (c.dir > 0) ? RoundToTick(c.slEff + lim.stopMax, -1) : RoundToTick(c.slEff - lim.stopMax, 1);
         c.stopDist = MathAbs(c.entry - c.slEff);
         if((c.dir > 0 && c.tp <= c.entry) || (c.dir < 0 && c.tp >= c.entry))
           {
            c.reason = (block != "") ? block : "Objectif trop proche de l'entrée de l'ordre limite";
            return;
           }
        }
      else
         Block(block, StringFormat("Stop %.2f $ : au-delà du maximum de %.2f $", c.stopDist, lim.stopMax));
     }

   ENUM_ORDER_TYPE otype = (c.dir > 0) ? ORDER_TYPE_BUY : ORDER_TYPE_SELL;
   double comm = MathMax(0.0, InpCommissionPerLot);

   //--- 5. Perte d'un lot au SL, calculée par MT5 (aucune hypothèse sur le contrat)
   double lossPrice = 0.0;
   double pr        = 0.0;
   if(OrderCalcProfit(otype, _Symbol, 1.0, c.entry, c.slEff, pr) && pr < 0.0)
      lossPrice = -pr;
   else
     {
      double tv = SymbolInfoDouble(_Symbol, SYMBOL_TRADE_TICK_VALUE_LOSS);
      if(tv <= 0.0)
         tv = SymbolInfoDouble(_Symbol, SYMBOL_TRADE_TICK_VALUE);
      double ts = TickSize();
      if(tv > 0.0 && ts > 0.0)
         lossPrice = c.stopDist / ts * tv;
     }
   if(lossPrice <= 0.0)
     {
      c.reason = (block != "") ? block : "Valeur du tick indisponible";
      return;
     }
   c.valuePerPrice = lossPrice / c.stopDist;
   c.lossPerLot    = lossPrice + comm;
   c.costPct       = (c.spread + comm / c.valuePerPrice) / c.stopDist * 100.0;
   c.spreadPct     = c.spread / c.stopDist * 100.0;

   //--- 6. Lots arrondis vers le bas : le risque ne dépasse jamais le budget ni le risque saisi
   double vmin = SymbolInfoDouble(_Symbol, SYMBOL_VOLUME_MIN);
   double want = c.budget;
   if(g_riskUser > 0.0)
      want = MathMin(want, g_riskUser);
   c.lots = NormalizeLotsDown(want / c.lossPerLot);
   if(g_lotsUser > 0.0)
      c.lots = MathMin(c.lots, NormalizeLotsDown(g_lotsUser));
   if(c.micro)
      c.lots = (vmin * c.lossPerLot <= c.budget) ? NormalizeLotsDown(vmin) : 0.0;
   c.risk = c.lots * c.lossPerLot;

   //--- 7. Objectif : R net de commission
   c.tpDist = MathAbs(c.tp - c.entry);
   double gtp = 0.0;
   if(OrderCalcProfit(otype, _Symbol, 1.0, c.entry, c.tp, gtp))
      c.tpR = (gtp - comm) / c.lossPerLot;
   else
      c.tpR = (c.tpDist * c.valuePerPrice - comm) / c.lossPerLot;
   c.tpGain = c.tpR * c.risk;

   //--- 8. Marge utilisée après le trade
   double marginNow = AccountInfoDouble(ACCOUNT_MARGIN);
   double mReq      = 0.0;
   if(c.lots > 0.0 && OrderCalcMargin(otype, _Symbol, c.lots, c.entry, mReq))
      c.marginReq = mReq;
   if(equity > 0.0)
      c.marginPct = (marginNow + c.marginReq) / equity * 100.0;

   c.valid = true;

   //--- 9. Contrôles du trade
   double stopsLvl = (double)SymbolInfoInteger(_Symbol, SYMBOL_TRADE_STOPS_LEVEL) * SymbolInfoDouble(_Symbol, SYMBOL_POINT);
   double worstEq  = equity + g_openRiskToSL - c.risk;
   bool   pending  = (c.kind != 0 || c.limit);
   double market   = (c.dir > 0) ? c.ask : c.bid;

   if((c.dir > 0 && symMode == SYMBOL_TRADE_MODE_SHORTONLY) || (c.dir < 0 && symMode == SYMBOL_TRADE_MODE_LONGONLY))
      Block(block, "Sens interdit sur ce symbole");
   if(!pending && ((c.dir > 0 && c.slEff >= c.bid) || (c.dir < 0 && c.slEff <= c.ask)))
      Block(block, "Stop trop proche du prix");
   if(c.tpR < InpMinR - 0.01)
      Block(block, StringFormat("Objectif à %.1fR : minimum %.1fR", c.tpR, InpMinR));
   if(c.costPct > InpMaxCostPct)
      Block(block, StringFormat("Coûts %.1f %% du stop (max %.1f %%)", c.costPct, InpMaxCostPct));
   if(lim.spreadMax > 0.0 && c.spreadPct > lim.spreadMax)
      Block(block, StringFormat("Spread %.0f %% du stop (max %.0f %% dans le carnet)", c.spreadPct, lim.spreadMax));
   if(c.lots <= 0.0 || c.lots < vmin)
      Block(block, c.micro ? "Micro-trade impossible : marge du jour insuffisante" : "Lot calculé sous le minimum de " + LotsStr(vmin));
   if(stopsLvl > 0.0 && !pending &&
      ((c.dir > 0 && (c.bid - c.slEff < stopsLvl || c.tp - c.bid < stopsLvl)) ||
       (c.dir < 0 && (c.slEff - c.ask < stopsLvl || c.ask - c.tp < stopsLvl))))
      Block(block, "SL ou TP trop proche (minimum du broker)");
   if(stopsLvl > 0.0 && pending && (MathAbs(market - c.entry) < stopsLvl || c.stopDist < stopsLvl || c.tpDist < stopsLvl))
      Block(block, "Ordre en attente trop proche du prix (minimum du broker)");
   if(c.marginPct > InpMaxMarginPct)
      Block(block, StringFormat("Marge %.0f %% après trade (max %.0f %%)", c.marginPct, InpMaxMarginPct));
   if(linked && lim.dayLimit > 0.0 && worstEq < lim.dayFloor - 0.01)
      Block(block, "SL touché = au-delà de la perte maximum du jour");
   if(linked && lim.weekStop > 0.0 && weekPnl + g_openRiskToSL - c.risk < -lim.weekStop - 0.01)
      Block(block, "SL touché = au-delà de la perte maximum de la semaine");
   if(linked && worstEq <= lim.floorUsd)
      Block(block, lim.own ? "SL touché = sous le plancher de " + Usd(lim.floorUsd) : "SL touché = plancher de perte maximum");

   c.warn = soft;
   if(InpSelfRules == RULE_BLOQUER && block == "")
      block = soft;
   c.reason  = block;
   c.allowed = (block == "");
  }

//+------------------------------------------------------------------+
//| Panneau : création des objets                                    |
//+------------------------------------------------------------------+
void MkRect(const string name, const int x, const int y, const int w, const int h, const color bg, const color border)
  {
   ObjectCreate(0, name, OBJ_RECTANGLE_LABEL, 0, 0, 0);
   ObjectSetInteger(0, name, OBJPROP_CORNER, CORNER_LEFT_UPPER);
   ObjectSetInteger(0, name, OBJPROP_XDISTANCE, x);
   ObjectSetInteger(0, name, OBJPROP_YDISTANCE, y);
   ObjectSetInteger(0, name, OBJPROP_XSIZE, w);
   ObjectSetInteger(0, name, OBJPROP_YSIZE, h);
   ObjectSetInteger(0, name, OBJPROP_BGCOLOR, bg);
   ObjectSetInteger(0, name, OBJPROP_BORDER_TYPE, BORDER_FLAT);
   ObjectSetInteger(0, name, OBJPROP_COLOR, border);
   ObjectSetInteger(0, name, OBJPROP_WIDTH, 1);
   ObjectSetInteger(0, name, OBJPROP_BACK, false);
   ObjectSetInteger(0, name, OBJPROP_SELECTABLE, false);
   ObjectSetInteger(0, name, OBJPROP_HIDDEN, true);
   ObjectSetInteger(0, name, OBJPROP_ZORDER, 0);
  }

void MkLabel(const string name, const int x, const int y, const string text, const color clr, const int fs,
             const string font = FONT_TV)
  {
   ObjectCreate(0, name, OBJ_LABEL, 0, 0, 0);
   ObjectSetInteger(0, name, OBJPROP_CORNER, CORNER_LEFT_UPPER);
   ObjectSetInteger(0, name, OBJPROP_ANCHOR, ANCHOR_LEFT_UPPER);
   ObjectSetInteger(0, name, OBJPROP_XDISTANCE, x);
   ObjectSetInteger(0, name, OBJPROP_YDISTANCE, y);
   ObjectSetString(0, name, OBJPROP_TEXT, (text == "") ? " " : text);
   ObjectSetString(0, name, OBJPROP_FONT, font);
   ObjectSetInteger(0, name, OBJPROP_FONTSIZE, fs);
   ObjectSetInteger(0, name, OBJPROP_COLOR, clr);
   ObjectSetInteger(0, name, OBJPROP_BACK, false);
   ObjectSetInteger(0, name, OBJPROP_SELECTABLE, false);
   ObjectSetInteger(0, name, OBJPROP_HIDDEN, true);
   ObjectSetInteger(0, name, OBJPROP_ZORDER, 5);
  }

void MkEdit(const string name, const int x, const int y, const int w, const int h, const string text, const int fs)
  {
   ObjectCreate(0, name, OBJ_EDIT, 0, 0, 0);
   ObjectSetInteger(0, name, OBJPROP_CORNER, CORNER_LEFT_UPPER);
   ObjectSetInteger(0, name, OBJPROP_XDISTANCE, x);
   ObjectSetInteger(0, name, OBJPROP_YDISTANCE, y);
   ObjectSetInteger(0, name, OBJPROP_XSIZE, w);
   ObjectSetInteger(0, name, OBJPROP_YSIZE, h);
   ObjectSetString(0, name, OBJPROP_TEXT, text);
   ObjectSetString(0, name, OBJPROP_FONT, FONT_TV);
   ObjectSetInteger(0, name, OBJPROP_FONTSIZE, fs);
   ObjectSetInteger(0, name, OBJPROP_ALIGN, ALIGN_RIGHT);
   ObjectSetInteger(0, name, OBJPROP_READONLY, false);
   ObjectSetInteger(0, name, OBJPROP_COLOR, clrWhite);
   ObjectSetInteger(0, name, OBJPROP_BGCOLOR, CLR_EDIT_BG);
   ObjectSetInteger(0, name, OBJPROP_BORDER_COLOR, CLR_BORDER);
   ObjectSetInteger(0, name, OBJPROP_BACK, false);
   ObjectSetInteger(0, name, OBJPROP_SELECTABLE, false);
   ObjectSetInteger(0, name, OBJPROP_HIDDEN, true);
   ObjectSetInteger(0, name, OBJPROP_ZORDER, 10);
  }

void MkButton(const string name, const int x, const int y, const int w, const int h, const string text,
              const color bg, const color fg, const int fs, const string font = FONT_TV)
  {
   ObjectCreate(0, name, OBJ_BUTTON, 0, 0, 0);
   ObjectSetInteger(0, name, OBJPROP_CORNER, CORNER_LEFT_UPPER);
   ObjectSetInteger(0, name, OBJPROP_XDISTANCE, x);
   ObjectSetInteger(0, name, OBJPROP_YDISTANCE, y);
   ObjectSetInteger(0, name, OBJPROP_XSIZE, w);
   ObjectSetInteger(0, name, OBJPROP_YSIZE, h);
   ObjectSetString(0, name, OBJPROP_TEXT, text);
   ObjectSetString(0, name, OBJPROP_FONT, font);
   ObjectSetInteger(0, name, OBJPROP_FONTSIZE, fs);
   ObjectSetInteger(0, name, OBJPROP_COLOR, fg);
   ObjectSetInteger(0, name, OBJPROP_BGCOLOR, bg);
   ObjectSetInteger(0, name, OBJPROP_BORDER_COLOR, CLR_BORDER);
   ObjectSetInteger(0, name, OBJPROP_STATE, false);
   ObjectSetInteger(0, name, OBJPROP_BACK, false);
   ObjectSetInteger(0, name, OBJPROP_SELECTABLE, false);
   ObjectSetInteger(0, name, OBJPROP_HIDDEN, true);
   ObjectSetInteger(0, name, OBJPROP_ZORDER, 10);
  }

void SetLabel(const string name, const string text, const color clr)
  {
   if(ObjectFind(0, name) < 0)
      return;
   string t = (text == "") ? " " : text;
   if(ObjectGetString(0, name, OBJPROP_TEXT) != t)
      ObjectSetString(0, name, OBJPROP_TEXT, t);
   if((color)ObjectGetInteger(0, name, OBJPROP_COLOR) != clr)
      ObjectSetInteger(0, name, OBJPROP_COLOR, clr);
  }

void SetButton(const string name, const color bg, const color fg)
  {
   if(ObjectFind(0, name) < 0)
      return;
   if((color)ObjectGetInteger(0, name, OBJPROP_BGCOLOR) != bg)
      ObjectSetInteger(0, name, OBJPROP_BGCOLOR, bg);
   if((color)ObjectGetInteger(0, name, OBJPROP_COLOR) != fg)
      ObjectSetInteger(0, name, OBJPROP_COLOR, fg);
  }

void SetEditText(const string name, const string text)
  {
   if(ObjectFind(0, name) >= 0)
      ObjectSetString(0, name, OBJPROP_TEXT, text);
  }

//+------------------------------------------------------------------+
//| Position en cours : break-even et paliers R1, R2, R3             |
//+------------------------------------------------------------------+
string TFName(const ENUM_TIMEFRAMES tf)
  {
   string s = EnumToString(tf);
   return (StringFind(s, "PERIOD_") == 0) ? StringSubstr(s, 7) : s;
  }

// Première position de l'EA sur le symbole (0 si aucune) ; elle reste sélectionnée
ulong CurrentPosition()
  {
   int total = PositionsTotal();
   for(int i = 0; i < total; i++)
     {
      ulong ticket = PositionGetTicket(i);
      if(ticket == 0)
         continue;
      if(PositionGetInteger(POSITION_MAGIC) != InpMagic)
         continue;
      if(PositionGetString(POSITION_SYMBOL) != _Symbol)
         continue;
      return ticket;
     }
   return 0;
  }

string LevelName(const int level)
  {
   if(level <= 0)
      return "SL initial";
   if(level == 1)
      return "BE";
   return "R" + IntegerToString(level - 1);
  }

// Prix d'un palier : 1 = break-even (coûts inclus), 2 = R1, 3 = R2, 4 = R3
double LevelPrice(const int level, const int dir, const double entry, const double slInit)
  {
   double rDist = MathAbs(entry - slInit);
   if(level <= 0 || rDist <= 0.0)
      return slInit;
   if(level == 1)
     {
      double offset = 0.0;
      if(InpBEIncludeCosts)
        {
         double p = 0.0;
         if(OrderCalcProfit((dir > 0) ? ORDER_TYPE_BUY : ORDER_TYPE_SELL, _Symbol, 1.0, entry, entry + rDist, p) && p != 0.0)
           {
            double valuePerPrice = MathAbs(p) / rDist;
            if(valuePerPrice > 0.0)
               offset = MathMax(0.0, InpCommissionPerLot) / valuePerPrice;
           }
        }
      return RoundToTick((dir > 0) ? entry + offset : entry - offset, (dir > 0) ? 1 : -1);
     }
   double mult = (double)(level - 1);
   return RoundToTick((dir > 0) ? entry + mult * rDist : entry - mult * rDist, (dir > 0) ? -1 : 1);
  }

// Déplace le stop de la position en cours vers le palier demandé, jamais en arrière
bool MoveStop(const int level)
  {
   ulong ticket = CurrentPosition();
   if(ticket == 0)
     {
      Flash("Aucune position ouverte");
      return false;
     }
   ulong  posId  = (ulong)PositionGetInteger(POSITION_IDENTIFIER);
   int    dir    = (PositionGetInteger(POSITION_TYPE) == POSITION_TYPE_BUY) ? 1 : -1;
   double entry  = PositionGetDouble(POSITION_PRICE_OPEN);
   double curSL  = PositionGetDouble(POSITION_SL);
   double tp     = PositionGetDouble(POSITION_TP);
   double slInit = GvGet(posId, "sl", curSL);
   if(slInit <= 0.0)
      slInit = curSL;
   if(slInit <= 0.0)
     {
      Flash("SL initial inconnu : impossible de calculer les paliers", 6000);
      return false;
     }

   double target = LevelPrice(level, dir, entry, slInit);
   MqlTick tick;
   if(!SymbolInfoTick(_Symbol, tick))
     {
      Flash("Pas de cotation");
      return false;
     }
   double stopsLvl = (double)SymbolInfoInteger(_Symbol, SYMBOL_TRADE_STOPS_LEVEL) * SymbolInfoDouble(_Symbol, SYMBOL_POINT);
   if((dir > 0 && target >= tick.bid - stopsLvl) || (dir < 0 && target <= tick.ask + stopsLvl))
     {
      Flash("Le prix n'a pas encore atteint " + LevelName(level));
      return false;
     }
   if(curSL > 0.0 && ((dir > 0 && target <= curSL) || (dir < 0 && target >= curSL)))
     {
      Flash("Le stop est déjà à ce niveau ou plus loin");
      return false;
     }

   bool ok = IsHedging() ? g_trade.PositionModify(ticket, target, tp) : g_trade.PositionModify(_Symbol, target, tp);
   uint rc = g_trade.ResultRetcode();
   if(!ok || (rc != TRADE_RETCODE_DONE && rc != TRADE_RETCODE_NO_CHANGES))
     {
      Flash("Stop non déplacé : " + g_trade.ResultRetcodeDescription(), 6000);
      return false;
     }
   GvSet(posId, "lvl", (double)level);
   GvSet(posId, "slwr", target);
   Flash("Stop au " + LevelName(level) + " : " + Px(target), 3000);
   return true;
  }

//+------------------------------------------------------------------+
//| Outil position : état sauvegardé (redémarrage, changement d'UT)   |
//+------------------------------------------------------------------+
string ToolGv(const string key) { return GvBase() + "tool_" + _Symbol + "_" + key; }

void SaveTool()
  {
   if(!g_tool.on)
     {
      GlobalVariablesDeleteAll(GvBase() + "tool_" + _Symbol + "_");
      return;
     }
   GlobalVariableSet(ToolGv("dir"),   (double)g_tool.dir);
   GlobalVariableSet(ToolGv("kind"),  (double)g_tool.kind);
   GlobalVariableSet(ToolGv("entry"), g_tool.entry);
   GlobalVariableSet(ToolGv("sl"),    g_tool.sl);
   GlobalVariableSet(ToolGv("tp"),    g_tool.tp);
   GlobalVariableSet(ToolGv("t1"),    (double)g_tool.t1);
   GlobalVariableSet(ToolGv("bars"),  (double)g_tool.bars);
   GlobalVariableSet(ToolGv("crt"),   (double)g_tool.created);
   GlobalVariableSet(ToolGv("raw"),   g_tool.raw ? 1.0 : 0.0);
   GlobalVariableSet(ToolGv("sig"),   (double)g_tool.sig);
   GlobalVariableSet(ToolGv("set"),   (double)g_tool.setup);
   GlobalVariableSet(ToolGv("exp"),   (double)g_tool.sigExp);
  }

void LoadTool()
  {
   g_tool.on = false;
   if(!GlobalVariableCheck(ToolGv("dir")))
      return;
   g_tool.dir     = (int)GlobalVariableGet(ToolGv("dir"));
   g_tool.kind    = (int)GlobalVariableGet(ToolGv("kind"));
   g_tool.entry   = GlobalVariableGet(ToolGv("entry"));
   g_tool.sl      = GlobalVariableGet(ToolGv("sl"));
   g_tool.tp      = GlobalVariableGet(ToolGv("tp"));
   g_tool.t1      = (datetime)(long)GlobalVariableGet(ToolGv("t1"));
   g_tool.bars    = (int)GlobalVariableGet(ToolGv("bars"));
   g_tool.created = (datetime)(long)GlobalVariableGet(ToolGv("crt"));
   g_tool.raw     = (GlobalVariableGet(ToolGv("raw")) > 0.5);
   g_tool.sig     = (long)GlobalVariableGet(ToolGv("sig"));
   g_tool.setup   = (int)GlobalVariableGet(ToolGv("set"));
   g_tool.sigExp  = (datetime)(long)GlobalVariableGet(ToolGv("exp"));
   if(g_tool.bars < 5)
      g_tool.bars = InpToolBars;
   if(g_tool.kind < 0 || g_tool.kind > 2)
      g_tool.kind = 0;
   g_tool.on = (g_tool.dir != 0 && g_tool.sl > 0.0 && g_tool.tp > 0.0);
  }

// Ferme l'outil (après l'envoi, Fermer, Q ou Échap)
void ClearTool()
  {
   g_tool.on   = false;
   g_tool.dir  = 0;
   g_tool.kind = 0;
   g_tool.sig  = 0;
   g_riskUser  = 0.0;
   g_lotsUser  = 0.0;
   if(g_drag != DRAG_NONE)
     {
      g_drag = DRAG_NONE;
      ChartSetInteger(0, CHART_MOUSE_SCROLL, g_scrollWas);
     }
   SaveTool();
   ObjectsDeleteAll(0, PFXT);
   ChartRedraw(0);
  }

// Entrée montrée par l'outil : le prix actuel au Marché, l'entrée de l'ordre limite automatique,
// sinon l'entrée choisie (Limite, Stop)
double ToolEntry()
  {
   if(g_calc.valid && g_calc.limit && g_calc.dir == g_tool.dir)
      return g_calc.entry;
   if(g_tool.kind != 0)
      return g_tool.entry;
   MqlTick tk;
   if(SymbolInfoTick(_Symbol, tk) && tk.bid > 0.0)
      return (g_tool.dir > 0) ? tk.ask : tk.bid;
   return g_tool.entry;
  }

// Sell ou Buy : trace l'outil au prix actuel. Un outil déjà tracé garde son écart de stop et son R.
bool NewTool(const int dir)
  {
   MqlTick tk;
   if(!SymbolInfoTick(_Symbol, tk) || tk.bid <= 0.0 || tk.ask <= 0.0)
     {
      Flash("Pas de cotation : l'outil ne peut pas être tracé");
      return false;
     }
   SLimits lim;
   GetLimits(lim);
   double stop = MathMax(lim.stopMin, MathMin(lim.stopMax, InpDefStopUsd));
   double r    = g_rTarget;
   if(g_tool.on)
     {
      double e0 = ToolEntry();
      double s0 = MathAbs(e0 - g_tool.sl);
      double t0 = MathAbs(g_tool.tp - e0);
      if(s0 > 0.0 && t0 > 0.0)
        {
         stop = s0;
         r    = t0 / s0;
        }
     }
   double adj = (dir < 0 && InpSellSpreadAdjust) ? tk.ask - tk.bid : 0.0;
   double e   = (dir > 0) ? tk.ask : tk.bid;
   g_tool.on      = true;
   g_tool.dir     = dir;
   g_tool.kind    = 0;
   g_tool.entry   = e;
   g_tool.sl      = (dir > 0) ? RoundToTick(e - stop, -1) : RoundToTick(e + stop - adj, 1);
   g_tool.tp      = (dir > 0) ? RoundToTick(e + r * stop, 1) : RoundToTick(e - r * stop - adj, -1);
   g_tool.created = iTime(_Symbol, PERIOD_CURRENT, 0);
   g_tool.t1      = g_tool.created;            // l'outil part de la bougie en cours, vers la droite
   if(!(bool)ChartGetInteger(0, CHART_SHIFT))
      ChartSetInteger(0, CHART_SHIFT, true);   // marge à droite, comme TradingView
   g_tool.bars    = (InpToolBars >= 5) ? InpToolBars : 40;
   g_tool.raw     = false;
   g_tool.sig     = 0;
   g_tool.setup   = 0;
   g_tool.sigExp  = 0;
   g_riskUser     = 0.0;
   g_lotsUser     = 0.0;
   SaveTool();
   return true;
  }

// Onglet Marché, Limite ou Stop
void SetKind(const int kind)
  {
   if(!g_tool.on)
     {
      Flash("Trace d'abord l'outil : Sell ou Buy (S / W)");
      return;
     }
   if(kind < 0 || kind > 2 || kind == g_tool.kind)
      return;
   if(g_tool.kind == 0)
      g_tool.entry = ToolEntry();   // part du prix actuel : déplace l'entrée à la souris
   g_tool.kind = kind;
   SaveTool();
  }

// Ordre Limite ou Stop selon la place de l'entrée par rapport au prix
int PendingKind(const int dir, const double entry)
  {
   MqlTick tk;
   if(!SymbolInfoTick(_Symbol, tk))
      return 1;
   if(dir > 0)
      return (entry < tk.ask) ? 1 : 2;
   return (entry > tk.bid) ? 1 : 2;
  }

//+------------------------------------------------------------------+
//| Outil position : tracé façon TradingView                         |
//+------------------------------------------------------------------+
// Couleur fg posée à « a » (0 à 1) sur le fond bg : remplace la transparence, absente de MT5
color Blend(const color bg, const color fg, const double a)
  {
   int b0 = (int)bg;
   int f0 = (int)fg;
   int r  = (int)MathRound((b0 & 0xFF) + (((f0 & 0xFF) - (b0 & 0xFF)) * a));
   int g  = (int)MathRound(((b0 >> 8) & 0xFF) + ((((f0 >> 8) & 0xFF) - ((b0 >> 8) & 0xFF)) * a));
   int b  = (int)MathRound(((b0 >> 16) & 0xFF) + ((((f0 >> 16) & 0xFF) - ((b0 >> 16) & 0xFF)) * a));
   return (color)(r | (g << 8) | (b << 16));
  }

void ToolRect(const string name, const datetime t1, const double p1, const datetime t2, const double p2, const color clr)
  {
   if(ObjectFind(0, name) < 0)
     {
      ObjectCreate(0, name, OBJ_RECTANGLE, 0, t1, p1, t2, p2);
      ObjectSetInteger(0, name, OBJPROP_FILL, true);
      ObjectSetInteger(0, name, OBJPROP_BACK, true);
      ObjectSetInteger(0, name, OBJPROP_SELECTABLE, false);
      ObjectSetInteger(0, name, OBJPROP_SELECTED, false);
      ObjectSetInteger(0, name, OBJPROP_HIDDEN, true);
      ObjectSetInteger(0, name, OBJPROP_WIDTH, 1);
     }
   else
     {
      ObjectMove(0, name, 0, t1, p1);
      ObjectMove(0, name, 1, t2, p2);
     }
   if((color)ObjectGetInteger(0, name, OBJPROP_COLOR) != clr)
      ObjectSetInteger(0, name, OBJPROP_COLOR, clr);
  }

void ToolLine(const string name, const datetime t1, const double p, const datetime t2, const color clr, const int style)
  {
   if(ObjectFind(0, name) < 0)
     {
      ObjectCreate(0, name, OBJ_TREND, 0, t1, p, t2, p);
      ObjectSetInteger(0, name, OBJPROP_RAY_RIGHT, false);
      ObjectSetInteger(0, name, OBJPROP_RAY_LEFT, false);
      ObjectSetInteger(0, name, OBJPROP_SELECTABLE, false);
      ObjectSetInteger(0, name, OBJPROP_SELECTED, false);
      ObjectSetInteger(0, name, OBJPROP_HIDDEN, true);
      ObjectSetInteger(0, name, OBJPROP_BACK, false);
      ObjectSetInteger(0, name, OBJPROP_WIDTH, 1);
     }
   else
     {
      ObjectMove(0, name, 0, t1, p);
      ObjectMove(0, name, 1, t2, p);
     }
   ObjectSetInteger(0, name, OBJPROP_COLOR, clr);
   ObjectSetInteger(0, name, OBJPROP_STYLE, style);
  }

// Poignée (carré ou rond) posée sur un prix : on la saisit à la souris
void ToolHandle(const string name, const datetime t, const double p, const string glyph)
  {
   if(ObjectFind(0, name) < 0)
     {
      ObjectCreate(0, name, OBJ_TEXT, 0, t, p);
      ObjectSetString(0, name, OBJPROP_FONT, FONT_SYM);
      ObjectSetInteger(0, name, OBJPROP_FONTSIZE, 12);
      ObjectSetInteger(0, name, OBJPROP_ANCHOR, ANCHOR_CENTER);
      ObjectSetInteger(0, name, OBJPROP_SELECTABLE, false);
      ObjectSetInteger(0, name, OBJPROP_SELECTED, false);
      ObjectSetInteger(0, name, OBJPROP_HIDDEN, true);
      ObjectSetInteger(0, name, OBJPROP_BACK, false);
      ObjectSetInteger(0, name, OBJPROP_ZORDER, 4);
      ObjectSetInteger(0, name, OBJPROP_COLOR, CLR_ACCENT);
     }
   else
      ObjectMove(0, name, 0, t, p);
   if(ObjectGetString(0, name, OBJPROP_TEXT) != glyph)
      ObjectSetString(0, name, OBJPROP_TEXT, glyph);
  }

int TagWidth(const string text)
  {
   uint w = 0, h = 0;
   TextSetFont(FONT_TV, -90);            // 9 points, comme l'étiquette : la mesure suit l'échelle de l'écran
   if(!TextGetSize(text, w, h) || w == 0)
      w = (uint)(StringLen(text) * Dp(6));
   return (int)w + Dp(14);
  }

// Étiquette colorée de l'outil (fond + texte), en pixels, centrée sur xc ; le texte est centré dans le cadre
void ToolTag(const string name, const int xc, const int y, const string text, const color bg, const int width)
  {
   int bw = (width > 0) ? width : TagWidth(text);
   int bh = Dp(18);
   int cw = (int)ChartGetInteger(0, CHART_WIDTH_IN_PIXELS);
   int x  = xc - bw / 2;
   if(cw > 0 && x + bw > cw - 4)
      x = cw - 4 - bw;
   if(x < 2)
      x = 2;
   int yy = (y < 2) ? 2 : y;
   string nb = name + "b";
   string nt = name + "t";
   if(ObjectFind(0, nb) < 0)
     {
      ObjectCreate(0, nb, OBJ_RECTANGLE_LABEL, 0, 0, 0);
      ObjectSetInteger(0, nb, OBJPROP_CORNER, CORNER_LEFT_UPPER);
      ObjectSetInteger(0, nb, OBJPROP_BORDER_TYPE, BORDER_FLAT);
      ObjectSetInteger(0, nb, OBJPROP_WIDTH, 1);
      ObjectSetInteger(0, nb, OBJPROP_SELECTABLE, false);
      ObjectSetInteger(0, nb, OBJPROP_HIDDEN, true);
      ObjectSetInteger(0, nb, OBJPROP_BACK, false);
      ObjectSetInteger(0, nb, OBJPROP_ZORDER, 1);
     }
   ObjectSetInteger(0, nb, OBJPROP_XDISTANCE, x);
   ObjectSetInteger(0, nb, OBJPROP_YDISTANCE, yy);
   ObjectSetInteger(0, nb, OBJPROP_XSIZE, bw);
   ObjectSetInteger(0, nb, OBJPROP_YSIZE, bh);
   ObjectSetInteger(0, nb, OBJPROP_BGCOLOR, bg);
   ObjectSetInteger(0, nb, OBJPROP_COLOR, bg);
   if(ObjectFind(0, nt) < 0)
     {
      ObjectCreate(0, nt, OBJ_LABEL, 0, 0, 0);
      ObjectSetInteger(0, nt, OBJPROP_CORNER, CORNER_LEFT_UPPER);
      ObjectSetInteger(0, nt, OBJPROP_ANCHOR, ANCHOR_CENTER);
      ObjectSetString(0, nt, OBJPROP_FONT, FONT_TV);
      ObjectSetInteger(0, nt, OBJPROP_FONTSIZE, 9);
      ObjectSetInteger(0, nt, OBJPROP_COLOR, clrWhite);
      ObjectSetInteger(0, nt, OBJPROP_SELECTABLE, false);
      ObjectSetInteger(0, nt, OBJPROP_HIDDEN, true);
      ObjectSetInteger(0, nt, OBJPROP_BACK, false);
      ObjectSetInteger(0, nt, OBJPROP_ZORDER, 2);
     }
   ObjectSetInteger(0, nt, OBJPROP_XDISTANCE, x + bw / 2);
   ObjectSetInteger(0, nt, OBJPROP_YDISTANCE, yy + bh / 2);
   if(ObjectGetString(0, nt, OBJPROP_TEXT) != text)
      ObjectSetString(0, nt, OBJPROP_TEXT, text);
  }

// Pixels de l'outil : bords gauche et droit, entrée, stop, objectif
bool ToolXY(int &x1, int &x2, int &yE, int &yS, int &yT)
  {
   datetime t2 = g_tool.t1 + g_tool.bars * PeriodSeconds();
   double   en = ToolEntry();
   int      tmp = 0;
   if(!ChartTimePriceToXY(0, 0, g_tool.t1, en, x1, yE))
      return false;
   if(!ChartTimePriceToXY(0, 0, t2, en, x2, tmp))
      return false;
   if(!ChartTimePriceToXY(0, 0, g_tool.t1, g_tool.sl, tmp, yS))
      return false;
   return ChartTimePriceToXY(0, 0, g_tool.t1, g_tool.tp, tmp, yT);
  }

// Étiquettes : stop et cible aux bords de l'outil, P&L d'ouverture et ratio au milieu
void PlaceToolTags()
  {
   int x1 = 0, x2 = 0, yE = 0, yS = 0, yT = 0;
   if(!ToolXY(x1, x2, yE, yS, yT))
      return;
   int    xc  = (x1 + x2) / 2;
   bool   v   = g_calc.valid && g_calc.dir == g_tool.dir;
   double en  = ToolEntry();
   double ts  = TickSize();
   double pct = (en > 0.0) ? 100.0 / en : 0.0;
   string ccy = " " + AccountInfoString(ACCOUNT_CURRENCY);
   string sTxt, tTxt, m1, m2;
   double pnl = 0.0;
   if(v && ts > 0.0)
     {
      sTxt = "Stop : " + FrN(g_calc.stopDist, 2) + " (" + FrN(g_calc.stopDist * pct, 3) + " %) " + FrN(g_calc.stopDist / ts, 0)
             + ", Montant : " + FrN(g_calc.risk, 2) + ccy;
      tTxt = "Cible : " + FrN(g_calc.tpDist, 2) + " (" + FrN(g_calc.tpDist * pct, 3) + " %) " + FrN(g_calc.tpDist / ts, 0)
             + ", Montant : " + FrN(g_calc.tpGain, 2) + ccy;
      double exitPx = (g_calc.dir > 0) ? g_calc.bid : g_calc.ask;
      pnl = g_calc.dir * (exitPx - g_calc.entry) * g_calc.lots * g_calc.valuePerPrice;
      m1  = "Ouverture P&L : " + FrN(pnl, 2) + ", Qté : " + LotsStr(g_calc.lots) + " lot";
      m2  = "Ratio Risque/Récompense : " + FrN(g_calc.stopDist > 0.0 ? g_calc.tpDist / g_calc.stopDist : 0.0, 2);
     }
   else
     {
      sTxt = "Stop : " + FrN(MathAbs(en - g_tool.sl), 2);
      tTxt = "Cible : " + FrN(MathAbs(g_tool.tp - en), 2);
      m1   = "Qté : -";
      m2   = (g_calc.reason != "") ? g_calc.reason : "-";
     }
   int  h     = Dp(18);
   bool slTop = (yS < yT);
   ToolTag(PFXT + "LT", xc, MathMin(yS, yT) - h - Dp(4), slTop ? sTxt : tTxt, slTop ? CLR_RED : CLR_GREEN, 0);
   ToolTag(PFXT + "LB", xc, MathMax(yS, yT) + Dp(4),     slTop ? tTxt : sTxt, slTop ? CLR_GREEN : CLR_RED, 0);
   int   mw = MathMax(TagWidth(m1), TagWidth(m2));
   color mc = (pnl < 0.0 || !v) ? CLR_RED : CLR_GREEN;
   ToolTag(PFXT + "M1", xc, yE - h, m1, mc, mw);
   ToolTag(PFXT + "M2", xc, yE,     m2, mc, mw);
  }

void DrawTool()
  {
   if(!g_tool.on)
     {
      if(ObjectFind(0, PFXT + "RISK") >= 0)
        {
         ObjectsDeleteAll(0, PFXT);
         ChartRedraw(0);
        }
      return;
     }
   datetime t1 = g_tool.t1;
   datetime t2 = t1 + g_tool.bars * PeriodSeconds();
   double   en = ToolEntry();
   color    bg = (color)ChartGetInteger(0, CHART_COLOR_BACKGROUND);
   bool     pend = (g_tool.kind != 0 || (g_calc.valid && g_calc.limit));
   ToolRect(PFXT + "RISK", t1, en, t2, g_tool.sl, Blend(bg, CLR_RED, 0.22));
   ToolRect(PFXT + "REW",  t1, en, t2, g_tool.tp, Blend(bg, CLR_GREEN, 0.22));
   ToolLine(PFXT + "ENT", t1, en, t2, CLR_TV_LINE, pend ? STYLE_DASH : STYLE_SOLID);
   ToolHandle(PFXT + "HSL", t1, g_tool.sl, "□");
   ToolHandle(PFXT + "HTP", t1, g_tool.tp, "□");
   ToolHandle(PFXT + "HEN", t1, en, "○");
   ToolHandle(PFXT + "HW",  t2, en, "□");
   // Flèche sur le prix d'entrée, une bougie avant l'outil : là où est le prix avant d'entrer
   // (ou l'entrée de l'ordre en attente)
   if(InpShowMarks)
      MkArrow(PFXT + "ARR", t1 - PeriodSeconds(), en, (g_tool.dir > 0) ? "▲" : "▼",
              (g_tool.dir > 0) ? InpMarkBuyClr : InpMarkSellClr, g_tool.dir < 0);
   PlaceToolTags();
  }

//+------------------------------------------------------------------+
//| Outil position : souris                                          |
//+------------------------------------------------------------------+
bool InPanel(const int x, const int y)
  {
   return (x >= g_panX && x <= g_panX + g_panW && y >= g_panY && y <= g_panY + g_panH);
  }

// Bouton gauche enfoncé : quelle poignée est saisie ?
void StartDrag(const int x, const int y)
  {
   int x1 = 0, x2 = 0, yE = 0, yS = 0, yT = 0;
   if(!ToolXY(x1, x2, yE, yS, yT))
      return;
   int tol = Dp(7);
   int d   = DRAG_NONE;
   if(MathAbs(x - x2) <= tol + Dp(2) && MathAbs(y - yE) <= tol + Dp(2))
      d = DRAG_WIDTH;
   else if(x >= x1 - tol && x <= x2 + tol)
     {
      if(MathAbs(y - yS) <= tol)
         d = DRAG_SL;
      else if(MathAbs(y - yT) <= tol)
         d = DRAG_TP;
      else if(MathAbs(y - yE) <= tol)
         d = DRAG_ENTRY;
      else if(y > MathMin(yS, yT) && y < MathMax(yS, yT))
         d = DRAG_BODY;
     }
   if(d == DRAG_NONE)
      return;
   int      sub = 0;
   datetime t   = 0;
   double   p   = 0.0;
   if(!ChartXYToTimePrice(0, x, y, sub, t, p) || sub != 0)
      return;
   g_drag      = d;
   g_dragX0    = x;
   g_dragY0    = y;
   g_dragP0    = p;
   g_dragT0    = t;
   g_dragEn0   = ToolEntry();
   g_dragSl0   = g_tool.sl;
   g_dragTp0   = g_tool.tp;
   g_dragT10   = g_tool.t1;
   g_dragKind0 = g_tool.kind;
   g_scrollWas = (bool)ChartGetInteger(0, CHART_MOUSE_SCROLL);
   ChartSetInteger(0, CHART_MOUSE_SCROLL, false);   // le graphique ne défile pas pendant le réglage
  }

// Déplacement : la poignée suit la souris ; stop et objectif restent de part et d'autre de l'entrée
void DragTo(const int x, const int y)
  {
   int      sub = 0;
   datetime t   = 0;
   double   p   = 0.0;
   if(!ChartXYToTimePrice(0, x, y, sub, t, p) || sub != 0)
      return;
   double ts  = TickSize();
   int    dir = g_tool.dir;
   double en  = ToolEntry();
   long   per = PeriodSeconds();
   if(g_drag == DRAG_SL)
     {
      double v = RoundToTick(p, 0);
      g_tool.sl = (dir > 0) ? MathMin(v, en - ts) : MathMax(v, en + ts);
     }
   else if(g_drag == DRAG_TP)
     {
      double v = RoundToTick(p, 0);
      g_tool.tp = (dir > 0) ? MathMax(v, en + ts) : MathMin(v, en - ts);
     }
   else if(g_drag == DRAG_ENTRY)
     {
      double lo = MathMin(g_tool.sl, g_tool.tp) + ts;
      double hi = MathMax(g_tool.sl, g_tool.tp) - ts;
      double v  = MathMax(lo, MathMin(hi, RoundToTick(p, 0)));
      g_tool.entry = v;
      g_tool.kind  = PendingKind(dir, v);    // l'entrée quitte le prix : ordre Limite ou Stop
     }
   else if(g_drag == DRAG_BODY)
     {
      double dp = RoundToTick(p - g_dragP0, 0);
      long   dt = (per > 0) ? (((long)t - (long)g_dragT0) / per) * per : 0;
      g_tool.t1 = (datetime)((long)g_dragT10 + dt);
      if(MathAbs(dp) >= 2.0 * ts || g_dragKind0 != 0)
        {
         g_tool.entry = RoundToTick(g_dragEn0 + dp, 0);
         g_tool.sl    = RoundToTick(g_dragSl0 + dp, 0);
         g_tool.tp    = RoundToTick(g_dragTp0 + dp, 0);
         g_tool.kind  = PendingKind(dir, g_tool.entry);
        }
     }
   else if(g_drag == DRAG_WIDTH && per > 0)
     {
      int bars = (int)(((long)t - (long)g_tool.t1) / per);
      g_tool.bars = MathMax(5, MathMin(500, bars));
     }
   if(GetTickCount() - g_lastDragTick >= 30)
     {
      g_lastDragTick = GetTickCount();
      ComputeCalc(g_calc);
      DrawTool();
      SyncEdits();
      RenderPanel();
     }
  }

void EndDrag()
  {
   g_drag = DRAG_NONE;
   ChartSetInteger(0, CHART_MOUSE_SCROLL, g_scrollWas);
   SaveTool();
   UpdateAll(true);
  }

//+------------------------------------------------------------------+
//| Gestion automatique : BE structurel, trailing, TP glissant       |
//+------------------------------------------------------------------+
double AutoAtr()
  {
   if(g_atrHandle == INVALID_HANDLE)
      return 0.0;
   double buf[];
   if(CopyBuffer(g_atrHandle, 0, 1, 1, buf) != 1)
      return 0.0;
   return buf[0];
  }

// Dernier creux (dir > 0) ou sommet (dir < 0) confirmé sur l'unité surveillée
double LastSwing(const int dir)
  {
   int len = (InpSwingLen > 0) ? InpSwingLen : 1;
   for(int i = len + 1; i <= 60; i++)
     {
      double v = (dir > 0) ? iLow(_Symbol, InpAutoTF, i) : iHigh(_Symbol, InpAutoTF, i);
      if(v <= 0.0)
         break;
      bool ok = true;
      for(int k = 1; k <= len && ok; k++)
        {
         double a = (dir > 0) ? iLow(_Symbol, InpAutoTF, i - k) : iHigh(_Symbol, InpAutoTF, i - k);
         double b = (dir > 0) ? iLow(_Symbol, InpAutoTF, i + k) : iHigh(_Symbol, InpAutoTF, i + k);
         if(a <= 0.0 || b <= 0.0)
           {
            ok = false;
            break;
           }
         if(dir > 0 && (a < v || b < v))
            ok = false;
         if(dir < 0 && (a > v || b > v))
            ok = false;
        }
      if(ok)
         return v;
     }
   return 0.0;
  }

bool WriteSL(const ulong ticket, const ulong posId, const double sl, const double tp, const string why)
  {
   bool ok = IsHedging() ? g_trade.PositionModify(ticket, sl, tp) : g_trade.PositionModify(_Symbol, sl, tp);
   uint rc = g_trade.ResultRetcode();
   if(!ok || (rc != TRADE_RETCODE_DONE && rc != TRADE_RETCODE_NO_CHANGES))
     {
      Print(TAG, "Stop non déplacé (", why, ") : ", g_trade.ResultRetcodeDescription());
      return false;
     }
   GvSet(posId, "slwr", sl);
   Flash(why + " : " + Px(sl), 3000);
   return true;
  }

// Appelée une fois par bougie fermée de l'unité surveillée
void AutoManage()
  {
   if(!g_autoOn)
      return;
   ulong ticket = CurrentPosition();
   if(ticket == 0)
      return;

   ulong  posId  = (ulong)PositionGetInteger(POSITION_IDENTIFIER);
   int    dir    = (PositionGetInteger(POSITION_TYPE) == POSITION_TYPE_BUY) ? 1 : -1;
   double entry  = PositionGetDouble(POSITION_PRICE_OPEN);
   double curSL  = PositionGetDouble(POSITION_SL);
   double curTP  = PositionGetDouble(POSITION_TP);
   double slInit = GvGet(posId, "sl", curSL);
   double rDist  = MathAbs(entry - slInit);
   if(rDist <= 0.0)
      return;

   //--- Tes actions priment : un SL différent de celui écrit par l'EA fige l'automatique
   double written = GvGet(posId, "slwr", slInit);
   if(curSL > 0.0 && MathAbs(curSL - written) > TickSize() * 0.5 && GvGet(posId, "man", 0.0) < 0.5)
     {
      GvSet(posId, "man", 1.0);
      Flash("Stop déplacé à la main : automatique arrêté sur ce trade", 6000);
     }
   if(GvGet(posId, "man", 0.0) > 0.5)
      return;

   MqlTick tick;
   if(!SymbolInfoTick(_Symbol, tick))
      return;
   double c1 = iClose(_Symbol, InpAutoTF, 1);
   double o1 = iOpen(_Symbol, InpAutoTF, 1);
   if(c1 <= 0.0 || o1 <= 0.0)
      return;

   double atr    = AutoAtr();
   double guard  = MathMax(0.0, InpMinAtrDist * atr);
   double spread = tick.ask - tick.bid;
   double rNow   = (dir > 0) ? (c1 - entry) / rDist : (entry - c1) / rDist;
   int    lvl    = (int)GvGet(posId, "lvl", 0.0);

   //--- Coûts convertis en prix, pour un break-even réellement à zéro
   double beOff = 0.0;
   double pr    = 0.0;
   if(OrderCalcProfit((dir > 0) ? ORDER_TYPE_BUY : ORDER_TYPE_SELL, _Symbol, 1.0, entry, entry + rDist, pr) && pr != 0.0)
     {
      double vpp = MathAbs(pr) / rDist;
      if(vpp > 0.0)
         beOff = MathMax(0.0, InpCommissionPerLot) / vpp;
     }
   double floorPrice = (dir > 0) ? entry + beOff : entry - beOff;

   //--- 1. Break-even structurel, puis trailing structurel
   double target = 0.0;
   string why    = "";
   if(lvl < 1 && rNow >= InpBETriggerR)
     {
      double sw = LastSwing(dir);
      target = floorPrice;
      if(sw > 0.0)
        {
         double cand = (dir > 0) ? sw - spread : sw + spread;
         if((dir > 0 && cand > floorPrice) || (dir < 0 && cand < floorPrice))
            target = cand;
        }
      why = "BE automatique";
     }
   else
      if(lvl >= 1 && InpAutoTrail)
        {
         double sw = LastSwing(dir);
         if(sw > 0.0)
           {
            double cand = RoundToTick((dir > 0) ? sw - spread : sw + spread, (dir > 0) ? -1 : 1);
            bool better = (dir > 0) ? (cand > curSL + TickSize() * 0.5) : (cand < curSL - TickSize() * 0.5);
            if(better)
              {
               target = cand;
               why    = "Trailing automatique";
              }
           }
        }

   if(target > 0.0)
     {
      target = RoundToTick(target, (dir > 0) ? -1 : 1);
      bool farEnough = (dir > 0) ? (tick.bid - target >= guard) : (target - tick.ask >= guard);
      double stopsLvl = (double)SymbolInfoInteger(_Symbol, SYMBOL_TRADE_STOPS_LEVEL) * SymbolInfoDouble(_Symbol, SYMBOL_POINT);
      bool legal = (dir > 0) ? (target < tick.bid - stopsLvl) : (target > tick.ask + stopsLvl);
      if(farEnough && legal)
        {
         if(WriteSL(ticket, posId, target, curTP, why))
           {
            if(lvl < 1)
               GvSet(posId, "lvl", 1.0);
            if(!PositionSelectByTicket(ticket))
               return;
            curSL = PositionGetDouble(POSITION_SL);
           }
        }
     }

   //--- 2. TP glissant
   if(curTP <= 0.0)
      return;
   if(InpTPMode2 == TPM_PALIERS && lvl >= 1)
     {
      int steps = (int)GvGet(posId, "tpst", 0.0);
      if(steps < InpTPMaxSteps)
        {
         double buffer = MathMax(InpTPBufferR * rDist, 0.5 * atr);
         bool   proche = (dir > 0) ? (c1 >= curTP - buffer) : (c1 <= curTP + buffer);
         bool   elan   = (dir > 0) ? (c1 > o1) : (c1 < o1);
         if(proche && elan)
           {
            double newTP = RoundToTick((dir > 0) ? curTP + InpTPStepR * rDist : curTP - InpTPStepR * rDist, (dir > 0) ? 1 : -1);
            bool ok = IsHedging() ? g_trade.PositionModify(ticket, curSL, newTP) : g_trade.PositionModify(_Symbol, curSL, newTP);
            if(ok && g_trade.ResultRetcode() == TRADE_RETCODE_DONE)
              {
               GvSet(posId, "tpst", (double)(steps + 1));
               Flash(StringFormat("TP repoussé à %.1fR : %s", (g_rTarget + InpTPStepR * (steps + 1)), Px(newTP)), 4000);
              }
           }
        }
     }
   else
      if(InpTPMode2 == TPM_LIBERE && rNow >= InpTPFreeR)
        {
         bool ok = IsHedging() ? g_trade.PositionModify(ticket, curSL, 0.0) : g_trade.PositionModify(_Symbol, curSL, 0.0);
         if(ok && g_trade.ResultRetcode() == TRADE_RETCODE_DONE)
            Flash("TP retiré : la sortie se fera au trailing", 4000);
        }
  }

bool CloseCurrent()
  {
   ulong ticket = CurrentPosition();
   if(ticket == 0)
     {
      Flash("Aucune position ouverte");
      return false;
     }
   g_trade.SetDeviationInPoints((ulong)((InpMaxSlippagePts > 0) ? InpMaxSlippagePts : 0));
   bool ok = g_trade.PositionClose(ticket);
   uint rc = g_trade.ResultRetcode();
   if(!ok || (rc != TRADE_RETCODE_DONE && rc != TRADE_RETCODE_PLACED))
     {
      Flash("Fermeture refusée : " + g_trade.ResultRetcodeDescription(), 6000);
      return false;
     }
   Flash("Position fermée", 3000);
   g_statsDirty = true;
   return true;
  }

//+------------------------------------------------------------------+
//| Statistiques : taux de réussite et R moyen des derniers trades   |
//+------------------------------------------------------------------+
string StatSlice(const double &rs[], const int take)
  {
   int total = ArraySize(rs);
   int n     = (total < take) ? total : take;
   if(n <= 0)
      return StringFormat("%d tr", take) + " : -";
   int    wins = 0;
   double sum  = 0.0;
   for(int i = total - n; i < total; i++)
     {
      sum += rs[i];
      if(rs[i] > 0.0)
         wins++;
     }
   return StringFormat("%d tr %.0f %% %+.2fR", n, 100.0 * wins / n, sum / n);
  }

void RefreshStats()
  {
   g_lastStatsCalc = GetTickCount();
   g_statsTxt      = "Stats : -";
   datetime from = TimeCurrent() - 60 * 86400;
   if(!HistorySelect(from, TimeCurrent() + 86400))
      return;

   ulong  ids[];
   double nets[];
   bool   closed[];
   ArrayResize(ids, 0);
   ArrayResize(nets, 0);
   ArrayResize(closed, 0);

   int total = HistoryDealsTotal();
   for(int i = 0; i < total; i++)
     {
      ulong d = HistoryDealGetTicket(i);
      if(d == 0)
         continue;
      if(HistoryDealGetInteger(d, DEAL_MAGIC) != InpMagic)
         continue;
      long dtype = HistoryDealGetInteger(d, DEAL_TYPE);
      if(dtype != DEAL_TYPE_BUY && dtype != DEAL_TYPE_SELL)
         continue;
      ulong pos = (ulong)HistoryDealGetInteger(d, DEAL_POSITION_ID);
      double net = HistoryDealGetDouble(d, DEAL_PROFIT) + HistoryDealGetDouble(d, DEAL_COMMISSION)
                 + HistoryDealGetDouble(d, DEAL_SWAP)   + HistoryDealGetDouble(d, DEAL_FEE);
      long dentry = HistoryDealGetInteger(d, DEAL_ENTRY);
      int idx = -1;
      for(int k = 0; k < ArraySize(ids); k++)
         if(ids[k] == pos)
           {
            idx = k;
            break;
           }
      if(idx < 0)
        {
         idx = ArraySize(ids);
         ArrayResize(ids, idx + 1);
         ArrayResize(nets, idx + 1);
         ArrayResize(closed, idx + 1);
         ids[idx]    = pos;
         nets[idx]   = 0.0;
         closed[idx] = false;
        }
      nets[idx] += net;
      if(dentry == DEAL_ENTRY_OUT || dentry == DEAL_ENTRY_OUT_BY || dentry == DEAL_ENTRY_INOUT)
         closed[idx] = true;
     }

   double rs[];
   ArrayResize(rs, 0);
   for(int k = 0; k < ArraySize(ids); k++)
     {
      if(!closed[k])
         continue;
      double risk = GvGet(ids[k], "risk", 0.0);
      if(risk <= 0.0)
         continue;
      int m = ArraySize(rs);
      ArrayResize(rs, m + 1);
      rs[m] = nets[k] / risk;
     }
   if(ArraySize(rs) == 0)
      return;
   g_statsTxt = StatSlice(rs, 20) + "   " + StatSlice(rs, 50);
  }

//+------------------------------------------------------------------+
//| Flèches sur le graphique : armement et entrées                   |
//+------------------------------------------------------------------+
void MkArrow(const string name, const datetime t, const double price, const string glyph, const color clr,
             const bool above)
  {
   if(ObjectFind(0, name) < 0)
      ObjectCreate(0, name, OBJ_TEXT, 0, t, price);
   else
      ObjectMove(0, name, 0, t, price);
   ObjectSetString(0, name, OBJPROP_TEXT, glyph);
   ObjectSetString(0, name, OBJPROP_FONT, FONT_SYM);
   ObjectSetInteger(0, name, OBJPROP_FONTSIZE, (InpMarkSize > 0) ? InpMarkSize : 8);
   ObjectSetInteger(0, name, OBJPROP_COLOR, clr);
   ObjectSetInteger(0, name, OBJPROP_ANCHOR, above ? ANCHOR_LOWER : ANCHOR_UPPER);
   ObjectSetInteger(0, name, OBJPROP_SELECTABLE, false);
   ObjectSetInteger(0, name, OBJPROP_SELECTED, false);
   ObjectSetInteger(0, name, OBJPROP_HIDDEN, true);
   ObjectSetInteger(0, name, OBJPROP_BACK, false);
   ObjectSetInteger(0, name, OBJPROP_ZORDER, 3);
  }

// Redessine les flèches d'entrée depuis l'historique (changement d'unité, redémarrage)
void RedrawMarks()
  {
   if(!InpShowMarks)
      return;
   datetime from = TimeCurrent() - (datetime)((long)((InpMarkDays > 0) ? InpMarkDays : 1) * 86400);
   if(!HistorySelect(from, TimeCurrent() + 86400))
      return;
   int total = HistoryDealsTotal();
   for(int i = 0; i < total; i++)
     {
      ulong d = HistoryDealGetTicket(i);
      if(d == 0)
         continue;
      if(HistoryDealGetInteger(d, DEAL_MAGIC) != InpMagic)
         continue;
      if(HistoryDealGetString(d, DEAL_SYMBOL) != _Symbol)
         continue;
      if(HistoryDealGetInteger(d, DEAL_ENTRY) != DEAL_ENTRY_IN)
         continue;
      long dt = HistoryDealGetInteger(d, DEAL_TYPE);
      if(dt != DEAL_TYPE_BUY && dt != DEAL_TYPE_SELL)
         continue;
      MarkEntry((ulong)HistoryDealGetInteger(d, DEAL_POSITION_ID), (dt == DEAL_TYPE_BUY) ? 1 : -1,
                (datetime)HistoryDealGetInteger(d, DEAL_TIME), HistoryDealGetDouble(d, DEAL_PRICE));
     }
  }

// Efface les flèches plus anciennes que l'historique choisi
void CleanMarks()
  {
   datetime limit = TimeCurrent() - (datetime)((long)((InpMarkDays > 0) ? InpMarkDays : 1) * 86400);
   string names[];
   ArrayResize(names, 0);
   int total = ObjectsTotal(0, -1, OBJ_TEXT);
   for(int i = total - 1; i >= 0; i--)
     {
      string nm = ObjectName(0, i, -1, OBJ_TEXT);
      if(StringFind(nm, PFXM) != 0)
         continue;
      if((datetime)ObjectGetInteger(0, nm, OBJPROP_TIME) >= limit)
         continue;
      int k = ArraySize(names);
      ArrayResize(names, k + 1);
      names[k] = nm;
     }
   for(int k = 0; k < ArraySize(names); k++)
      ObjectDelete(0, names[k]);
  }

// Mesure la largeur réelle d'un caractère, pour couper les textes au bon endroit
void MeasureFont(const string font, const int fs)
  {
   uint w = 0, h = 0;
   g_fs = fs;
   TextSetFont(font, -fs * 10);
   if(TextGetSize("0123456789ABCDEFGHIJ", w, h) && w > 0)
      g_charPx = (int)MathMax(4.0, MathCeil((double)w / 20.0));
   else
      g_charPx = (int)MathMax(4.0, MathRound(fs * 0.85));
  }

// Coupe un texte à la largeur disponible (pixels), mesurée dans la police du ticket à pt points
string FitPx(const string text, const int px, const int pt)
  {
   uint w = 0, h = 0;
   TextSetFont(FONT_TV, -pt * 10);
   if(!TextGetSize(text, w, h))
     {
      int maxc = (g_charPx > 0) ? px / g_charPx : 40;
      if(maxc < 5)
         maxc = 5;
      return (StringLen(text) <= maxc) ? text : StringSubstr(text, 0, maxc - 1) + "…";
     }
   if((int)w <= px)
      return text;
   int lo = 1, hi = StringLen(text) - 1;     // plus long début qui tient avec « … »
   while(lo < hi)
     {
      int mid = (lo + hi + 1) / 2;
      if(TextGetSize(StringSubstr(text, 0, mid) + "…", w, h) && (int)w <= px)
         lo = mid;
      else
         hi = mid - 1;
     }
   return StringSubstr(text, 0, lo) + "…";
  }

// Étiquette alignée à droite
void MkLabelR(const string name, const int x, const int y, const string text, const color clr, const int fs,
              const string font = FONT_TV)
  {
   MkLabel(name, x, y, text, clr, fs, font);
   ObjectSetInteger(0, name, OBJPROP_ANCHOR, ANCHOR_RIGHT_UPPER);
  }

void SetRect(const string name, const color bg)
  {
   if(ObjectFind(0, name) < 0)
      return;
   if((color)ObjectGetInteger(0, name, OBJPROP_BGCOLOR) != bg)
      ObjectSetInteger(0, name, OBJPROP_BGCOLOR, bg);
  }

void SetL(const string name, const string text, const color clr) { SetLabel(name, FitPx(text, g_colL, g_fs), clr); }
void SetR(const string name, const string text, const color clr) { SetLabel(name, FitPx(text, g_colR, g_fs), clr); }
void SetF(const string name, const string text, const color clr) { SetLabel(name, FitPx(text, g_panInner, g_fs - 1), clr); }

// Flèche du sens, posée sur le prix d'entrée de la position
void MarkEntry(const ulong posId, const int dir, const datetime t, const double price)
  {
   if(!InpShowMarks || t == 0 || price <= 0.0)
      return;
   string nm = PFXM + "E" + IntegerToString((long)posId);
   if(dir > 0)
      MkArrow(nm, t, price, "▲", InpMarkBuyClr, false);
   else
      MkArrow(nm, t, price, "▼", InpMarkSellClr, true);
   ChartRedraw(0);
  }

//+------------------------------------------------------------------+
//| Ticket : textes                                                  |
//+------------------------------------------------------------------+
// Nombre au format français : 4 024,77
string FrN(const double v, const int digits)
  {
   string s   = DoubleToString(MathAbs(v), digits);
   int    dot = StringFind(s, ".");
   string ip  = (dot >= 0) ? StringSubstr(s, 0, dot) : s;
   string dp  = (dot >= 0) ? StringSubstr(s, dot + 1) : "";
   string out = "";
   int    n   = StringLen(ip);
   for(int i = 0; i < n; i++)
     {
      out += StringSubstr(ip, i, 1);
      int left = n - 1 - i;
      if(left > 0 && left % 3 == 0)
         out += " ";
     }
   if(digits > 0)
      out += "," + dp;
   bool neg = (v < 0.0 && MathAbs(v) >= 0.5 * MathPow(10.0, -digits));
   return (neg ? "−" : "") + out;
  }

// Ligne des annonces, comme la version 3
string NewsLine(color &clr)
  {
   clr = CLR_MUTED;
   if(!InpNewsFilter)
      return "News : filtre désactivé";
   if(MQLInfoInteger(MQL_TESTER))
      return "News : indisponible dans le testeur";
   if(!g_calendarOk)
     {
      clr = CLR_ORANGE;
      return "News : calendrier indisponible";
     }
   string   nm    = "";
   datetime tm    = 0;
   bool     inWin = false;
   NewsCheck(TimeTradeServer(), nm, tm, inWin);
   if(inWin)
     {
      clr = CLR_RED;
      return "NEWS " + TimeToString(tm, TIME_MINUTES) + " " + nm;
     }
   if(tm > 0)
      return "News " + TimeToString(tm, TIME_MINUTES) + " " + nm;
   return "News : rien de fort sur 24 h";
  }

// Ordre qui partira au bouton d'envoi
string OrderText()
  {
   string side = (g_calc.dir > 0) ? "Buy" : "Sell";
   if(g_calc.limit)
      return StringFormat("Limite %s %s lot à %s (stop ramené à %s $, %d min)", side, LotsStr(g_calc.lots), Px(g_calc.entry),
                          FrN(g_calc.stopDist, 2), InpLimitMin);
   if(g_calc.kind == 1)
      return StringFormat("Limite %s %s lot à %s", side, LotsStr(g_calc.lots), Px(g_calc.entry));
   if(g_calc.kind == 2)
      return StringFormat("Stop %s %s lot à %s", side, LotsStr(g_calc.lots), Px(g_calc.entry));
   return StringFormat("%s %s lot au marché", side, LotsStr(g_calc.lots));
  }

// Champs du ticket : jamais réécrits pendant la saisie
void SetEditIfIdle(const string name, const string text)
  {
   if(name == g_editing || ObjectFind(0, name) < 0)
      return;
   if(ObjectGetString(0, name, OBJPROP_TEXT) != text)
      ObjectSetString(0, name, OBJPROP_TEXT, text);
  }

void SyncEdits()
  {
   bool   v   = g_calc.valid && g_tool.on && g_calc.dir == g_tool.dir;
   double ts  = TickSize();
   double ref = (g_calc.capRef > 0.0) ? g_calc.capRef : AccountInfoDouble(ACCOUNT_BALANCE);
   // Risque : celui saisi, sinon celui du carnet (déjà plafonné par la marge du jour)
   double show = (g_riskUser > 0.0) ? MathMin(g_riskUser, g_calc.budget) : g_calc.budget;
   string rTxt = (g_riskPct == 1) ? DoubleToString((ref > 0.0) ? show / ref * 100.0 : 0.0, 2) : DoubleToString(show, 2);
   SetEditIfIdle(PFX + "E_RISK", rTxt);
   SetEditIfIdle(PFX + "E_LOTS", v ? LotsStr(g_calc.lots) : "");
   SetEditIfIdle(PFX + "E_TP",   g_tool.on ? Px(v ? g_calc.tp : g_tool.tp) : "");
   SetEditIfIdle(PFX + "E_TPT",  (v && ts > 0.0) ? DoubleToString(g_calc.tpDist / ts, 0) : "");
   SetEditIfIdle(PFX + "E_SL",   g_tool.on ? Px(v ? g_calc.slEff : g_tool.sl) : "");
   SetEditIfIdle(PFX + "E_SLT",  (v && ts > 0.0) ? DoubleToString(g_calc.stopDist / ts, 0) : "");
  }

//+------------------------------------------------------------------+
//| Ticket : création, comme le ticket d'ordre de TradingView        |
//+------------------------------------------------------------------+
void MkEditL(const string name, const int x, const int y, const int w, const int h, const int fs)
  {
   MkEdit(name, x, y, w, h, "", fs);
   ObjectSetInteger(0, name, OBJPROP_ALIGN, ALIGN_LEFT);
  }

void MkLabelC(const string name, const int x, const int y, const string text, const color clr, const int fs, const string font)
  {
   MkLabel(name, x, y, text, clr, fs, font);
   ObjectSetInteger(0, name, OBJPROP_ANCHOR, ANCHOR_UPPER);
  }

void CreatePanel()
  {
   ObjectsDeleteAll(0, PFX);

   double su    = MathMax(0.6, MathMin(3.0, InpPanelScale));   // échelle choisie : texte et cadres
   double s     = su * g_k;                                    // cadres : aussi à l'échelle de l'écran
   int    fs    = (int)MathMax(7.0, MathRound(9.0 * su));
   int    fsB   = (int)MathMax(9.0, MathRound(13.0 * su));
   int    W     = (int)(MathMax(280.0, (double)InpPanelWidth) * s);
   int    pad   = (int)(12 * s);
   int    rh    = (int)(17 * s);
   int    eh    = (int)(26 * s);
   int    gap   = (int)(8 * s);
   int    x0    = InpPanelX;
   int    y0    = InpPanelY;
   int    xL    = x0 + pad;
   int    xR    = x0 + W - pad;
   int    inner = W - 2 * pad;
   g_scale    = s;
   g_panInner = inner;
   g_colL     = (int)(inner * 0.56);
   g_colR     = (int)(inner * 0.42);
   MeasureFont(FONT_TV, fs);

   MkRect(PFX + "BG", x0, y0, W, 100, CLR_BG, CLR_BORDER);

   //--- En-tête
   int y = y0 + (int)(9 * s);
   MkLabel (PFX + "TITLE", xL, y, _Symbol, clrWhite, fs + 2, FONT_TVB);
   MkLabelR(PFX + "SUB",   xR, y + (int)(3 * s), "Calculateur XAU 4.01", CLR_MUTED, fs - 1, FONT_TV);
   y += (int)(27 * s);

   //--- Sell | spread | Buy
   int boxH = (int)(48 * s);
   int boxW = (inner - gap) / 2;
   int xB   = xL + boxW + gap;
   MkRect (PFX + "SELLBG", xL, y, boxW, boxH, CLR_SIDE_OFF, CLR_BORDER);
   MkLabel(PFX + "SELLT", xL + (int)(10 * s), y + (int)(6 * s), "Sell", CLR_SELL_TX, fs, FONT_TV);
   MkLabel(PFX + "SELLP", xL + (int)(10 * s), y + (int)(21 * s), "-", CLR_SELL_TX, fsB, FONT_TVB);
   MkRect  (PFX + "BUYBG", xB, y, boxW, boxH, CLR_SIDE_OFF, CLR_BORDER);
   MkLabelR(PFX + "BUYT", xR - (int)(10 * s), y + (int)(6 * s), "Buy", CLR_BUY_TX, fs, FONT_TV);
   MkLabelR(PFX + "BUYP", xR - (int)(10 * s), y + (int)(21 * s), "-", CLR_BUY_TX, fsB, FONT_TVB);
   int spW = (int)(38 * s);
   int spH = (int)(18 * s);
   MkRect  (PFX + "SPRBG", xL + boxW + gap / 2 - spW / 2, y + (boxH - spH) / 2, spW, spH, CLR_BG, CLR_BORDER);
   MkLabelC(PFX + "SPRT", xL + boxW + gap / 2, y + (boxH - spH) / 2 + (int)(3 * s), "-", CLR_TEXT, fs - 1, FONT_TV);
   y += boxH + (int)(12 * s);

   //--- Marché | Limite | Stop
   g_tabW = inner / 3;
   g_tabY = y;
   MkLabelC(PFX + "TAB0", xL + g_tabW / 2,         y, "Marché", CLR_MUTED, fs + 1, FONT_TV);
   MkLabelC(PFX + "TAB1", xL + g_tabW + g_tabW / 2, y, "Limite", CLR_MUTED, fs + 1, FONT_TV);
   MkLabelC(PFX + "TAB2", xL + 2 * g_tabW + g_tabW / 2, y, "Stop", CLR_MUTED, fs + 1, FONT_TV);
   MkRect(PFX + "TABSEP", xL, y + (int)(22 * s), inner, 1, CLR_BORDER, CLR_BORDER);
   MkRect(PFX + "TABL", xL, y + (int)(20 * s), g_tabW, (int)MathMax(2.0, 2.0 * s), clrWhite, clrWhite);
   y += (int)(34 * s);

   //--- Risque ↔ lot
   int half = (inner - (int)(28 * s)) / 2;
   int sw   = xL + half + (int)(14 * s);
   MkLabel (PFX + "RMODE", xL, y, "Risque USD ▼", CLR_MUTED, fs, FONT_SYM);
   MkLabelR(PFX + "RHINT", xR, y, " ", CLR_MUTED, fs - 1, FONT_TV);
   y += rh + (int)(2 * s);
   MkEditL (PFX + "E_RISK", xL, y, half, eh, fs + 1);
   MkLabelC(PFX + "SW1", sw, y + (int)(5 * s), "↔", CLR_MUTED, fs + 1, FONT_SYM);
   MkEditL (PFX + "E_LOTS", xR - half, y, half - (int)(34 * s), eh, fs + 1);
   MkLabelR(PFX + "LOTU", xR, y + (int)(6 * s), "lot", CLR_MUTED, fs, FONT_TV);
   y += eh + (int)(10 * s);

   //--- Valeur du trade, marge disponible, valeur du tick
   int infoH = (int)(64 * s);
   int ix    = xL + (int)(10 * s);
   int ixr   = xR - (int)(10 * s);
   MkRect  (PFX + "INFOBG", xL, y, inner, infoH, CLR_INFO_BG, CLR_INFO_BG);
   MkLabel (PFX + "I1L", ix,  y + (int)(7 * s),  "Valeur du trade", CLR_MUTED, fs, FONT_TV);
   MkLabelR(PFX + "I1R", ixr, y + (int)(7 * s),  "-", CLR_TEXT, fs, FONT_TV);
   MkLabel (PFX + "I2L", ix,  y + (int)(25 * s), "Marge disponible", CLR_MUTED, fs, FONT_TV);
   MkLabelR(PFX + "I2R", ixr, y + (int)(25 * s), "-", CLR_TEXT, fs, FONT_TV);
   MkLabel (PFX + "I3L", ix,  y + (int)(43 * s), "Valeur du tick", CLR_MUTED, fs, FONT_TV);
   MkLabelR(PFX + "I3R", ixr, y + (int)(43 * s), "-", CLR_TEXT, fs, FONT_TV);
   y += infoH + (int)(12 * s);

   //--- Sorties : objectif et stop, en prix ↔ ticks
   int tW = half - (int)(40 * s);
   MkLabel(PFX + "EXITS", xL, y, "Sorties", clrWhite, fs + 1, FONT_TVB);
   y += rh + (int)(6 * s);
   MkLabel (PFX + "TPL", xL, y, "Take profit prix", CLR_MUTED, fs, FONT_TV);
   MkLabelR(PFX + "TPI", xR, y, " ", CLR_GREEN, fs, FONT_TV);
   y += rh + (int)(2 * s);
   MkEditL (PFX + "E_TP",  xL, y, half, eh, fs + 1);
   MkLabelC(PFX + "SW2",   sw, y + (int)(5 * s), "↔", CLR_MUTED, fs + 1, FONT_SYM);
   MkEditL (PFX + "E_TPT", xR - half, y, tW, eh, fs + 1);
   MkLabelR(PFX + "TPU",   xR, y + (int)(6 * s), "ticks", CLR_MUTED, fs, FONT_TV);
   y += eh + (int)(10 * s);
   MkLabel (PFX + "SLL", xL, y, "Stop Loss prix", CLR_MUTED, fs, FONT_TV);
   MkLabelR(PFX + "SLI", xR, y, " ", CLR_RED, fs, FONT_TV);
   y += rh + (int)(2 * s);
   MkEditL (PFX + "E_SL",  xL, y, half, eh, fs + 1);
   MkLabelC(PFX + "SW3",   sw, y + (int)(5 * s), "↔", CLR_MUTED, fs + 1, FONT_SYM);
   MkEditL (PFX + "E_SLT", xR - half, y, tW, eh, fs + 1);
   MkLabelR(PFX + "SLU",   xR, y + (int)(6 * s), "ticks", CLR_MUTED, fs, FONT_TV);
   y += eh + (int)(12 * s);
   MkLabel (PFX + "RRL", xL, y, "Risque / Récompense", CLR_MUTED, fs, FONT_TV);
   MkLabelR(PFX + "RRV", xR, y, "-", clrWhite, fs + 1, FONT_TVB);
   y += rh + (int)(10 * s);

   //--- Carnet, journée, annonces
   MkLabel(PFX + "C1", xL, y, " ", CLR_MUTED, fs - 1, FONT_TV);
   y += rh;
   MkLabel(PFX + "C2", xL, y, " ", CLR_MUTED, fs - 1, FONT_TV);
   y += rh;
   MkLabel(PFX + "C3", xL, y, " ", CLR_MUTED, fs - 1, FONT_TV);
   y += rh + (int)(6 * s);

   //--- Statut
   MkRect (PFX + "STBG", xL, y, inner, eh, CLR_TINT_N, CLR_TINT_N);
   MkLabel(PFX + "O_STATUS", xL + (int)(8 * s), y + (int)(6 * s), " ", CLR_MUTED, fs, FONT_TV);
   y += eh + (int)(8 * s);

   //--- Fermer | envoyer
   int bigH   = (int)(36 * s);
   int closeW = (int)(inner * 0.36);
   MkButton(PFX + "B_TCLOSE", xL, y, closeW, bigH, "Fermer", CLR_BTN, CLR_TEXT, fs + 1, FONT_TV);
   MkButton(PFX + "B_SEND", xL + closeW + gap, y, inner - closeW - gap, bigH, "Sell / Buy", CLR_DISABLED, CLR_MUTED, fs + 1, FONT_TVB);
   y += bigH + (int)(12 * s);

   //--- Position en cours : BE, trailing, automatique, clôture
   int g6 = (int)(6 * s);
   int q  = (inner - 3 * g6) / 4;
   MkRect  (PFX + "SEP3", xL, y, inner, 1, CLR_BORDER, CLR_BORDER);
   y += (int)(8 * s);
   MkLabel (PFX + "O_POS", xL, y, " ", CLR_MUTED, fs, FONT_TV);
   MkLabelR(PFX + "O_PNL", xR, y, " ", CLR_MUTED, fs, FONT_TV);
   y += rh + (int)(4 * s);
   MkButton(PFX + "B_BE",    xL,               y, q, eh, "BE (A)",   CLR_DISABLED, CLR_MUTED, fs, FONT_TV);
   MkButton(PFX + "B_TRAIL", xL + (q + g6),     y, q, eh, "R1 (D)",   CLR_DISABLED, CLR_MUTED, fs, FONT_TV);
   MkButton(PFX + "B_AUTO",  xL + 2 * (q + g6), y, q, eh, "AUTO",     CLR_BTN,      CLR_TEXT,  fs, FONT_TV);
   MkButton(PFX + "B_CLOSE", xL + 3 * (q + g6), y, q, eh, "Clôturer", CLR_DISABLED, CLR_MUTED, fs, FONT_TV);
   y += eh + g6;
   MkLabel(PFX + "O_STATS", xL, y, " ", CLR_MUTED, fs - 1, FONT_TV);
   y += rh + pad / 2;

   g_panX = x0;
   g_panY = y0;
   g_panW = W;
   g_panH = y - y0;
   ObjectSetInteger(0, PFX + "BG", OBJPROP_YSIZE, g_panH);
   ChartRedraw(0);
  }

//+------------------------------------------------------------------+
//| Ticket : affichage                                               |
//+------------------------------------------------------------------+
void RenderPanel()
  {
   SLimits lim;
   bool   linked = GetLimits(lim);
   bool   v      = g_calc.valid && g_tool.on && g_calc.dir == g_tool.dir;
   int    dir    = g_tool.on ? g_tool.dir : 0;
   int    kind   = g_tool.on ? g_tool.kind : 0;
   double equity = AccountInfoDouble(ACCOUNT_EQUITY);
   string ccy    = " " + AccountInfoString(ACCOUNT_CURRENCY);
   MqlTick tk;
   bool   hasTick = SymbolInfoTick(_Symbol, tk);

   //--- Sell | spread | Buy : le côté de l'outil est en surbrillance
   SetRect (PFX + "SELLBG", (dir < 0) ? CLR_SELL_ON : CLR_SIDE_OFF);
   SetRect (PFX + "BUYBG",  (dir > 0) ? CLR_BUY_ON : CLR_SIDE_OFF);
   SetLabel(PFX + "SELLT", "Sell", (dir < 0) ? clrWhite : CLR_SELL_TX);
   SetLabel(PFX + "BUYT",  "Buy",  (dir > 0) ? clrWhite : CLR_BUY_TX);
   SetLabel(PFX + "SELLP", hasTick ? FrN(tk.bid, _Digits) : "-", (dir < 0) ? clrWhite : CLR_SELL_TX);
   SetLabel(PFX + "BUYP",  hasTick ? FrN(tk.ask, _Digits) : "-", (dir > 0) ? clrWhite : CLR_BUY_TX);
   double pt = SymbolInfoDouble(_Symbol, SYMBOL_POINT);
   SetLabel(PFX + "SPRT", (hasTick && pt > 0.0) ? IntegerToString((int)MathRound((tk.ask - tk.bid) / pt)) : "-", CLR_TEXT);

   //--- Onglets
   SetLabel(PFX + "TAB0", "Marché", (kind == 0) ? clrWhite : CLR_MUTED);
   SetLabel(PFX + "TAB1", "Limite", (kind == 1) ? clrWhite : CLR_MUTED);
   SetLabel(PFX + "TAB2", "Stop",   (kind == 2) ? clrWhite : CLR_MUTED);
   if(ObjectFind(0, PFX + "TABL") >= 0)
     {
      int tx = g_panX + (int)(12 * g_scale) + g_tabW * kind;
      if((int)ObjectGetInteger(0, PFX + "TABL", OBJPROP_XDISTANCE) != tx)
         ObjectSetInteger(0, PFX + "TABL", OBJPROP_XDISTANCE, tx);
     }

   //--- Risque
   double ref = (lim.size > 0.0) ? lim.size : AccountInfoDouble(ACCOUNT_BALANCE);
   SetLabel(PFX + "RMODE", (g_riskPct == 1) ? "Risque % ▼" : "Risque " + AccountInfoString(ACCOUNT_CURRENCY) + " ▼", CLR_MUTED);
   string hint = "carnet non relié";
   if(linked)
      hint = "max " + FrN(g_calc.budget, 2) + " $ · " + FrN((ref > 0.0) ? g_calc.budget / ref * 100.0 : 0.0, 2) + " %"
             + (lim.carnet ? " (carnet)" : "");
   SetLabel(PFX + "RHINT", hint, linked ? CLR_MUTED : CLR_RED);

   //--- Valeur du trade, marge, valeur du tick
   double contract = SymbolInfoDouble(_Symbol, SYMBOL_TRADE_CONTRACT_SIZE);
   long   lev      = AccountInfoInteger(ACCOUNT_LEVERAGE);
   double tickVal  = SymbolInfoDouble(_Symbol, SYMBOL_TRADE_TICK_VALUE);
   SetLabel(PFX + "I1L", (lev > 0) ? "Valeur du trade (" + IntegerToString(lev) + "x)" : "Valeur du trade", CLR_MUTED);
   SetLabel(PFX + "I1R", v ? FrN(g_calc.lots * contract * g_calc.entry, 2) + ccy : "-", CLR_TEXT);
   SetLabel(PFX + "I2R", FrN(AccountInfoDouble(ACCOUNT_MARGIN_FREE), 2) + ccy, CLR_TEXT);
   SetLabel(PFX + "I3R", v ? FrN(g_calc.lots * tickVal, 2) + ccy : "-", CLR_TEXT);

   //--- Sorties
   SetLabel(PFX + "TPI", v ? "+" + FrN(g_calc.tpGain, 2) + " $ · " + FrN(g_calc.tpR, 1) + "R" : " ", CLR_GREEN);
   SetLabel(PFX + "SLI", v ? "−" + FrN(g_calc.risk, 2) + " $ · stop " + FrN(g_calc.stopDist, 2) + " $" : " ", CLR_RED);
   SetLabel(PFX + "RRV", (v && g_calc.stopDist > 0.0) ? FrN(g_calc.tpDist / g_calc.stopDist, 2) : "-", clrWhite);

   //--- Carnet, journée, annonces
   string c1 = "";
   color  k1 = CLR_MUTED;
   if(InpUseCarnet && !linked)
     {
      c1 = "Carnet XAU non relié : lance l'EA Carnet XAU sur ce compte";
      k1 = CLR_RED;
     }
   else if(lim.carnet)
     {
      if(lim.goal > 0.0)
        {
         double left = lim.goal - lim.gain;
         c1 = "Carnet · " + PhaseName(lim.phase) + " · objectif " + FrN(lim.goal, 0) + " $ · "
              + ((left > 0.0) ? "reste " + FrN(left, 2) + " $" : "atteint")
              + " · jours " + IntegerToString(lim.days) + "/" + IntegerToString(lim.minDays);
         k1 = (left > 0.0) ? CLR_MUTED : CLR_GREEN;
        }
      else
         c1 = "Carnet · " + PhaseName(lim.phase) + (lim.own ? " · fonds propres" : " · pas d'objectif");
      if(lim.reduced)
        {
         c1 += " · risque réduit";
         k1  = CLR_ORANGE;
        }
     }
   else
      c1 = (InpMode == MODE_FONDS) ? "Fonds propres · palier " + IntegerToString(lim.tier) + " · capital " + FrN(lim.size, 2) + " $"
                                   : "Prop firm · palier " + FrN(lim.tierMult * 100.0, 0) + " %";
   double dayLeft = equity - lim.dayFloor;
   string c2 = "Jour " + IntegerToString(g_tradesToday) + "/" + IntegerToString(lim.maxTrades) + " trades · "
               + IntegerToString(g_lossesToday) + "/" + IntegerToString(lim.maxLoss) + " pertes · reste "
               + FrN(MathMax(0.0, dayLeft), 2) + " $ · avant plancher " + FrN(MathMax(0.0, equity - lim.floorUsd), 0) + " $";
   color  k2 = (linked && (g_tradesToday >= lim.maxTrades || g_lossesToday >= lim.maxLoss || dayLeft <= 0.0)) ? CLR_ORANGE : CLR_MUTED;
   color  k3 = CLR_MUTED;
   string c3 = NewsLine(k3);
   SetF(PFX + "C1", c1, k1);
   SetF(PFX + "C2", linked ? c2 : " ", k2);
   SetF(PFX + "C3", c3, k3);

   //--- Barre de statut
   string st  = "";
   color  stc = CLR_MUTED;
   color  stb = CLR_TINT_N;
   if(g_flashText != "" && GetTickCount() < g_flashUntil)
     {
      st  = g_flashText;
      stc = CLR_ORANGE;
      stb = CLR_TINT_O;
     }
   else if(g_calc.allowed && g_calc.warn == "")
     {
      st  = "PRÊT · " + OrderText() + (g_calc.micro ? " · micro-trade (objectif atteint)" : "");
      stc = CLR_GREEN;
      stb = CLR_TINT_G;
     }
   else if(g_calc.allowed)
     {
      st  = "PRÊT (!) · " + g_calc.warn;
      stc = CLR_ORANGE;
      stb = CLR_TINT_O;
     }
   else if(g_calc.hint)
      st = g_calc.reason;
   else
     {
      st  = "BLOQUÉ · " + g_calc.reason;
      stc = CLR_RED;
      stb = CLR_TINT_R;
     }
   SetRect(PFX + "STBG", stb);
   SetLabel(PFX + "O_STATUS", FitPx(st, g_panInner - (int)(16 * g_scale), g_fs), stc);

   //--- Bouton d'envoi : Sell en rouge, Buy en bleu, gris s'il est bloqué
   string sendTxt = "Sell / Buy";
   if(g_tool.on)
     {
      string side = (dir > 0) ? "Buy" : "Sell";
      string pre  = (v && g_calc.limit) ? "Limite " : ((kind == 1) ? "Limite " : ((kind == 2) ? "Stop " : ""));
      sendTxt = pre + side + (v ? " " + LotsStr(g_calc.lots) + " lot" : "");
     }
   if(ObjectFind(0, PFX + "B_SEND") >= 0 && ObjectGetString(0, PFX + "B_SEND", OBJPROP_TEXT) != sendTxt)
      ObjectSetString(0, PFX + "B_SEND", OBJPROP_TEXT, sendTxt);
   bool canSend = (g_tool.on && g_calc.allowed);
   SetButton(PFX + "B_SEND", canSend ? ((dir > 0) ? CLR_ACCENT : CLR_RED) : CLR_DISABLED, canSend ? clrWhite : CLR_MUTED);

   //--- Position en cours
   ulong  posTicket = CurrentPosition();
   string posTxt    = "Aucune position";
   string pnlTxt    = " ";
   color  posClr    = CLR_MUTED;
   color  pnlClr    = CLR_MUTED;
   int    posLvl    = 0;
   if(posTicket > 0)
     {
      ulong  posId = (ulong)PositionGetInteger(POSITION_IDENTIFIER);
      int    pdir  = (PositionGetInteger(POSITION_TYPE) == POSITION_TYPE_BUY) ? 1 : -1;
      double plots = PositionGetDouble(POSITION_VOLUME);
      double pnet  = PositionGetDouble(POSITION_PROFIT) + PositionGetDouble(POSITION_SWAP) - plots * MathMax(0.0, InpCommissionPerLot);
      double prisk = GvGet(posId, "risk", 0.0);
      bool   manu  = (GvGet(posId, "man", 0.0) > 0.5);
      posLvl       = (int)GvGet(posId, "lvl", 0.0);
      posTxt       = ((pdir > 0) ? "ACHAT " : "VENTE ") + LotsStr(plots) + " · stop " + LevelName(posLvl) + (manu ? " · MANUEL" : "");
      posClr       = manu ? CLR_ORANGE : CLR_TEXT;
      pnlTxt       = (prisk > 0.0) ? (((pnet >= 0.0) ? "+" : "") + FrN(pnet / prisk, 1) + "R · " + SignedUsd(pnet)) : SignedUsd(pnet);
      pnlClr       = (pnet >= 0.0) ? CLR_GREEN : CLR_RED;
     }
   SetL(PFX + "O_POS", posTxt, posClr);
   SetR(PFX + "O_PNL", pnlTxt, pnlClr);
   SetF(PFX + "O_STATS", g_statsTxt, CLR_MUTED);

   bool canBE    = (posTicket > 0 && posLvl < 1);
   bool canTrail = (posTicket > 0 && posLvl < 4);
   int  nextLvl  = (posLvl + 1 > 4) ? 4 : posLvl + 1;
   if(ObjectFind(0, PFX + "B_TRAIL") >= 0)
      ObjectSetString(0, PFX + "B_TRAIL", OBJPROP_TEXT, LevelName(nextLvl) + " (D)");
   if(ObjectFind(0, PFX + "B_AUTO") >= 0)
      ObjectSetString(0, PFX + "B_AUTO", OBJPROP_TEXT, g_autoOn ? "AUTO ON" : "AUTO OFF");
   SetButton(PFX + "B_BE",    canBE    ? CLR_BTN : CLR_DISABLED, canBE    ? CLR_TEXT : CLR_MUTED);
   SetButton(PFX + "B_TRAIL", canTrail ? CLR_BTN : CLR_DISABLED, canTrail ? CLR_TEXT : CLR_MUTED);
   SetButton(PFX + "B_AUTO",  g_autoOn ? CLR_GREEN : CLR_BTN, g_autoOn ? clrWhite : CLR_MUTED);
   SetButton(PFX + "B_CLOSE", (posTicket > 0) ? CLR_RED : CLR_DISABLED, (posTicket > 0) ? clrWhite : CLR_MUTED);
   ChartRedraw(0);
  }

void UpdateAll(const bool force)
  {
   uint now = GetTickCount();
   if(!force && now - g_lastRenderTick < 200)
      return;
   g_lastRenderTick = now;
   if(g_statsDirty || now - g_lastStatsTick > 5000)
      RefreshDayStats();
   if(InpNewsFilter && (g_lastNewsTick == 0 || now - g_lastNewsTick > (uint)(g_calendarOk ? 60000 : 15000)))
      RefreshNews();
   datetime autoBar = iTime(_Symbol, InpAutoTF, 0);
   if(autoBar > 0 && autoBar != g_lastAutoBar)
     {
      bool first = (g_lastAutoBar == 0);
      g_lastAutoBar = autoBar;
      if(!first)
         AutoManage();
     }
   if(g_lastStatsCalc == 0 || now - g_lastStatsCalc > 60000)
      RefreshStats();
   ReadCarnet();
   ComputeCalc(g_calc);
   if(ReadSignal() && SignalValid())
      Flash(StringFormat("Signal Carnet S%d %s · SL %s · TP %s : C pour le charger", g_sig.setup,
                         (g_sig.dir > 0) ? "ACHAT" : "VENTE", Px(g_sig.sl), Px(g_sig.tp)), 8000);
   PublishBridge();
   if(g_drag == DRAG_NONE)
     {
      DrawTool();       // au Marché, l'entrée suit le prix
      SyncEdits();
     }
   RenderPanel();
  }

//+------------------------------------------------------------------+
//| Exécution                                                        |
//+------------------------------------------------------------------+
void Execute()
  {
   if(!g_tool.on)
     {
      Flash("Trace d'abord l'outil : Sell ou Buy (S / W)");
      return;
     }
   if(GetTickCount() - g_lastExecTick < 2000)
     {
      Flash("Patiente : un ordre vient d'être envoyé");
      return;
     }
   if(g_pending.active)
     {
      Flash("Ordre précédent en cours de confirmation");
      return;
     }
   int dir = g_tool.dir;
   RefreshDayStats();
   ReadCarnet();
   for(int attempt = 1; attempt <= 2; attempt++)
     {
      //--- Recalcul complet au prix exact du clic
      ComputeCalc(g_calc);
      if(!g_calc.allowed)
        {
         Flash("Refusé : " + g_calc.reason);
         return;
        }

      g_lastExecTick = GetTickCount();
      g_trade.SetExpertMagicNumber((ulong)InpMagic);
      g_trade.SetDeviationInPoints((ulong)(InpMaxSlippagePts > 0 ? InpMaxSlippagePts : 0));
      g_trade.SetTypeFilling(SymbolFilling());

      string cmt = (g_tool.sig > 0) ? StringFormat("CalcXAU %.1fR S%d #", g_calc.tpR, g_tool.setup) + IntegerToString(g_tool.sig)
                                    : StringFormat("CalcXAU %.1fR", g_calc.tpR);
      if(g_calc.limit)
        {
         PlacePending(dir, 1, InpLimitMin, cmt);     // stop au-delà du maximum : limite de 30 min
         return;
        }
      if(g_calc.kind != 0)
        {
         PlacePending(dir, g_calc.kind, InpPendingMin, cmt);
         return;
        }
      bool sent = (dir > 0) ? g_trade.Buy(g_calc.lots, _Symbol, g_calc.entry, g_calc.slEff, g_calc.tp, cmt)
                            : g_trade.Sell(g_calc.lots, _Symbol, g_calc.entry, g_calc.slEff, g_calc.tp, cmt);
      uint rc = g_trade.ResultRetcode();

      if(sent && (rc == TRADE_RETCODE_DONE || rc == TRADE_RETCODE_PLACED || rc == TRADE_RETCODE_DONE_PARTIAL))
        {
         g_pending.active      = true;
         g_pending.order       = g_trade.ResultOrder();
         g_pending.sent        = TimeTradeServer();
         g_pending.dir         = dir;
         g_pending.slEff       = g_calc.slEff;
         g_pending.rCfg        = g_calc.tpR;
         g_pending.keepTp      = true;
         g_pending.riskPlanned = g_calc.risk;
         g_pending.spread      = g_calc.spread;
         g_pending.tierMult    = g_calc.tierMult;
         g_pending.poc         = 0.0;

         Print(TAG, StringFormat("%s %s lot | entrée %s | SL %s | TP %s | risque %.2f $ | %.1fR | palier %.0f %%",
                                 (dir > 0) ? "ACHAT" : "VENTE", LotsStr(g_calc.lots), Px(g_calc.entry), Px(g_calc.slEff),
                                 Px(g_calc.tp), g_calc.risk, g_calc.tpR, g_calc.tierMult * 100.0));
         Flash("Ordre envoyé", 2500);
         ClearTool();
         g_statsDirty = true;

         //--- Si l'exécution est déjà dans l'historique, on la traite tout de suite
         ulong deal = g_trade.ResultDeal();
         if(deal > 0 && HistoryDealSelect(deal))
           {
            ulong posId = (ulong)HistoryDealGetInteger(deal, DEAL_POSITION_ID);
            if(posId > 0)
               HandleFill(posId);
           }
         return;
        }

      bool retry = (rc == TRADE_RETCODE_REQUOTE || rc == TRADE_RETCODE_PRICE_CHANGED || rc == TRADE_RETCODE_PRICE_OFF);
      if(!retry || attempt == 2)
        {
         Flash(StringFormat("Échec de l'ordre (%u) : %s", rc, g_trade.ResultRetcodeDescription()), 6000);
         return;
        }
      Sleep(200);
     }
  }

//+------------------------------------------------------------------+
//| Ordres en attente : Limite, Stop, limite automatique              |
//+------------------------------------------------------------------+
void SaveLimit()
  {
   string b = GvBase() + "lim_";
   GlobalVariableSet(b + "order", (double)g_limit.order);
   GlobalVariableSet(b + "placed", (double)g_limit.placed);
   GlobalVariableSet(b + "dir", (double)g_limit.dir);
   GlobalVariableSet(b + "kind", (double)g_limit.kind);
   GlobalVariableSet(b + "min", (double)g_limit.minutes);
   GlobalVariableSet(b + "sl", g_limit.slEff);
   GlobalVariableSet(b + "rcfg", g_limit.rCfg);
   GlobalVariableSet(b + "usepoc", g_limit.keepTp ? 1.0 : 0.0);
   GlobalVariableSet(b + "risk", g_limit.riskPlanned);
   GlobalVariableSet(b + "spread", g_limit.spread);
   GlobalVariableSet(b + "tier", g_limit.tierMult);
   GlobalVariableSet(b + "poc", g_limit.poc);
  }

void LoadLimit()
  {
   string b = GvBase() + "lim_";
   g_limit.active = false;
   if(!GlobalVariableCheck(b + "order"))
      return;
   g_limit.order       = (ulong)GlobalVariableGet(b + "order");
   g_limit.placed      = (datetime)GlobalVariableGet(b + "placed");
   g_limit.dir         = (int)GlobalVariableGet(b + "dir");
   g_limit.kind        = GlobalVariableCheck(b + "kind") ? (int)GlobalVariableGet(b + "kind") : 1;
   g_limit.minutes     = GlobalVariableCheck(b + "min") ? (int)GlobalVariableGet(b + "min") : InpLimitMin;
   g_limit.slEff       = GlobalVariableGet(b + "sl");
   g_limit.rCfg        = GlobalVariableGet(b + "rcfg");
   g_limit.keepTp      = (GlobalVariableGet(b + "usepoc") > 0.5);
   g_limit.riskPlanned = GlobalVariableGet(b + "risk");
   g_limit.spread      = GlobalVariableGet(b + "spread");
   g_limit.tierMult    = GlobalVariableGet(b + "tier");
   g_limit.poc         = GlobalVariableGet(b + "poc");
   g_limit.active      = (g_limit.order > 0 && g_limit.dir != 0);
  }

void ClearLimit()
  {
   g_limit.active = false;
   g_limit.order  = 0;
   GlobalVariablesDeleteAll(GvBase() + "lim_");
  }

// kind : 1 limite, 2 stop ; minutes : validité (0 = jusqu'à annulation)
void PlacePending(const int dir, const int kind, const int minutes, const string cmt)
  {
   if(g_limit.active)
     {
      Flash("Un ordre en attente existe déjà (Q pour l'annuler)");
      return;
     }
   // Ordres en attente : remplissage « Return », accepté par les courtiers
   g_trade.SetTypeFilling(ORDER_FILLING_RETURN);
   // Expiration tenue par le serveur si le symbole l'accepte (EA retiré ou terminal fermé)
   bool     spec = (minutes > 0 && (SymbolInfoInteger(_Symbol, SYMBOL_EXPIRATION_MODE) & SYMBOL_EXPIRATION_SPECIFIED) != 0);
   datetime exp  = spec ? TimeTradeServer() + minutes * 60 : (datetime)0;
   ENUM_ORDER_TYPE_TIME tt = spec ? ORDER_TIME_SPECIFIED : ORDER_TIME_GTC;
   bool ok = false;
   if(kind == 2)
      ok = (dir > 0) ? g_trade.BuyStop(g_calc.lots, g_calc.entry, _Symbol, g_calc.slEff, g_calc.tp, tt, exp, cmt)
                     : g_trade.SellStop(g_calc.lots, g_calc.entry, _Symbol, g_calc.slEff, g_calc.tp, tt, exp, cmt);
   else
      ok = (dir > 0) ? g_trade.BuyLimit(g_calc.lots, g_calc.entry, _Symbol, g_calc.slEff, g_calc.tp, tt, exp, cmt)
                     : g_trade.SellLimit(g_calc.lots, g_calc.entry, _Symbol, g_calc.slEff, g_calc.tp, tt, exp, cmt);
   uint rc = g_trade.ResultRetcode();
   g_trade.SetTypeFilling(SymbolFilling());
   string what = (kind == 2) ? "stop" : "limite";
   if(!ok || (rc != TRADE_RETCODE_DONE && rc != TRADE_RETCODE_PLACED))
     {
      Flash(StringFormat("Ordre %s refusé (%u) : %s", what, rc, g_trade.ResultRetcodeDescription()), 6000);
      return;
     }
   g_limit.active      = true;
   g_limit.order       = g_trade.ResultOrder();
   g_limit.placed      = TimeTradeServer();
   g_limit.dir         = dir;
   g_limit.kind        = kind;
   g_limit.minutes     = minutes;
   g_limit.slEff       = g_calc.slEff;
   g_limit.rCfg        = g_calc.tpR;
   g_limit.keepTp      = true;
   g_limit.riskPlanned = g_calc.risk;
   g_limit.spread      = g_calc.spread;
   g_limit.tierMult    = g_calc.tierMult;
   g_limit.poc         = 0.0;
   SaveLimit();
   Print(TAG, StringFormat("%s %s %s lot à %s | SL %s (%.2f $) | TP %s | risque %.2f $ | %s",
                           (kind == 2) ? "STOP" : "LIMITE", (dir > 0) ? "ACHAT" : "VENTE", LotsStr(g_calc.lots), Px(g_calc.entry),
                           Px(g_calc.slEff), g_calc.stopDist, Px(g_calc.tp), g_calc.risk,
                           (minutes > 0) ? IntegerToString(minutes) + " min" : "jusqu'à annulation"));
   Flash(StringFormat("Ordre %s %s à %s%s", what, (dir > 0) ? "ACHAT" : "VENTE", Px(g_calc.entry),
                      (minutes > 0) ? ", valable " + IntegerToString(minutes) + " min" : ""), 4000);
   ClearTool();
  }

// L'ordre en attente exécuté devient un ordre suivi comme un ordre au marché
void LimitFilled(const ulong posId)
  {
   g_pending.active      = true;
   g_pending.order       = g_limit.order;
   g_pending.sent        = TimeTradeServer();
   g_pending.dir         = g_limit.dir;
   g_pending.slEff       = g_limit.slEff;
   g_pending.rCfg        = g_limit.rCfg;
   g_pending.keepTp      = g_limit.keepTp;
   g_pending.riskPlanned = g_limit.riskPlanned;
   g_pending.spread      = g_limit.spread;
   g_pending.tierMult    = g_limit.tierMult;
   g_pending.poc         = g_limit.poc;
   ClearLimit();
   HandleFill(posId);
   Flash("Ordre en attente exécuté", 3000);
  }

// true si l'ordre en attente a été exécuté (en tout ou en partie)
bool LimitWasFilled()
  {
   if(SelectPositionById(g_limit.order) != 0)
      return true;
   if(HistoryOrderSelect(g_limit.order))
     {
      long st = HistoryOrderGetInteger(g_limit.order, ORDER_STATE);
      if(st == ORDER_STATE_FILLED || st == ORDER_STATE_PARTIAL)
         return true;
     }
   return false;
  }

// Expiration, annulation ou exécution de l'ordre en attente (minuterie)
void CheckLimit()
  {
   if(!g_limit.active)
      return;
   if(OrderSelect(g_limit.order))
     {
      //--- Toujours en attente : annulé à l'expiration, ou si une limite du compte est atteinte entre-temps
      SLimits lim;
      bool   linked = GetLimits(lim);
      double equity = AccountInfoDouble(ACCOUNT_EQUITY);
      string why    = "";
      if(g_limit.minutes > 0 && (long)TimeTradeServer() - (long)g_limit.placed > (long)g_limit.minutes * 60)
         why = StringFormat("%d min écoulées", g_limit.minutes);
      else if(linked && g_lossesToday >= lim.maxLoss)
         why = "pertes du jour atteintes";
      else if(linked && equity <= lim.floorUsd)
         why = "plancher atteint";
      else if(linked && lim.dayLimit > 0.0 && equity <= lim.dayFloor)
         why = "perte maximum du jour atteinte";
      else if(linked && lim.goal > 0.0 && lim.gain >= lim.goal && lim.days >= lim.minDays)
         why = "objectif de l'étape atteint";
      else if(linked && (lim.phase == 4 || lim.phase == 5))
         why = "compte échoué ou retiré dans le carnet";
      if(why != "" && g_trade.OrderDelete(g_limit.order))
        {
         ClearLimit();
         Flash("Ordre en attente annulé : " + why, 6000);
        }
      return;
     }
   //--- Plus en attente : exécuté (la position porte le numéro de l'ordre) ou annulé
   if(LimitWasFilled())
     {
      if(!GvHas(g_limit.order, "risk"))
         LimitFilled(g_limit.order);
      else
         ClearLimit();
      return;
     }
   ClearLimit();
  }

bool CancelLimit()
  {
   if(!g_limit.active)
      return false;
   if(OrderSelect(g_limit.order))
     {
      if(!g_trade.OrderDelete(g_limit.order))
        {
         Flash("Annulation refusée : " + g_trade.ResultRetcodeDescription(), 6000);
         return true;
        }
      ClearLimit();
      Flash("Ordre en attente annulé", 3000);
      return true;
     }
   CheckLimit(); // déjà exécuté ou expiré : on le traite comme tel
   return true;
  }

// Enregistre le trade exécuté ; le TP de l'outil reste là où il a été placé
void HandleFill(const ulong posId)
  {
   if(!g_pending.active)
      return;
   if(GvHas(posId, "risk"))
     {
      g_pending.active = false;
      return;
     }
   ulong ticket = SelectPositionById(posId);
   if(ticket == 0)
      return; // pas encore visible : le timer réessaiera

   double   fill     = PositionGetDouble(POSITION_PRICE_OPEN);
   double   vol      = PositionGetDouble(POSITION_VOLUME);
   double   sl       = PositionGetDouble(POSITION_SL);
   double   tp       = PositionGetDouble(POSITION_TP);
   datetime openTime = (datetime)PositionGetInteger(POSITION_TIME);
   if(sl <= 0.0)
      sl = g_pending.slEff;

   ENUM_ORDER_TYPE otype = (g_pending.dir > 0) ? ORDER_TYPE_BUY : ORDER_TYPE_SELL;
   double comm       = MathMax(0.0, InpCommissionPerLot);
   double p          = 0.0;
   double lossPrice  = 0.0;
   if(OrderCalcProfit(otype, _Symbol, 1.0, fill, sl, p) && p < 0.0)
      lossPrice = -p;
   double lossPerLot = lossPrice + comm;
   double realRisk   = vol * lossPerLot;

   //--- Sans TP choisi (ancien ordre en R cible), le TP suit le vrai prix d'entrée
   double newTP = tp;
   double dist  = MathAbs(fill - sl);
   if(!g_pending.keepTp && lossPrice > 0.0 && dist > 0.0)
     {
      double vpp   = lossPrice / dist;
      double gross = g_pending.rCfg * lossPerLot + comm;
      double d2    = gross / vpp;
      double calc  = (g_pending.dir > 0) ? RoundToTick(fill + d2, 1) : RoundToTick(fill - d2, -1);
      if(MathAbs(calc - tp) >= TickSize() * 0.5)
        {
         bool ok = IsHedging() ? g_trade.PositionModify(ticket, sl, calc) : g_trade.PositionModify(_Symbol, sl, calc);
         uint rc = g_trade.ResultRetcode();
         if(ok && (rc == TRADE_RETCODE_DONE || rc == TRADE_RETCODE_NO_CHANGES))
            newTP = calc;
         else
            Flash("TP non recalé après glissement : " + g_trade.ResultRetcodeDescription(), 6000);
        }
     }

   double g        = 0.0;
   double rPlanned = 0.0;
   if(lossPerLot > 0.0 && newTP > 0.0 && OrderCalcProfit(otype, _Symbol, 1.0, fill, newTP, g))
      rPlanned = (g - comm) / lossPerLot;

   GvSet(posId, "risk",   realRisk);
   GvSet(posId, "rplan",  rPlanned);
   GvSet(posId, "dir",    g_pending.dir);
   GvSet(posId, "entry",  fill);
   GvSet(posId, "sl",     sl);
   GvSet(posId, "tp",     newTP);
   GvSet(posId, "lots",   vol);
   GvSet(posId, "spread", g_pending.spread);
   GvSet(posId, "tier",   g_pending.tierMult);
   GvSet(posId, "poc",    g_pending.poc);
   GvSet(posId, "open",   (double)openTime);
   GvSet(posId, "lvl",    0.0);
   GvSet(posId, "slwr",   sl);
   GvSet(posId, "man",    0.0);
   GvSet(posId, "tpst",   0.0);

   MarkEntry(posId, g_pending.dir, openTime, fill);

   if(g_pending.riskPlanned > 0.0 && realRisk > g_pending.riskPlanned * 1.10)
      Flash(StringFormat("Glissement : risque réel %.2f $ au lieu de %.2f $", realRisk, g_pending.riskPlanned), 6000);

   g_pending.active = false;
   g_statsDirty     = true;
  }

// Filet de sécurité si la confirmation d'exécution n'est pas arrivée
void CheckPending()
  {
   if(!g_pending.active)
      return;
   int total = PositionsTotal();
   for(int i = 0; i < total; i++)
     {
      ulong ticket = PositionGetTicket(i);
      if(ticket == 0)
         continue;
      if(PositionGetInteger(POSITION_MAGIC) != InpMagic)
         continue;
      if(PositionGetString(POSITION_SYMBOL) != _Symbol)
         continue;
      if((long)PositionGetInteger(POSITION_TIME) < (long)g_pending.sent - 10)
         continue;
      ulong posId = (ulong)PositionGetInteger(POSITION_IDENTIFIER);
      if(GvHas(posId, "risk"))
         continue;
      HandleFill(posId);
      return;
     }
   if((long)TimeTradeServer() - (long)g_pending.sent > 60)
     {
      g_pending.active = false;
      Flash("Exécution non confirmée : vérifie l'onglet Trade", 8000);
     }
  }

//+------------------------------------------------------------------+
//| Journal CSV                                                      |
//+------------------------------------------------------------------+
bool WriteJournalRow(const ulong posId, const string sym, const datetime closeTime, const double closePrice,
                     const double net, const double r)
  {
   int flags = FILE_READ | FILE_WRITE | FILE_CSV | FILE_ANSI | FILE_COMMON | FILE_SHARE_READ;
   int h = FileOpen(InpJournalFile, flags, ';');
   if(h == INVALID_HANDLE)
     {
      Print(TAG, "Journal inaccessible (fichier ouvert dans Excel ?), erreur ", GetLastError());
      return false;
     }
   if(FileSize(h) == 0)
      FileWrite(h, "Ouverture", "Clôture", "Symbole", "Sens", "Entrée", "SL", "TP", "Prix de sortie",
                "Spread entrée", "Lots", "Risque $", "R visé", "POC", "Palier %", "Résultat $", "Résultat R",
                "Durée min", "Position");
   FileSeek(h, 0, SEEK_END);

   int      dg       = (int)SymbolInfoInteger(sym, SYMBOL_DIGITS);
   datetime openTime = (datetime)GvGet(posId, "open", 0.0);
   long     durMin   = (openTime > 0 && closeTime > 0) ? ((long)closeTime - (long)openTime) / 60 : 0;

   FileWrite(h,
             TimeToString(openTime, TIME_DATE | TIME_MINUTES),
             TimeToString(closeTime, TIME_DATE | TIME_MINUTES),
             sym,
             (GvGet(posId, "dir", 0.0) > 0.0) ? "ACHAT" : "VENTE",
             Num(GvGet(posId, "entry"), dg),
             Num(GvGet(posId, "sl"), dg),
             Num(GvGet(posId, "tp"), dg),
             Num(closePrice, dg),
             Num(GvGet(posId, "spread"), dg),
             Num(GvGet(posId, "lots"), 2),
             Num(GvGet(posId, "risk"), 2),
             Num(GvGet(posId, "rplan"), 2),
             Num(GvGet(posId, "poc"), dg),
             Num(GvGet(posId, "tier") * 100.0, 0),
             Num(net, 2),
             Num(r, 2),
             IntegerToString(durMin),
             IntegerToString((long)posId));
   FileClose(h);
   return true;
  }

void JournalClosed(const ulong posId)
  {
   if(GvHas(posId, "done"))
      return;
   if(!HistorySelectByPosition(posId))
      return;
   int total = HistoryDealsTotal();
   if(total <= 0)
      return;

   double   net        = 0.0;
   double   closePrice = 0.0;
   datetime closeTime  = 0;
   string   sym        = "";
   bool     hasOut     = false;
   for(int i = 0; i < total; i++)
     {
      ulong d = HistoryDealGetTicket(i);
      if(d == 0)
         continue;
      net += HistoryDealGetDouble(d, DEAL_PROFIT) + HistoryDealGetDouble(d, DEAL_COMMISSION)
           + HistoryDealGetDouble(d, DEAL_SWAP)   + HistoryDealGetDouble(d, DEAL_FEE);
      if(sym == "")
         sym = HistoryDealGetString(d, DEAL_SYMBOL);
      long de = HistoryDealGetInteger(d, DEAL_ENTRY);
      if(de == DEAL_ENTRY_OUT || de == DEAL_ENTRY_OUT_BY || de == DEAL_ENTRY_INOUT)
        {
         hasOut = true;
         datetime t = (datetime)HistoryDealGetInteger(d, DEAL_TIME);
         if(t >= closeTime)
           {
            closeTime  = t;
            closePrice = HistoryDealGetDouble(d, DEAL_PRICE);
           }
        }
     }
   if(!hasOut)
      return;

   double risk = GvGet(posId, "risk", 0.0);
   double r    = (risk > 0.0) ? net / risk : 0.0;
   GvSet(posId, "res", r);

   if(InpWriteJournal && !WriteJournalRow(posId, sym, closeTime, closePrice, net, r))
      return; // nouvel essai au prochain passage du timer
   GvSet(posId, "done", 1.0);
   Print(TAG, "Position ", IntegerToString((long)posId), " fermée : ", SignedUsd(net), StringFormat(" (%.2fR)", r));
  }

//+------------------------------------------------------------------+
//| Ticket : saisie                                                  |
//+------------------------------------------------------------------+
void OnEditDone(const string name)
  {
   double v      = ParseNumber(ObjectGetString(0, name, OBJPROP_TEXT));
   double ts     = TickSize();
   double spread = (g_calc.valid) ? g_calc.spread : 0.0;
   double ref    = (g_calc.capRef > 0.0) ? g_calc.capRef : AccountInfoDouble(ACCOUNT_BALANCE);
   int    dir    = g_tool.dir;
   double adjSl  = (dir < 0 && InpSellSpreadAdjust && !g_tool.raw) ? spread : 0.0;
   double adjTp  = (dir < 0 && InpSellSpreadAdjust) ? spread : 0.0;
   double en     = (g_calc.valid && g_calc.dir == dir) ? g_calc.entry : ToolEntry();

   if(name == PFX + "E_RISK")
     {
      // Risque en $ ou en % du compte ; vide = celui du carnet ; jamais au-dessus du maximum
      double usd = (g_riskPct == 1) ? v / 100.0 * ref : v;
      g_lotsUser = 0.0;
      if(usd <= 0.0)
         g_riskUser = 0.0;
      else if(usd > g_calc.budget + 0.005)
        {
         g_riskUser = 0.0;
         Flash("Risque maximum " + Usd(g_calc.budget) + (InpUseCarnet ? " (carnet)" : " (palier)"), 5000);
        }
      else
         g_riskUser = usd;
     }
   else if(name == PFX + "E_LOTS")
     {
      g_riskUser = 0.0;
      g_lotsUser = v;
      if(v > 0.0 && g_calc.lossPerLot > 0.0 && NormalizeLotsDown(v) * g_calc.lossPerLot > g_calc.budget + 0.005)
         Flash("Lot limité au risque maximum de " + Usd(g_calc.budget), 5000);
     }
   else if(!g_tool.on || v <= 0.0)
      Flash("Trace d'abord l'outil : Sell ou Buy (S / W)");
   else if(name == PFX + "E_TP")
      g_tool.tp = v - adjTp;                                       // le champ montre le prix de l'ordre
   else if(name == PFX + "E_TPT")
      g_tool.tp = ((dir > 0) ? en + v * ts : en - v * ts) - adjTp;
   else if(name == PFX + "E_SL")
      g_tool.sl = v - adjSl;
   else if(name == PFX + "E_SLT")
      g_tool.sl = ((dir > 0) ? en - v * ts : en + v * ts) - adjSl;
   SaveTool();
  }

//+------------------------------------------------------------------+
//| Événements                                                       |
//+------------------------------------------------------------------+
int OnInit()
  {
   if(!InpUseCarnet && InpMode == MODE_FONDS &&
      (InpStartCapital <= 0.0 || InpLossBudget < 4 || InpFloorUsd < 0.0 || InpFloorUsd >= InpStartCapital ||
       InpTier1Max <= 0.0 || InpTier2Max <= InpTier1Max || InpTier3Max <= InpTier2Max ||
       InpTier2Pct <= 0.0 || InpTier3Pct <= 0.0 || InpTier4Pct <= 0.0 || InpTier2Pct > 10.0))
     {
      Print(TAG, "Fonds propres : capital > 0, au moins 4 pertes de budget, plancher sous le capital, paliers croissants");
      return INIT_PARAMETERS_INCORRECT;
     }
   if(!InpUseCarnet && InpMode == MODE_PROP &&
      (InpInitialBalance <= 0.0 || InpPropRiskUsd <= 0.0 ||
       InpTier1DDPct <= 0.0 || InpTier2DDPct <= InpTier1DDPct || InpTier1Mult <= 0.0 || InpTier2Mult <= 0.0))
     {
      Print(TAG, "Prop firm : capital et risque > 0, le palier 2 doit être plus profond que le palier 1");
      return INIT_PARAMETERS_INCORRECT;
     }
   if(InpStopMin <= 0.0 || InpStopMax <= InpStopMin || InpLimitMin < 1 || InpPendingMin < 0 || InpMinR < 1.0 ||
      InpDefStopUsd <= 0.0 || InpToolBars < 5)
     {
      Print(TAG, "Outil : stop par défaut > 0, minimum > 0 et maximum au-dessus, largeur d'au moins 5 bougies, R minimum d'au moins 1");
      return INIT_PARAMETERS_INCORRECT;
     }
   if(InpMaxTradesDay < 1 || InpMaxLossesDay < 1 || InpMaxLossesWeek < 1 || InpDayStopR <= 0.0 || InpWeekStopR <= 0.0 ||
      InpMaxOpenPositions < 1 || InpResetHourServer < 0 || InpResetHourServer > 23 || InpRTarget < InpMinR)
     {
      Print(TAG, "Paramètres invalides : limites du jour et de la semaine, heure de remise à zéro ou R de l'outil sous le R minimum");
      return INIT_PARAMETERS_INCORRECT;
     }

   int reason = UninitializeReason();
   if(!g_loaded)
     {
      g_pending.active = false;
      g_pending.order  = 0;
     }
   if(!g_loaded || reason == REASON_PARAMETERS)
      g_rTarget = InpRTarget;
   g_lastSymbol = _Symbol;
   g_loaded     = true;
   g_k          = MathMax(0.75, MathMin(3.0, TerminalInfoInteger(TERMINAL_SCREEN_DPI) / 96.0));

   g_trade.SetExpertMagicNumber((ulong)InpMagic);
   g_trade.LogLevel(LOG_LEVEL_ERRORS);
   if(!IsHedging())
      Print(TAG, "Compte en mode netting : l'outil fonctionne, mais il est conçu pour un compte hedging.");

   g_autoOn    = InpAutoOn;
   g_atrHandle = iATR(_Symbol, InpAutoTF, (InpAtrPeriod > 0) ? InpAtrPeriod : 14);
   if(g_atrHandle == INVALID_HANDLE)
      Print(TAG, "ATR indisponible : le garde-fou anti-bruit sera ignoré.");
   //--- Version 3 : l'armement sur bougie n'existe plus
   GlobalVariablesDeleteAll(GvBase() + "arm");
   ObjectDelete(0, PFXM + "ARM");
   g_drag      = DRAG_NONE;
   g_mouseDown = false;
   g_editing   = "";
   LoadTool();
   LoadLimit();
   CleanMarks();
   RedrawMarks();
   g_dayStart   = 0;    // force la relecture de la référence du jour
   g_statsDirty = true;
   CleanupRegistry();
   ScanRegistry();
   RefreshDayStats();
   RefreshNews();
   ChartSetInteger(0, CHART_EVENT_MOUSE_MOVE, true);   // poignées de l'outil à la souris
   CreatePanel();
   UpdateAll(true);
   EventSetMillisecondTimer(500);
   return INIT_SUCCEEDED;
  }

void OnDeinit(const int reason)
  {
   EventKillTimer();
   GlobalVariableDel(Bridge("calc_on"));
   if(g_atrHandle != INVALID_HANDLE)
      IndicatorRelease(g_atrHandle);
   if(g_drag != DRAG_NONE)
     {
      g_drag = DRAG_NONE;
      ChartSetInteger(0, CHART_MOUSE_SCROLL, g_scrollWas);
     }
   ObjectsDeleteAll(0, PFX);
   ObjectsDeleteAll(0, PFXT);       // l'outil est sauvegardé : retracé au prochain démarrage
   if(reason == REASON_REMOVE || reason == REASON_PROGRAM)
      ObjectsDeleteAll(0, PFXM);
   ChartRedraw(0);
  }

void OnTick()
  {
   UpdateAll(false);
  }

void OnTimer()
  {
   CheckPending();
   CheckLimit();
   static uint lastScan = 0;
   if(GetTickCount() - lastScan > 60000)
     {
      lastScan = GetTickCount();
      ScanRegistry();
     }
   UpdateAll(false);
  }

void OnTradeTransaction(const MqlTradeTransaction &trans, const MqlTradeRequest &request, const MqlTradeResult &result)
  {
   if(trans.type != TRADE_TRANSACTION_DEAL_ADD)
      return;
   g_statsDirty = true;

   ulong deal = trans.deal;
   if(deal == 0 || !HistoryDealSelect(deal))
      return;
   long  dentry = HistoryDealGetInteger(deal, DEAL_ENTRY);
   ulong posId  = (ulong)HistoryDealGetInteger(deal, DEAL_POSITION_ID);
   ulong order  = (ulong)HistoryDealGetInteger(deal, DEAL_ORDER);
   long  magic  = HistoryDealGetInteger(deal, DEAL_MAGIC);

   if(dentry == DEAL_ENTRY_IN)
     {
      if(g_limit.active && order == g_limit.order)
         LimitFilled(posId);
      else if(g_pending.active && (order == g_pending.order || magic == InpMagic))
         HandleFill(posId);
     }
   else if(dentry == DEAL_ENTRY_OUT || dentry == DEAL_ENTRY_OUT_BY || dentry == DEAL_ENTRY_INOUT)
     {
      if(GvHas(posId, "risk") && SelectPositionById(posId) == 0)
         JournalClosed(posId);
     }
  }

// D : stop au palier suivant (BE, R1, R2, R3)
void NextStop()
  {
   ulong tk = CurrentPosition();
   int   lv = (tk > 0) ? (int)GvGet((ulong)PositionGetInteger(POSITION_IDENTIFIER), "lvl", 0.0) : 0;
   MoveStop((lv + 1 > 4) ? 4 : lv + 1);
  }

void OnChartEvent(const int id, const long &lparam, const double &dparam, const string &sparam)
  {
   //--- Souris : poignées de l'outil (stop, objectif, entrée, largeur, outil entier)
   if(id == CHARTEVENT_MOUSE_MOVE)
     {
      int  x    = (int)lparam;
      int  y    = (int)dparam;
      bool down = ((StringToInteger(sparam) & 1) == 1);
      if(down && !g_mouseDown)
        {
         g_mouseDown = true;
         if(g_tool.on && !InPanel(x, y))
            StartDrag(x, y);
        }
      else if(!down && g_mouseDown)
        {
         g_mouseDown = false;
         if(g_drag != DRAG_NONE)
            EndDrag();
        }
      else if(down && g_drag != DRAG_NONE)
         DragTo(x, y);
      return;
     }

   //--- Zoom ou défilement : les étiquettes de l'outil se recalent
   if(id == CHARTEVENT_CHART_CHANGE)
     {
      if(g_tool.on && g_drag == DRAG_NONE)
        {
         DrawTool();
         ChartRedraw(0);
        }
      return;
     }

   if(id == CHARTEVENT_CLICK)
     {
      g_editing = "";
      return;
     }

   //--- Clavier : main gauche sur W S Q A D E, pouce sur Espace
   if(id == CHARTEVENT_KEYDOWN && InpKeysOn)
     {
      int key = (int)lparam;
      if(key == 87 || key == 66)       // W ou B : outil long (achat)
         NewTool(1);
      else if(key == 83)               // S : outil court (vente)
         NewTool(-1);
      else if(key == 67)               // C : outil depuis le dernier signal du Carnet XAU
         LoadSignal();
      else if(key == 32)               // Espace : envoyer l'ordre de l'outil
         Execute();
      else if(key == 81 || key == 27)  // Q ou Échap : annuler l'ordre en attente, sinon fermer l'outil
        {
         if(!CancelLimit() && g_tool.on)
           {
            ClearTool();
            Flash("Outil fermé", 2000);
           }
        }
      else if(key == 65)               // A : break-even
         MoveStop(1);
      else if(key == 68)               // D : trailing, palier suivant
         NextStop();
      else if(key == 69)               // E E : fermer, deux appuis en moins de 1,5 s
        {
         if(GetTickCount() - g_lastKeyE < 1500)
           {
            g_lastKeyE = 0;
            CloseCurrent();
           }
         else
           {
            g_lastKeyE = GetTickCount();
            Flash("E encore une fois pour fermer", 1500);
           }
        }
      UpdateAll(true);
      return;
     }

   if(id == CHARTEVENT_CUSTOM + 101)
     {
      UpdateAll(true);
      return;
     }

   if(id == CHARTEVENT_OBJECT_ENDEDIT)
     {
      if(StringFind(sparam, PFX + "E_") != 0)
         return;
      g_editing = "";
      OnEditDone(sparam);
      UpdateAll(true);
      return;
     }

   if(id == CHARTEVENT_OBJECT_CLICK)
     {
      if(StringFind(sparam, PFX) != 0)
         return;
      long type = ObjectGetInteger(0, sparam, OBJPROP_TYPE);
      if(type == OBJ_EDIT)
        {
         g_editing = sparam;            // saisie en cours : le champ n'est plus réécrit
         return;
        }
      g_editing = "";
      if(type == OBJ_BUTTON)
         ObjectSetInteger(0, sparam, OBJPROP_STATE, false);

      if(StringFind(sparam, PFX + "SELL") == 0)
         NewTool(-1);
      else if(StringFind(sparam, PFX + "BUY") == 0)
         NewTool(1);
      else if(sparam == PFX + "TAB0")
         SetKind(0);
      else if(sparam == PFX + "TAB1")
         SetKind(1);
      else if(sparam == PFX + "TAB2")
         SetKind(2);
      else if(sparam == PFX + "RMODE")
         g_riskPct = 1 - g_riskPct;     // risque en $ ou en % du compte
      else if(sparam == PFX + "B_TCLOSE")
        {
         if(!CancelLimit())
            ClearTool();
        }
      else if(sparam == PFX + "B_SEND")
         Execute();
      else if(sparam == PFX + "B_CLOSE")
         CloseCurrent();
      else if(sparam == PFX + "B_AUTO")
        {
         g_autoOn = !g_autoOn;
         Flash(g_autoOn ? "Gestion automatique activée" : "Gestion automatique désactivée", 3000);
        }
      else if(sparam == PFX + "B_BE")
         MoveStop(1);
      else if(sparam == PFX + "B_TRAIL")
         NextStop();
      else
         return;
      UpdateAll(true);
     }
  }
//+------------------------------------------------------------------+
