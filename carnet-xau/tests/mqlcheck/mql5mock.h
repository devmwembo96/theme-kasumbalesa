// Simulation minimale de l'API MQL5 pour vérifier la syntaxe des EA avec g++ (-fsyntax-only).
// Elle ne reproduit aucun comportement : seulement des types et des signatures plausibles.
#pragma once
#include <string>
#include <deque>
#include <cstddef>
#include <cstdint>
#include <cfloat>
#include <climits>
#include <initializer_list>

typedef std::string string;
typedef long long datetime;
typedef unsigned int color;
typedef unsigned int uint;
typedef unsigned short ushort;
typedef unsigned char uchar;
typedef int ENUM_TIMEFRAMES;
typedef int ENUM_ORDER_TYPE;
typedef int ENUM_ORDER_TYPE_FILLING;
typedef int ENUM_ORDER_TYPE_TIME;
typedef int ENUM_OBJECT;
typedef int ENUM_ANCHOR_POINT;
typedef int ENUM_BASE_CORNER;
typedef int ENUM_LINE_STYLE;
typedef int ENUM_POSITION_TYPE;
typedef int ENUM_DEAL_TYPE;
typedef int ENUM_DEAL_ENTRY;
typedef int ENUM_TRADE_TRANSACTION_TYPE;
typedef int ENUM_ALIGN_MODE;

#define MQLRGB(r, g, b) ((color)((r) | ((g) << 8) | ((b) << 16)))

template<typename T> struct MArr
  {
   std::deque<T> v;
   MArr() {}
   MArr(std::initializer_list<T> l) : v(l) {}
   T &operator[](long i) { return v[(size_t)i]; }
   const T &operator[](long i) const { return v[(size_t)i]; }
  };
template<typename T> int ArraySize(const MArr<T> &a) { return (int)a.v.size(); }
template<typename T> int ArrayResize(MArr<T> &a, int n, int r = 0) { a.v.resize((size_t)n); return n; }
template<typename T> int ArraySize(const T &a) { return 0; }

struct MqlTick { datetime time; double bid; double ask; double last; ulong volume; long time_msc; uint flags; double volume_real; };
struct MqlDateTime { int year; int mon; int day; int hour; int min; int sec; int day_of_week; int day_of_year; };
struct MqlCalendarValue { ulong id; ulong event_id; datetime time; datetime period; int revision; long actual_value; long prev_value; long revised_prev_value; long forecast_value; int impact_type; };
struct MqlCalendarEvent { ulong id; int type; int sector; int frequency; int time_mode; ulong country_id; int unit; int importance; int multiplier; uint digits; string source_url; string event_code; string name; };
struct MqlTradeTransaction { ulong deal; ulong order; string symbol; int type; int order_type; int order_state; int deal_type; int time_type; datetime time_expiration; double price; double price_trigger; double price_sl; double price_tp; double volume; ulong position; ulong position_by; };
struct MqlTradeRequest { int action; ulong magic; ulong order; string symbol; double volume; double price; double stoplimit; double sl; double tp; ulong deviation; int type; int type_filling; int type_time; datetime expiration; string comment; ulong position; ulong position_by; };
struct MqlTradeResult { uint retcode; ulong deal; ulong order; double volume; double price; double bid; double ask; string comment; uint request_id; int retcode_external; };
struct MqlRates { datetime time; double open; double high; double low; double close; long tick_volume; int spread; long real_volume; };

class CTrade
  {
public:
   template<typename... A> void SetExpertMagicNumber(A...) {}
   template<typename... A> void SetDeviationInPoints(A...) {}
   template<typename... A> void SetTypeFilling(A...) {}
   template<typename... A> void LogLevel(A...) {}
   template<typename... A> bool Buy(A...) { return true; }
   template<typename... A> bool Sell(A...) { return true; }
   template<typename... A> bool BuyLimit(A...) { return true; }
   template<typename... A> bool SellLimit(A...) { return true; }
   template<typename... A> bool BuyStop(A...) { return true; }
   template<typename... A> bool SellStop(A...) { return true; }
   template<typename... A> bool PositionModify(A...) { return true; }
   template<typename... A> bool PositionClose(A...) { return true; }
   template<typename... A> bool OrderDelete(A...) { return true; }
   uint   ResultRetcode() { return 0; }
   string ResultRetcodeDescription() { return ""; }
   ulong  ResultOrder() { return 0; }
   ulong  ResultDeal() { return 0; }
  };

