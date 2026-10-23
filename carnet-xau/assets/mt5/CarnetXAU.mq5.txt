//+------------------------------------------------------------------+
//| CarnetXAU.mq5 — version 2.16.0                                   |
//|                                                                  |
//| 1. Envoie au Carnet XAU les trades fermés, le solde, l'équité,   |
//|    la perte du jour et les bougies du courtier (D1 à M1), toutes |
//|    les 15 minutes (analyses de séance et rejeu du soir).         |
//| 2. Lit le plan de Claude et le trace sur le graphique : zones,   |
//|    niveaux, invalidation, range attendu. Redessine dès que le    |
//|    plan change (relecture de 17 h New York, zone cassée…).       |
//| 3. Surveille l'invalidation et les zones en temps réel (M15).    |
//| 4. Signal BUY NOW / SELL NOW quand les conditions du plan sont   |
//|    réunies, position longue ou courte dessinée, lot calculé.     |
//|    Alertes : fenêtre MT5, notification mobile, Telegram.         |
//|                                                                  |
//| SIGNAL SEULEMENT : l'EA n'ouvre, ne modifie et ne ferme aucune   |
//| position. Vous décidez et passez l'ordre vous-même.              |
//|                                                                  |
//| Chaque signal est suivi comme un trade virtuel « Signal Claude » |
//| (50 % à 2R puis stop au prix d'entrée) jusqu'à sa clôture ; le   |
//| résultat en R part au carnet : taux de réussite propre aux       |
//| signaux, séparé de vos trades.                                   |
//|                                                                  |
//| PC éteint : au redémarrage, l'EA relit les bougies manquées      |
//| (7 jours au plus) pour clôturer les signaux en cours et mettre   |
//| à jour zones et invalidation, et renvoie 30 jours de trades.     |
//| Aucun signal n'est émis pour la période où MT5 était fermé.      |
//|                                                                  |
//| Installation :                                                   |
//| 1. Renommer ce fichier en CarnetXAU.mq5, le placer dans          |
//|    Fichier › Ouvrir le dossier des données › MQL5 › Experts,     |
//|    l'ouvrir dans MetaEditor et compiler (F7).                    |
//| 2. MT5 : Outils › Options › Expert Advisors : cocher             |
//|    « Autoriser WebRequest pour les URL listées » et ajouter      |
//|    https://revnefty.store                                        |
//| 3. Notifications mobiles : Outils › Options › Notifications,     |
//|    activer et saisir le MetaQuotes ID de l'application MT5.      |
//| 4. Glisser l'EA sur un graphique XAUUSD (M1 conseillé) et coller |
//|    la clé MT5 du compte (onglet Comptes du carnet).              |
//|                                                                  |
//| Pont avec le Calculateur XAU (même compte, autre graphique) :    |
//| - chaque signal lui est transmis : touche C pour le charger,     |
//|   Espace pour l'envoyer (l'ordre porte la référence du signal) ; |
//| - il donne le risque du palier (lot des signaux) et son état :   |
//|   à l'arrêt (pertes, plancher), le signal dit de ne pas prendre ;|
//| - il reçoit du carnet le risque, les limites du jour, l'étape et |
//|   l'objectif du compte (variables cx_*) : il s'y règle seul.     |
//|                                                                  |
//| Affichage façon TradingView : police Trebuchet MS, couleurs      |
//| semi-transparentes sur le fond du graphique, et mise à l'échelle |
//| de l'écran (Windows à 125 % ou 150 %) : le texte ne déborde plus.|
//| Plan et signaux dessinés en CCanvas (2.15), comme l'indicateur    |
//| LGD+CSD+Fib : zones vert achat / rouge vente, texte à la taille   |
//| des blocs BB, étiquettes pleines ; tableau du Calculateur XAU.    |
//| 2.16 : départ des niveaux et zones sur leur bougie d'origine     |
//| (champ « origin » du plan, heure du serveur), sinon sur la mèche |
//| H1 qui marque le prix (côté selon le type H / L), sinon au bord. |
//|                                                                  |
//| Règles du signal (calculées à partir du plan de Claude) :        |
//| - le prix touche une zone de Claude non cassée ;                 |
//| - extrême de la touche dans la zone (mèche tolérée) ;            |
//| - setup 3 (iFVG) : une bougie M1 clôture au-delà du FVG          |
//|   contraire laissé par la jambe qui a amené le prix dans la zone |
//|   (FVG baissier pour un achat, haussier pour une vente) : le FVG |
//|   est inversé et devient le support (résistance) ;               |
//| - sinon cassure de structure M1 : clôture au-delà du dernier     |
//|   sommet pivot (achat) ou creux pivot (vente) avant l'extrême ;  |
//|   setup 1 si l'extrême a balayé un niveau de liquidité de Claude |
//|   (ou le bord de la zone), sinon setup 2, seulement dans le sens |
//|   du biais dominant ;                                            |
//| - stop derrière l'extrême + marge, entre 1,50 et 8,00 $ ; s'il  |
//|   est plus large : BUY LIMIT / SELL LIMIT au prix où le stop     |
//|   revient à 8,00 $ (repli attendu, valable 30 minutes) ;         |
//| - au moins 2R de place jusqu'à la prochaine zone ou niveau       |
//|   opposé (objectif = ce niveau, sinon 3R) ;                      |
//| - entrée « retour FVG » suivie en parallèle (trade virtuel, sans |
//|   alerte) : ordre limite au bord de l'iFVG (setup 3) ou du FVG   |
//|   laissé par la cassure (setups 1 et 2), même stop, 30 min ;     |
//|   le carnet compare les deux entrées ;                           |
//| - filtres : pas d'annonce dans 15 min (ni dans les 30 min        |
//|   passées), spread ≤ spread max % du stop, limites du jour       |
//|   (trades, pertes, 80 % de la perte journalière), invalidation   |
//|   non touchée dans ce sens.                                      |
//+------------------------------------------------------------------+
#property copyright "Carnet XAU"
#property version   "2.16"
#property description "Plan de Claude sur le graphique, signaux d'exécution (signal seulement) et synchronisation du Carnet XAU."

#include <Canvas\Canvas.mqh>

input string InpUrl         = "https://revnefty.store/wp-json/carnet-xau/v1/mt5"; // Adresse du carnet
input string InpKey         = "";    // Clé MT5 du compte (onglet Comptes)
input string InpSymbol      = "XAU"; // Trades envoyés : symboles qui contiennent… (vide = tous)
input int    InpDaysBack    = 3;     // Jours d'historique renvoyés à chaque synchronisation
input int    InpSnapshotSec = 60;    // Intervalle de synchronisation, en secondes (15 minimum)
input bool   InpDrawPlan    = true;  // Tracer le plan de Claude
input bool   InpTVStyle     = true;  // Graphique au style TradingView clair (fond blanc, sans grille)
input bool   InpSignals     = true;  // Calculer les signaux BUY NOW / SELL NOW
input double InpSLBuffer    = 0.30;  // Marge du stop derrière l'extrême ($, en plus du spread)
input int    InpTouchBars   = 30;    // Bougies M1 où chercher la touche de zone
input int    InpSwingBars   = 20;    // Bougies M1 avant l'extrême où chercher le sommet / creux pivot et le FVG
input double InpFvgMin      = 0.30;  // Taille minimale d'un FVG ($)
input bool   InpRetest      = true;  // Suivre aussi l'entrée « retour FVG » (trade virtuel, sans alerte)
input int    InpCooldownMin = 60;    // Minutes sans nouveau signal sur la même zone
input bool   InpUseLimit    = true;  // Stop trop large : proposer un ordre limite (stop ramené au maximum)
input int    InpLimitMin    = 30;    // Validité d'un signal limite, en minutes
input bool   InpAlertPopup  = true;  // Alerte : fenêtre et son MT5
input bool   InpAlertPush   = true;  // Alerte : notification mobile MT5
input bool   InpAlertCarnet = true;  // Alerte : carnet et Telegram

//--- Plan reçu du carnet
struct CxZone
  {
   string key;
   string type;
   string tf;
   double lo;
   double hi;
   bool   buy;
   int    strength;
   string state;     // vierge, testee, cassee
   string origin;    // bougie d'origine « jj/mm hh:mm », heure du serveur (vide : carnet d'avant 2.15)
   datetime lastSignal;
  };
struct CxLevel { double price; string type; string origin; };
//--- Scénario d'exécution de la dernière analyse de séance (outil position longue / courte).
struct CxPos { bool buy; double entry; double sl; double tp; string label; };
CxPos    g_pos[];
double   g_sinv = 0;      // invalidation de la dernière analyse de séance

CxZone   g_zones[];
CxLevel  g_levels[];
datetime g_news[];
string   g_newsName[];
string   g_hash = "", g_week = "";
int      g_bull = 0, g_bear = 0, g_range = 0;
double   g_inval = 0, g_rlo = 0, g_rhi = 0, g_ref = 0;
bool     g_invalidated = false;
double   g_risk = 50, g_riskRed = 35, g_redAt = 300, g_contract = 100, g_spreadMax = 15;
double   g_stopMin = 1.5, g_stopMax = 8.0, g_dailyLim = 300, g_size = 6000, g_floor = 5400;
int      g_maxTrades = 4, g_maxLoss = 3;
//--- Étape du compte (ligne « G| » du plan), transmise au Calculateur XAU avec le risque et les limites.
string   g_phase = "";
double   g_goal = 0, g_gain = 0, g_weekStopR = 4;
int      g_daysDone = 0, g_minDays = 0, g_weekLoss = 4;
bool     g_ownKind = false, g_rOk = false;
datetime g_planAt = 0;          // dernière lecture réussie du plan (heure locale)
//--- Réglages des signaux : valeurs des paramètres d'entrée, remplacées par celles que Claude
//    ajuste dans le carnet après le rejeu du soir (ligne « S| » du plan).
int      g_touch = 30, g_swing = 20, g_limitMin = 30, g_cooldown = 60;
double   g_fvg = 0.30, g_slbuf = 0.30;
bool     g_planOk = false;

//--- Trade virtuel « Signal Claude » : chaque signal est suivi jusqu'à sa clôture, pris ou non,
//    avec la gestion du carnet (50 % à 2R, puis stop au prix d'entrée). Sauvegardé dans un fichier.
struct CxTrack
  {
   string   ref;       // identifiant envoyé au carnet
   string   id;        // préfixe des objets dessinés
   string   label;
   bool     buy;
   double   entry;
   double   sl;
   double   tp;
   double   stop;
   int      status;    // 0 limite en attente, 1 ouvert, 2 terminé
   bool     hit2r;
   double   mfe;       // meilleur mouvement, en R
   double   mae;       // pire mouvement, en R
   datetime expires;   // fin de validité de la limite
   datetime opened;
  };
CxTrack  g_tracks[];
#define TRACK_FILE "CarnetXAU_signaux.csv"

//--- Dessins des signaux (outil position de TradingView), gardés 7 jours dans un fichier : ils restent
//    affichés après un changement d'unité de temps ou un redémarrage, comme les objets des versions 2.14.
struct CxDraw
  {
   string   id;
   datetime t;
   datetime t2;
   bool     buy;
   double   entry;
   double   sl;
   double   tp;
   double   p2r;       // 0 : variante « retour FVG » (trait d'entrée seul)
   string   text;
   bool     grey;      // limite annulée
  };
CxDraw   g_draws[];
#define DRAW_FILE "CarnetXAU_dessins.csv"
#define TRACK_MAX_HOURS 24

//--- État
bool     g_dirty = true;
bool     g_firstSync = true;   // premier envoi après le démarrage : 30 jours d'historique
datetime g_lastFull = 0, g_lastCandles = 0, g_lastM1 = 0, g_lastSignal = 0;
datetime g_doneM1 = 0, g_doneM15 = 0;   // dernières bougies traitées (rattrapage après un arrêt du PC)
string   GV_M1 = "", GV_M15 = "";         // noms des variables globales du terminal (par compte et symbole)
#define CATCHUP_DAYS 7
string   g_status = "";
int      g_signalCount = 0;

#define PFX_PLAN "CXP_"
#define PFX_SIG  "CXS_"
#define PFX_UI   "CXU_"

//--- Police de TradingView sous Windows ; Arial pour les symboles ▲ ▼ ○ ↔, absents de Trebuchet MS.
#define FONT_TV  "Trebuchet MS"
#define FONT_TVB "Trebuchet MS Bold"
//--- Échelle de l'écran : Windows à 125 % ou 150 % agrandit le texte (en points) mais pas les
//    distances (en pixels) ; elles sont multipliées par DPI / 96 pour que rien ne se chevauche.
double   g_k = 1.0;
int      Dp(double px) { return (int)MathRound(px * g_k); }
//--- Canvas du plan (dessiné dans DrawPlan, redessiné par CvRender).
CCanvas  g_cvs;
bool     g_cvsOk = false;
string   g_cvsName = "";
int      g_cvW = 0, g_cvH = 0;

//+------------------------------------------------------------------+
int OnInit()
  {
   if(StringLen(InpKey) < 16)
     {
      Alert("Carnet XAU : collez la clé MT5 du compte dans les paramètres de l'EA.");
      return(INIT_PARAMETERS_INCORRECT);
     }
   EventSetTimer((int)MathMax(15, InpSnapshotSec));
   g_touch = InpTouchBars; g_swing = InpSwingBars; g_fvg = InpFvgMin; g_slbuf = InpSLBuffer; g_limitMin = InpLimitMin; g_cooldown = InpCooldownMin;
   g_hash = "";                 // changement d'unité de temps : les variables restent, les objets non → tout redessiner
   g_lastM1 = 0;
   LoadTracks();
   LoadDraws();
   ObjectsDeleteAll(0, PFX_SIG);   // objets des signaux des versions 2.14 et avant : redessinés en canvas
   string gv = "CarnetXAU_" + IntegerToString(AccountInfoInteger(ACCOUNT_LOGIN)) + "_" + _Symbol;
   GV_M1  = gv + "_M1";
   GV_M15 = gv + "_M15";
   if(GlobalVariableCheck(GV_M1))
      g_doneM1 = (datetime)GlobalVariableGet(GV_M1);
   if(GlobalVariableCheck(GV_M15))
      g_doneM15 = (datetime)GlobalVariableGet(GV_M15);
   g_k = MathMax(0.75, MathMin(3.0, TerminalInfoInteger(TERMINAL_SCREEN_DPI) / 96.0));
   ApplyTVStyle();
   ChartSetInteger(0, CHART_FOREGROUND, false);   // bougies derrière le tableau et le plan
   DrawPlan();                     // canvas créé avant le tableau : le tableau reste au-dessus
   Sync();
   FetchPlan();
   PublishCarnet();
   SendCandles();
   DrawPanel();
   return(INIT_SUCCEEDED);
  }

void OnDeinit(const int reason)
  {
   EventKillTimer();
   GlobalVariableDel(Bridge("cx_on"));   // le Calculateur sait que le carnet n'est plus relié
   if(reason == REASON_REMOVE)
      RestoreStyle();
   if(g_cvsOk)
      g_cvs.Destroy();
   g_cvsOk = false;
   ObjectsDeleteAll(0, PFX_PLAN);
   ObjectsDeleteAll(0, PFX_UI);
  }

void OnTimer()
  {
   static datetime day = 0;
   if(ServerDayStart() != day)
     {
      day = ServerDayStart();
      g_signalCount = 0;          // compteur « aujourd'hui »
     }
   Sync();
   FetchPlan();
   PublishCarnet();
   if(TimeCurrent() - g_lastCandles >= 900)
      SendCandles();
   DrawPanel();
  }

