//+------------------------------------------------------------------+
//| CarnetXAU.mq5 — version 2.14.0                                   |
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
#property version   "2.14"
#property description "Plan de Claude sur le graphique, signaux d'exécution (signal seulement) et synchronisation du Carnet XAU."

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
   datetime lastSignal;
  };
struct CxLevel { double price; string type; };
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
#define TAG_PT   8                 // taille des noms de lignes et des étiquettes des positions (points)
//--- Échelle de l'écran : Windows à 125 % ou 150 % agrandit le texte (en points) mais pas les
//    distances (en pixels) ; elles sont multipliées par DPI / 96 pour que rien ne se chevauche.
double   g_k = 1.0;
int      Dp(double px) { return (int)MathRound(px * g_k); }

//--- Largeur réelle d'un texte en Trebuchet MS, en pixels, à l'échelle de l'écran.
int TextPx(string txt, int pt, bool bold)
  {
   uint w = 0, h = 0;
   TextSetFont(FONT_TV, -pt * 10, bold ? FW_BOLD : FW_NORMAL);
   if(!TextGetSize(txt, w, h) || w == 0)
      return (int)(StringLen(txt) * pt * 0.8 * g_k);
   return (int)w;
  }

//--- Couleur fg posée à « a » (0 à 1) sur le fond du graphique : la transparence de TradingView.
color Blend(color bg, color fg, double a)
  {
   int b0 = (int)bg, f0 = (int)fg;
   int r  = (int)MathRound((b0 & 0xFF) + (((f0 & 0xFF) - (b0 & 0xFF)) * a));
   int g  = (int)MathRound(((b0 >> 8) & 0xFF) + ((((f0 >> 8) & 0xFF) - ((b0 >> 8) & 0xFF)) * a));
   int b  = (int)MathRound(((b0 >> 16) & 0xFF) + ((((f0 >> 16) & 0xFF) - ((b0 >> 16) & 0xFF)) * a));
   return (color)(r | (g << 8) | (b << 16));
  }
color ChartBg() { return (color)ChartGetInteger(0, CHART_COLOR_BACKGROUND); }

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
   string gv = "CarnetXAU_" + IntegerToString(AccountInfoInteger(ACCOUNT_LOGIN)) + "_" + _Symbol;
   GV_M1  = gv + "_M1";
   GV_M15 = gv + "_M15";
   if(GlobalVariableCheck(GV_M1))
      g_doneM1 = (datetime)GlobalVariableGet(GV_M1);
   if(GlobalVariableCheck(GV_M15))
      g_doneM15 = (datetime)GlobalVariableGet(GV_M15);
   g_k = MathMax(0.75, MathMin(3.0, TerminalInfoInteger(TERMINAL_SCREEN_DPI) / 96.0));
   ApplyTVStyle();
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
   if(id != CHARTEVENT_CHART_CHANGE || !InpDrawPlan || g_hash == "")
      return;
   long s = ChartGetInteger(0, CHART_SCALE), f = ChartGetInteger(0, CHART_FIRST_VISIBLE_BAR), w = ChartGetInteger(0, CHART_WIDTH_IN_BARS);
   if(s == scale && f == first && w == width)
      return;
   scale = s; first = f; width = w;
   DrawPlan();
  }