// Canvas\Canvas.mqh : méthodes utilisées par les EA (dessin en ARGB)
class CCanvas
  {
public:
   template<typename... A> bool CreateBitmapLabel(A...) { return true; }
   template<typename... A> bool CreateBitmap(A...) { return true; }
   template<typename... A> void Destroy(A...) {}
   template<typename... A> bool Resize(A...) { return true; }
   template<typename... A> void Erase(A...) {}
   template<typename... A> void Update(A...) {}
   template<typename... A> uint PixelGet(A...) { return 0; }
   template<typename... A> void PixelSet(A...) {}
   template<typename... A> bool FontSet(A...) { return true; }
   template<typename... A> void TextOut(A...) {}
   template<typename... A> void TextSize(A...) {}
   template<typename... A> int  TextWidth(A...) { return 0; }
   template<typename... A> int  TextHeight(A...) { return 0; }
   template<typename... A> void FillRectangle(A...) {}
   template<typename... A> void Rectangle(A...) {}
   template<typename... A> void LineHorizontal(A...) {}
   template<typename... A> void LineVertical(A...) {}
   template<typename... A> void Line(A...) {}
   template<typename... A> void LineAA(A...) {}
   template<typename... A> void FillCircle(A...) {}
   template<typename... A> void FillTriangle(A...) {}
   int Width() { return 0; }
   int Height() { return 0; }
   string ChartObjectName() { return ""; }
  };