//--- Zoom ou défilement : les noms au milieu des lignes et les prix du bord droit se recalent.
void OnChartEvent(const int id, const long &lparam, const double &dparam, const string &sparam)
  {
   static long scale = -1, first = -1, width = -1;
   if(id != CHARTEVENT_CHART_CHANGE)
      return;
   long s = ChartGetInteger(0, CHART_SCALE), f = ChartGetInteger(0, CHART_FIRST_VISIBLE_BAR), w = ChartGetInteger(0, CHART_WIDTH_IN_BARS);
   if(s == scale && f == first && w == width)
     {
      CvRender();               // échelle des prix déplacée : le canvas suit
      return;
     }
   scale = s; first = f; width = w;
   DrawPlan();
  }

void OnTick()
  {
   //--- Échelle automatique des prix : le canvas du plan suit le graphique.
   static double pmx = 0, pmn = 0;
   double mx = ChartGetDouble(0, CHART_PRICE_MAX, 0), mn = ChartGetDouble(0, CHART_PRICE_MIN, 0);
   if(g_cvsOk && (mx != pmx || mn != pmn))
     {
      pmx = mx; pmn = mn;
      CvRender();
     }
   datetime m1 = iTime(_Symbol, PERIOD_M1, 0);
   if(m1 == g_lastM1)
      return;
   g_lastM1 = m1;               // nouvelle bougie M1 : la précédente vient de clôturer
   PublishCarnet();             // signe de vie pour le Calculateur XAU
   static datetime lastBar = 0;
   datetime bar = iTime(_Symbol, PERIOD_CURRENT, 0);
   if(bar != lastBar)
     {
      lastBar = bar;
      DrawPlan();               // le scénario et les étiquettes suivent la dernière bougie
     }
   Monitor();                   // invalidation et zones sur clôture M15 (et rattrapage après un arrêt)
   ProcessTracks();             // signaux en cours, bougie par bougie depuis la dernière traitée
   DrawPanel();                 // zones les plus proches du prix
   if(InpSignals && g_planOk && !PendingLimit())
      CheckSignals();
  }

//+------------------------------------------------------------------+
//| Suivi des signaux (trades virtuels)                               |
//+------------------------------------------------------------------+
bool PendingLimit()
  {
   for(int i = 0; i < ArraySize(g_tracks); i++)
      if(g_tracks[i].status == 0 && StringFind(g_tracks[i].label, "RETOUR") < 0)
         return true;                               // la variante « retour FVG » ne bloque rien
   return false;
  }

void SaveTracks()
  {
   int h = FileOpen(TRACK_FILE, FILE_WRITE | FILE_CSV | FILE_ANSI, ';');
   if(h == INVALID_HANDLE)
      return;
   for(int i = 0; i < ArraySize(g_tracks); i++)
     {
      CxTrack t = g_tracks[i];
      if(t.status == 2)
         continue;                               // seuls les signaux en cours sont gardés
      FileWrite(h, t.ref, t.id, t.label, (int)t.buy, Num(t.entry, 2), Num(t.sl, 2), Num(t.tp, 2), Num(t.stop, 2),
                t.status, (int)t.hit2r, Num(t.mfe, 2), Num(t.mae, 2), (long)t.expires, (long)t.opened);
     }
   FileClose(h);
  }

void LoadTracks()
  {
   ArrayResize(g_tracks, 0);
   if(!FileIsExist(TRACK_FILE))
      return;
   int h = FileOpen(TRACK_FILE, FILE_READ | FILE_CSV | FILE_ANSI, ';');
   if(h == INVALID_HANDLE)
      return;
   while(!FileIsEnding(h))
     {
      string ref = FileReadString(h);
      if(ref == "")
         break;
      int k = ArraySize(g_tracks);
      ArrayResize(g_tracks, k + 1);
      g_tracks[k].ref     = ref;
      g_tracks[k].id      = FileReadString(h);
      g_tracks[k].label   = FileReadString(h);
      g_tracks[k].buy     = (StringToInteger(FileReadString(h)) == 1);
      g_tracks[k].entry   = StringToDouble(FileReadString(h));
      g_tracks[k].sl      = StringToDouble(FileReadString(h));
      g_tracks[k].tp      = StringToDouble(FileReadString(h));
      g_tracks[k].stop    = StringToDouble(FileReadString(h));
      g_tracks[k].status  = (int)StringToInteger(FileReadString(h));
      g_tracks[k].hit2r   = (StringToInteger(FileReadString(h)) == 1);
      g_tracks[k].mfe     = StringToDouble(FileReadString(h));
      g_tracks[k].mae     = StringToDouble(FileReadString(h));
      g_tracks[k].expires = (datetime)StringToInteger(FileReadString(h));
      g_tracks[k].opened  = (datetime)StringToInteger(FileReadString(h));
     }
   FileClose(h);
  }

//--- t : heure du serveur de l'événement (clôture de la bougie), envoyée en UTC.
void TrackEvent(int i, string status, string extra, datetime t)
  {
   SendEvent("{\"type\":\"signal_update\",\"ref\":" + JsonText(g_tracks[i].ref) + ",\"status\":\"" + status + "\"" + extra
             + ",\"time\":" + IntegerToString((long)t - ServerOffset()) + "}");
  }

//--- Clôture d'un trade virtuel : résultat en R envoyé au carnet.
void CloseTrack(int i, double r, string when, datetime t)
  {
   g_tracks[i].status = 2;
   string st  = (r > 0.05) ? "gagne" : (r < -0.05 ? "perdu" : "be");
   string res = (r >= 0 ? "+" : "-") + Fr(MathAbs(r), 1) + "R";
   g_status = "Signal Claude " + g_tracks[i].label + " clôturé : " + res + when + ".";
   DrawUpdate(g_tracks[i].id, g_tracks[i].label + " : résultat " + res, false);
   TrackEvent(i, st, ",\"result_r\":" + Num(r, 2) + ",\"reached_2r\":" + (g_tracks[i].hit2r ? "true" : "false")
              + ",\"mfe\":" + Num(g_tracks[i].mfe, 2) + ",\"mae\":" + Num(g_tracks[i].mae, 2), t);
   ChartRedraw();
   DrawPanel();
  }

//--- Bougies M1 clôturées depuis la dernière traitée (toutes, si le PC a été éteint), dans l'ordre.
void ProcessTracks()
  {
   datetime last = iTime(_Symbol, PERIOD_M1, 1);
   if(last == 0)
      return;
   bool open = false;
   for(int i = 0; i < ArraySize(g_tracks); i++)
      if(g_tracks[i].status != 2)
         open = true;
   if(open && g_doneM1 > 0 && last > g_doneM1)
     {
      datetime from = (datetime)MathMax((double)(g_doneM1 + 60), (double)(last - CATCHUP_DAYS * 86400));
      MqlRates r[];
      int n = CopyRates(_Symbol, PERIOD_M1, from, last, r);   // r[0] = la plus ancienne
      for(int j = 0; j < n; j++)
         if(r[j].time > g_doneM1)
            ProcessBar(r[j].high, r[j].low, r[j].close, r[j].time + 60, last - r[j].time > 120);
     }
   if(last != g_doneM1)
     {
      g_doneM1 = last;
      GlobalVariableSet(GV_M1, (double)g_doneM1);
     }
  }

//--- Une bougie M1 clôturée (à l'heure t) : limite déclenchée ou annulée, stop, 2R, objectif,
//    retour au prix d'entrée, clôture au bout de 24 h. late = bougie rattrapée après un arrêt.
void ProcessBar(double hi, double lo, double cl, datetime t, bool late)
  {
   bool   changed = false;
   string when = late ? " (pendant l'arrêt de MT5, à " + TimeToString(t, TIME_DATE | TIME_MINUTES) + ")" : "";
   for(int i = 0; i < ArraySize(g_tracks); i++)
     {
      if(g_tracks[i].status == 2)
         continue;
      bool   buy = g_tracks[i].buy;
      double e   = g_tracks[i].entry, st = g_tracks[i].stop;
      if(g_tracks[i].status == 0)
        {
         if(t - 60 >= g_tracks[i].expires)
           {
            g_tracks[i].status = 2;
            changed = true;
            DrawUpdate(g_tracks[i].id, g_tracks[i].label + " annulé (prix non atteint en " + IntegerToString(g_limitMin) + " min)", true);
            bool variant = StringFind(g_tracks[i].label, "RETOUR") >= 0;   // variante suivie sans alerte
            if(!late && !variant)
               Notify("Carnet XAU : " + g_tracks[i].label + " " + Fr(e, 2) + " annulé, prix non atteint en " + IntegerToString(g_limitMin) + " min.");
            TrackEvent(i, "annule", "", t);
            g_status = g_tracks[i].label + " annulé (délai écoulé).";
            ChartRedraw();
           }
         else if(buy ? lo <= e : hi >= e)
           {
            g_tracks[i].status = 1;
            g_tracks[i].opened = t - 60;
            changed = true;
            if(!late && StringFind(g_tracks[i].label, "RETOUR") < 0)
               Notify("Carnet XAU : " + g_tracks[i].label + " atteint à " + Fr(e, 2) + " — conditions réunies, à vous de décider.");
            TrackEvent(i, "ouvert", "", t);
           }
         continue;
        }
      // Position ouverte : mouvements en R depuis l'entrée.
      double up   = buy ? (hi - e) / st : (e - lo) / st;   // meilleur mouvement de la bougie
      double down = buy ? (lo - e) / st : (e - hi) / st;   // pire mouvement de la bougie
      g_tracks[i].mfe = MathMax(g_tracks[i].mfe, up);
      g_tracks[i].mae = MathMin(g_tracks[i].mae, down);
      double rrTp = MathAbs(g_tracks[i].tp - e) / st;
      changed = true;
      if(!g_tracks[i].hit2r)
        {
         if(down <= -1.0)
            CloseTrack(i, -1.0, when, t);                     // stop touché (prudence : vérifié en premier)
         else if(up >= 2.0)
           {
            g_tracks[i].hit2r = true;                      // 50 % sortis à 2R, stop au prix d'entrée
            if(up >= rrTp)
               CloseTrack(i, 1.0 + 0.5 * rrTp, when, t);
           }
        }
      else
        {
         if(up >= rrTp)
            CloseTrack(i, 1.0 + 0.5 * rrTp, when, t);         // objectif atteint
         else if(down <= 0)
            CloseTrack(i, 1.0, when, t);                      // retour au prix d'entrée : +1R acquis à 2R
        }
      // Au-delà de 24 h : clôture virtuelle au dernier cours.
      if(g_tracks[i].status == 1 && t - g_tracks[i].opened > TRACK_MAX_HOURS * 3600)
        {
         double now = buy ? (cl - e) / st : (e - cl) / st;
         CloseTrack(i, g_tracks[i].hit2r ? 1.0 + 0.5 * now : now, when, t);
        }
     }
   if(changed)
      SaveTracks();
  }

void OnTradeTransaction(const MqlTradeTransaction &trans, const MqlTradeRequest &request, const MqlTradeResult &result)
  {
   if(trans.type == TRADE_TRANSACTION_DEAL_ADD)
      g_dirty = true;
  }

//+------------------------------------------------------------------+
//| Outils                                                            |
//+------------------------------------------------------------------+
long ServerOffset()
  {
   long off = (long)(TimeTradeServer() - TimeGMT());
   return (long)MathRound(off / 900.0) * 900;
  }

string Num(double v, int digits) { return DoubleToString(v, digits); }

string Fr(double v, int digits)
  {
   string s = DoubleToString(v, digits);
   StringReplace(s, ".", ",");
   return s;
  }

string JsonText(string s)
  {
   StringReplace(s, "\\", "\\\\");
   StringReplace(s, "\"", "\\\"");
   return "\"" + s + "\"";
  }

bool Contains(const long &list[], long v)
  {
   for(int i = 0; i < ArraySize(list); i++)
      if(list[i] == v)
         return true;
   return false;
  }

//+------------------------------------------------------------------+
//| HTTP                                                              |
//+------------------------------------------------------------------+
int Http(string method, string url, string body, string contentType, string &response)
  {
   char data[], result[];
   string resultHeaders;
   if(body != "")
     {
      int len = StringToCharArray(body, data, 0, WHOLE_ARRAY, CP_UTF8);
      ArrayResize(data, (int)MathMax(0, len - 1));
     }
   string headers = "X-CX-Key: " + InpKey + "\r\n";
   if(contentType != "")
      headers += "Content-Type: " + contentType + "\r\n";
   ResetLastError();
   int code = WebRequest(method, url, headers, 15000, data, result, resultHeaders);
   if(code == -1)
     {
      int err = GetLastError();
      g_status = (err == 4014) ? "Ajoutez l'adresse du site dans Outils › Options › Expert Advisors › WebRequest."
                               : "Erreur WebRequest " + IntegerToString(err);
      Print("Carnet XAU : ", g_status);
      return -1;
     }
   response = CharArrayToString(result, 0, WHOLE_ARRAY, CP_UTF8);
   if(code != 200)
      Print("Carnet XAU : réponse ", code, " ", response);
   return code;
  }

//+------------------------------------------------------------------+
//| Synchronisation : trades fermés et instantané                     |
//+------------------------------------------------------------------+
string PositionJson(long position, long offset)
  {
   if(!HistorySelectByPosition(position))
      return "";
   string   symbol = "";
   long     side = -1;
   double   inVol = 0, inValue = 0, outVol = 0, outValue = 0;
   double   profit = 0, commission = 0, swap = 0, sl = 0, tp = 0;
   string   sigRef = "";
   datetime openTime = 0, closeTime = 0;
   int total = HistoryDealsTotal();
   for(int i = 0; i < total; i++)
     {
      ulong deal = HistoryDealGetTicket(i);
      long  type = HistoryDealGetInteger(deal, DEAL_TYPE);
      if(type != DEAL_TYPE_BUY && type != DEAL_TYPE_SELL)
         continue;
      long     entry = HistoryDealGetInteger(deal, DEAL_ENTRY);
      double   vol   = HistoryDealGetDouble(deal, DEAL_VOLUME);
      double   price = HistoryDealGetDouble(deal, DEAL_PRICE);
      datetime t     = (datetime)HistoryDealGetInteger(deal, DEAL_TIME);
      profit     += HistoryDealGetDouble(deal, DEAL_PROFIT);
      commission += HistoryDealGetDouble(deal, DEAL_COMMISSION) + HistoryDealGetDouble(deal, DEAL_FEE);
      swap       += HistoryDealGetDouble(deal, DEAL_SWAP);
      if(entry == DEAL_ENTRY_IN)
        {
         if(openTime == 0 || t < openTime)
           {
            openTime = t;
            symbol   = HistoryDealGetString(deal, DEAL_SYMBOL);
            side     = type;
            ulong order = (ulong)HistoryDealGetInteger(deal, DEAL_ORDER);
            if(HistoryOrderSelect(order))
              {
               sl = HistoryOrderGetDouble(order, ORDER_SL);
               tp = HistoryOrderGetDouble(order, ORDER_TP);
               // Ordre du Calculateur XAU chargé depuis un signal : « CalcXAU … #référence »
               string cm = HistoryOrderGetString(order, ORDER_COMMENT);
               int    at = StringFind(cm, "#");
               if(at >= 0)
                  sigRef = StringSubstr(cm, at + 1);
              }
           }
         inVol   += vol;
         inValue += vol * price;
        }
      else
        {
         outVol   += vol;
         outValue += vol * price;
         if(t > closeTime)
            closeTime = t;
        }
     }
   if(side < 0 || inVol <= 0 || outVol <= 0)
      return "";
   if(StringLen(InpSymbol) > 0 && StringFind(symbol, InpSymbol) < 0)
      return "";
   return "{\"ticket\":\"" + IntegerToString(position) + "\""
          + ",\"symbol\":" + JsonText(symbol)
          + ",\"side\":\"" + (side == DEAL_TYPE_BUY ? "buy" : "sell") + "\""
          + ",\"lots\":" + Num(inVol, 2)
          + ",\"open_price\":" + Num(inValue / inVol, 3)
          + ",\"close_price\":" + Num(outValue / outVol, 3)
          + ",\"sl\":" + Num(sl, 3)
          + ",\"tp\":" + Num(tp, 3)
          + ",\"profit\":" + Num(profit, 2)
          + ",\"commission\":" + Num(commission, 2)
          + ",\"swap\":" + Num(swap, 2)
          + ",\"open_time\":" + IntegerToString((long)openTime - offset)
          + ",\"close_time\":" + IntegerToString((long)closeTime - offset)
          + (sigRef != "" ? ",\"signal\":" + JsonText(sigRef) : "")
          + "}";
  }