void OnTick()
  {
   datetime m1 = iTime(_Symbol, PERIOD_M1, 0);
   if(m1 == g_lastM1)
      return;
   g_lastM1 = m1;               // nouvelle bougie M1 : la précédente vient de clôturer
   PublishCarnet();             // signe de vie pour le Calculateur XAU
   static datetime lastBar = 0;
   datetime bar = iTime(_Symbol, PERIOD_CURRENT, 0);
   if(InpDrawPlan && g_hash != "" && bar != lastBar)
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
   ObjectSetString(0, g_tracks[i].id + "_T", OBJPROP_TEXT, g_tracks[i].label + " : résultat " + res);
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
            string names[] = {"_TP", "_SL", "_2R", "_T"};
            for(int n = 0; n < ArraySize(names); n++)
               ObjectSetInteger(0, g_tracks[i].id + names[n], OBJPROP_COLOR, C'225,225,225');
            ObjectSetString(0, g_tracks[i].id + "_T", OBJPROP_TEXT, g_tracks[i].label + " annulé (prix non atteint en " + IntegerToString(g_limitMin) + " min)");
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

void Text(string name, datetime t, double price, string txt, color clr, int size)
  {
   ObjectCreate(0, name, OBJ_TEXT, 0, t, price);
   ObjectSetString(0, name, OBJPROP_TEXT, txt);
   ObjectSetString(0, name, OBJPROP_FONT, FONT_TV);
   ObjectSetInteger(0, name, OBJPROP_COLOR, clr);
   ObjectSetInteger(0, name, OBJPROP_FONTSIZE, size);
   ObjectSetInteger(0, name, OBJPROP_ANCHOR, ANCHOR_LEFT_LOWER);
   ObjBase(name);
  }

void HLine(string name, double price, color clr, ENUM_LINE_STYLE style, int width, string tip)
  {
   ObjectCreate(0, name, OBJ_HLINE, 0, 0, price);
   ObjectSetInteger(0, name, OBJPROP_COLOR, clr);
   ObjectSetInteger(0, name, OBJPROP_STYLE, style);
   ObjectSetInteger(0, name, OBJPROP_WIDTH, width);
   ObjectSetInteger(0, name, OBJPROP_BACK, true);
   ObjectSetString(0, name, OBJPROP_TOOLTIP, tip);
   ObjBase(name);
  }

void Rect(string name, datetime t1, double p1, datetime t2, double p2, color clr, string tip)
  {
   ObjectCreate(0, name, OBJ_RECTANGLE, 0, t1, p1, t2, p2);
   ObjectSetInteger(0, name, OBJPROP_COLOR, clr);
   ObjectSetInteger(0, name, OBJPROP_FILL, true);
   ObjectSetInteger(0, name, OBJPROP_BACK, true);
   ObjectSetString(0, name, OBJPROP_TOOLTIP, tip);
   ObjBase(name);
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

//--- Bougie d'origine : la dernière (hors bougie en cours) qui touche le prix ou la zone.
datetime OriginTime(double lo, double hi)
  {
   int n = (int)MathMin(600, Bars(_Symbol, PERIOD_CURRENT) - 1);
   for(int i = 2; i < n; i++)
      if(iLow(_Symbol, PERIOD_CURRENT, i) <= hi + 0.15 && iHigh(_Symbol, PERIOD_CURRENT, i) >= lo - 0.15)
         return iTime(_Symbol, PERIOD_CURRENT, i);
   return iTime(_Symbol, PERIOD_CURRENT, MathMax(1, n - 1));
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

void Seg(string name, datetime t1, datetime t2, double price, color clr, ENUM_LINE_STYLE style, bool ray)
  {
   ObjectCreate(0, name, OBJ_TREND, 0, t1, price, t2, price);
   ObjectSetInteger(0, name, OBJPROP_COLOR, clr);
   ObjectSetInteger(0, name, OBJPROP_STYLE, style);
   ObjectSetInteger(0, name, OBJPROP_WIDTH, 1);
   ObjectSetInteger(0, name, OBJPROP_RAY_RIGHT, ray);
   ObjectSetInteger(0, name, OBJPROP_BACK, true);
   ObjBase(name);
  }

//--- Texte centré en petites capitales, comme les étiquettes des outils de TradingView.
void Tag(string name, datetime t, double price, string txt, color clr, ENUM_ANCHOR_POINT anchor)
  {
   ObjectCreate(0, name, OBJ_TEXT, 0, t, price);
   ObjectSetString(0, name, OBJPROP_TEXT, txt);
   ObjectSetString(0, name, OBJPROP_FONT, FONT_TVB);
   ObjectSetInteger(0, name, OBJPROP_FONTSIZE, TAG_PT);
   ObjectSetInteger(0, name, OBJPROP_COLOR, clr);
   ObjectSetInteger(0, name, OBJPROP_ANCHOR, anchor);
   ObjBase(name);
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
//    prix déjà affiché (moins de 13 pixels à l'échelle de l'écran).
void PriceTag(string name, double price, color clr)
  {
   int y = PriceY(price);
   for(int i = 0; i < ArraySize(g_tagY); i++)
      if(MathAbs(g_tagY[i] - y) < Dp(13))
         return;
   int n = ArraySize(g_tagY);
   ArrayResize(g_tagY, n + 1);
   g_tagY[n] = y;
   ObjectCreate(0, name, OBJ_ARROW_RIGHT_PRICE, 0, RightTime(), price);
   ObjectSetInteger(0, name, OBJPROP_COLOR, clr);
   ObjectSetInteger(0, name, OBJPROP_WIDTH, 1);
   ObjBase(name);
  }

//--- Ligne de l'origine au bord droit, nom au milieu, trait interrompu sous le texte.
void NamedLine(string id, datetime t1, double price, string txt, color clr, ENUM_LINE_STYLE style)
  {
   //--- En bougies (les nuits et week-ends n'en ont pas) et dans la partie visible du graphique.
   int sh  = iBarShift(_Symbol, PERIOD_CURRENT, t1);
   int vis = (int)ChartGetInteger(0, CHART_FIRST_VISIBLE_BAR);
   if(sh < 0 || sh > vis)
      sh = vis;
   t1 = iTime(_Symbol, PERIOD_CURRENT, sh);
   datetime tEnd = RightTime();
   int w = TextPx(txt, TAG_PT, true) + Dp(10), g = (int)MathCeil(w / (double)BarPx() / 2.0);
   //--- Nom au milieu du trait, sinon au quart, aux trois quarts… : la première place libre.
   double fr[] = { 0.5, 0.25, 0.75, 0.12, 0.88 };
   int y = PriceY(price), m = sh / 2;
   for(int k = 0; k < ArraySize(fr); k++)
     {
      int c = (int)MathRound(sh * (1 - fr[k])), x = 0, yy = 0;
      ChartTimePriceToXY(0, 0, iTime(_Symbol, PERIOD_CURRENT, c), price, x, yy);
      bool isFree = true;
      for(int j = 0; j < ArraySize(g_lblX) && isFree; j++)
         if(MathAbs(g_lblY[j] - y) < Dp(12) && MathAbs(g_lblX[j] - x) < (g_lblW[j] + w) / 2 + Dp(6))
            isFree = false;
      if(isFree)
        {
         m = c;
         break;
        }
     }
   int nx = 0, ny = 0, n = ArraySize(g_lblX);
   ChartTimePriceToXY(0, 0, iTime(_Symbol, PERIOD_CURRENT, m), price, nx, ny);
   ArrayResize(g_lblX, n + 1);
   ArrayResize(g_lblY, n + 1);
   ArrayResize(g_lblW, n + 1);
   g_lblX[n] = nx;
   g_lblY[n] = y;
   g_lblW[n] = w;
   datetime tm = iTime(_Symbol, PERIOD_CURRENT, m);
   if(sh - m > g + 1 && m - g > 0)
     {
      Seg(id + "a", t1, iTime(_Symbol, PERIOD_CURRENT, m + g), price, clr, style, false);
      Seg(id + "b", iTime(_Symbol, PERIOD_CURRENT, m - g), tEnd, price, clr, style, false);
     }
   else
      Seg(id + "a", t1, tEnd, price, clr, style, false);
   Tag(id + "t", tm, price, txt, clr, ANCHOR_CENTER);
  }

void DrawPlan()
  {
   ObjectsDeleteAll(0, PFX_PLAN);
   ArrayResize(g_tagY, 0);
   ArrayResize(g_lblX, 0);
   ArrayResize(g_lblY, 0);
   ArrayResize(g_lblW, 0);
   datetime tFar = TimeCurrent() + 7 * 86400;

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
      string id  = PFX_PLAN + "P" + IntegerToString(k);
      double stop = MathAbs(p.entry - p.sl);
      if(stop <= 0)
         continue;
      datetime xa = FutureTime(3 + k * (len + 4)), xb = FutureTime(3 + k * (len + 4) + len), xm = xa + (xb - xa) / 2;
      Rect(id + "tp", xa, p.entry, xb, p.tp, Blend(ChartBg(), C'8,153,129', 0.20), "Objectif " + Fr(p.tp, 2));
      Rect(id + "sl", xa, p.entry, xb, p.sl, Blend(ChartBg(), C'242,54,69', 0.20), "Stop " + Fr(p.sl, 2));
      Seg(id + "e", xa, xb, p.entry, C'120,123,134', STYLE_SOLID, false);
      bool up = p.tp > p.entry;
      double rr = MathAbs(p.tp - p.entry) / stop;
      Tag(id + "tt", xm, p.tp, "Objectif " + Fr(p.tp, 2) + " · " + Fr(rr, 1) + "R", C'8,153,129', up ? ANCHOR_LOWER : ANCHOR_UPPER);
      Tag(id + "st", xm, p.sl, "Stop " + Fr(p.sl, 2) + " · " + Fr(stop, 2) + " $", C'242,54,69', up ? ANCHOR_UPPER : ANCHOR_LOWER);
      Tag(id + "et", xm, p.entry, (p.buy ? "Achat " : "Vente ") + Fr(p.entry, 2) + (p.label != "" ? " · " + p.label : ""), C'19,23,34', up ? ANCHOR_UPPER : ANCHOR_LOWER);
      if(k == 0)
        {
         datetime t0 = iTime(_Symbol, PERIOD_CURRENT, 0);
         double   c0 = iClose(_Symbol, PERIOD_CURRENT, 0);
         ObjectCreate(0, id + "w1", OBJ_TREND, 0, t0, c0, xa + 2 * per, p.entry);
         ObjectCreate(0, id + "w2", OBJ_TREND, 0, xa + 2 * per, p.entry, xb - 3 * per, p.tp);
         for(int w = 1; w <= 2; w++)
           {
            string nm = id + "w" + IntegerToString(w);
            ObjectSetInteger(0, nm, OBJPROP_COLOR, C'41,98,255');
            ObjectSetInteger(0, nm, OBJPROP_STYLE, STYLE_DASH);
            ObjectSetInteger(0, nm, OBJPROP_RAY_RIGHT, false);
            ObjBase(nm);
           }
         PriceTag(id + "pe", p.entry, C'120,123,134');
         PriceTag(id + "pt", p.tp, C'8,153,129');
         PriceTag(id + "ps", p.sl, C'242,54,69');
        }
     }
   //--- Invalidation : celle de la dernière analyse de séance, sinon celle du plan de la semaine.
   double inv = g_sinv > 0 ? g_sinv : g_inval;
   if(inv > 0)
     {
      color clr = g_invalidated && g_sinv <= 0 ? C'178,181,190' : C'242,54,69';
      NamedLine(PFX_PLAN + "INV", OriginTime(inv, inv), inv, g_invalidated && g_sinv <= 0 ? "INVALIDATION TOUCHÉE" : "INVALIDATION", clr, STYLE_SOLID);
      PriceTag(PFX_PLAN + "INVp", inv, clr);
     }
   //--- Niveaux de liquidité : trait plein et fin, nom au milieu, prix coloré au bord droit.
   for(int i = 0; i < ArraySize(g_levels); i++)
     {
      color  clr = LevelColor(g_levels[i].type);
      string id  = PFX_PLAN + "L" + IntegerToString(i);
      NamedLine(id, OriginTime(g_levels[i].price, g_levels[i].price), g_levels[i].price, LevelName(g_levels[i].type), clr, STYLE_SOLID);
      PriceTag(id + "p", g_levels[i].price, clr);
     }
   //--- Zones encore valides (une zone cassée n'est plus tracée, comme sur la capture du carnet) :
   //    rectangle plein de l'origine vers la droite, ligne médiane en tirets avec le nom, plus haut et
   //    plus bas au bord droit (ocre pour un order block, bleu achat, rouge vente).
   for(int i = 0; i < ArraySize(g_zones); i++)
     {
      CxZone z = g_zones[i];
      if(z.state == "cassee")
         continue;
      string up = z.type + (z.tf == "US" || z.tf == "" ? "" : " " + z.tf);
      StringToUpper(up);
      bool   ob   = (StringFind(up, "OB") >= 0 || StringFind(up, "ORDER") >= 0);
      color  fill = ob ? Blend(ChartBg(), C'201,160,90', 0.26) : Blend(ChartBg(), z.buy ? C'41,98,255' : C'242,54,69', 0.13);
      color  ink  = ob ? C'19,23,34' : (z.buy ? C'41,98,255' : C'242,54,69');
      color  axis = ob ? C'245,159,0' : (z.buy ? C'41,98,255' : C'242,54,69');
      string id   = PFX_PLAN + "Z" + IntegerToString(i);
      datetime t1 = OriginTime(z.lo, z.hi);
      Rect(id, t1, z.lo, tFar, z.hi, fill, up + " " + Fr(z.lo, 2) + "–" + Fr(z.hi, 2));
      NamedLine(id + "m", t1, (z.lo + z.hi) / 2, up, ink, STYLE_DASH);
      PriceTag(id + "h", z.hi, axis);
      PriceTag(id + "l", z.lo, axis);
     }
   ChartRedraw();
  }

//--- Bandeau d'état en haut à gauche ; y en pixels à 100 %, mis à l'échelle de l'écran.
int g_uiW = 0;                     // texte le plus large du bandeau (pixels)

void Label(string name, int y, string txt, color clr)
  {
   if(ObjectFind(0, name) < 0)
     {
      ObjectCreate(0, name, OBJ_LABEL, 0, 0, 0);
      ObjectSetInteger(0, name, OBJPROP_CORNER, CORNER_LEFT_UPPER);
      ObjectSetInteger(0, name, OBJPROP_XDISTANCE, Dp(10));
      ObjectSetString(0, name, OBJPROP_FONT, FONT_TV);
      ObjectSetInteger(0, name, OBJPROP_FONTSIZE, 9);
      ObjBase(name);
     }
   ObjectSetInteger(0, name, OBJPROP_YDISTANCE, Dp(y));
   ObjectSetInteger(0, name, OBJPROP_COLOR, clr);
   ObjectSetString(0, name, OBJPROP_TEXT, txt == "" ? " " : txt);
   g_uiW = (int)MathMax(g_uiW, TextPx(txt, 9, false));
  }

void DrawPanel()
  {
   string bg = PFX_UI + "BG";
   if(ObjectFind(0, bg) < 0)
     {
      ObjectCreate(0, bg, OBJ_RECTANGLE_LABEL, 0, 0, 0);
      ObjectSetInteger(0, bg, OBJPROP_CORNER, CORNER_LEFT_UPPER);
      ObjectSetInteger(0, bg, OBJPROP_XDISTANCE, Dp(4));
      ObjectSetInteger(0, bg, OBJPROP_YDISTANCE, Dp(16));
      ObjectSetInteger(0, bg, OBJPROP_XSIZE, Dp(460));
      ObjectSetInteger(0, bg, OBJPROP_YSIZE, Dp(120));
      ObjectSetInteger(0, bg, OBJPROP_BGCOLOR, (color)ChartGetInteger(0, CHART_COLOR_BACKGROUND));
      ObjectSetInteger(0, bg, OBJPROP_BORDER_TYPE, BORDER_FLAT);
      ObjectSetInteger(0, bg, OBJPROP_COLOR, C'224,227,235');
      ObjectSetInteger(0, bg, OBJPROP_BACK, false);
      ObjBase(bg);
     }
   g_uiW = 0;
   string bias = "neutre";
   if(g_bull > g_bear)
      bias = "haussier";
   else if(g_bear > g_bull)
      bias = "baissier";
   Label(PFX_UI + "1", 20, "Carnet XAU · plan de Claude" + (g_week == "" ? "" : " · semaine du " + g_week), C'19,23,34');
   Label(PFX_UI + "2", 36, g_planOk ? "Biais " + bias + " · haussier " + IntegerToString(g_bull) + " % · baissier " + IntegerToString(g_bear)
         + " % · range " + IntegerToString(g_range) + " %" : "Aucun plan publié pour la semaine", C'19,23,34');
   Label(PFX_UI + "3", 52, g_inval > 0 ? "Invalidation " + Fr(g_inval, 2) + (g_invalidated ? " : TOUCHÉE, signaux bloqués dans ce sens" : " : intacte") : "Pas d'invalidation",
         g_invalidated ? C'242,54,69' : C'120,123,134');
   Label(PFX_UI + "4", 68, NearestZones(), C'120,123,134');
   string calc = "";
   if(CalcAlive())
     {
      int block = CalcBlock();
      calc = block > 0 ? " · Calculateur à l'arrêt : " + CalcBlockText(block)
                       : " · Calculateur relié, risque " + Fr(GlobalVariableGet(Bridge("calc_risk")), 2) + " $";
     }
   Label(PFX_UI + "5", 84, "Signaux : " + (InpSignals ? IntegerToString(g_signalCount) + " aujourd'hui · aucun ordre passé" : "désactivés") + calc,
         CalcBlock() > 0 ? C'242,54,69' : C'120,123,134');
   // État : coupé en deux lignes si besoin.
   string st = g_status, st2 = "";
   if(StringLen(st) > 62)
     {
      int cut = 62;
      while(cut > 30 && StringGetCharacter(st, cut) != ' ')
         cut--;
      st2 = StringSubstr(st, cut + 1, 62);
      st  = StringSubstr(st, 0, cut);
     }
   Label(PFX_UI + "6", 100, st, C'120,123,134');
   Label(PFX_UI + "7", 116, st2, C'120,123,134');
   int bw = (int)MathMax(Dp(460), g_uiW + Dp(16));   // le cadre s'élargit plutôt que de laisser déborder
   if(ObjectGetInteger(0, bg, OBJPROP_XSIZE) != bw)
      ObjectSetInteger(0, bg, OBJPROP_XSIZE, bw);
   ChartRedraw();
  }

//--- Zones de Claude les plus proches du prix, au-dessus et en dessous (utile quand elles sont hors de l'écran).
string NearestZones()
  {
   if(!g_planOk)
      return "";
   double px = SymbolInfoDouble(_Symbol, SYMBOL_BID);
   int up = -1, dn = -1;
   for(int i = 0; i < ArraySize(g_zones); i++)
     {
      if(g_zones[i].state == "cassee")
         continue;
      if(g_zones[i].lo > px && (up < 0 || g_zones[i].lo < g_zones[up].lo))
         up = i;
      if(g_zones[i].hi < px && (dn < 0 || g_zones[i].hi > g_zones[dn].hi))
         dn = i;
      if(g_zones[i].lo <= px && g_zones[i].hi >= px)
         return "Prix DANS la zone " + (g_zones[i].buy ? "d'achat " : "de vente ") + Fr(g_zones[i].lo, 1) + "-" + Fr(g_zones[i].hi, 1);
     }
   string s = "Zones :";
   if(up >= 0)
      s += " haut " + (g_zones[up].buy ? "achat " : "vente ") + Fr(g_zones[up].lo, 1) + " (+" + Fr(g_zones[up].lo - px, 1) + ")";
   if(dn >= 0)
      s += " · bas " + (g_zones[dn].buy ? "achat " : "vente ") + Fr(g_zones[dn].hi, 1) + " (-" + Fr(px - g_zones[dn].hi, 1) + ")";
   return s;
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

   ObjectCreate(0, id + "_A", buy ? OBJ_ARROW_BUY : OBJ_ARROW_SELL, 0, t, entry);
   ObjectSetInteger(0, id + "_A", OBJPROP_WIDTH, 3);
   ObjBase(id + "_A");
   // Position dessinée : objectif en vert, stop en rouge (comme l'outil position longue / courte).
   Rect(id + "_TP", t, entry, t2, tp, Blend(ChartBg(), C'8,153,129', 0.20), "Objectif " + Fr(tp, 2));
   Rect(id + "_SL", t, entry, t2, sl, Blend(ChartBg(), C'242,54,69', 0.20), "Stop " + Fr(sl, 2));
   double p2r = buy ? entry + 2 * stop : entry - 2 * stop;
   ObjectCreate(0, id + "_2R", OBJ_TREND, 0, t, p2r, t2, p2r);
   ObjectSetInteger(0, id + "_2R", OBJPROP_STYLE, STYLE_DOT);
   ObjectSetInteger(0, id + "_2R", OBJPROP_COLOR, clrSeaGreen);
   ObjectSetInteger(0, id + "_2R", OBJPROP_RAY_RIGHT, false);
   ObjBase(id + "_2R");
   Text(id + "_T", t, buy ? tp : sl, label + " " + Fr(lot, 2) + " lot · SL " + Fr(sl, 2) + " · TP " + Fr(tp, 2) + " (" + Fr(rr, 1) + "R) S" + setup,
        buy ? C'31,122,90' : C'178,58,46', 9);
   ChartRedraw();

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
   ObjectCreate(0, id + "_E", OBJ_TREND, 0, t, entry, t + 30 * 60, entry);
   ObjectSetInteger(0, id + "_E", OBJPROP_STYLE, STYLE_DOT);
   ObjectSetInteger(0, id + "_E", OBJPROP_COLOR, buy ? clrSeaGreen : clrIndianRed);
   ObjectSetInteger(0, id + "_E", OBJPROP_RAY_RIGHT, false);
   ObjectSetString(0, id + "_E", OBJPROP_TOOLTIP, "Retour FVG " + Fr(entry, 2));
   ObjBase(id + "_E");
   SendEvent("{\"type\":\"signal\",\"variant\":true,\"ref\":" + JsonText(ref) + ",\"kind\":\"retest\",\"side\":\"" + (buy ? "buy" : "sell") + "\",\"setup\":\"" + setup + "\",\"zone\":"
             + JsonText(z.type + " " + z.tf + " " + Num(z.lo, 2) + "-" + Num(z.hi, 2)) + ",\"entry\":" + Num(entry, 2) + ",\"sl\":" + Num(sl, 2)
             + ",\"tp\":" + Num(tp, 2) + ",\"lot\":" + Num(lot, 2) + ",\"rr\":" + Num(rr, 2) + ",\"stop\":" + Num(stop, 2)
             + ",\"time\":" + IntegerToString((long)TimeGMT()) + "}");
  }
//+------------------------------------------------------------------+