#define V template<typename... A>
V uint ColorToARGB(A...);
// string
V string DoubleToString(A...);
V string IntegerToString(A...);
V string StringSubstr(A...);
V string TimeToString(A...);
V string EnumToString(A...);
V string StringFormat(A...);
V string AccountInfoString(A...);
V string ObjectGetString(A...);
V string GlobalVariableName(A...);
V string HistoryDealGetString(A...);
V string PositionGetString(A...);
V string OrderGetString(A...);
V string HistoryOrderGetString(A...);
V string ObjectName(A...);
V string CharArrayToString(A...);
V string ShortToString(A...);
V string SymbolInfoString(A...);
V string TerminalInfoString(A...);
V string MQLInfoString(A...);
// bool
V bool SymbolInfoTick(A...);
V bool OrderCalcProfit(A...);
V bool OrderCalcMargin(A...);
V bool ObjectCreate(A...);
V bool ObjectMove(A...);
V bool ObjectDelete(A...);
V bool ObjectSetInteger(A...);
V bool ObjectSetString(A...);
V bool ObjectSetDouble(A...);
V bool GlobalVariableCheck(A...);
V bool GlobalVariableDel(A...);
V bool HistorySelect(A...);
V bool HistorySelectByPosition(A...);
V bool HistoryDealSelect(A...);
V bool HistoryOrderSelect(A...);
V bool OrderSelect(A...);
V bool PositionSelectByTicket(A...);
V bool PositionSelect(A...);
V bool ChartSetInteger(A...);
V bool ChartSetString(A...);
V bool ChartSetDouble(A...);
V bool ChartTimePriceToXY(A...);
V bool ChartXYToTimePrice(A...);
V bool TextSetFont(A...);
V bool TextGetSize(A...);
V bool CalendarValueHistory(A...);
V bool CalendarEventById(A...);
V bool TimeToStruct(A...);
V bool IndicatorRelease(A...);
V bool SendNotification(A...);
V bool EventChartCustom(A...);
V bool EventSetTimer(A...);
V bool EventSetMillisecondTimer(A...);
V bool FileIsExist(A...);
V bool FileDelete(A...);
V bool FileIsEnding(A...);
V bool SymbolSelect(A...);
// int
V int  ObjectFind(A...);
V int  StringFind(A...);
V int  StringLen(A...);
V int  StringSplit(A...);
V int  ObjectsDeleteAll(A...);
V int  ObjectsTotal(A...);
V int  GlobalVariablesTotal(A...);
V int  GlobalVariablesDeleteAll(A...);
V int  HistoryDealsTotal(A...);
V int  HistoryOrdersTotal(A...);
V int  PositionsTotal(A...);
V int  OrdersTotal(A...);
V int  iBarShift(A...);
V int  iBars(A...);
V int  Bars(A...);
V int  CopyBuffer(A...);
V int  CopyRates(A...);
V int  PeriodSeconds(A...);
V int  UninitializeReason(A...);
V int  GetLastError(A...);
V int  StringReplace(A...);
V int  iATR(A...);
V int  TerminalInfoInteger(A...);
V int  MQLInfoInteger(A...);
V int  WebRequest(A...);
V int  StringToCharArray(A...);
V int  FileOpen(A...);
V int  FileWrite(A...);
V int  StringGetCharacter(A...);
V int  ArrayCopy(A...);
V int  ArrayInitialize(A...);
V int  ArraySetAsSeries(A...);
V int  StringTrimLeft(A...);
V int  StringTrimRight(A...);
V int  StringToUpper(A...);
V int  StringToLower(A...);
V int  MathRand(A...);
// double
V double GlobalVariableGet(A...);
V double AccountInfoDouble(A...);
V double HistoryDealGetDouble(A...);
V double HistoryOrderGetDouble(A...);
V double PositionGetDouble(A...);
V double OrderGetDouble(A...);
V double SymbolInfoDouble(A...);
V double ObjectGetDouble(A...);
V double ChartGetDouble(A...);
V double iHigh(A...);
V double iLow(A...);
V double iClose(A...);
V double iOpen(A...);
V double StringToDouble(A...);
V double NormalizeDouble(A...);
V double MathAbs(A...);
V double MathRound(A...);
V double MathFloor(A...);
V double MathCeil(A...);
V double MathLog10(A...);
V double MathLog(A...);
V double MathPow(A...);
V double MathSqrt(A...);
V double FileReadNumber(A...);
// long
V long ObjectGetInteger(A...);
V long ChartGetInteger(A...);
V long AccountInfoInteger(A...);
V long HistoryDealGetInteger(A...);
V long HistoryOrderGetInteger(A...);
V long PositionGetInteger(A...);
V long OrderGetInteger(A...);
V long SymbolInfoInteger(A...);
V long StringToInteger(A...);
V long ChartFirst(A...);
V long ChartNext(A...);
V long ChartID(A...);
V long iVolume(A...);
V long FileSize(A...);
V long FileSeek(A...);
V long FileTell(A...);
// datetime
V datetime TimeCurrent(A...);
V datetime TimeLocal(A...);
V datetime TimeTradeServer(A...);
V datetime TimeGMT(A...);
V datetime iTime(A...);
V datetime StructToTime(A...);
V datetime GlobalVariableTime(A...);
V datetime GlobalVariableSet(A...);
V datetime StringToTime(A...);
// ulong
V ulong PositionGetTicket(A...);
V ulong HistoryDealGetTicket(A...);
V ulong HistoryOrderGetTicket(A...);
V ulong OrderGetTicket(A...);
// uint
V uint GetTickCount(A...);
// void
V void Print(A...);
V void PrintFormat(A...);
V void Alert(A...);
V void Comment(A...);
V void ChartRedraw(A...);
V void EventKillTimer(A...);
V void ResetLastError(A...);
V void Sleep(A...);
V void FileClose(A...);
V void FileFlush(A...);
V string FileReadString(A...);
#undef V

template<typename X, typename Y> auto MathMax(X a, Y b) -> decltype(a + b) { return a > b ? a : b; }
template<typename X, typename Y> auto MathMin(X a, Y b) -> decltype(a + b) { return a < b ? a : b; }

extern string _Symbol;
extern int    _Digits;
extern double _Point;
extern int    _Period;