string TradesJson()
  {
   long     offset = ServerOffset();
   datetime now    = TimeCurrent();
   int      days   = g_firstSync ? (int)MathMax(30, InpDaysBack) : InpDaysBack;
   if(!HistorySelect(now - days * 86400, now + 60))
      return "";
   long positions[];
   int  total = HistoryDealsTotal();
   for(int i = 0; i < total; i++)
     {
      ulong deal  = HistoryDealGetTicket(i);
      long  entry = HistoryDealGetInteger(deal, DEAL_ENTRY);
      if(entry != DEAL_ENTRY_OUT && entry != DEAL_ENTRY_OUT_BY && entry != DEAL_ENTRY_INOUT)
         continue;
      long pos = HistoryDealGetInteger(deal, DEAL_POSITION_ID);
      if(!Contains(positions, pos))
        {
         int n = ArraySize(positions);
         ArrayResize(positions, n + 1);
         positions[n] = pos;
        }
     }
   string items = "";
   for(int i = 0; i < ArraySize(positions); i++)
     {
      if(PositionSelectByTicket((ulong)positions[i]))
         continue;
      string item = PositionJson(positions[i], offset);
      if(item != "")
         items += (items == "" ? "" : ",") + item;
     }
   return items;
  }

datetime ServerDayStart()
  {
   MqlDateTime s;
   TimeToStruct(TimeTradeServer(), s);
   s.hour = 0;
   s.min  = 0;
   s.sec  = 0;
   return StructToTime(s);
  }

//--- Résultat réalisé depuis minuit (heure du serveur), nombre de trades et de pertes du jour.
double TodayStats(int &trades, int &losses)
  {
   trades = 0;
   losses = 0;
   double realised = 0;
   if(!HistorySelect(ServerDayStart(), TimeCurrent() + 60))
      return 0;
   int total = HistoryDealsTotal();
   for(int i = 0; i < total; i++)
     {
      ulong  deal = HistoryDealGetTicket(i);
      long   type = HistoryDealGetInteger(deal, DEAL_TYPE);
      if(type == DEAL_TYPE_BALANCE || type == DEAL_TYPE_CREDIT || type == DEAL_TYPE_BONUS)
         continue;                               // dépôt, retrait de gains, crédit : pas un résultat du jour
      double pnl  = HistoryDealGetDouble(deal, DEAL_PROFIT) + HistoryDealGetDouble(deal, DEAL_COMMISSION)
                    + HistoryDealGetDouble(deal, DEAL_SWAP) + HistoryDealGetDouble(deal, DEAL_FEE);
      realised += pnl;
      long entry = HistoryDealGetInteger(deal, DEAL_ENTRY);
      if(entry == DEAL_ENTRY_OUT || entry == DEAL_ENTRY_OUT_BY)
        {
         trades++;
         if(pnl < 0)
            losses++;
        }
     }
   return realised;
  }

string SnapshotJson()
  {
   double balance = AccountInfoDouble(ACCOUNT_BALANCE);
   double equity  = AccountInfoDouble(ACCOUNT_EQUITY);
   int t, l;
   double realised = TodayStats(t, l);
   double dailyLoss = (balance - realised) - equity; // positif = perte
   return "{\"balance\":" + Num(balance, 2) + ",\"equity\":" + Num(equity, 2) + ",\"daily_loss\":" + Num(dailyLoss, 2) + "}";
  }

void Sync()
  {
   bool   full = g_dirty || TimeCurrent() - g_lastFull >= 600;
   string json = "{\"login\":" + IntegerToString(AccountInfoInteger(ACCOUNT_LOGIN));
   if(full)
      json += ",\"trades\":[" + TradesJson() + "]";
   json += ",\"snapshot\":" + SnapshotJson() + "}";
   string resp;
   if(Http("POST", InpUrl, json, "application/json", resp) == 200 && full)
     {
      g_dirty     = false;
      g_firstSync = false;
      g_lastFull  = TimeCurrent();
     }
  }

//--- Bougies du courtier (D1, H4, H1), heures en UTC, pour l'analyse de Claude. La dernière est
//    incluse : bougie en cours en semaine, bougie complète du vendredi le week-end.
void AddCandles(string &body, ENUM_TIMEFRAMES tf, string name, int count, long offset)
  {
   MqlRates r[];
   int n = CopyRates(_Symbol, tf, 0, count, r);
   for(int i = 0; i < n; i++)
      body += name + "|" + IntegerToString((long)r[i].time - offset) + "|" + Num(r[i].open, 2) + "|" + Num(r[i].high, 2)
              + "|" + Num(r[i].low, 2) + "|" + Num(r[i].close, 2) + "\n";
  }

void SendCandles()
  {
   long offset = ServerOffset();
   string body = "";
   AddCandles(body, PERIOD_D1, "D1", 120, offset);
   AddCandles(body, PERIOD_H4, "H4", 300, offset);
   AddCandles(body, PERIOD_H1, "H1", 500, offset);
   AddCandles(body, PERIOD_M15, "M15", 400, offset);
   AddCandles(body, PERIOD_M5, "M5", 600, offset);
   AddCandles(body, PERIOD_M1, "M1", 1500, offset);
   string resp;
   if(body != "" && Http("POST", InpUrl + "/candles", body, "text/plain", resp) == 200)
      g_lastCandles = TimeCurrent();
  }

//+------------------------------------------------------------------+
//| Lecture du plan                                                   |
//+------------------------------------------------------------------+
void FetchPlan()
  {
   string resp;
   if(Http("GET", InpUrl + "/plan", "", "", resp) != 200)
      return;
   string lines[];
   int n = StringSplit(resp, '\n', lines);
   if(n < 1)
      return;
   string head[];
   if(StringSplit(lines[0], '|', head) < 3 || head[0] != "V")
      return;
   g_planAt = TimeLocal();
   if(head[1] == g_hash)
      return;                    // rien de nouveau

   // Conserver l'heure du dernier signal par zone d'une version à l'autre.
   string   oldKeys[];
   datetime oldSig[];
   int oz = ArraySize(g_zones);
   ArrayResize(oldKeys, oz);
   ArrayResize(oldSig, oz);
   for(int i = 0; i < oz; i++)
     {
      oldKeys[i] = g_zones[i].key;
      oldSig[i]  = g_zones[i].lastSignal;
     }

   ArrayResize(g_zones, 0);
   ArrayResize(g_levels, 0);
   ArrayResize(g_pos, 0);
   g_sinv = 0;
   ArrayResize(g_news, 0);
   ArrayResize(g_newsName, 0);
   g_bull = 0; g_bear = 0; g_range = 0;
   g_inval = 0; g_rlo = 0; g_rhi = 0; g_ref = 0; g_invalidated = false;

   for(int i = 1; i < n; i++)
     {
      string f[];
      int c = StringSplit(lines[i], '|', f);
      if(c < 2)
         continue;
      if(f[0] == "B" && c >= 4)
        {
         g_bull = (int)StringToInteger(f[1]);
         g_bear = (int)StringToInteger(f[2]);
         g_range = (int)StringToInteger(f[3]);
        }
      else if(f[0] == "I" && c >= 6)
        {
         g_inval = StringToDouble(f[1]);
         g_rlo   = StringToDouble(f[2]);
         g_rhi   = StringToDouble(f[3]);
         g_ref   = StringToDouble(f[4]);
         g_invalidated = (f[5] == "1");
        }
      else if(f[0] == "Z" && c >= 9)
        {
         int k = ArraySize(g_zones);
         ArrayResize(g_zones, k + 1);
         g_zones[k].key      = f[1];
         g_zones[k].type     = f[2];
         g_zones[k].tf       = f[3];
         g_zones[k].lo       = StringToDouble(f[4]);
         g_zones[k].hi       = StringToDouble(f[5]);
         g_zones[k].buy      = (f[6] != "vente");
         g_zones[k].strength = (int)StringToInteger(f[7]);
         g_zones[k].state    = f[8];
         g_zones[k].origin   = c >= 10 ? f[9] : "";
         g_zones[k].lastSignal = 0;
         for(int j = 0; j < oz; j++)
            if(oldKeys[j] == f[1])
               g_zones[k].lastSignal = oldSig[j];
        }
      else if(f[0] == "L" && c >= 3)
        {
         int k = ArraySize(g_levels);
         ArrayResize(g_levels, k + 1);
         g_levels[k].price = StringToDouble(f[1]);
         g_levels[k].type  = f[2];
         g_levels[k].origin = c >= 4 ? f[3] : "";
        }
      else if(f[0] == "X" && c >= 2)
         g_sinv = StringToDouble(f[1]);
      else if(f[0] == "P" && c >= 5)
        {
         int k = ArraySize(g_pos);
         ArrayResize(g_pos, k + 1);
         g_pos[k].buy   = (f[1] != "vente");
         g_pos[k].entry = StringToDouble(f[2]);
         g_pos[k].sl    = StringToDouble(f[3]);
         g_pos[k].tp    = StringToDouble(f[4]);
         g_pos[k].label = c >= 6 ? f[5] : "";
        }
      else if(f[0] == "N" && c >= 4)
        {
         int k = ArraySize(g_news);
         ArrayResize(g_news, k + 1);
         ArrayResize(g_newsName, k + 1);
         g_news[k]     = (datetime)StringToInteger(f[1]);  // UTC
         g_newsName[k] = f[3];
        }
      else if(f[0] == "R" && c >= 13)
        {
         g_risk      = StringToDouble(f[1]);
         g_riskRed   = StringToDouble(f[2]);
         g_redAt     = StringToDouble(f[3]);
         g_contract  = StringToDouble(f[4]);
         g_maxTrades = (int)StringToInteger(f[5]);
         g_maxLoss   = (int)StringToInteger(f[6]);
         g_spreadMax = StringToDouble(f[7]);
         g_stopMin   = StringToDouble(f[8]);
         g_stopMax   = StringToDouble(f[9]);
         g_dailyLim  = StringToDouble(f[10]);
         g_size      = StringToDouble(f[11]);
         g_floor     = StringToDouble(f[12]);
         g_rOk       = true;
        }
      else if(f[0] == "G" && c >= 10)
        {
         g_phase     = f[1];
         g_goal      = StringToDouble(f[2]);
         g_gain      = StringToDouble(f[3]);
         g_daysDone  = (int)StringToInteger(f[4]);
         g_minDays   = (int)StringToInteger(f[5]);
         g_weekLoss  = (int)StringToInteger(f[6]);
         g_weekStopR = StringToDouble(f[7]);
         g_ownKind   = (f[8] == "propre");
        }
      else if(f[0] == "S" && c >= 7)
        {
         // Réglages ajustés par Claude (bornés par le carnet).
         g_touch    = (int)StringToInteger(f[1]);
         g_swing    = (int)StringToInteger(f[2]);
         g_fvg      = StringToDouble(f[3]);
         g_slbuf    = StringToDouble(f[4]);
         g_limitMin = (int)StringToInteger(f[5]);
         g_cooldown = (int)StringToInteger(f[6]);
        }
     }
   g_hash   = head[1];
   g_week   = head[2];
   g_planOk = (ArraySize(g_zones) > 0);
   g_status = g_planOk ? "Plan de Claude reçu (" + IntegerToString(ArraySize(g_zones)) + " zones)." : "Aucun plan publié pour la semaine.";
   if(InpDrawPlan)
      DrawPlan();
  }

//+------------------------------------------------------------------+
//| Tracé du plan                                                     |
//+------------------------------------------------------------------+
void ObjBase(string name)
  {
   ObjectSetInteger(0, name, OBJPROP_SELECTABLE, false);
   ObjectSetInteger(0, name, OBJPROP_HIDDEN, true);
  }

//--- Style TradingView clair : fond blanc, sans grille, bougies vert 089981 et rouge F23645.
//    Les réglages d'origine du graphique sont gardés et rétablis quand l'EA est retiré.
ENUM_CHART_PROPERTY_INTEGER g_styleKeys[] = { CHART_MODE, CHART_SHOW_GRID, CHART_COLOR_BACKGROUND, CHART_COLOR_FOREGROUND,
   CHART_COLOR_CANDLE_BULL, CHART_COLOR_CANDLE_BEAR, CHART_COLOR_CHART_UP, CHART_COLOR_CHART_DOWN, CHART_COLOR_CHART_LINE,
   CHART_COLOR_BID, CHART_COLOR_ASK, CHART_COLOR_LAST, CHART_COLOR_STOP_LEVEL, CHART_SHOW_VOLUMES, CHART_SHIFT };
long   g_styleOld[];
double g_shiftOld = 0;

void RestoreStyle()
  {
   if(ArraySize(g_styleOld) != ArraySize(g_styleKeys))
      return;
   for(int i = 0; i < ArraySize(g_styleKeys); i++)
      ChartSetInteger(0, g_styleKeys[i], g_styleOld[i]);
   ChartSetDouble(0, CHART_SHIFT_SIZE, g_shiftOld);
   ChartRedraw();
  }

void ApplyTVStyle()
  {
   if(!InpTVStyle)
      return;
   if(ArraySize(g_styleOld) != ArraySize(g_styleKeys))
     {
      ArrayResize(g_styleOld, ArraySize(g_styleKeys));
      for(int i = 0; i < ArraySize(g_styleKeys); i++)
         g_styleOld[i] = ChartGetInteger(0, g_styleKeys[i]);
      g_shiftOld = ChartGetDouble(0, CHART_SHIFT_SIZE);
     }
   ChartSetInteger(0, CHART_MODE, CHART_CANDLES);
   ChartSetInteger(0, CHART_SHOW_GRID, false);
   ChartSetInteger(0, CHART_COLOR_BACKGROUND, clrWhite);
   ChartSetInteger(0, CHART_COLOR_FOREGROUND, C'19,23,34');
   ChartSetInteger(0, CHART_COLOR_CANDLE_BULL, C'8,153,129');
   ChartSetInteger(0, CHART_COLOR_CANDLE_BEAR, C'242,54,69');
   ChartSetInteger(0, CHART_COLOR_CHART_UP, C'8,153,129');
   ChartSetInteger(0, CHART_COLOR_CHART_DOWN, C'242,54,69');
   ChartSetInteger(0, CHART_COLOR_CHART_LINE, C'120,123,134');
   ChartSetInteger(0, CHART_COLOR_BID, C'120,123,134');
   ChartSetInteger(0, CHART_COLOR_ASK, C'178,181,190');
   ChartSetInteger(0, CHART_COLOR_LAST, C'120,123,134');
   ChartSetInteger(0, CHART_COLOR_STOP_LEVEL, C'242,54,69');
   ChartSetInteger(0, CHART_SHOW_VOLUMES, CHART_VOLUME_HIDE);
   ChartSetInteger(0, CHART_SHIFT, true);                 // place à droite pour l'outil position
   ChartSetDouble(0, CHART_SHIFT_SIZE, 30);
   ChartRedraw();
  }

