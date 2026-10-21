/* Carnet XAU : graphique d'une analyse de Claude, au style TradingView clair, pour la capture d'écran
   envoyée sur Telegram. Bougies du courtier, zones et niveaux de l'analyse, outil « position longue /
   courte » de chaque scénario d'exécution et trajet simulé du prix. Données : window.CX_DATA. */
(function () {
  "use strict";
  const D = window.CX_DATA || {}, LW = window.LightweightCharts;
  const OFF = 7200; // heure de Lubumbashi sur l'axe du temps (UTC+2)
  const C = {
    bg: "#ffffff", grid: "#f0f3fa", text: "#131722", muted: "#787b86",
    up: "#089981", down: "#f23645", blue: "#2962ff",
    demand: "rgba(41,98,255,0.13)", supply: "rgba(242,54,69,0.13)",
    tp: "rgba(8,153,129,0.20)", sl: "rgba(242,54,69,0.20)",
    font: '-apple-system, BlinkMacSystemFont, "Trebuchet MS", Roboto, Ubuntu, sans-serif'
  };
  const fr = (v, d) => Number(v).toLocaleString("fr-FR", { minimumFractionDigits: d, maximumFractionDigits: d });
  const done = msg => { window.CX_READY = true; if (msg) document.getElementById("msg").textContent = msg; };
  const bars = (D.bars || []).map(b => ({ time: b[0] + OFF, open: b[1], high: b[2], low: b[3], close: b[4] }));
  if (!LW || bars.length < 10) { done("Pas assez de bougies du courtier pour dessiner le graphique."); return; }

  // En-tête comme la légende de TradingView : symbole, unité, OHLC de la dernière bougie.
  const last = bars[bars.length - 1];
  document.getElementById("sym").textContent = "Or / Dollar Américain · " + D.tfLabel + " · " + (D.broker || "Courtier");
  const ohlc = document.getElementById("ohlc"), chg = last.close - bars[bars.length - 2].close;
  ohlc.innerHTML = ["O", "H", "B", "C"].map((k, i) => k + ' <b style="color:' + (last.close >= last.open ? C.up : C.down) + '">' + fr([last.open, last.high, last.low, last.close][i], 2) + "</b>").join(" ")
    + ' <b style="color:' + (chg >= 0 ? C.up : C.down) + '">' + (chg >= 0 ? "+" : "−") + fr(Math.abs(chg), 2) + "</b>";
  document.getElementById("title").textContent = D.title || "";
  document.getElementById("sub").textContent = D.sub || "";

  const box = document.getElementById("chart");
  const chart = LW.createChart(box, {
    width: box.clientWidth, height: box.clientHeight,
    layout: { background: { type: "solid", color: C.bg }, textColor: C.text, fontFamily: C.font, fontSize: 11 },
    grid: { vertLines: { color: C.grid }, horzLines: { color: C.grid } },
    rightPriceScale: { borderColor: "#e0e3eb", scaleMargins: { top: 0.06, bottom: 0.06 } },
    timeScale: { borderColor: "#e0e3eb", timeVisible: D.tf !== "D1", secondsVisible: false, rightOffset: D.rightOffset || 40, barSpacing: D.barSpacing || 7 },
    crosshair: { mode: LW.CrosshairMode.Hidden, vertLine: { visible: false }, horzLine: { visible: false } },
    localization: { locale: "fr-FR", priceFormatter: v => fr(v, 2) },
    handleScroll: false, handleScale: false
  });
  const ser = chart.addCandlestickSeries({ upColor: C.up, downColor: C.down, borderUpColor: C.up, borderDownColor: C.down, wickUpColor: C.up, wickDownColor: C.down, priceLineColor: C.muted, priceLineStyle: LW.LineStyle.Dotted });
  ser.setData(bars);

  const zones = (D.zones || []).filter(z => isFinite(z.low) && isFinite(z.high));
  const levels = (D.levels || []).filter(l => isFinite(l.price));
  const pos = (D.positions || []).filter(p => isFinite(p.entry) && isFinite(p.sl) && isFinite(p.tp));

  // Bougie d'origine : la dernière qui touche une des bornes, sinon le début du graphique.
  const origin = prices => { for (let i = bars.length - 2; i >= 0; i--) { const b = bars[i]; if (prices.some(p => p >= b.low - 0.15 && p <= b.high + 0.15)) return i; } return 0; };
  zones.forEach(z => { z.from = isFinite(z.from) ? Math.max(0, bars.findIndex(b => b.time >= z.from + OFF)) : origin([z.low, z.high]); });
  levels.forEach(l => { l.from = origin([l.price]); });

  // Échelle : bougies visibles, scénarios et zones proches.
  ser.applyOptions({ autoscaleInfoProvider: orig => { const r = orig(); if (!r || !r.priceRange) return r; let lo = r.priceRange.minValue, hi = r.priceRange.maxValue; const span = hi - lo, near = v => v > lo - span * 0.6 && v < hi + span * 0.6;
    pos.forEach(p => [p.entry, p.sl, p.tp].forEach(v => { lo = Math.min(lo, v); hi = Math.max(hi, v); }));
    zones.forEach(z => { if (near(z.low) || near(z.high)) { lo = Math.min(lo, z.low); hi = Math.max(hi, z.high); } });
    levels.forEach(l => { if (near(l.price)) { lo = Math.min(lo, l.price); hi = Math.max(hi, l.price); } });
    return { priceRange: { minValue: lo, maxValue: hi }, margins: r.margins }; } });

  // Étiquette arrondie, texte petit (comme les étiquettes des outils de dessin de TradingView).
  function tag(ctx, x, y, text, bg, fg, r, align) {
    ctx.font = "500 " + Math.round(10 * r) + "px " + C.font;
    const w = ctx.measureText(text).width + 10 * r, h = 16 * r;
    let x0 = align === "left" ? x : align === "right" ? x - w : x - w / 2;
    ctx.fillStyle = bg; ctx.beginPath(); ctx.roundRect ? ctx.roundRect(x0, y - h / 2, w, h, 3 * r) : ctx.rect(x0, y - h / 2, w, h); ctx.fill();
    ctx.fillStyle = fg; ctx.textAlign = "left"; ctx.textBaseline = "middle"; ctx.fillText(text, x0 + 5 * r, y + 0.5 * r);
    return { x0: x0, w: w, h: h };
  }

  const prim = {
    _c: null, _s: null,
    attached(p) { this._c = p.chart; this._s = p.series; },
    detached() { this._c = this._s = null; },
    updateAllViews() {},
    paneViews() { const self = this; return [{ zOrder: () => "bottom", renderer: () => ({ draw() {}, drawBackground: t => self.paint(t, "back") }) },
                                           { zOrder: () => "top", renderer: () => ({ draw: t => self.paint(t, "front") }) }]; },
    paint(target, layer) {
      target.useBitmapCoordinateSpace(({ context: ctx, bitmapSize: sz, horizontalPixelRatio: hr, verticalPixelRatio: vr }) => {
        const ts = this._c.timeScale(), s = this._s;
        const X = i => { const x = ts.logicalToCoordinate(i); return x == null ? null : x * hr; };
        const Y = p => { const y = s.priceToCoordinate(p); return y == null ? null : y * vr; };
        const n = bars.length;
        ctx.save();
        if (layer === "back") {
          // Zones : rectangle plein de l'origine au bord droit, nom en petit au centre.
          zones.forEach(z => {
            const x = Math.max(0, X(z.from) || 0), y1 = Y(z.high), y2 = Y(z.low); if (y1 == null || y2 == null) return;
            const buy = z.side !== "vente";
            ctx.fillStyle = buy ? C.demand : C.supply; ctx.fillRect(x, Math.min(y1, y2), sz.width - x, Math.max(2 * vr, Math.abs(y2 - y1)));
            if (Math.abs(y2 - y1) >= 11 * vr) {
              ctx.font = Math.round(10 * vr) + "px " + C.font; ctx.fillStyle = buy ? C.blue : C.down; ctx.textAlign = "center"; ctx.textBaseline = "middle";
              // Au milieu de la partie passée de la zone : jamais sous l'outil position.
              const xe = Math.max(x + 40 * hr, X(n - 1) || sz.width);
              ctx.fillText(z.label, (x + xe) / 2, (y1 + y2) / 2);
            }
          });
          // Niveaux de liquidité : trait pointillé fin de l'origine au bord droit.
          levels.forEach(l => {
            const x = Math.max(0, X(l.from) || 0), y = Y(l.price); if (y == null) return;
            ctx.strokeStyle = l.color; ctx.lineWidth = hr; ctx.setLineDash([2 * hr, 3 * hr]);
            ctx.beginPath(); ctx.moveTo(x, Math.round(y) + 0.5); ctx.lineTo(sz.width, Math.round(y) + 0.5); ctx.stroke();
          });
          ctx.setLineDash([]);
          // Outil position : zone objectif (vert) et zone stop (rouge), comme « position longue / courte ».
          pos.forEach(p => {
            const xa = X(n - 1 + p.at), xb = X(n - 1 + p.at + p.len); if (xa == null || xb == null) return;
            const ye = Y(p.entry), yt = Y(p.tp), ys = Y(p.sl); if (ye == null || yt == null || ys == null) return;
            ctx.fillStyle = C.tp; ctx.fillRect(xa, Math.min(ye, yt), xb - xa, Math.abs(yt - ye));
            ctx.fillStyle = C.sl; ctx.fillRect(xa, Math.min(ye, ys), xb - xa, Math.abs(ys - ye));
            ctx.strokeStyle = C.muted; ctx.lineWidth = hr; ctx.beginPath(); ctx.moveTo(xa, Math.round(ye) + 0.5); ctx.lineTo(xb, Math.round(ye) + 0.5); ctx.stroke();
          });
        } else {
          // Trajet simulé du scénario principal : du prix actuel à l'entrée, puis à l'objectif.
          pos.forEach((p, k) => {
            const x0 = X(n - 1), xa = X(n - 1 + p.at + 2), xb = X(n - 1 + p.at + p.len - 3);
            const y0 = Y(last.close), ye = Y(p.entry), yt = Y(p.tp); if ([x0, xa, xb, y0, ye, yt].some(v => v == null)) return;
            if (k === 0) {
            ctx.strokeStyle = C.blue; ctx.lineWidth = 1.2 * hr; ctx.setLineDash([5 * hr, 4 * hr]);
            ctx.beginPath(); ctx.moveTo(x0, y0); ctx.lineTo(xa, ye); ctx.lineTo(xb, yt); ctx.stroke(); ctx.setLineDash([]);
            const a = Math.atan2(yt - ye, xb - xa), L = 7 * hr; ctx.fillStyle = C.blue; ctx.beginPath();
            ctx.moveTo(xb, yt); ctx.lineTo(xb - L * Math.cos(a - 0.45), yt - L * Math.sin(a - 0.45)); ctx.lineTo(xb - L * Math.cos(a + 0.45), yt - L * Math.sin(a + 0.45)); ctx.fill();
            }
            // Étiquettes de l'outil position : objectif en haut (ou en bas), stop à l'opposé, entrée au milieu.
            const buy = p.tp > p.entry, xm = (X(n - 1 + p.at) + X(n - 1 + p.at + p.len)) / 2, gap = 11 * vr;
            tag(ctx, xm, buy ? yt - gap : yt + gap, "Objectif " + fr(p.tp, 2) + " · " + fr(p.rr, 1) + "R", C.up, "#fff", vr);
            tag(ctx, xm, buy ? Y(p.sl) + gap : Y(p.sl) - gap, "Stop " + fr(p.sl, 2) + " · " + fr(p.stop, 2) + " $", C.down, "#fff", vr);
            tag(ctx, xm, ye, (buy ? "Achat " : "Vente ") + fr(p.entry, 2) + (p.lot ? " · " + fr(p.lot, 2) + " lot" : ""), "#ffffff", C.text, vr);
          });
          // Noms des niveaux au bord droit, écartés pour ne jamais se chevaucher.
          const right = levels.map(l => ({ y: Y(l.price), t: l.label, c: l.color })).filter(r => r.y != null).sort((a, b) => a.y - b.y);
          let ly = -1e9; ctx.font = Math.round(10 * vr) + "px " + C.font; ctx.textAlign = "right"; ctx.textBaseline = "bottom";
          right.forEach(r => { const y = Math.max(r.y - 2 * vr, ly + 13 * vr); ly = y; ctx.fillStyle = r.c; ctx.fillText(r.t, sz.width - 6 * hr, y); });
        }
        ctx.restore();
      });
    }
  };
  ser.attachPrimitive(prim);

  // Prix du scénario principal sur l'échelle de droite, comme TradingView (un seul : rien ne se chevauche).
  pos.slice(0, 1).forEach(p => {
    ser.createPriceLine({ price: p.tp, color: C.up, lineVisible: false, axisLabelVisible: true, title: "" });
    ser.createPriceLine({ price: p.sl, color: C.down, lineVisible: false, axisLabelVisible: true, title: "" });
    ser.createPriceLine({ price: p.entry, color: C.muted, lineVisible: false, axisLabelVisible: true, title: "" });
  });
  chart.timeScale().setVisibleLogicalRange({ from: Math.max(0, bars.length - (D.visible || 120)), to: bars.length - 1 + (D.rightOffset || 40) });
  // Deux images pour que les dessins soient en place avant la capture.
  requestAnimationFrame(() => requestAnimationFrame(() => done("")));
})();