//--- Couleur d'un niveau : PDH, PDL, PWH, PWL en bleu ; EQH, EQL en gris foncé ; le reste en gris.
color LevelColor(string t)
  {
   string u = t;
   StringToUpper(u);
   if(StringLen(u) >= 3 && StringGetCharacter(u, 0) == 'P' && (StringFind("DWM", StringSubstr(u, 1, 1)) >= 0) && (StringFind("HL", StringSubstr(u, 2, 1)) >= 0))
      return C'41,98,255';
   if(StringFind(u, "EQH") == 0 || StringFind(u, "EQL") == 0)
      return C'67,70,81';
   return C'120,123,134';
  }

string LevelName(string t)
  {
   string u = t;
   StringToUpper(u);
   if(u == "EQH" || u == "EQL")
      u += " LIQUIDITY";
   return u;
  }

//+------------------------------------------------------------------+
//| Plan dessiné en CCanvas, comme l'indicateur LGD+CSD+Fib :        |
//| vraie transparence, traits de 1 px, texte Trebuchet MS, prix du  |
//| bord droit en étiquettes pleines comme l'échelle de TradingView. |
//| DrawPlan() calcule la mise en page ; CvRender() redessine à      |
//| chaque défilement, zoom ou changement d'échelle des prix.        |
//+------------------------------------------------------------------+
enum ENUM_CV_KIND { CV_RECT = 0, CV_SEG = 1, CV_TEXT = 2, CV_PRICE = 3, CV_TREND = 4 };
struct CvItem
  {
   int      kind;
   datetime t1;
   datetime t2;
   double   p1;
   double   p2;
   uint     c1;        // remplissage, trait ou texte
   uint     c2;        // bord du rectangle, fond de l'étiquette (0 : aucun)
   int      style;     // STYLE_SOLID, STYLE_DASH, STYLE_DOT
   int      anchor;    // ANCHOR_CENTER, ANCHOR_LOWER (au-dessus du prix), ANCHOR_UPPER (en dessous)
   string   text;
  };
CvItem   g_cv[];
#define  CV_FONT 45                 // dixièmes de point : même taille que « BB » de LGD+CSD+Fib, pour tout le plan

uint Ar(color c, double a) { return ColorToARGB(c, (uchar)MathRound(255.0 * MathMax(0.0, MathMin(1.0, a)))); }

void CvAdd(int kind, datetime t1, datetime t2, double p1, double p2, uint c1, uint c2, int style, int anchor, string text)
  {
   int n = ArraySize(g_cv);
   ArrayResize(g_cv, n + 1, 64);
   g_cv[n].kind = kind; g_cv[n].t1 = t1; g_cv[n].t2 = t2; g_cv[n].p1 = p1; g_cv[n].p2 = p2;
   g_cv[n].c1 = c1; g_cv[n].c2 = c2; g_cv[n].style = style; g_cv[n].anchor = anchor; g_cv[n].text = text;
  }

//--- Largeur du texte du canvas, en pixels.
int CvTextPx(string txt)
  {
   uint w = 0, h = 0;
   TextSetFont(FONT_TV, -CV_FONT, FW_NORMAL);
   if(!TextGetSize(txt, w, h) || w == 0)
      return (int)(StringLen(txt) * CV_FONT * 0.08 * g_k);
   return (int)w;
  }

int BarPx()           { return (int)MathPow(2, (double)ChartGetInteger(0, CHART_SCALE)); }
datetime FutureTime(double bars) { return iTime(_Symbol, PERIOD_CURRENT, 0) + (datetime)(bars * PeriodSeconds(PERIOD_CURRENT)); }

//--- Bord droit du graphique, moins la largeur d'une étiquette de prix.
datetime RightTime()
  {
   int vis   = (int)ChartGetInteger(0, CHART_WIDTH_IN_BARS);
   int first = (int)ChartGetInteger(0, CHART_FIRST_VISIBLE_BAR);
   double fut = vis - first - 1 - Dp(80) / (double)BarPx();
   return FutureTime(MathMax(31, fut));
  }

//--- Places déjà prises sur le graphique (en pixels), remises à zéro à chaque dessin : deux prix ou deux
//    noms ne se chevauchent jamais, comme sur TradingView. Le premier dessiné garde sa place.
int g_tagY[];
int g_lblX[], g_lblY[], g_lblW[];

int PriceY(double price)
  {
   int x = 0, y = 0;
   ChartTimePriceToXY(0, 0, iTime(_Symbol, PERIOD_CURRENT, 0), price, x, y);
   return y;
  }

//--- Prix sur fond de couleur au bord droit, comme l'échelle de TradingView ; omis s'il recouvrirait un
//    prix déjà affiché.
void PriceTag(string name, double price, color clr)
  {
   int y = PriceY(price);
   for(int i = 0; i < ArraySize(g_tagY); i++)
      if(MathAbs(g_tagY[i] - y) < Dp(11))
         return;
   int n = ArraySize(g_tagY);
   ArrayResize(g_tagY, n + 1);
   g_tagY[n] = y;
   CvAdd(CV_PRICE, 0, 0, price, 0, Ar(clr, 1.0), 0, STYLE_SOLID, ANCHOR_CENTER, Fr(price, 2));
  }

//--- Bougie d'origine d'un niveau ou d'une zone, calculée une fois par plan et par unité de temps :
//    1. « origin » du plan (jj/mm hh:mm, heure du serveur), affinée dans l'unité affichée : la bougie
//       de cette période dont la mèche marque le prix ;
//    2. sinon la dernière bougie H1 (6 semaines) dont le plus haut (niveau H, haut, high) ou le plus
//       bas (niveau L, bas, low) marque le prix à 0,30 $ près, affinée de même ;
//    3. sinon le bord gauche du graphique : jamais une bougie prise au hasard.
string   g_orgKey[];
datetime g_orgT[];
string   g_orgHash = "";
#define  ORG_TOL 0.30

//--- 1 : plus hauts, -1 : plus bas, 0 : les deux (zone, type inconnu).
int TypeSide(string type)
  {
   string u = type;
   StringToUpper(u);
   int n = StringLen(u);
   if(n == 0)
      return 0;
   if(StringFind(u, "HAUT") >= 0 || StringFind(u, "HIGH") >= 0 || StringSubstr(u, n - 1) == "H")
      return 1;
   if(StringFind(u, "BAS") >= 0 || StringFind(u, "LOW") >= 0 || StringSubstr(u, n - 1) == "L")
      return -1;
   return 0;
  }

//--- « 16/10 08:00 », « jeudi 16/10, 08h00 », « 22/10 » : premier jour/mois et première heure du texte.
datetime ParseOrigin(string txt, bool &hasHour)
  {
   hasHour = false;
   int n = StringLen(txt), d = -1, mo = -1, hh = 0, mi = 0;
   for(int i = 0; i + 2 < n && d < 0; i++)
     {
      int j = i;
      string a = "", b = "";
      while(j < n && StringGetCharacter(txt, j) >= '0' && StringGetCharacter(txt, j) <= '9' && StringLen(a) < 2) { a += StringSubstr(txt, j, 1); j++; }
      if(a == "" || j >= n || StringGetCharacter(txt, j) != '/')
         continue;
      j++;
      while(j < n && StringGetCharacter(txt, j) >= '0' && StringGetCharacter(txt, j) <= '9' && StringLen(b) < 2) { b += StringSubstr(txt, j, 1); j++; }
      if(b == "")
         continue;
      d = (int)StringToInteger(a);
      mo = (int)StringToInteger(b);
      //--- heure après la date : chiffres, « : » ou « h », deux chiffres
      for(int k = j; k + 2 < n; k++)
        {
         string c = "";
         int m = k;
         while(m < n && StringGetCharacter(txt, m) >= '0' && StringGetCharacter(txt, m) <= '9' && StringLen(c) < 2) { c += StringSubstr(txt, m, 1); m++; }
         if(c == "" || m + 2 > n)
            continue;
         ushort sep = StringGetCharacter(txt, m);
         if(sep != ':' && sep != 'h')
            continue;
         string e = StringSubstr(txt, m + 1, 2);
         if(StringLen(e) == 2 && StringGetCharacter(e, 0) >= '0' && StringGetCharacter(e, 0) <= '9' && StringGetCharacter(e, 1) >= '0' && StringGetCharacter(e, 1) <= '9')
           {
            hh = (int)StringToInteger(c);
            mi = (int)StringToInteger(e);
            hasHour = true;
           }
         break;
        }
     }
   if(d < 1 || d > 31 || mo < 1 || mo > 12)
      return 0;
   MqlDateTime now;
   TimeToStruct(TimeCurrent(), now);
   MqlDateTime o;
   o.year = now.year; o.mon = mo; o.day = d; o.hour = hh; o.min = mi; o.sec = 0;
   datetime t = StructToTime(o);
   if(t > TimeCurrent() + 86400)                 // plan de fin décembre lu en janvier
     {
      o.year--;
      t = StructToTime(o);
     }
   return t;
  }

//--- Dans [from, from + span[, sur l'unité affichée : la bougie dont la mèche marque le prix.
datetime Refine(datetime from, int span, double lo, double hi, int side)
  {
   int first = iBarShift(_Symbol, PERIOD_CURRENT, from, false);
   if(first < 0)
      return 0;
   datetime best = iTime(_Symbol, PERIOD_CURRENT, first);
   double   gap  = DBL_MAX;
   for(int i = first; i >= 0; i--)
     {
      datetime t = iTime(_Symbol, PERIOD_CURRENT, i);
      if(t >= from + span)
         break;
      if(t + PeriodSeconds(PERIOD_CURRENT) <= from)
         continue;
      double h = iHigh(_Symbol, PERIOD_CURRENT, i), l = iLow(_Symbol, PERIOD_CURRENT, i);
      double g = side > 0 ? MathAbs(h - hi) : (side < 0 ? MathAbs(l - lo) : MathMin(MathAbs(h - hi), MathAbs(l - lo)));
      if(g < gap)
        {
         gap  = g;
         best = t;
        }
     }
   return best;
  }

datetime Origin(string key, double lo, double hi, int side, string origin, int span)
  {
   if(g_orgHash != g_hash + "|" + IntegerToString(PeriodSeconds(PERIOD_CURRENT)))
     {
      g_orgHash = g_hash + "|" + IntegerToString(PeriodSeconds(PERIOD_CURRENT));
      ArrayResize(g_orgKey, 0);
      ArrayResize(g_orgT, 0);
     }
   key += "|" + DoubleToString(lo, 2) + "|" + DoubleToString(hi, 2);
   for(int i = 0; i < ArraySize(g_orgKey); i++)
      if(g_orgKey[i] == key)
         return g_orgT[i];
   datetime t = 0;
   //--- 1. Bougie d'origine du plan.
   bool hasHour = false;
   datetime o = ParseOrigin(origin, hasHour);
   if(o > 0 && o <= TimeCurrent())
      t = Refine(o, hasHour ? MathMax(span, PeriodSeconds(PERIOD_CURRENT)) : 86400, lo, hi, side);
   //--- 2. Dernière bougie H1 dont la mèche marque le prix.
   if(t == 0)
     {
      int n = (int)MathMin(1000, Bars(_Symbol, PERIOD_H1) - 1);
      for(int i = 1; i < n && t == 0; i++)
        {
         double h = iHigh(_Symbol, PERIOD_H1, i), l = iLow(_Symbol, PERIOD_H1, i);
         bool hit = side > 0 ? MathAbs(h - hi) <= ORG_TOL
                  : side < 0 ? MathAbs(l - lo) <= ORG_TOL
                             : (MathAbs(h - hi) <= ORG_TOL || MathAbs(l - lo) <= ORG_TOL);
         if(hit)
            t = Refine(iTime(_Symbol, PERIOD_H1, i), 3600, lo, hi, side);
        }
     }
   //--- 3. Bord gauche : la plus ancienne bougie chargée.
   if(t == 0)
      t = iTime(_Symbol, PERIOD_CURRENT, Bars(_Symbol, PERIOD_CURRENT) - 1);
   int k = ArraySize(g_orgKey);
   ArrayResize(g_orgKey, k + 1);
   ArrayResize(g_orgT, k + 1);
   g_orgKey[k] = key;
   g_orgT[k]   = t;
   return t;
  }

//--- Durée d'une bougie de l'unité d'une zone (H1, H4, M15, D…), en secondes ; 1 h par défaut.
int TfSeconds(string tf)
  {
   string u = tf;
   StringToUpper(u);
   if(u == "M1")  return 60;
   if(u == "M5")  return 300;
   if(u == "M15") return 900;
   if(u == "M30") return 1800;
   if(u == "H4")  return 14400;
   if(u == "D" || u == "D1") return 86400;
   if(u == "W" || u == "W1") return 7 * 86400;
   return 3600;
  }

//--- Place libre pour un texte de largeur w centré en x, à la hauteur y (pixels) ; réservée si libre.
bool TakeSpot(int x, int y, int w)
  {
   for(int j = 0; j < ArraySize(g_lblX); j++)
      if(MathAbs(g_lblY[j] - y) < Dp(9) && MathAbs(g_lblX[j] - x) < (g_lblW[j] + w) / 2 + Dp(6))
         return false;
   int n = ArraySize(g_lblX);
   ArrayResize(g_lblX, n + 1);
   ArrayResize(g_lblY, n + 1);
   ArrayResize(g_lblW, n + 1);
   g_lblX[n] = x;
   g_lblY[n] = y;
   g_lblW[n] = w;
   return true;
  }

//--- Ligne de l'origine au bord droit, nom juste au-dessus du trait comme les lignes de TradingView :
//    au milieu de la partie visible, sinon au quart, aux trois quarts… : la première place libre.
void NamedLine(string id, datetime t1, double price, string txt, color clr, ENUM_LINE_STYLE style)
  {
   datetime tEnd = RightTime();
   CvAdd(CV_SEG, t1, tEnd, price, price, Ar(clr, 1.0), 0, style, 0, "");
   int W = (int)ChartGetInteger(0, CHART_WIDTH_IN_PIXELS);
   int xa = (int)MathRound(CvX(t1)), xb = (int)MathRound(CvX(tEnd)), y = (int)MathRound(CvY(price));
   int w = CvTextPx(txt) + Dp(6);
   xa = MathMax(xa, 0) + w / 2 + Dp(4);
   xb = MathMin(xb, W) - w / 2 - Dp(4);
   if(xb < xa)
      return;                                    // trait trop court à l'écran : pas de nom
   int ty = y - Dp(7);                            // hauteur du texte, au-dessus du trait
   double fr[] = { 0.5, 0.25, 0.75, 0.12, 0.88, 0.0, 1.0 };
   for(int k = 0; k < ArraySize(fr); k++)
     {
      int x = (int)MathRound(xa + (xb - xa) * fr[k]);
      if(!TakeSpot(x, ty, w))
         continue;
      //--- t2 = 1 : position x en pixels (p2) ; DrawPlan est refait à chaque défilement ou zoom.
      CvAdd(CV_TEXT, 0, 1, price, x, Ar(clr, 1.0), 0, STYLE_SOLID, ANCHOR_LOWER, txt);
      return;
     }
  }

void SaveDraws()
  {
   int h = FileOpen(DRAW_FILE, FILE_WRITE | FILE_CSV | FILE_UNICODE, ';');
   if(h == INVALID_HANDLE)
      return;
   for(int i = 0; i < ArraySize(g_draws); i++)
     {
      CxDraw d = g_draws[i];
      if(TimeCurrent() - d.t > 7 * 86400)
         continue;
      FileWrite(h, d.id, (long)d.t, (long)d.t2, (int)d.buy, Num(d.entry, 2), Num(d.sl, 2), Num(d.tp, 2), Num(d.p2r, 2), d.text, (int)d.grey);
     }
   FileClose(h);
  }

void LoadDraws()
  {
   ArrayResize(g_draws, 0);
   if(!FileIsExist(DRAW_FILE))
      return;
   int h = FileOpen(DRAW_FILE, FILE_READ | FILE_CSV | FILE_UNICODE, ';');
   if(h == INVALID_HANDLE)
      return;
   while(!FileIsEnding(h))
     {
      string id = FileReadString(h);
      if(id == "")
         break;
      int k = ArraySize(g_draws);
      ArrayResize(g_draws, k + 1);
      g_draws[k].id    = id;
      g_draws[k].t     = (datetime)StringToInteger(FileReadString(h));
      g_draws[k].t2    = (datetime)StringToInteger(FileReadString(h));
      g_draws[k].buy   = (StringToInteger(FileReadString(h)) == 1);
      g_draws[k].entry = StringToDouble(FileReadString(h));
      g_draws[k].sl    = StringToDouble(FileReadString(h));
      g_draws[k].tp    = StringToDouble(FileReadString(h));
      g_draws[k].p2r   = StringToDouble(FileReadString(h));
      g_draws[k].text  = FileReadString(h);
      g_draws[k].grey  = (StringToInteger(FileReadString(h)) == 1);
     }
   FileClose(h);
  }

void DrawAdd(string id, datetime t, datetime t2, bool buy, double entry, double sl, double tp, double p2r, string text)
  {
   int k = ArraySize(g_draws);
   ArrayResize(g_draws, k + 1);
   g_draws[k].id = id; g_draws[k].t = t; g_draws[k].t2 = t2; g_draws[k].buy = buy;
   g_draws[k].entry = entry; g_draws[k].sl = sl; g_draws[k].tp = tp; g_draws[k].p2r = p2r;
   g_draws[k].text = text; g_draws[k].grey = false;
   SaveDraws();
   DrawPlan();
  }

//--- Texte d'un signal (résultat, annulation) ; grey : limite annulée, dessin en gris.
void DrawUpdate(string id, string text, bool grey)
  {
   for(int i = 0; i < ArraySize(g_draws); i++)
      if(g_draws[i].id == id)
        {
         g_draws[i].text = text;
         g_draws[i].grey = g_draws[i].grey || grey;
        }
   SaveDraws();
   DrawPlan();
  }

//--- Signaux : outil position longue / courte de TradingView (objectif vert, stop rouge à 20 %),
//    étiquette BUY / SELL à pointe sur l'entrée, 2R en pointillés, texte du signal en étiquette pleine.
void DrawSignals()
  {
   color gry = C'178,181,190';
   for(int i = 0; i < ArraySize(g_draws); i++)
     {
      CxDraw d = g_draws[i];
      color dir = d.grey ? gry : (d.buy ? C'8,153,129' : C'242,54,69');
      if(d.p2r == 0)
        {
         CvAdd(CV_SEG, d.t, d.t2, d.entry, d.entry, Ar(dir, 1.0), 0, STYLE_DOT, 0, "");
         continue;
        }
      CvAdd(CV_RECT, d.t, d.t2, d.entry, d.tp, Ar(d.grey ? gry : C'8,153,129', d.grey ? 0.12 : 0.20), 0, STYLE_SOLID, 0, "");
      CvAdd(CV_RECT, d.t, d.t2, d.entry, d.sl, Ar(d.grey ? gry : C'242,54,69', d.grey ? 0.12 : 0.20), 0, STYLE_SOLID, 0, "");
      CvAdd(CV_SEG, d.t, d.t2, d.p2r, d.p2r, Ar(d.grey ? gry : C'8,153,129', 1.0), 0, STYLE_DOT, 0, "");
      CvAdd(CV_TEXT, d.t, 0, d.entry, 0, Ar(clrWhite, 1.0), Ar(dir, 1.0), 1, d.buy ? ANCHOR_UPPER : ANCHOR_LOWER, d.buy ? "BUY" : "SELL");
      CvAdd(CV_TEXT, d.t + (d.t2 - d.t) / 2, 0, MathMax(d.tp, d.sl), 0, Ar(clrWhite, 1.0), Ar(dir, 1.0), STYLE_SOLID, ANCHOR_LOWER, d.text);
     }
  }

void DrawPlan()
  {
   ObjectsDeleteAll(0, PFX_PLAN);    // objets des versions précédentes
   ArrayResize(g_cv, 0);
   ArrayResize(g_tagY, 0);
   ArrayResize(g_lblX, 0);
   ArrayResize(g_lblY, 0);
   ArrayResize(g_lblW, 0);
   CvMap();                        // pixels des traits et des noms, pour la mise en page
   if(!InpDrawPlan || g_hash == "")
     {
      DrawSignals();             // les signaux restent dessinés, même sans plan
      CvRender();
      return;
     }

   //--- Ordre de priorité des prix au bord droit : scénario principal, invalidation, niveaux, zones.
   //--- Scénarios d'exécution : outils position longue / courte côte à côte à droite du prix, trajet
   //    simulé du premier. Leur largeur s'adapte à la place libre à droite.
   int per = PeriodSeconds(PERIOD_CURRENT), np = ArraySize(g_pos);
   int vis = (int)ChartGetInteger(0, CHART_WIDTH_IN_BARS), first = (int)ChartGetInteger(0, CHART_FIRST_VISIBLE_BAR);
   double room = MathMax(31, vis - first - 1 - Dp(80) / (double)BarPx());
   int len = np > 0 ? (int)MathMax(8, MathMin(26, (room - 3) / np - 4)) : 26;
   for(int k = 0; k < np; k++)
     {
      CxPos  p   = g_pos[k];
      double stop = MathAbs(p.entry - p.sl);
      if(stop <= 0)
         continue;
      datetime xa = FutureTime(3 + k * (len + 4)), xb = FutureTime(3 + k * (len + 4) + len), xm = xa + (xb - xa) / 2;
      //--- Outil position de TradingView : zones vert et rouge à 20 %, étiquettes pleines.
      CvAdd(CV_RECT, xa, xb, p.entry, p.tp, Ar(C'8,153,129', 0.20), 0, STYLE_SOLID, 0, "");
      CvAdd(CV_RECT, xa, xb, p.entry, p.sl, Ar(C'242,54,69', 0.20), 0, STYLE_SOLID, 0, "");
      CvAdd(CV_SEG, xa, xb, p.entry, p.entry, Ar(C'120,123,134', 1.0), 0, STYLE_SOLID, 0, "");
      bool up = p.tp > p.entry;
      double rr = MathAbs(p.tp - p.entry) / stop;
      CvAdd(CV_TEXT, xm, 0, p.tp, 0, Ar(clrWhite, 1.0), Ar(C'8,153,129', 1.0), STYLE_SOLID, up ? ANCHOR_LOWER : ANCHOR_UPPER,
            "Objectif " + Fr(p.tp, 2) + " · " + Fr(rr, 1) + "R");
      CvAdd(CV_TEXT, xm, 0, p.sl, 0, Ar(clrWhite, 1.0), Ar(C'242,54,69', 1.0), STYLE_SOLID, up ? ANCHOR_UPPER : ANCHOR_LOWER,
            "Stop " + Fr(p.sl, 2) + " · " + Fr(stop, 2) + " $");
      CvAdd(CV_TEXT, xm, 0, p.entry, 0, Ar(clrWhite, 1.0), Ar(C'120,123,134', 1.0), STYLE_SOLID, up ? ANCHOR_UPPER : ANCHOR_LOWER,
            (p.buy ? "Achat " : "Vente ") + Fr(p.entry, 2) + (p.label != "" ? " · " + p.label : ""));
      if(k == 0)
        {
         datetime t0 = iTime(_Symbol, PERIOD_CURRENT, 0);
         double   c0 = iClose(_Symbol, PERIOD_CURRENT, 0);
         CvAdd(CV_TREND, t0, xa + 2 * per, c0, p.entry, Ar(C'41,98,255', 1.0), 0, STYLE_DASH, 0, "");
         CvAdd(CV_TREND, xa + 2 * per, xb - 3 * per, p.entry, p.tp, Ar(C'41,98,255', 1.0), 0, STYLE_DASH, 0, "");
         PriceTag("", p.entry, C'120,123,134');
         PriceTag("", p.tp, C'8,153,129');
         PriceTag("", p.sl, C'242,54,69');
        }
     }
   //--- Zones encore valides (une zone cassée n'est plus tracée, comme sur la capture du carnet) :
   //    rectangle de l'origine au bord droit, plus haut et plus bas en étiquettes de prix.
   for(int i = 0; i < ArraySize(g_zones); i++)
     {
      CxZone z = g_zones[i];
      if(z.state == "cassee")
         continue;
      string up = z.type + (z.tf == "US" || z.tf == "" ? "" : " " + z.tf);
      StringToUpper(up);
      //--- Comme les blocs BB de LGD+CSD+Fib : vert achat, rouge vente, fond à 25 %, bord plein, nom au centre.
      color  base = z.buy ? C'8,153,129' : C'242,54,69';
      datetime t1 = Origin("Z" + z.key, z.lo, z.hi, 0, z.origin, TfSeconds(z.tf));
      CvAdd(CV_RECT, t1, RightTime(), z.lo, z.hi, Ar(base, 0.25), Ar(base, 1.0), STYLE_SOLID, 0, up);
      //--- Nom au centre de la partie visible du bloc : sa place est réservée avant celle des niveaux.
      int W = (int)ChartGetInteger(0, CHART_WIDTH_IN_PIXELS);
      int xa = (int)MathRound(CvX(t1)), xb = (int)MathRound(CvX(RightTime()));
      TakeSpot((MathMax(xa, 0) + MathMin(xb, W)) / 2, (int)MathRound(CvY((z.lo + z.hi) / 2)), CvTextPx(up) + Dp(6));
     }
   //--- Invalidation : celle de la dernière analyse de séance, sinon celle du plan de la semaine.
   double inv = g_sinv > 0 ? g_sinv : g_inval;
   if(inv > 0)
     {
      color clr = g_invalidated && g_sinv <= 0 ? C'178,181,190' : C'242,54,69';
      NamedLine(PFX_PLAN + "INV", Origin("INV", inv, inv, inv >= SymbolInfoDouble(_Symbol, SYMBOL_BID) ? 1 : -1, "", 3600), inv, g_invalidated && g_sinv <= 0 ? "INVALIDATION TOUCHÉE" : "INVALIDATION", clr, STYLE_SOLID);
      PriceTag("", inv, clr);
     }
   //--- Niveaux de liquidité : trait plein et fin, nom au milieu, prix coloré au bord droit.
   for(int i = 0; i < ArraySize(g_levels); i++)
     {
      color  clr = LevelColor(g_levels[i].type);
      NamedLine("", Origin(g_levels[i].type, g_levels[i].price, g_levels[i].price, TypeSide(g_levels[i].type), g_levels[i].origin, 3600), g_levels[i].price, LevelName(g_levels[i].type), clr, STYLE_SOLID);
      PriceTag("", g_levels[i].price, clr);
     }
   //--- Plus haut et plus bas des zones au bord droit, après l'invalidation et les niveaux.
   for(int i = 0; i < ArraySize(g_zones); i++)
     {
      if(g_zones[i].state == "cassee")
         continue;
      color base = g_zones[i].buy ? C'8,153,129' : C'242,54,69';
      PriceTag("", g_zones[i].hi, base);
      PriceTag("", g_zones[i].lo, base);
     }
   DrawSignals();
   CvRender();
  }

//+------------------------------------------------------------------+
//| Rendu du canvas                                                  |
//+------------------------------------------------------------------+
double g_cvX0 = 0, g_cvSp = 1, g_cvY0 = 0, g_cvKy = 0, g_cvPMax = 0;
int    g_cvF = 0;
datetime g_cvT0 = 0;

double CvX(datetime t)
  {
   int per = PeriodSeconds(PERIOD_CURRENT);
   double sh;
   if(t > g_cvT0)
      sh = -(double)(t - g_cvT0) / per;
   else
      sh = iBarShift(_Symbol, PERIOD_CURRENT, t, false);
   return g_cvX0 + (g_cvF - sh) * g_cvSp;
  }
double CvY(double p) { return g_cvY0 + (g_cvPMax - p) * g_cvKy; }

void CvPx(int x, int y, uint c, double cov)
  {
   if(x < 0 || y < 0 || x >= g_cvW || y >= g_cvH)
      return;
   double sa = ((c >> 24) & 0xFF) / 255.0 * cov;
   if(sa <= 0.0)
      return;
   uint d = g_cvs.PixelGet(x, y);
   double da = ((d >> 24) & 0xFF) / 255.0;
   double oa = sa + da * (1.0 - sa);
   if(oa <= 0.0)
      return;
   double k = da * (1.0 - sa);
   uint r = (uint)MathRound((((c >> 16) & 0xFF) * sa + ((d >> 16) & 0xFF) * k) / oa);
   uint g = (uint)MathRound((((c >> 8) & 0xFF) * sa + ((d >> 8) & 0xFF) * k) / oa);
   uint b = (uint)MathRound(((c & 0xFF) * sa + (d & 0xFF) * k) / oa);
   g_cvs.PixelSet(x, y, ((uint)MathRound(oa * 255.0) << 24) | (r << 16) | (g << 8) | b);
  }

void CvFill(int x1, int y1, int x2, int y2, uint c)
  {
   int xa = MathMax(MathMin(x1, x2), 0), xb = MathMin(MathMax(x1, x2), g_cvW - 1);
   int ya = MathMax(MathMin(y1, y2), 0), yb = MathMin(MathMax(y1, y2), g_cvH - 1);
   for(int y = ya; y <= yb; y++)
      for(int x = xa; x <= xb; x++)
         CvPx(x, y, c, 1.0);
  }

void CvFillRound(int x1, int y1, int x2, int y2, double rad, uint c)
  {
   for(int y = MathMax(y1, 0); y < MathMin(y2, g_cvH); y++)
      for(int x = MathMax(x1, 0); x < MathMin(x2, g_cvW); x++)
        {
         double px = x + 0.5, py = y + 0.5;
         double qx = MathMax(MathMax(x1 + rad - px, px - (x2 - rad)), 0.0);
         double qy = MathMax(MathMax(y1 + rad - py, py - (y2 - rad)), 0.0);
         double cov = 1.0;
         if(qx > 0.0 && qy > 0.0)
            cov = MathMax(0.0, MathMin(1.0, rad + 0.5 - MathSqrt(qx * qx + qy * qy)));
         if(cov > 0.0)
            CvPx(x, y, c, cov);
        }
  }

//--- Trait de 1 px (TradingView garde 1 px à 125 % et 150 %), plein, tirets ou points.
void CvLine(double x1, double y1, double x2, double y2, int style, uint c)
  {
   double len = MathSqrt((x2 - x1) * (x2 - x1) + (y2 - y1) * (y2 - y1));
   int on = 0, off = 0;
   if(style == STYLE_DASH) { on = Dp(4); off = Dp(4); }
   if(style == STYLE_DOT)  { on = 1; off = 2; }
   if(len < 1.0)
     {
      CvPx((int)MathRound(x1), (int)MathRound(y1), c, 1.0);
      return;
     }
   //--- Les traits hors de l'écran ne sont pas parcourus pixel par pixel.
   double ta = 0.0, tb = len;
   if(y1 == y2)
     {
      double xl = MathMin(x1, x2), xr = MathMax(x1, x2);
      if(xr < 0 || xl > g_cvW)
         return;
      double sgn = x2 >= x1 ? 1.0 : -1.0;
      ta = MathMax(0.0, sgn > 0 ? -x1 : x1 - g_cvW);
      tb = MathMin(len, sgn > 0 ? g_cvW - x1 : x1);
     }
   int lx = -100000, ly = -100000;
   for(double s = ta; s <= tb; s += 1.0)
     {
      if(style != STYLE_SOLID && ((int)s % (on + off)) >= on)
         continue;
      int x = (int)MathRound(x1 + (x2 - x1) * s / len), y = (int)MathRound(y1 + (y2 - y1) * s / len);
      if(x == lx && y == ly)
         continue;
      lx = x; ly = y;
      CvPx(x, y, c, 1.0);
     }
  }

//--- Bougie/temps et prix vers pixels, depuis la première bougie visible.
bool CvMap()
  {
   int bars = Bars(_Symbol, PERIOD_CURRENT);
   if(bars < 3)
      return false;
   g_cvF = (int)ChartGetInteger(0, CHART_FIRST_VISIBLE_BAR);
   g_cvF = MathMax(1, MathMin(g_cvF, bars - 1));
   g_cvT0 = iTime(_Symbol, PERIOD_CURRENT, 0);
   double pMax = ChartGetDouble(0, CHART_PRICE_MAX, 0), pMin = ChartGetDouble(0, CHART_PRICE_MIN, 0);
   int xa = 0, ya = 0, xb = 0, yb = 0;
   if(pMax <= pMin || !ChartTimePriceToXY(0, 0, iTime(_Symbol, PERIOD_CURRENT, g_cvF), pMax, xa, ya)
      || !ChartTimePriceToXY(0, 0, iTime(_Symbol, PERIOD_CURRENT, g_cvF - 1), pMin, xb, yb))
      return false;
   g_cvX0 = xa;
   g_cvSp = xb - xa;
   if(g_cvSp <= 0)
      g_cvSp = BarPx();
   g_cvPMax = pMax; g_cvY0 = ya; g_cvKy = (double)(yb - ya) / (pMax - pMin);
   return true;
  }

//--- Pointe d'étiquette : sommet (ax, ay), base à ay + dir*h.
void CvPointer(double ax, int ay, int h, double halfW, int dir, uint c)
  {
   for(int j = 0; j < h; j++)
     {
      double half = halfW * (j + 0.5) / h;
      int y = ay + dir * j - (dir < 0 ? 1 : 0);
      for(int x = (int)MathFloor(ax - half - 1); x <= (int)MathCeil(ax + half + 1); x++)
        {
         double cov = MathMax(0.0, MathMin(1.0, half + 0.5 - MathAbs(x + 0.5 - ax)));
         if(cov > 0.0)
            CvPx(x, y, c, cov);
        }
     }
  }

void CvRender()
  {
   int W = (int)ChartGetInteger(0, CHART_WIDTH_IN_PIXELS);
   int H = (int)ChartGetInteger(0, CHART_HEIGHT_IN_PIXELS, 0);
   int bars = Bars(_Symbol, PERIOD_CURRENT);
   if(W <= 0 || H <= 0 || bars < 3)
      return;
   if(!g_cvsOk)
     {
      g_cvsName = PFX_UI + "CV";
      if(!g_cvs.CreateBitmapLabel(0, 0, g_cvsName, 0, 0, W, H, COLOR_FORMAT_ARGB_NORMALIZE))
         return;
      g_cvsOk = true;
      ObjectSetInteger(0, g_cvsName, OBJPROP_HIDDEN, true);
      ObjectSetInteger(0, g_cvsName, OBJPROP_SELECTABLE, false);
      ObjectSetInteger(0, g_cvsName, OBJPROP_BACK, false);
     }
   else if(g_cvs.Width() != W || g_cvs.Height() != H)
      g_cvs.Resize(W, H);
   g_cvW = W; g_cvH = H;
   g_cvs.Erase(0);

   if(!CvMap())
     {
      g_cvs.Update(true);
      return;
     }

   g_cvs.FontSet(FONT_TV, -CV_FONT);
   int n = ArraySize(g_cv);
   //--- Rectangles, puis traits, puis textes, puis prix du bord droit.
   for(int pass = 0; pass < 4; pass++)
      for(int i = 0; i < n; i++)
        {
         CvItem it = g_cv[i];
         if(pass == 0 && it.kind == CV_RECT)
           {
            int x1 = (int)MathRound(CvX(it.t1)), x2 = (int)MathRound(CvX(it.t2));
            int y1 = (int)MathRound(CvY(MathMax(it.p1, it.p2))), y2 = (int)MathRound(CvY(MathMin(it.p1, it.p2)));
            if(x2 < 0 || x1 > W || y2 < 0 || y1 > H)
               continue;
            if(it.c2 != 0)
              {
               CvFill(x1 + 1, y1 + 1, x2 - 1, y2 - 1, it.c1);
               CvLine(x1, y1, x2, y1, STYLE_SOLID, it.c2);
               CvLine(x1, y2, x2, y2, STYLE_SOLID, it.c2);
               CvLine(x1, y1 + 1, x1, y2 - 1, STYLE_SOLID, it.c2);
               CvLine(x2, y1 + 1, x2, y2 - 1, STYLE_SOLID, it.c2);
              }
            else
               CvFill(x1, y1, x2, y2, it.c1);
            if(it.text != "")
              {
               //--- Nom au centre de la partie visible du bloc, dans la couleur du bord.
               int va = MathMax(x1, 0), vb = MathMin(x2, W);
               g_cvs.TextOut((va + vb) / 2, (y1 + y2) / 2, it.text, it.c2 | 0xFF000000, TA_CENTER | TA_VCENTER);
              }
           }
         else if(pass == 1 && (it.kind == CV_SEG || it.kind == CV_TREND))
           {
            double x1 = MathRound(CvX(it.t1)), x2 = MathRound(CvX(it.t2));
            double y1 = MathRound(CvY(it.p1)), y2 = MathRound(CvY(it.p2));
            CvLine(x1, y1, x2, y2, it.style, it.c1);
           }
         else if(pass == 2 && it.kind == CV_TEXT)
           {
            int x = it.t2 == 1 ? (int)it.p2 : (int)MathRound(CvX(it.t1)), y = (int)MathRound(CvY(it.p1));
            if(x < -300 || x > W + 300)
               continue;
            int tw = 0, th = 0;
            g_cvs.TextSize(it.text, tw, th);
            int padX = Dp(3), padY = Dp(1), gap = it.style == 1 ? Dp(3) : Dp(2);
            int bw = tw + 2 * padX, bh = th + 2 * padY;
            int top = it.anchor == ANCHOR_LOWER ? y - gap - bh : (it.anchor == ANCHOR_UPPER ? y + gap : y - bh / 2);
            if(it.c2 != 0)
              {
               //--- style 1 : pointe vers le prix, comme les étiquettes « CSD ».
               if(it.style == 1 && it.anchor != ANCHOR_CENTER)
                  CvPointer(x, y, gap + 1, gap, it.anchor == ANCHOR_UPPER ? 1 : -1, it.c2);
               CvFillRound(x - bw / 2, top, x - bw / 2 + bw, top + bh, 2.0 * g_k, it.c2);
              }
            g_cvs.TextOut(x, top + bh / 2, it.text, it.c1, TA_CENTER | TA_VCENTER);
           }
         else if(pass == 3 && it.kind == CV_PRICE)
           {
            int y = (int)MathRound(CvY(it.p1));
            if(y < -20 || y > H + 20)
               continue;
            int tw = 0, th = 0;
            g_cvs.TextSize(it.text, tw, th);
            int bw = tw + 2 * Dp(3), bh = th + 2 * Dp(1);
            int x1 = (int)MathRound(CvX(RightTime())), x2 = x1 + bw;
            CvFillRound(x1, y - bh / 2, x2, y - bh / 2 + bh, 2.0 * g_k, it.c1);
            g_cvs.TextOut(x1 + bw / 2, y, it.text, Ar(clrWhite, 1.0), TA_CENTER | TA_VCENTER);
           }
        }
   g_cvs.Update(true);
  }

//+------------------------------------------------------------------+
//| Tableau en haut à gauche, même dessin que le ticket du           |
//| Calculateur XAU : fond 131722, cadres 363A45, Trebuchet MS.      |
//+------------------------------------------------------------------+
#define UI_BG      C'19,23,34'
#define UI_BORDER  C'54,58,69'
#define UI_TEXT    C'219,222,229'
#define UI_MUTED   C'134,139,152'
#define UI_GREEN   C'8,153,129'
#define UI_RED     C'242,54,69'
#define UI_ORANGE  C'238,164,52'
#define UI_INFO_BG C'30,34,45'
#define UI_TINT_G  C'16,48,42'
#define UI_TINT_R  C'64,24,30'
#define UI_TINT_O  C'60,46,22'
#define UI_TINT_N  C'30,34,45'
#define UI_SELL_ON C'84,28,36'
#define UI_SELL_TX C'247,82,95'
#define UI_BUY_ON  C'20,44,100'
#define UI_BUY_TX  C'91,156,246'
#define UI_SIDE    C'30,34,45'

int g_uiInner = 280;               // largeur utile du tableau (pixels)
int g_uiFs    = 9;                 // taille du texte (points)
int g_uiX     = 0;

void UiRect(string name, int x, int y, int w, int h, color bg, color border)
  {
   if(ObjectFind(0, name) < 0)
     {
      ObjectCreate(0, name, OBJ_RECTANGLE_LABEL, 0, 0, 0);
      ObjectSetInteger(0, name, OBJPROP_CORNER, CORNER_LEFT_UPPER);
      ObjectSetInteger(0, name, OBJPROP_BORDER_TYPE, BORDER_FLAT);
      ObjectSetInteger(0, name, OBJPROP_WIDTH, 1);
      ObjectSetInteger(0, name, OBJPROP_BACK, false);
      ObjBase(name);
     }
   ObjectSetInteger(0, name, OBJPROP_XDISTANCE, x);
   ObjectSetInteger(0, name, OBJPROP_YDISTANCE, y);
   ObjectSetInteger(0, name, OBJPROP_XSIZE, w);
   ObjectSetInteger(0, name, OBJPROP_YSIZE, h);
   ObjectSetInteger(0, name, OBJPROP_BGCOLOR, bg);
   ObjectSetInteger(0, name, OBJPROP_COLOR, border);
  }

//--- Texte coupé à la largeur disponible (pixels), avec « … ».
string FitPx(string text, int px, int pt)
  {
   uint w = 0, h = 0;
   TextSetFont(FONT_TV, -pt * 10);
   if(!TextGetSize(text, w, h) || (int)w <= px)
      return text;
   int lo = 1, hi = StringLen(text) - 1;
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

//--- anchor : ANCHOR_LEFT_UPPER, ANCHOR_RIGHT_UPPER ou ANCHOR_UPPER (centré).
void UiText(string name, int x, int y, string txt, color clr, int fs, string font, ENUM_ANCHOR_POINT anchor)
  {
   if(ObjectFind(0, name) < 0)
     {
      ObjectCreate(0, name, OBJ_LABEL, 0, 0, 0);
      ObjectSetInteger(0, name, OBJPROP_CORNER, CORNER_LEFT_UPPER);
      ObjectSetInteger(0, name, OBJPROP_BACK, false);
      ObjBase(name);
     }
   ObjectSetInteger(0, name, OBJPROP_ANCHOR, anchor);
   ObjectSetInteger(0, name, OBJPROP_XDISTANCE, x);
   ObjectSetInteger(0, name, OBJPROP_YDISTANCE, y);
   ObjectSetString(0, name, OBJPROP_FONT, font);
   ObjectSetInteger(0, name, OBJPROP_FONTSIZE, fs);
   ObjectSetInteger(0, name, OBJPROP_COLOR, clr);
   string t = txt == "" ? " " : txt;
   if(ObjectGetString(0, name, OBJPROP_TEXT) != t)
      ObjectSetString(0, name, OBJPROP_TEXT, t);
  }

//--- Zones de Claude les plus proches du prix : au-dessus, en dessous, ou celle où se trouve le prix.
void NearestZones(string &above, string &below, string &inside)
  {
   above = "-"; below = "-"; inside = "";
   if(!g_planOk)
      return;
   double px = SymbolInfoDouble(_Symbol, SYMBOL_BID);
   int up = -1, dn = -1;
   for(int i = 0; i < ArraySize(g_zones); i++)
     {
      if(g_zones[i].state == "cassee")
         continue;
      if(g_zones[i].lo <= px && g_zones[i].hi >= px)
         inside = (g_zones[i].buy ? "achat " : "vente ") + Fr(g_zones[i].lo, 1) + " - " + Fr(g_zones[i].hi, 1);
      if(g_zones[i].lo > px && (up < 0 || g_zones[i].lo < g_zones[up].lo))
         up = i;
      if(g_zones[i].hi < px && (dn < 0 || g_zones[i].hi > g_zones[dn].hi))
         dn = i;
     }
   if(up >= 0)
      above = (g_zones[up].buy ? "achat " : "vente ") + Fr(g_zones[up].lo, 1) + " (+" + Fr(g_zones[up].lo - px, 1) + ")";
   if(dn >= 0)
      below = (g_zones[dn].buy ? "achat " : "vente ") + Fr(g_zones[dn].hi, 1) + " (−" + Fr(px - g_zones[dn].hi, 1) + ")";
  }

void DrawPanel()
  {
   double s   = g_k;
   int    fs  = g_uiFs, fsB = 13;
   int    W   = (int)(300 * s), pad = (int)(12 * s), rh = (int)(18 * s), gap = (int)(8 * s);
   int    x0  = Dp(10), y0 = Dp(24);
   int    xL  = x0 + pad, xR = x0 + W - pad, inner = W - 2 * pad;
   g_uiInner = inner;
   g_uiX     = x0;
   string P   = PFX_UI + "P_";

   UiRect(P + "BG", x0, y0, W, 100, UI_BG, UI_BORDER);

   //--- En-tête
   int y = y0 + (int)(9 * s);
   UiText(P + "TITLE", xL, y, "Carnet XAU", clrWhite, fs + 2, FONT_TVB, ANCHOR_LEFT_UPPER);
   UiText(P + "SUB", xR, y + (int)(3 * s), g_week == "" ? "plan de Claude" : "semaine du " + g_week, UI_MUTED, fs - 1, FONT_TV, ANCHOR_RIGHT_UPPER);
   y += (int)(27 * s);

   //--- Baissier | range | haussier, comme Sell | spread | Buy : le biais dominant en surbrillance
   int  boxH = (int)(48 * s), boxW = (inner - gap) / 2, xB = xL + boxW + gap;
   bool bear = g_planOk && g_bear > g_bull, bull = g_planOk && g_bull > g_bear;
   UiRect(P + "BEARBG", xL, y, boxW, boxH, bear ? UI_SELL_ON : UI_SIDE, UI_BORDER);
   UiText(P + "BEART", xL + (int)(10 * s), y + (int)(6 * s), "Baissier", bear ? clrWhite : UI_SELL_TX, fs, FONT_TV, ANCHOR_LEFT_UPPER);
   UiText(P + "BEARP", xL + (int)(10 * s), y + (int)(21 * s), g_planOk ? IntegerToString(g_bear) + " %" : "-", bear ? clrWhite : UI_SELL_TX, fsB, FONT_TVB, ANCHOR_LEFT_UPPER);
   UiRect(P + "BULLBG", xB, y, boxW, boxH, bull ? UI_BUY_ON : UI_SIDE, UI_BORDER);
   UiText(P + "BULLT", xR - (int)(10 * s), y + (int)(6 * s), "Haussier", bull ? clrWhite : UI_BUY_TX, fs, FONT_TV, ANCHOR_RIGHT_UPPER);
   UiText(P + "BULLP", xR - (int)(10 * s), y + (int)(21 * s), g_planOk ? IntegerToString(g_bull) + " %" : "-", bull ? clrWhite : UI_BUY_TX, fsB, FONT_TVB, ANCHOR_RIGHT_UPPER);
   int spW = (int)(54 * s), spH = (int)(18 * s);
   UiRect(P + "RNGBG", xL + boxW + gap / 2 - spW / 2, y + (boxH - spH) / 2, spW, spH, UI_BG, UI_BORDER);
   UiText(P + "RNGT", xL + boxW + gap / 2, y + (boxH - spH) / 2 + (int)(3 * s), g_planOk ? "range " + IntegerToString(g_range) + " %" : "-", UI_TEXT, fs - 1, FONT_TV, ANCHOR_UPPER);
   y += boxH + (int)(12 * s);

   //--- Invalidation, zones, signaux : libellé à gauche, valeur à droite
   string above, below, inside;
   NearestZones(above, below, inside);
   int    block = CalcBlock();
   string calc  = !CalcAlive() ? "non relié" : (block > 0 ? "à l'arrêt : " + CalcBlockText(block)
                                                          : "relié · risque " + Fr(GlobalVariableGet(Bridge("calc_risk")), 2) + " $");
   string lab[6], val[6];
   color  vc[6];
   lab[0] = "Invalidation";
   val[0] = g_inval > 0 ? Fr(g_inval, 2) + (g_invalidated ? " · touchée" : " · intacte") : "-";
   vc[0]  = g_invalidated ? UI_RED : UI_TEXT;
   lab[1] = inside != "" ? "Prix dans la zone" : "Zone au-dessus";
   val[1] = inside != "" ? inside : above;
   vc[1]  = inside != "" ? UI_ORANGE : UI_TEXT;
   lab[2] = "Zone en dessous";
   val[2] = below;
   vc[2]  = UI_TEXT;
   lab[3] = "Zones du plan";
   val[3] = g_planOk ? IntegerToString(ArraySize(g_zones)) : "aucun plan";
   vc[3]  = g_planOk ? UI_TEXT : UI_ORANGE;
   lab[4] = "Signaux aujourd'hui";
   val[4] = InpSignals ? IntegerToString(g_signalCount) + " · aucun ordre passé" : "désactivés";
   vc[4]  = UI_TEXT;
   lab[5] = "Calculateur";
   val[5] = calc;
   vc[5]  = block > 0 ? UI_RED : (CalcAlive() ? UI_GREEN : UI_MUTED);
   int infoH = 6 * rh + (int)(10 * s);
   int ix = xL + (int)(10 * s), ixr = xR - (int)(10 * s);
   UiRect(P + "INFOBG", xL, y, inner, infoH, UI_INFO_BG, UI_INFO_BG);
   for(int i = 0; i < 6; i++)
     {
      int yy = y + (int)(7 * s) + i * rh;
      UiText(P + "L" + IntegerToString(i), ix, yy, lab[i], UI_MUTED, fs, FONT_TV, ANCHOR_LEFT_UPPER);
      UiText(P + "R" + IntegerToString(i), ixr, yy, FitPx(val[i], (int)(inner * 0.58), fs), vc[i], fs, FONT_TV, ANCHOR_RIGHT_UPPER);
     }
   y += infoH + (int)(10 * s);

   //--- Statut
   int    eh  = (int)(26 * s);
   color  stc = UI_MUTED, stb = UI_TINT_N;
   if(!g_planOk)                        { stc = UI_ORANGE; stb = UI_TINT_O; }
   if(g_invalidated)                    { stc = UI_RED;    stb = UI_TINT_R; }
   if(StringFind(g_status, "BUY") == 0 || StringFind(g_status, "SELL") == 0) { stc = UI_GREEN; stb = UI_TINT_G; }
   UiRect(P + "STBG", xL, y, inner, eh, stb, stb);
   UiText(P + "ST", xL + (int)(8 * s), y + (int)(6 * s), FitPx(g_status, inner - (int)(16 * s), fs), stc, fs, FONT_TV, ANCHOR_LEFT_UPPER);
   y += eh + pad;

   ObjectSetInteger(0, P + "BG", OBJPROP_YSIZE, y - y0);
   ChartRedraw();
  }

//+------------------------------------------------------------------+
//| Événements vers le carnet                                         |
//+------------------------------------------------------------------+
void SendEvent(string json)
  {
   if(!InpAlertCarnet)
      return;
   string resp;
   Http("POST", InpUrl + "/event", json, "application/json", resp);
  }

int Dominant() { return (g_bull > g_bear) ? 1 : (g_bear > g_bull ? -1 : 0); }

//--- Surveillance sur clôture M15 : invalidation et zones. Après un arrêt du PC, toutes les
//    bougies M15 manquées (7 jours au plus) sont relues dans l'ordre.
void Monitor()
  {
   datetime last = iTime(_Symbol, PERIOD_M15, 1);
   if(last == 0 || last == g_doneM15)
      return;
   if(g_planOk)
     {
      datetime from = g_doneM15 > 0 ? g_doneM15 + 900 : last;
      from = (datetime)MathMax((double)from, (double)(last - CATCHUP_DAYS * 86400));
      if(g_week != "")
         from = (datetime)MathMax((double)from, (double)StringToTime(g_week));   // jamais avant le lundi du plan
      MqlRates r[];
      int n = from <= last ? CopyRates(_Symbol, PERIOD_M15, from, last, r) : 0;   // r[0] = la plus ancienne
      bool changed = false;
      for(int j = 0; j < n; j++)
         if(r[j].time > g_doneM15 || g_doneM15 == 0)
            changed = MonitorBar(r[j].high, r[j].low, r[j].close, r[j].time + 900, last - r[j].time > 1800) || changed;
      if(changed && InpDrawPlan)
         DrawPlan();
      if(changed)
         DrawPanel();
     }
   g_doneM15 = last;
   GlobalVariableSet(GV_M15, (double)g_doneM15);
  }

//--- Une bougie M15 clôturée (à l'heure t, serveur) ; late = rattrapée après un arrêt.
bool MonitorBar(double hi, double lo, double c, datetime t, bool late)
  {
   long   when = (long)t - ServerOffset();          // heure UTC de la clôture
   string note = late ? " (pendant l'arrêt de MT5, " + TimeToString(t, TIME_DATE | TIME_MINUTES) + ")" : "";
   bool   changed = false;
   int    d = Dominant();

   if(!g_invalidated && g_inval > 0 && ((d == 1 && c < g_inval) || (d == -1 && c > g_inval)))
     {
      g_invalidated = true;
      changed = true;
      Notify("Carnet XAU : invalidation " + Fr(g_inval, 2) + " touchée (clôture M15 " + Fr(c, 2) + ")" + note + ".");
      SendEvent("{\"type\":\"invalidation\",\"price\":" + Num(c, 2) + ",\"time\":" + IntegerToString(when) + "}");
     }
   for(int i = 0; i < ArraySize(g_zones); i++)
     {
      if(g_zones[i].state == "cassee")
         continue;
      bool broken = g_zones[i].buy ? (c < g_zones[i].lo) : (c > g_zones[i].hi);
      if(broken)
        {
         g_zones[i].state = "cassee";
         changed = true;
         SendEvent("{\"type\":\"zone\",\"key\":" + JsonText(g_zones[i].key) + ",\"state\":\"cassee\",\"price\":" + Num(c, 2) + ",\"time\":" + IntegerToString(when) + "}");
        }
      else if(g_zones[i].state == "vierge" && lo <= g_zones[i].hi && hi >= g_zones[i].lo)
        {
         g_zones[i].state = "testee";
         SendEvent("{\"type\":\"zone\",\"key\":" + JsonText(g_zones[i].key) + ",\"state\":\"testee\",\"price\":" + Num(c, 2) + ",\"time\":" + IntegerToString(when) + "}");
        }
     }
   return changed;
  }

//+------------------------------------------------------------------+
//| Pont avec le Calculateur XAU : variables globales du terminal    |
//+------------------------------------------------------------------+
string Bridge(string key)
  {
   return "CXB_" + IntegerToString(AccountInfoInteger(ACCOUNT_LOGIN)) + "_" + key;
  }

// Calculateur présent sur le même compte (signe de vie de moins de 30 s)
bool CalcAlive()
  {
   string n = Bridge("calc_on");
   return GlobalVariableCheck(n) && (long)TimeLocal() - (long)GlobalVariableGet(n) < 30;
  }

// État du Calculateur : 0 prêt, 1 pertes du jour, 2 perte du jour, 3 pertes de la semaine,
// 4 perte de la semaine, 5 plancher
int CalcBlock()
  {
   if(!CalcAlive() || !GlobalVariableCheck(Bridge("calc_block")))
      return 0;
   return (int)GlobalVariableGet(Bridge("calc_block"));
  }

string CalcBlockText(int code)
  {
   if(code == 1) return "nombre de pertes du jour atteint";
   if(code == 2) return "perte maximum du jour atteinte";
   if(code == 3) return "nombre de pertes de la semaine atteint";
   if(code == 4) return "perte maximum de la semaine atteinte";
   if(code == 5) return "plancher du capital atteint";
   if(code == 6) return "objectif de l'étape atteint";
   if(code == 7) return "compte échoué ou retiré dans le carnet";
   if(code == 8) return "limite de trades du jour atteinte";
   return "";
  }

// Risque, limites du jour, étape et objectif du compte lus dans le carnet, pour le Calculateur XAU.
// Rien n'est publié avant la première lecture du plan : le Calculateur ne prend jamais des valeurs par défaut.
void PublishCarnet()
  {
   if(!g_rOk)
      return;
   int ph = 0;
   if(g_phase == "phase1") ph = 1;
   else if(g_phase == "phase2") ph = 2;
   else if(g_phase == "finance") ph = 3;
   else if(g_phase == "echoue") ph = 4;
   else if(g_phase == "retire") ph = 5;
   GlobalVariableSet(Bridge("cx_risk"), g_risk);
   GlobalVariableSet(Bridge("cx_riskred"), g_riskRed);
   GlobalVariableSet(Bridge("cx_redat"), g_redAt);
   GlobalVariableSet(Bridge("cx_contract"), g_contract);
   GlobalVariableSet(Bridge("cx_maxtrades"), g_maxTrades);
   GlobalVariableSet(Bridge("cx_maxloss"), g_maxLoss);
   GlobalVariableSet(Bridge("cx_spreadmax"), g_spreadMax);
   GlobalVariableSet(Bridge("cx_stopmin"), g_stopMin);
   GlobalVariableSet(Bridge("cx_stopmax"), g_stopMax);
   GlobalVariableSet(Bridge("cx_daylim"), g_dailyLim);
   GlobalVariableSet(Bridge("cx_size"), g_size);
   GlobalVariableSet(Bridge("cx_floor"), g_floor);
   GlobalVariableSet(Bridge("cx_phase"), ph);
   GlobalVariableSet(Bridge("cx_goal"), g_goal);
   GlobalVariableSet(Bridge("cx_gain"), g_gain);
   GlobalVariableSet(Bridge("cx_days"), g_daysDone);
   GlobalVariableSet(Bridge("cx_mindays"), g_minDays);
   GlobalVariableSet(Bridge("cx_wkloss"), g_weekLoss);
   GlobalVariableSet(Bridge("cx_wkstopr"), g_weekStopR);
   GlobalVariableSet(Bridge("cx_own"), g_ownKind ? 1.0 : 0.0);
   GlobalVariableSet(Bridge("cx_at"), (double)g_planAt);
   GlobalVariableSet(Bridge("cx_on"), (double)TimeLocal());   // en dernier : les valeurs sont complètes
  }

// Transmet le signal au Calculateur ; la référence est écrite en dernier (elle déclenche la lecture)
void BridgeSignal(bool buy, string kind, string setup, double entry, double sl, double tp, string ref)
  {
   GlobalVariableSet(Bridge("sig_dir"), buy ? 1.0 : -1.0);
   GlobalVariableSet(Bridge("sig_entry"), entry);
   GlobalVariableSet(Bridge("sig_sl"), sl);
   GlobalVariableSet(Bridge("sig_tp"), tp);
   GlobalVariableSet(Bridge("sig_limit"), kind == "LIMIT" ? 1.0 : 0.0);
   GlobalVariableSet(Bridge("sig_setup"), StringToDouble(setup));
   GlobalVariableSet(Bridge("sig_exp"), (double)((long)TimeLocal() + (kind == "LIMIT" ? g_limitMin : 5) * 60));
   GlobalVariableSet(Bridge("sig_ref"), StringToDouble(ref));
   long ch = ChartFirst();
   while(ch >= 0)
     {
      if(ch != ChartID())
         EventChartCustom(ch, 101, buy ? 1 : -1, sl, ref);
      ch = ChartNext(ch);
     }
  }

void Notify(string msg)
  {
   if(InpAlertPopup)
      Alert(msg);
   if(InpAlertPush)
      SendNotification(msg);
  }

//+------------------------------------------------------------------+
//| Signaux                                                           |
//+------------------------------------------------------------------+
bool NewsBlocked(string &why)
  {
   datetime now = TimeGMT();
   for(int i = 0; i < ArraySize(g_news); i++)
     {
      long dt = (long)g_news[i] - (long)now;
      if(dt <= 15 * 60 && dt >= -30 * 60)
        {
         why = "annonce " + g_newsName[i];
         return true;
        }
     }
   return false;
  }

//--- Prochain objectif opposé au-delà du prix d'entrée (zone, niveau ou range attendu).
double NextTarget(bool buy, double entry)
  {
   double best = 0;
   for(int i = 0; i < ArraySize(g_zones); i++)
     {
      if(g_zones[i].state == "cassee" || g_zones[i].buy == buy)
         continue;
      double p = buy ? g_zones[i].lo : g_zones[i].hi;
      if(buy ? p > entry : p < entry)
         if(best == 0 || (buy ? p < best : p > best))
            best = p;
     }
   for(int i = 0; i < ArraySize(g_levels); i++)
     {
      double p = g_levels[i].price;
      if(buy ? p > entry : p < entry)
         if(best == 0 || (buy ? p < best : p > best))
            best = p;
     }
   double r = buy ? g_rhi : g_rlo;
   if(r > 0 && (buy ? r > entry : r < entry))
      if(best == 0 || (buy ? r < best : r > best))
         best = r;
   return best;
  }

//--- L'extrême a-t-il balayé un niveau de liquidité (ou le bord de la zone) avant de revenir ?
bool Swept(bool buy, double extreme, double closeAtExtreme, double zoneEdge)
  {
   if(buy ? (extreme < zoneEdge && closeAtExtreme > zoneEdge) : (extreme > zoneEdge && closeAtExtreme < zoneEdge))
      return true;
   for(int i = 0; i < ArraySize(g_levels); i++)
     {
      double p = g_levels[i].price;
      if(buy ? (extreme < p && closeAtExtreme > p) : (extreme > p && closeAtExtreme < p))
         return true;
     }
   return false;
  }

void CheckSignals()
  {
   int need = g_touch + g_swing + 4;
   MqlRates r[];
   ArraySetAsSeries(r, true);
   if(CopyRates(_Symbol, PERIOD_M1, 1, need, r) < need)
      return;                                     // r[0] = dernière bougie M1 clôturée
   if(TimeCurrent() - g_lastSignal < 600)
      return;                                     // 10 minutes entre deux signaux

   int d = Dominant();
   for(int i = 0; i < ArraySize(g_zones); i++)
     {
      CxZone z = g_zones[i];
      if(z.state == "cassee")
         continue;
      if(TimeCurrent() - z.lastSignal < g_cooldown * 60)
         continue;
      bool buy = z.buy;
      int  dir = buy ? 1 : -1;
      if(g_invalidated && dir == d)
         continue;                                // plan invalidé dans ce sens

      // Extrême de la touche : plus bas (achat) ou plus haut (vente) des bougies qui ont touché la zone.
      int e = -1;
      for(int k = 1; k <= g_touch; k++)
        {
         bool touched = (r[k].low <= z.hi && r[k].high >= z.lo);
         if(!touched)
            continue;
         if(e < 0 || (buy ? r[k].low < r[e].low : r[k].high > r[e].high))
            e = k;
        }
      if(e < 1)
         continue;
      double extreme = buy ? r[e].low : r[e].high;
      // L'extrême peut déborder de la zone en mèche, mais pas clôturer au-delà.
      if(buy ? r[e].close < z.lo : r[e].close > z.hi)
         continue;

      bool   sweep = Swept(buy, extreme, r[e].close, buy ? z.lo : z.hi);
      string setup = "";
      double retest = 0;                          // entrée « retour FVG » (0 = aucune)

      // Setup 3, iFVG : le FVG contraire le plus récent de la jambe vers la zone (bougies e…e+N),
      // jamais clôturé au-delà jusqu'ici, est franchi en clôture par la dernière bougie.
      for(int k = e; k <= e + g_swing && k + 2 < need; k++)
        {
         double top = buy ? r[k + 2].low : r[k].low;    // achat : FVG baissier entre le bas de A et le haut de C
         double bot = buy ? r[k].high : r[k + 2].high;  // vente : FVG haussier entre le haut de A et le bas de C
         if(top - bot < g_fvg)
            continue;
         bool used = false;
         for(int j = 1; j < k; j++)
            if(buy ? r[j].close > top : r[j].close < bot)
               used = true;                       // déjà inversé plus tôt
         if(!used && (buy ? (r[0].close > top && r[1].close <= top) : (r[0].close < bot && r[1].close >= bot)))
           {
            setup  = "3";
            retest = buy ? top : bot;             // retour sur le bord de l'iFVG
           }
         break;                                   // seul le FVG le plus récent compte
        }

      if(setup == "")
        {
         // Cassure de structure : dernier sommet pivot (achat) ou creux pivot (vente) avant l'extrême,
         // à défaut le plus haut (plus bas) des N bougies.
         double swing = buy ? 0 : DBL_MAX;
         bool   piv   = false;
         for(int k = e + 2; k <= e + g_swing && k + 2 < need; k++)
           {
            if(buy ? (r[k].high > r[k - 1].high && r[k].high > r[k - 2].high && r[k].high >= r[k + 1].high && r[k].high >= r[k + 2].high)
                   : (r[k].low < r[k - 1].low && r[k].low < r[k - 2].low && r[k].low <= r[k + 1].low && r[k].low <= r[k + 2].low))
              {
               swing = buy ? r[k].high : r[k].low;
               piv   = true;
               break;
              }
           }
         if(!piv)
            for(int k = e + 1; k <= e + g_swing && k < need; k++)
               swing = buy ? MathMax(swing, r[k].high) : MathMin(swing, r[k].low);
         // Cassure fraîche : la dernière bougie clôture au-delà, la précédente non.
         bool brk = buy ? (r[0].close > swing && r[1].close <= swing) : (r[0].close < swing && r[1].close >= swing);
         if(!brk)
            continue;
         setup = sweep ? "1" : "2";
         if(!sweep && dir != d)
            continue;                             // setup 2 : seulement avec le biais
         // Retour : bord du FVG le plus récent laissé par la cassure (bougies 0…e).
         for(int k = 0; k + 2 <= e; k++)
            if(buy ? (r[k].low - r[k + 2].high >= g_fvg) : (r[k + 2].low - r[k].high >= g_fvg))
              {
               retest = buy ? r[k].low : r[k].high;
               break;
              }
        }

      double ask = SymbolInfoDouble(_Symbol, SYMBOL_ASK);
      double bid = SymbolInfoDouble(_Symbol, SYMBOL_BID);
      double spread = ask - bid;
      double entry = buy ? ask : bid;
      double sl    = buy ? extreme - g_slbuf - spread : extreme + g_slbuf + spread;
      double stop  = MathAbs(entry - sl);
      string kind  = "NOW";
      if(stop > g_stopMax && InpUseLimit)
        {
         // Ordre limite : entrée au prix où le stop revient au maximum autorisé.
         entry = buy ? sl + g_stopMax : sl - g_stopMax;
         stop  = g_stopMax;
         kind  = "LIMIT";
        }
      string why   = "";
      if(stop < g_stopMin || stop > g_stopMax)
         why = "stop " + Fr(stop, 2) + " $ hors de " + Fr(g_stopMin, 2) + "-" + Fr(g_stopMax, 2) + " $";
      else if(spread > stop * g_spreadMax / 100.0)
         why = "spread " + Fr(spread, 2) + " $ trop large";
      else if(NewsBlocked(why))
         why = why + " (15 min avant / 30 min après)";

      double target = NextTarget(buy, entry);
      double tp     = target > 0 ? target : (buy ? entry + 3 * stop : entry - 3 * stop);
      double rr     = stop > 0 ? MathAbs(tp - entry) / stop : 0;
      if(why == "" && rr < 2.0)
         why = "moins de 2R de place (" + Fr(rr, 1) + "R)";

      int trades, losses;
      double pnl = TodayStats(trades, losses);
      if(why == "" && trades >= g_maxTrades)
         why = "limite de " + IntegerToString(g_maxTrades) + " trades atteinte";
      if(why == "" && losses >= g_maxLoss)
         why = IntegerToString(losses) + " pertes aujourd'hui";
      if(why == "" && -pnl >= 0.8 * g_dailyLim)
         why = "proche de la limite de perte journalière";

      g_zones[i].lastSignal = TimeCurrent();
      if(why != "")
        {
         g_status = "Signal " + (buy ? "achat" : "vente") + " ignoré sur " + z.type + " " + z.tf + " : " + why + ".";
         DrawPanel();
         continue;
        }

      // Lot : risque du palier donné par le Calculateur XAU s'il tourne sur ce compte ; sinon risque
      // du carnet (réduit si le compte est à −seuil), plafonné à la marge − 5 $.
      double balance = AccountInfoDouble(ACCOUNT_BALANCE);
      double risk = (balance - g_size <= -g_redAt) ? g_riskRed : g_risk;
      if(CalcAlive() && GlobalVariableCheck(Bridge("calc_risk")) && GlobalVariableGet(Bridge("calc_risk")) > 0)
         risk = GlobalVariableGet(Bridge("calc_risk"));
      else
        {
         double margin = balance - g_floor;
         if(risk > margin - 5)
            risk = MathMax(0, margin - 5);
        }
      double lot = MathFloor(risk / (stop * g_contract) * 100.0 + 1e-9) / 100.0;
      if(lot < 0.01)
        {
         g_status = "Signal ignoré : lot inférieur à 0,01.";
         DrawPanel();
         continue;
        }
      // Variante « retour FVG » : même stop et même objectif, entrée plus proche de l'extrême.
      double vStop = retest > 0 ? MathAbs(retest - sl) : 0;
      bool   vOk   = InpRetest && retest > 0 && kind == "NOW" && (buy ? retest < entry : retest > entry)
                     && vStop >= g_stopMin && vStop <= g_stopMax && MathAbs(tp - retest) / vStop >= 2.0;
      ShowSignal(buy, kind, setup, z, entry, sl, tp, rr, lot, stop, r[0].time, vOk ? retest : 0);
      if(vOk)
         ShowRetest(buy, setup, z, retest, sl, tp, MathAbs(tp - retest) / vStop,
                    MathFloor(risk / (vStop * g_contract) * 100.0 + 1e-9) / 100.0, vStop, r[0].time);
      g_lastSignal = TimeCurrent();
      return;
     }
  }

void ShowSignal(bool buy, string kind, string setup, CxZone &z, double entry, double sl, double tp, double rr, double lot, double stop, datetime t, double retest)
  {
   g_signalCount++;
   string id = PFX_SIG + IntegerToString((long)t);
   datetime t2 = t + 60 * 60;
   string label = (buy ? "BUY " : "SELL ") + kind;
   string ref = IntegerToString((long)t);
   int k = ArraySize(g_tracks);
   ArrayResize(g_tracks, k + 1);
   g_tracks[k].ref     = ref;
   g_tracks[k].id      = id;
   g_tracks[k].label   = label;
   g_tracks[k].buy     = buy;
   g_tracks[k].entry   = entry;
   g_tracks[k].sl      = sl;
   g_tracks[k].tp      = tp;
   g_tracks[k].stop    = stop;
   g_tracks[k].status  = (kind == "LIMIT") ? 0 : 1;
   g_tracks[k].hit2r   = false;
   g_tracks[k].mfe     = 0;
   g_tracks[k].mae     = 0;
   g_tracks[k].expires = TimeCurrent() + g_limitMin * 60;
   g_tracks[k].opened  = (kind == "LIMIT") ? (datetime)0 : TimeCurrent();
   SaveTracks();

   // Position dessinée : objectif en vert, stop en rouge (comme l'outil position longue / courte).
   double p2r = buy ? entry + 2 * stop : entry - 2 * stop;
   DrawAdd(id, t, t2, buy, entry, sl, tp, p2r,
           label + " " + Fr(lot, 2) + " lot · SL " + Fr(sl, 2) + " · TP " + Fr(tp, 2) + " (" + Fr(rr, 1) + "R) S" + setup);

   int    block = CalcBlock();
   string note  = block > 0 ? "Calculateur à l'arrêt (" + CalcBlockText(block) + ") : ne pas prendre."
                            : (CalcAlive() ? "Calculateur : touche C pour charger le signal, Espace pour l'envoyer." : "");
   string msg = "Carnet XAU : " + label + " " + _Symbol + " — " + z.type + " " + z.tf + ", entrée " + Fr(entry, 2) + ", stop " + Fr(sl, 2)
                + ", objectif " + Fr(tp, 2) + " (" + Fr(rr, 1) + "R), lot " + Fr(lot, 2)
                + (kind == "LIMIT" ? ", valable " + IntegerToString(g_limitMin) + " min" : "") + ". Signal seulement : à vous de décider."
                + (note != "" ? " " + note : "");
   Notify(msg);
   BridgeSignal(buy, kind, setup, entry, sl, tp, ref);
   g_status = label + " " + TimeToString(TimeCurrent(), TIME_MINUTES) + " sur " + z.type + " " + z.tf + ".";
   SendEvent("{\"type\":\"signal\",\"ref\":" + JsonText(ref) + ",\"kind\":\"" + (kind == "LIMIT" ? "limit" : "now") + "\",\"side\":\"" + (buy ? "buy" : "sell") + "\",\"setup\":\"" + setup + "\",\"zone\":"
             + JsonText(z.type + " " + z.tf + " " + Num(z.lo, 2) + "-" + Num(z.hi, 2)) + ",\"entry\":" + Num(entry, 2) + ",\"sl\":" + Num(sl, 2)
             + ",\"tp\":" + Num(tp, 2) + ",\"lot\":" + Num(lot, 2) + ",\"rr\":" + Num(rr, 2) + ",\"stop\":" + Num(stop, 2)
             + (retest > 0 ? ",\"retest\":" + Num(retest, 2) : "")
             + (block > 0 ? ",\"note\":" + JsonText(note) : "")
             + ",\"time\":" + IntegerToString((long)TimeGMT()) + "}");
   DrawPanel();
  }

//--- Variante « retour FVG » : trade virtuel en ordre limite, suivi sans alerte pour comparer les entrées.
void ShowRetest(bool buy, string setup, CxZone &z, double entry, double sl, double tp, double rr, double lot, double stop, datetime t)
  {
   string id = PFX_SIG + IntegerToString((long)t) + "r";
   string ref = IntegerToString((long)t) + "r";
   int k = ArraySize(g_tracks);
   ArrayResize(g_tracks, k + 1);
   g_tracks[k].ref     = ref;
   g_tracks[k].id      = id;
   g_tracks[k].label   = (buy ? "BUY " : "SELL ") + "RETOUR";
   g_tracks[k].buy     = buy;
   g_tracks[k].entry   = entry;
   g_tracks[k].sl      = sl;
   g_tracks[k].tp      = tp;
   g_tracks[k].stop    = stop;
   g_tracks[k].status  = 0;
   g_tracks[k].hit2r   = false;
   g_tracks[k].mfe     = 0;
   g_tracks[k].mae     = 0;
   g_tracks[k].expires = TimeCurrent() + g_limitMin * 60;
   g_tracks[k].opened  = 0;
   SaveTracks();
   DrawAdd(id, t, t + 30 * 60, buy, entry, sl, tp, 0, "Retour FVG " + Fr(entry, 2));
   SendEvent("{\"type\":\"signal\",\"variant\":true,\"ref\":" + JsonText(ref) + ",\"kind\":\"retest\",\"side\":\"" + (buy ? "buy" : "sell") + "\",\"setup\":\"" + setup + "\",\"zone\":"
             + JsonText(z.type + " " + z.tf + " " + Num(z.lo, 2) + "-" + Num(z.hi, 2)) + ",\"entry\":" + Num(entry, 2) + ",\"sl\":" + Num(sl, 2)
             + ",\"tp\":" + Num(tp, 2) + ",\"lot\":" + Num(lot, 2) + ",\"rr\":" + Num(rr, 2) + ",\"stop\":" + Num(stop, 2)
             + ",\"time\":" + IntegerToString((long)TimeGMT()) + "}");
  }
//+------------------------------------------------------------------+
