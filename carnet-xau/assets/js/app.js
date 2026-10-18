/* Carnet XAU — interface reprise du prototype carnet-xau.html.
 * La couche de stockage passe par l'API REST du plugin (nonce wp_rest) ; seuls les champs
 * du ticket du jour restent dans le navigateur, comme confort de saisie. */
(function(){
"use strict";
const CFG=window.CX_CONFIG||{};
const ROOT=document.getElementById("cx-app");
if(!ROOT)return;
const $=s=>ROOT.querySelector(s);
const $$=s=>[...ROOT.querySelectorAll(s)];
const fmt=(n,d=2)=>Number.isFinite(n)?n.toLocaleString("fr-FR",{minimumFractionDigits:d,maximumFractionDigits:d}):"—";
const money=n=>Number.isFinite(n)?(n<0?"−":"")+fmt(Math.abs(n),0)+" $":"—";
const money2=n=>Number.isFinite(n)?(n<0?"−":"")+fmt(Math.abs(n),2)+" $":"—";
const num=v=>{if(v===null||v===undefined)return NaN;const x=parseFloat(String(v).replace(",","."));return Number.isFinite(x)?x:NaN};
const pad=n=>String(n).padStart(2,"0");
const dateStr=d=>d.getFullYear()+"-"+pad(d.getMonth()+1)+"-"+pad(d.getDate());
const todayStr=()=>dateStr(new Date());
const nowTime=()=>{const d=new Date();return pad(d.getHours())+":"+pad(d.getMinutes())};
function toast(t){const e=$("#toast");e.textContent=t;e.classList.add("on");clearTimeout(toast.t);toast.t=setTimeout(()=>e.classList.remove("on"),2600)}
function el(tag,attrs,...kids){const e=document.createElement(tag);for(const k in (attrs||{})){if(k==="class")e.className=attrs[k];else if(k==="text")e.textContent=attrs[k];else e.setAttribute(k,attrs[k])}kids.forEach(c=>c!=null&&e.append(c));return e}
const errMsg=e=>(e&&e.message)||"erreur";

/* ---------- semaines ---------- */
function mondayOf(d){const x=new Date(d.getFullYear(),d.getMonth(),d.getDate());const w=(x.getDay()+6)%7;x.setDate(x.getDate()-w);return x}
function weekId(mon){return dateStr(mon)}
// Semaine à préparer : la semaine en cours, ou la suivante le samedi et le dimanche.
function planWeek(){const d=new Date(),m=mondayOf(d);if(d.getDay()===6||d.getDay()===0)m.setDate(m.getDate()+7);return m}
function weekLabel(mon){const e=new Date(mon);e.setDate(e.getDate()+4);const o={day:"numeric",month:"short"};return "Semaine du "+mon.toLocaleDateString("fr-FR",o)+" au "+e.toLocaleDateString("fr-FR",o)}

/* ---------- API REST ---------- */
function setStatus(cls,txt){const st=$("#status");st.className="status "+cls;st.querySelector("span").textContent=txt}
function apiUrl(path,params){
  let u=(CFG.root||"")+path;
  const q=new URLSearchParams();for(const k in (params||{}))if(params[k]!==""&&params[k]!=null)q.set(k,params[k]);
  const qs=q.toString();if(qs)u+=(u.indexOf("?")>=0?"&":"?")+qs;
  return u;
}
async function refreshNonce(){
  try{const fd=new FormData();fd.append("action","cx_nonce");
    const r=await fetch(CFG.ajax,{method:"POST",credentials:"same-origin",body:fd});const j=await r.json();
    if(j&&j.success&&j.data&&j.data.nonce){CFG.nonce=j.data.nonce;return true}}catch(e){}
  return false;
}
async function api(path,opts={},retry=true){
  const o={method:opts.method||"GET",credentials:"same-origin",headers:{"X-WP-Nonce":CFG.nonce||""}};
  if(opts.body instanceof FormData)o.body=opts.body;
  else if(opts.body!==undefined){o.headers["Content-Type"]="application/json";o.body=JSON.stringify(opts.body)}
  let res;
  try{res=await fetch(apiUrl(path,opts.params),o)}
  catch(e){setStatus("err","Hors ligne");throw {code:"offline",message:"pas de connexion"}}
  if((res.status===403||res.status===401)&&retry){
    const j=await res.clone().json().catch(()=>({}));
    if(j.code==="rest_cookie_invalid_nonce"&&await refreshNonce())return api(path,opts,false);
  }
  if(opts.raw){if(!res.ok)throw {code:res.status,message:"erreur "+res.status};setStatus("ok","Synchronisé");return res}
  const data=await res.json().catch(()=>null);
  if(!res.ok){
    if(res.status===401||res.status===403)setStatus("err","Session expirée : reconnectez-vous");
    throw {code:(data&&data.code)||res.status,message:(data&&data.message)||("erreur "+res.status)};
  }
  setStatus("ok","Synchronisé");
  return data;
}
async function download(path,params){
  const res=await api(path,{raw:true,params});
  const blob=await res.blob();
  const cd=res.headers.get("Content-Disposition")||"";const m=cd.match(/filename="([^"]+)"/);
  const a=el("a",{href:URL.createObjectURL(blob),download:m?m[1]:"carnet-xau"});
  document.body.append(a);a.click();setTimeout(()=>{URL.revokeObjectURL(a.href);a.remove()},1000);
}

/* ---------- confort : champs du ticket du jour, dans ce navigateur ---------- */
const ls={
  get(k,def){try{const v=localStorage.getItem("carnetxau:"+k);return v?JSON.parse(v):def}catch(e){return def}},
  set(k,v){try{localStorage.setItem("carnetxau:"+k,JSON.stringify(v))}catch(e){}}
};

/* ---------- état ---------- */
const STATUS_L={phase1:"Phase 1",phase2:"Phase 2",finance:"Compte financé",echoue:"Échoué",retire:"Retiré"};
const EMO_L={calme:"Calme",presse:"Pressé",revanche:"Revanche",fatigue:"Fatigue"};
let ACCOUNTS=[], ACTIVE=null, S={}, TRADES=[], PAYOUTS=[], SNAPS={};
let PLAN_WEEK=planWeek(), CUR_PLAN=null, THIS_WEEK_PLAN=null, EDIT_ID=null, CAPTURE=null, CALC={lot:NaN,stop:NaN};
let PAY_EDIT=null, ACC_EDIT=null;
const day={balance:"",days:"",stop:"",spread:""};
const accName=id=>{const a=ACCOUNTS.find(x=>x.id===id);return a?a.name:"—"};

/* ---------- onglets ---------- */
$$(".cx-nav button").forEach(b=>b.addEventListener("click",()=>showTab(b.dataset.tab)));
function showTab(t){
  $$(".cx-nav button").forEach(b=>b.setAttribute("aria-selected",String(b.dataset.tab===t)));
  ["jour","plan","journal","stats","comptes"].forEach(x=>{$("#tab-"+x).hidden=x!==t});
  if(t==="stats"){renderStats();mcNote()}
  if(t==="comptes"){renderAccounts();loadConnections()}
  const top=ROOT.getBoundingClientRect().top+window.scrollY;
  if(window.scrollY>top)window.scrollTo(0,Math.max(0,top-100));
}

/* ---------- comptes : sélection ---------- */
function fillAccountSelects(){
  const opts=(all)=>{const o=[];if(all)o.push(el("option",{value:"",text:"Tous les comptes"}));ACCOUNTS.forEach(a=>o.push(el("option",{value:String(a.id),text:a.name+" · "+(STATUS_L[a.status]||a.status)})));return o};
  [["#acct-switch",false],["#tf-account",false],["#pf-account",false],["#jf-account",true],["#sf-account",true]].forEach(([s,all])=>{
    const e=$(s),prev=e.value;e.replaceChildren(...opts(all));
    if(prev&&ACCOUNTS.some(a=>String(a.id)===prev))e.value=prev;else if(ACTIVE)e.value=String(ACTIVE.id);
  });
  if(ACTIVE)$("#acct-switch").value=String(ACTIVE.id);
}
function setActive(){
  ACTIVE=ACCOUNTS.find(a=>a.active)||ACCOUNTS[0]||null;
  if(!ACTIVE)return;
  const p=ACTIVE.params;
  S=Object.assign({},p,{status:ACTIVE.status,size:ACTIVE.size,phase:ACTIVE.status==="phase1"?"1":ACTIVE.status==="phase2"?"2":"f"});
  fillSettings();loadDay();
}
$("#acct-switch").addEventListener("change",async e=>{
  const id=e.target.value;
  try{ACCOUNTS=await api("accounts/"+id+"/activate",{method:"POST"});setActive();fillAccountSelects();
    ["#jf-account","#sf-account","#tf-account","#pf-account"].forEach(s=>$(s).value=String(ACTIVE.id));
    renderTrades();calc();if(!$("#tab-stats").hidden)renderStats();if(!$("#tab-comptes").hidden)renderAccounts();
    toast("Compte actif : "+ACTIVE.name)}
  catch(err){toast("Changement impossible : "+errMsg(err));$("#acct-switch").value=ACTIVE?String(ACTIVE.id):""}
});

/* ---------- paramètres du compte actif ---------- */
const PARAM_FIELDS=["mindays","t1","t2","tf","daily","max","contract","risk","riskred","redat","maxtrades","maxloss","spreadmax"];
function fillSettings(){
  if(!ACTIVE)return;
  $("#s-phase").value=ACTIVE.status;$("#s-size").value=ACTIVE.size;
  PARAM_FIELDS.forEach(k=>{$("#s-"+k).value=ACTIVE.params[k]});
}
$("#btn-settings").addEventListener("click",async()=>{
  if(!ACTIVE)return;
  const params={};for(const k of PARAM_FIELDS){params[k]=num($("#s-"+k).value);if(!Number.isFinite(params[k])){toast("Valeur manquante : vérifiez chaque champ.");return}}
  const size=num($("#s-size").value);if(!Number.isFinite(size)||size<=0){toast("Taille du compte invalide.");return}
  try{const a=await api("accounts/"+ACTIVE.id,{method:"PUT",body:{status:$("#s-phase").value,size,params}});
    replaceAccount(a);setActive();fillAccountSelects();toast("Paramètres enregistrés")}
  catch(e){toast("Enregistrement impossible : "+errMsg(e))}
  calc();
});
function replaceAccount(a){const i=ACCOUNTS.findIndex(x=>x.id===a.id);if(i>=0)ACCOUNTS[i]=a;else ACCOUNTS.push(a)}

/* ---------- ticket du jour ---------- */
function accTrades(id){return TRADES.filter(t=>t.accountId===id)}
function phaseTradesBeforeToday(){
  if(!ACTIVE)return [];
  const from=ACTIVE.phaseStart||"0000-00-00", t=todayStr();
  return accTrades(ACTIVE.id).filter(x=>x.date>=from&&x.date<t);
}
function todaySnap(){const s=ACTIVE&&SNAPS[ACTIVE.id];return s&&s.at.slice(0,10)===todayStr()?s:null}
function renderMt5Line(){
  const box=$("#mt5-line"), s=ACTIVE&&SNAPS[ACTIVE.id];
  if(!s){box.hidden=true;return}
  const mins=Math.max(0,Math.round((Date.now()-new Date(s.at.replace(" ","T")).getTime())/60000));
  const ago=mins<1?"à l’instant":mins<60?"il y a "+mins+" min":mins<1440?"il y a "+Math.round(mins/60)+" h":"le "+new Date(s.at.replace(" ","T")).toLocaleDateString("fr-FR");
  box.replaceChildren(el("span",null,"MT5 "),el("span",null,"solde ",el("b",{text:money2(s.balance)})),el("span",null,"équité ",el("b",{text:money2(s.equity)})),
    el("span",null,s.dailyLoss>0?"perte du jour ":"gain du jour ",el("b",{text:money2(Math.abs(s.dailyLoss))})),el("span",{class:mins>10?"old":"",text:ago}));
  box.hidden=false;
}
function autoDays(){return new Set(phaseTradesBeforeToday().map(x=>x.date)).size}
function autoBalance(){return Math.round((ACTIVE.size+phaseTradesBeforeToday().reduce((a,x)=>a+(num(x.pnl)||0),0))*100)/100}
function loadDay(){
  const saved=ls.get("day:"+ACTIVE.id,null);
  const fresh=!(saved&&saved.date===todayStr());
  ["balance","days","stop","spread"].forEach(k=>{day[k]=fresh?"":(saved[k]||"")});
  const sn=todaySnap();
  if(!day.balance)day.balance=String(sn?Math.round((sn.equity+sn.dailyLoss)*100)/100:autoBalance());
  if(day.days==="")day.days=String(autoDays());
  ["balance","days","stop","spread"].forEach(k=>{$("#in-"+k).value=day[k]});
}
["balance","days","stop","spread"].forEach(k=>{
  const i=$("#in-"+k);i.addEventListener("input",()=>{day[k]=i.value;if(ACTIVE)ls.set("day:"+ACTIVE.id,Object.assign({date:todayStr()},day));calc()});
});
function todayTrades(){const t=todayStr();return ACTIVE?accTrades(ACTIVE.id).filter(x=>x.date===t):[]}
function calc(){
  if(!ACTIVE)return;
  const size=S.size, bal=num(day.balance), stop=num(day.stop), spread=num(day.spread);
  const phase=S.phase, pct=phase==="1"?S.t1:phase==="2"?S.t2:S.tf;
  const target=size*pct/100, floor=size*(1-S.max/100), dailyLim=size*S.daily/100;
  const tt=todayTrades(), netToday=tt.reduce((a,x)=>a+(num(x.pnl)||0),0), lossesToday=tt.filter(x=>num(x.r)<0).length;
  const daysDone=Number.isFinite(num(day.days))?num(day.days):0;
  const daysLeft=phase==="f"?1:Math.max(1,S.mindays-daysDone);
  CALC={lot:NaN,stop:NaN};
  $("#t-phase").textContent=(STATUS_L[S.status]||"Phase 1")+" · "+ACTIVE.name;
  renderMt5Line();
  $("#t-meta").textContent="Compte "+money(size)+" · objectif "+fmt(pct,1)+" % ("+money(target)+") · plancher "+money(floor);
  const alerts=[];
  if(S.status==="echoue"||S.status==="retire")alerts.push(["warn","Ce compte est « "+STATUS_L[S.status].toLowerCase()+" ». Choisissez un autre compte actif pour trader."]);
  if(!Number.isFinite(bal)){["o-lot","o-target","o-tp","o-2r","o-margin"].forEach(id=>$("#"+id).textContent="—");
    ["o-lot-s","o-target-s","o-tp-s","o-2r-s","o-margin-s"].forEach(id=>$("#"+id).textContent="");
    alerts.push(["warn","Entrez le solde du matin pour lancer le calcul."]);}
  const profit=bal-size, remaining=target-profit;
  const dayTarget=remaining>0?remaining/daysLeft:0;
  const reduced=Number.isFinite(bal)&&profit<=-S.redat;
  let risk=reduced?S.riskred:S.risk;
  const margin=bal-floor;
  if(Number.isFinite(margin)&&risk>margin-5)risk=Math.max(0,margin-5);
  $("#o-today").textContent=money(netToday);
  $("#o-today-s").textContent=tt.length+"/"+S.maxtrades+" trades · "+lossesToday+"/"+S.maxloss+" pertes";
  if(Number.isFinite(bal)){
    $("#o-target").textContent=remaining>0?money(dayTarget):"Atteint";
    $("#o-target-s").textContent=remaining>0?("Reste "+money(remaining)+" sur "+daysLeft+" jour"+(daysLeft>1?"s":"")):"Micro-trades à 0,01 lot jusqu’au jour minimum";
    $("#o-margin").textContent=money(margin);
    $("#o-margin-s").textContent="≈ "+(risk>0?Math.floor(margin/(risk+5)):0)+" pertes possibles · limite du jour "+money(dailyLim+Math.min(0,netToday));
    if(remaining<=0)alerts.push(["go","Objectif de l’étape atteint. Faites seulement des micro-trades jusqu’au nombre de jours minimum."]);
    if(reduced)alerts.push(["warn","Le compte est à "+money(profit)+" : risque réduit à "+money(S.riskred)+" par trade."]);
    if(margin<=S.risk)alerts.push(["stop","Marge presque épuisée : un seul trade de plus peut faire perdre le compte."]);
  }
  if(Number.isFinite(stop)&&stop>0&&Number.isFinite(bal)&&risk>0){
    const lot=Math.floor(risk/(stop*S.contract)*100+1e-9)/100;
    const realRisk=lot*stop*S.contract;
    CALC={lot,stop};
    $("#o-lot").textContent=lot>0?fmt(lot,2):"0,00";
    $("#o-lot-s").textContent="Risque réel "+money(realRisk)+" pour un stop de "+fmt(stop,2)+" $";
    if(lot>0&&remaining>0){const tp=dayTarget/(lot*S.contract);$("#o-tp").textContent=fmt(tp,2)+" $";$("#o-tp-s").textContent="de mouvement · soit "+fmt(tp/stop,1)+"R"}
    else{$("#o-tp").textContent="—";$("#o-tp-s").textContent=""}
    $("#o-2r").textContent=fmt(stop*2,2)+" $";$("#o-2r-s").textContent="Fermez 50 % (+"+money(realRisk)+") puis stop au prix d’entrée";
    if(lot<0.01)alerts.push(["stop","Stop trop large pour ce risque : le lot tombe sous 0,01."]);
    if(stop<1.5||stop>4)alerts.push(["warn","Stop hors de la plage 1,50 – 4,00 $ : passez votre tour ou revoyez le placement."]);
    if(Number.isFinite(spread)&&spread>0){const p=spread/stop*100;if(p>S.spreadmax)alerts.push(["warn","Le spread fait "+fmt(p,0)+" % du stop (max "+S.spreadmax+" %) : attendez un spread plus bas."])}
  }else if(Number.isFinite(bal)){$("#o-lot").textContent="—";$("#o-lot-s").textContent="Entrez la distance du stop";$("#o-tp").textContent="—";$("#o-tp-s").textContent="";$("#o-2r").textContent="—";$("#o-2r-s").textContent=""}
  if(lossesToday>=S.maxloss)alerts.push(["stop","Arrêt pour aujourd’hui : "+lossesToday+" pertes."]);
  else if(tt.length>=S.maxtrades)alerts.push(["stop","Limite de "+S.maxtrades+" trades atteinte pour aujourd’hui."]);
  if(Number.isFinite(bal)&&remaining>0&&netToday>=dayTarget&&tt.length>0)alerts.push(["go","Objectif du jour atteint : arrêtez de trader aujourd’hui."]);
  if(-netToday>=dailyLim*0.8)alerts.push(["stop","Vous approchez de la limite de perte journalière."]);
  const box=$("#alerts");box.replaceChildren(...alerts.map(([c,t])=>el("div",{class:"alert "+c,text:t})));
  box.hidden=!alerts.length;
  updateVerdict();
}

/* ---------- check-list ---------- */
const CHECKS=["Setup 1 (liquidité) ou setup 2 (continuation) clairement identifié","Niveau fixe touché (EQH/EQL, 2 Liq, PDH/PDL, session fermée) ou BoS confirmé","Gros triangle de signal dans le bon sens","Aucun filtre : pas de news, pas de signal opposé depuis 15 min, pas de range serré","Stop entre 1,50 $ et 4,00 $","Au moins 2R de place jusqu’à la prochaine liquidité"];
function buildChecklist(){
  $("#checklist").replaceChildren(...CHECKS.map((t,i)=>{const cb=el("input",{type:"checkbox",id:"cx-ck"+i});cb.addEventListener("change",updateVerdict);return el("label",{class:"check"},cb,el("span",{text:t}))}));
}
function updateVerdict(){
  const ok=CHECKS.every((_,i)=>$("#cx-ck"+i)&&$("#cx-ck"+i).checked);
  const blocked=$$("#alerts .alert.stop").length>0;
  const v=$("#verdict");
  if(ok&&!blocked){v.className="verdict yes";v.textContent="Six « oui » : vous pouvez entrer."}
  else if(ok&&blocked){v.className="verdict no";v.textContent="Check-list validée, mais une alerte rouge bloque le trade."}
  else{v.className="verdict no";v.textContent="Cochez les six points pour valider le trade."}
  $("#btn-log").disabled=!(ok&&!blocked);
}
$("#btn-log").addEventListener("click",()=>{
  resetTradeForm();
  if(Number.isFinite(CALC.stop))$("#tf-stop").value=CALC.stop;
  if(Number.isFinite(CALC.lot)){$("#tf-lot").value=CALC.lot;if(Number.isFinite(CALC.stop))$("#tf-risk").value=(CALC.lot*CALC.stop*S.contract).toFixed(2)}
  $("#tf-rules").checked=true;
  CHECKS.forEach((_,i)=>{$("#cx-ck"+i).checked=false});updateVerdict();
  showTab("journal");$("#tf-r").focus();
});

/* ---------- plan de la semaine (rédigé par Claude, en lecture seule) ---------- */
const TREND_L={h:"Haussière",b:"Baissière",r:"Range"}, BIAS_L={h:"Haussier",b:"Baissier",n:"Neutre / range"};
const EFFECT_L={up:"Soutient l’or",down:"Pèse sur l’or",neutral:"Neutre"}, PD_L={premium:"Premium",discount:"Discount",equilibre:"Équilibre"};
const FUND_L={dxy:"Dollar (DXY)",rates:"Taux réels US",fed:"Attentes Fed",geo:"Géopolitique"};
const px=v=>v==null||v===""?"—":fmt(num(v),2);
const todayFr=()=>new Date().toLocaleDateString("fr-FR",{weekday:"long"}).toLowerCase();
function probBar(p){
  const bar=el("div",{class:"probbar",role:"img","aria-label":"Haussier "+p.bull+" %, baissier "+p.bear+" %, range "+p.range+" %"});
  [["bull","h","Haussier"],["range","n","Range"],["bear","b","Baissier"]].forEach(([k,c,l])=>{if(p[k]>0)bar.append(el("div",{class:"seg "+c,style:"flex:"+p[k],text:l+" "+p[k]+" %"}))});
  return bar;
}
function card(title,...kids){return el("div",{class:"panel"},el("h3",{text:title}),...kids)}
function kv(k,v,s){return el("div",{class:"kpi"},el("div",{class:"v",text:v}),el("div",{class:"k",text:k}),s?el("div",{class:"k",text:s}):null)}
function table(heads,rows){
  return el("div",{class:"scroll-x"},el("table",{class:"ptable"},el("thead",null,el("tr",null,...heads.map(h=>el("th",{text:h})))),el("tbody",null,...rows.map(r=>el("tr",null,...r.map(c=>c instanceof Node?el("td",null,c):el("td",{text:c==null?"":String(c)})))))));
}
// Carte verticale : zones, niveaux, prix, range attendu et invalidation.
function levelMap(c){
  const p=c.prediction||{}, refs=c.refs||{}, zones=c.zones||[], levels=c.levels||[];
  const vals=[...zones.flatMap(z=>[z.low,z.high]),...levels.map(l=>l.price),refs.price,p.range_high,p.range_low,p.invalidation].map(num).filter(Number.isFinite);
  if(vals.length<2)return null;
  let mn=Math.min(...vals),mx=Math.max(...vals);const pad=(mx-mn)*0.06||5;mn-=pad;mx+=pad;
  const W=640,H=Math.max(360,Math.min(640,120+28*(zones.length+levels.length))),T=10,B=10,Y=v=>T+(mx-v)*(H-T-B)/(mx-mn);
  const ns="http://www.w3.org/2000/svg",s=document.createElementNS(ns,"svg");s.setAttribute("viewBox","0 0 "+W+" "+H);s.setAttribute("class","lvmap");s.setAttribute("role","img");s.setAttribute("aria-label","Carte des zones et niveaux de la semaine");
  const add=(tag,a,txt)=>{const n=document.createElementNS(ns,tag);for(const k in a)n.setAttribute(k,a[k]);if(txt!=null)n.textContent=txt;s.append(n);return n};
  if(Number.isFinite(num(p.range_low))&&Number.isFinite(num(p.range_high)))add("rect",{x:W-34,y:Y(num(p.range_high)),width:10,height:Math.max(2,Y(num(p.range_low))-Y(num(p.range_high))),rx:3,fill:"var(--gold-soft)",stroke:"var(--gold)"});
  zones.forEach(z=>{const lo=num(z.low),hi=num(z.high);if(!Number.isFinite(lo)||!Number.isFinite(hi))return;const buy=z.side!=="vente";
    add("rect",{x:150,y:Y(hi),width:W-200,height:Math.max(3,Y(lo)-Y(hi)),rx:4,fill:buy?"var(--win-soft)":"var(--loss-soft)",stroke:buy?"var(--win)":"var(--loss)","stroke-opacity":z.state==="testee"?".4":"1"});
    add("text",{x:160,y:(Y(hi)+Y(lo))/2+4,"font-size":"12",fill:buy?"var(--win)":"var(--loss)"},(z.type||"Zone")+" "+(z.tf||"")+" "+"★".repeat(z.strength||0));
  });
  const labels=[];
  levels.forEach(l=>{const v=num(l.price);if(!Number.isFinite(v))return;add("line",{x1:110,x2:W-46,y1:Y(v),y2:Y(v),stroke:"var(--muted)","stroke-dasharray":"4 4"});labels.push({y:Y(v),t:px(v)+"  "+(l.type||""),c:"var(--ink)"})});
  if(Number.isFinite(num(p.invalidation))){const v=num(p.invalidation);add("line",{x1:110,x2:W-46,y1:Y(v),y2:Y(v),stroke:"var(--loss)","stroke-width":"1.5","stroke-dasharray":"8 4"});labels.push({y:Y(v),t:px(v)+"  invalidation",c:"var(--loss)"})}
  if(Number.isFinite(num(refs.price))){const v=num(refs.price);add("line",{x1:110,x2:W-46,y1:Y(v),y2:Y(v),stroke:"var(--gold)","stroke-width":"2.5"});labels.push({y:Y(v),t:px(v)+"  prix",c:"var(--gold-ink)",b:1})}
  labels.sort((a,b)=>a.y-b.y);let last=-99;labels.forEach(l=>{let y=Math.max(l.y,last+14);last=y;add("text",{x:4,y:y+4,"font-size":"11.5",fill:l.c,"font-weight":l.b?"700":"400"},l.t)});
  return s;
}
function renderPlanView(p){
  const box=$("#plan-view");
  if(!p){box.replaceChildren(el("div",{class:"empty",text:"Le plan de cette semaine n’a pas encore été publié par Claude."}));return}
  const c=p.content;
  if(!c){ // plan d'origine (avant le plan élargi)
    const lv=(p.levels||[]).filter(x=>x.price);
    box.replaceChildren(card("Plan (ancien format)",el("div",null,"Biais : ",el("span",{class:"bias "+(p.bias||"n"),text:BIAS_L[p.bias]||"Non défini"})),
      p.struct?el("p",{class:"pre",text:p.struct}):null,lv.length?table(["Prix","Type","Note"],lv.map(l=>[px(l.price),l.type,l.note])):null,
      (p.sc||[]).length?el("ul",null,...p.sc.map(s=>el("li",{text:s.text}))):null,p.review?el("p",{class:"pre",text:"Bilan : "+p.review}):null));return}
  const pr=c.prediction||{}, refs=c.refs||{}, out=[];
  out.push(el("p",{class:"hint byline",text:"Rédigé par Claude"+(p.publishedAt?" le "+new Date(p.publishedAt.replace(" ","T")).toLocaleDateString("fr-FR",{weekday:"long",day:"numeric",month:"long"})+" à "+p.publishedAt.slice(11,16):"")+"."}));
  // 1. Prévision
  const pv=card("Prévision de la semaine",probBar(pr));
  if(pr.summary)pv.append(el("p",{class:"lead",text:pr.summary}));
  const g=el("div",{class:"grid g2"});
  if(pr.main)g.append(el("div",{class:"scn main"},el("div",{class:"k",text:"Scénario principal"}),el("p",{text:pr.main})));
  if(pr.alt)g.append(el("div",{class:"scn"},el("div",{class:"k",text:"Scénario alternatif"}),el("p",{text:pr.alt})));
  if(g.childNodes.length)pv.append(g);
  const kp=el("div",{class:"kpis",style:"margin:12px 0 0"});
  if(pr.range_low!=null||pr.range_high!=null)kp.append(kv("Range attendu",px(pr.range_low)+" – "+px(pr.range_high)));
  if(pr.invalidation!=null)kp.append(kv("Invalidation",px(pr.invalidation)));
  if(kp.childNodes.length)pv.append(kp);
  out.push(pv);
  // 2. Repères
  const rk=[["Prix de référence",refs.price],["Ouverture hebdo",refs.week_open],["Haut semaine passée",refs.prev_week_high],["Bas semaine passée",refs.prev_week_low],["Haut mois passé",refs.prev_month_high],["Bas mois passé",refs.prev_month_low],["ATR journalier",refs.atr_d],["ATR hebdo",refs.atr_w]].filter(x=>x[1]!=null);
  if(rk.length)out.push(card("Repères de prix",el("div",{class:"kpis"},...rk.map(([k,v])=>kv(k,(k.startsWith("ATR")?"":"")+px(v)+(k.startsWith("ATR")?" $":""))))));
  // 6. Carte
  const map=levelMap(c);if(map)out.push(card("Carte des niveaux",map,el("p",{class:"hint",style:"margin-top:8px",text:"Vert : zone d’achat · rouge : zone de vente · trait doré : prix · tirets rouges : invalidation · barre dorée à droite : range attendu."})));
  // 3. Structure
  if((c.structure||[]).length)out.push(card("Structure par unité de temps",table(["UT","Tendance","Dernier BoS / ChoCh","Zone","Note"],c.structure.map(s=>[s.tf,el("span",{class:"bias "+({h:"h",b:"b",r:"n"}[s.trend]||"n"),text:TREND_L[s.trend]||"—"}),s.last,PD_L[s.pd]||"",s.note]))));
  // 4. Zones
  if((c.zones||[]).length)out.push(card("Zones",table(["Zone","Type","UT","Réaction","Force","État","Note"],c.zones.slice().sort((a,b)=>num(b.high)-num(a.high)).map(z=>[px(z.low)+" – "+px(z.high),z.type,z.tf,el("span",{class:"bias "+(z.side==="vente"?"b":"h"),text:z.side==="vente"?"Vente":"Achat"}),"★".repeat(z.strength||0),z.state==="testee"?"Testée":"Vierge",z.note]))));
  // 5. Niveaux de liquidité
  if((c.levels||[]).length)out.push(card("Niveaux de liquidité",table(["Prix","Type","Note"],c.levels.slice().sort((a,b)=>num(b.price)-num(a.price)).map(l=>[px(l.price),l.type,l.note]))));
  // 7. Fondamentaux
  if((c.fundamentals||[]).length||c.fund_notes){const f=card("Fondamentaux");
    if((c.fundamentals||[]).length)f.append(el("div",{class:"kpis"},...c.fundamentals.map(x=>el("div",{class:"kpi"},el("div",{class:"k",text:x.label||FUND_L[x.key]||x.key}),el("div",{class:"eff "+(x.effect||""),text:EFFECT_L[x.effect]||"—"}),x.value?el("div",{class:"k",text:x.value}):null,x.note?el("div",{class:"k",text:x.note}):null))));
    if(c.fund_notes)f.append(el("p",{class:"pre",style:"margin-top:10px",text:c.fund_notes}));out.push(f)}
  // 8. Annonces
  if((c.calendar||[]).length)out.push(card("Annonces (zones de non-trade : 15 min avant, 30 min après)",table(["Jour","Heure","Annonce","Impact","Prévision","Précédent"],c.calendar.map(e=>[e.day,e.time,e.event,el("span",{class:"imp "+(e.impact||""),text:{high:"Fort",medium:"Moyen",low:"Faible"}[e.impact]||"—"}),e.forecast,e.previous]))));
  // 9. Jour par jour
  if((c.days||[]).length){const t=todayFr();out.push(card("Plan jour par jour",el("div",{class:"days"},...c.days.map(d=>el("div",{class:"day"+(d.day===t?" today":"")},
    el("div",{class:"row"},el("b",{text:d.day.charAt(0).toUpperCase()+d.day.slice(1)}),el("span",{class:"bias "+(d.bias||"n"),text:BIAS_L[d.bias]||""}),d.day===t?el("span",{class:"tag",text:"aujourd’hui"}):null),
    d.session?el("div",{class:"k",text:"Session : "+d.session}):null,d.focus?el("p",{text:d.focus}):null)))))}
  // 10. Scénarios
  if((c.scenarios||[]).length)out.push(card("Scénarios",el("div",{class:"trades"},...c.scenarios.map(s=>el("div",{class:"scn"},
    el("div",{class:"row"},el("b",{text:"Si "}),el("span",{text:s.if}),el("span",{class:"spacer"}),s.prob!=null?el("span",{class:"tag",text:s.prob+" %"}):null),
    el("div",null,el("b",{text:"Alors "}),el("span",{text:s.then})),
    el("div",{class:"k",text:[s.setup?"Setup "+s.setup:"",s.side?(s.side==="vente"?"Vente":"Achat"):"",s.entry?"entrée "+s.entry:"",s.sl!=null?"stop "+px(s.sl):"",s.tp!=null?"objectif "+px(s.tp):""].filter(Boolean).join(" · ")}),
    s.invalidation?el("div",{class:"k",text:"Invalidation : "+s.invalidation}):null)))));
  // 11. Règles
  if((c.rules||[]).length)out.push(card("Règles de la semaine",el("ul",{class:"rules"},...c.rules.map(r=>el("li",{text:r})))));
  // 12. Bilan
  if(c.review&&(c.review.text||c.review.score!=null))out.push(card("Bilan de fin de semaine",c.review.score!=null?el("div",{class:"kpis"},kv("Note du plan",fmt(c.review.score,1).replace(",0","")+" / 10")):null,c.review.text?el("p",{class:"pre",text:c.review.text}):null));
  if(c.sources)out.push(el("p",{class:"hint",text:"Sources : "+c.sources}));
  box.replaceChildren(...out);
}
/* ---------- plan miniature (onglet Jour) ---------- */
function renderPlanMini(){
  const p=THIS_WEEK_PLAN, box=$("#planmini");box.replaceChildren();
  box.append(el("h3",{text:"Plan de la semaine"}));
  if(!p){box.append(el("p",{class:"hint",text:"Le plan de cette semaine n’a pas encore été publié par Claude."}));return}
  const c=p.content, t=todayFr();
  if(c&&c.prediction){box.append(probBar(c.prediction));if(c.prediction.summary)box.append(el("p",{style:"margin:8px 0 0",text:c.prediction.summary}))}
  else box.append(el("div",null,"Biais : ",el("span",{class:"bias "+(p.bias||"n"),text:BIAS_L[p.bias]||"Non défini"})));
  const day=c&&(c.days||[]).find(d=>d.day===t);
  if(day&&day.focus)box.append(el("p",{style:"margin:8px 0 0"},el("b",{text:"Aujourd’hui : "}),document.createTextNode(day.focus)));
  const zs=c?(c.zones||[]).slice().sort((a,b)=>num(b.high)-num(a.high)):[];
  const lv=(c?c.levels:p.levels||[]).filter(x=>x.price);
  if(zs.length||lv.length){const w=el("div",{style:"margin-top:6px"});
    zs.forEach(z=>w.append(el("span",{class:"lv "+(z.side==="vente"?"sell":"buy"),text:px(z.low)+"–"+px(z.high)+" · "+(z.type||"zone")})));
    lv.slice().sort((a,b)=>num(b.price)-num(a.price)).forEach(x=>w.append(el("span",{class:"lv",text:px(x.price)+" · "+(x.type||"niveau")})));box.append(w)}
  const news=(p.cal||[]).filter(x=>(x.day||"").toLowerCase()===t);
  if(news.length)box.append(el("div",{class:"alert warn",style:"margin-top:10px",text:"Aujourd’hui : "+news.map(n=>(n.time||"")+" "+(n.event||"")).join(" · ")+". Pas de trade 15 min avant, 30 min après."}));
  const b=el("button",{type:"button",class:"btn ghost sm",style:"margin-top:10px",text:"Voir le plan complet"});b.addEventListener("click",()=>{PLAN_WEEK=planWeek();loadPlan();showTab("plan")});box.append(b);
}
$("#wk-prev").addEventListener("click",()=>{PLAN_WEEK.setDate(PLAN_WEEK.getDate()-7);loadPlan()});
$("#wk-next").addEventListener("click",()=>{PLAN_WEEK.setDate(PLAN_WEEK.getDate()+7);loadPlan()});
$("#wk-today").addEventListener("click",()=>{PLAN_WEEK=planWeek();loadPlan()});
async function loadPlan(){
  $("#wk-label").textContent=weekLabel(PLAN_WEEK);
  const wid=weekId(PLAN_WEEK);
  let p=null;try{p=await api("plans/"+wid)}catch(e){toast("Lecture du plan impossible : "+errMsg(e))}
  if(wid!==weekId(PLAN_WEEK))return; // semaine changée entre-temps
  CUR_PLAN=p;renderPlanView(p);
}

/* ---------- journal ---------- */
function setCapture(c){
  CAPTURE=c;
  $("#tf-file-name").textContent=c?(c.name||"Image jointe"):"Aucune image";
  $("#tf-file-clear").hidden=!c;
  $("#tf-file").value="";
}
$("#tf-file").addEventListener("change",async e=>{
  const f=e.target.files&&e.target.files[0];if(!f)return;
  if(f.size>8*1024*1024){toast("Image trop lourde (8 Mo maximum).");e.target.value="";return}
  const fd=new FormData();fd.append("file",f);
  $("#tf-file-name").textContent="Envoi en cours…";$("#tf-save").disabled=true;
  try{const r=await api("captures",{method:"POST",body:fd});setCapture({id:r.id,url:r.url,name:f.name});toast("Capture envoyée")}
  catch(err){setCapture(CAPTURE);toast("Envoi impossible : "+errMsg(err))}
  $("#tf-save").disabled=false;
});
$("#tf-file-clear").addEventListener("click",()=>setCapture(null));
let pnlTouched=false;
function resetTradeForm(){
  EDIT_ID=null;pnlTouched=false;$("#tf-title").textContent="Nouveau trade";$("#tf-cancel").hidden=true;$("#tf-save").textContent="Enregistrer le trade";
  if(ACTIVE)$("#tf-account").value=String(ACTIVE.id);
  $("#tf-date").value=todayStr();$("#tf-time").value=nowTime();$("#tf-dir").value="achat";$("#tf-setup").value="1";$("#tf-level").value="EQL";$("#tf-emotion").value="";
  ["stop","lot","risk","r","pnl","link","notes"].forEach(k=>$("#tf-"+k).value="");
  ["onlevel","bias","rules"].forEach(k=>$("#tf-"+k).checked=false);
  setCapture(null);
  if(ACTIVE)$("#tf-risk").value=S.risk;
}
$("#tf-pnl").addEventListener("input",()=>{pnlTouched=true});
function autoPnl(){const r=num($("#tf-r").value),k=num($("#tf-risk").value);if(!pnlTouched&&Number.isFinite(r)&&Number.isFinite(k))$("#tf-pnl").value=(r*k).toFixed(2)}
$("#tf-r").addEventListener("input",autoPnl);$("#tf-risk").addEventListener("input",autoPnl);
$("#tf-cancel").addEventListener("click",resetTradeForm);
$("#tf-save").addEventListener("click",async()=>{
  const t={accountId:parseInt($("#tf-account").value,10),date:$("#tf-date").value,time:$("#tf-time").value,dir:$("#tf-dir").value,setup:$("#tf-setup").value,level:$("#tf-level").value,
    stop:$("#tf-stop").value,lot:$("#tf-lot").value,risk:$("#tf-risk").value,r:$("#tf-r").value,pnl:$("#tf-pnl").value,
    onLevel:$("#tf-onlevel").checked,withBias:$("#tf-bias").checked,rules:$("#tf-rules").checked,emotion:$("#tf-emotion").value,
    captureId:CAPTURE?CAPTURE.id:null,link:$("#tf-link").value.trim(),notes:$("#tf-notes").value};
  if(!t.date||!Number.isFinite(num(t.r))){toast("Indiquez au moins la date et le résultat en R.");return}
  if(t.link&&!/^https?:\/\//i.test(t.link)){toast("Le lien doit commencer par https://");return}
  const btn=$("#tf-save");btn.disabled=true;
  const editing=EDIT_ID;
  try{
    const saved=await api(editing?"trades/"+editing:"trades",{method:editing?"PUT":"POST",body:t});
    const i=TRADES.findIndex(x=>x.id===saved.id);if(i>=0)TRADES[i]=saved;else TRADES.push(saved);
    toast(editing?"Trade modifié":"Trade enregistré");resetTradeForm();refreshAll();
  }catch(e){toast("Enregistrement impossible : "+errMsg(e))}
  btn.disabled=false;
});
$("#jf-setup").addEventListener("change",renderTrades);
$("#jf-account").addEventListener("change",renderTrades);
$("#btn-csv").addEventListener("click",async()=>{try{await download("export/trades.csv",{account:$("#jf-account").value})}catch(e){toast("Export impossible : "+errMsg(e))}});
const SETUP_L={"1":"Setup 1","2":"Setup 2","hors":"Hors setup"};
const tradeKey=t=>(t.date||"")+(t.time||"")+String(t.id).padStart(10,"0");
function sortedTrades(list){return list.slice().sort((a,b)=>tradeKey(b).localeCompare(tradeKey(a)))}
function renderTrades(){
  const f=$("#jf-setup").value, acc=$("#jf-account").value, box=$("#trades");
  const list=sortedTrades(TRADES).filter(t=>(!f||(f==="todo"?t.toComplete:t.setup===f))&&(!acc||String(t.accountId)===acc));
  if(!list.length){box.replaceChildren(el("div",{class:"empty",text:TRADES.length?"Aucun trade pour ce filtre.":"Aucun trade pour l’instant. Votre premier trade noté apparaîtra ici."}));return}
  box.replaceChildren(...list.map(t=>{
    const r=num(t.r), cls=r>0?"w":r<0?"l":"e";
    const d=new Date(t.date+"T00:00:00");
    const info=el("div",null,
      el("div",{class:"t1",text:(t.dir==="vente"?"Vente":"Achat")+" · "+(SETUP_L[t.setup]||"")+" · "+(t.level||"")}),
      el("div",{class:"t2",text:d.toLocaleDateString("fr-FR",{weekday:"short",day:"numeric",month:"short"})+" "+(t.time||"")+(t.stop!=null?" · stop "+fmt(t.stop,2)+" $":"")+(t.lot!=null?" · "+fmt(t.lot,2)+" lot":"")+(t.pnl!=null?" · "+money2(t.pnl):"")}));
    const tags=el("div",{style:"margin-top:3px"});
    if(!acc)tags.append(el("span",{class:"tag",text:accName(t.accountId)}));
    if(t.source==="mt5")tags.append(el("span",{class:"tag"+(t.toComplete?" bad":""),text:t.toComplete?"MT5 · à compléter":"MT5"}));
    if(t.onLevel)tags.append(el("span",{class:"tag",text:"niveau clé"}));
    if(t.withBias)tags.append(el("span",{class:"tag",text:"avec le biais"}));
    tags.append(t.rules?el("span",{class:"tag",text:"règles respectées"}):el("span",{class:"tag bad",text:"hors règles"}));
    if(t.emotion)tags.append(el("span",{class:"tag"+(t.emotion==="calme"?"":" bad"),text:EMO_L[t.emotion]||t.emotion}));
    if(t.link)tags.append(el("a",{href:t.link,target:"_blank",rel:"noopener noreferrer",class:"tag",text:"TradingView"}));
    info.append(tags);
    if(t.notes)info.append(el("div",{class:"t2",text:t.notes}));
    if(t.captureUrl){const a=el("a",{href:t.captureUrl,target:"_blank",rel:"noopener"},el("img",{src:t.captureUrl,alt:"Capture du trade",class:"thumb",loading:"lazy"}));info.append(a)}
    const acts=el("div",{class:"row"});
    const e=el("button",{type:"button",class:"btn ghost sm",text:"Modifier"});e.addEventListener("click",()=>editTrade(t));
    const x=el("button",{type:"button",class:"btn ghost sm danger",text:"Supprimer"});x.addEventListener("click",async()=>{if(!confirm("Supprimer ce trade ?"))return;
      try{await api("trades/"+t.id,{method:"DELETE"});TRADES=TRADES.filter(y=>y.id!==t.id);if(EDIT_ID===t.id)resetTradeForm();refreshAll();toast("Trade supprimé")}catch(err){toast("Suppression impossible : "+errMsg(err))}});
    acts.append(e,x);
    return el("div",{class:"trade"},el("div",{class:"r "+cls,text:(r>0?"+":r<0?"−":"")+fmt(Math.abs(r),1)+"R"}),info,acts);
  }));
}
function editTrade(t){
  EDIT_ID=t.id;$("#tf-title").textContent="Modifier le trade";$("#tf-cancel").hidden=false;$("#tf-save").textContent="Enregistrer les modifications";
  $("#tf-account").value=String(t.accountId);
  $("#tf-date").value=t.date||"";$("#tf-time").value=t.time||"";$("#tf-dir").value=t.dir||"achat";$("#tf-setup").value=t.setup||"1";$("#tf-level").value=t.level||"Autre";$("#tf-emotion").value=t.emotion||"";
  ["stop","lot","risk","r","pnl"].forEach(k=>$("#tf-"+k).value=t[k]!=null&&Number.isFinite(num(t[k]))?t[k]:"");
  $("#tf-link").value=t.link||"";$("#tf-notes").value=t.notes||"";$("#tf-onlevel").checked=!!t.onLevel;$("#tf-bias").checked=!!t.withBias;$("#tf-rules").checked=!!t.rules;
  setCapture(t.captureId?{id:t.captureId,url:t.captureUrl,name:"Image jointe"}:null);
  pnlTouched=true;$("#trade-form").scrollIntoView({behavior:"smooth",block:"start"});
}

/* ---------- statistiques ---------- */
function agg(list){
  const n=list.length, rs=list.map(t=>num(t.r)).filter(Number.isFinite);
  const wins=rs.filter(r=>r>0).length, pos=rs.filter(r=>r>0).reduce((a,b)=>a+b,0), neg=-rs.filter(r=>r<0).reduce((a,b)=>a+b,0);
  return {n,wr:n?wins/n:0,avg:n?rs.reduce((a,b)=>a+b,0)/n:0,sum:rs.reduce((a,b)=>a+b,0),pf:neg?pos/neg:(pos?Infinity:0),pnl:list.reduce((a,t)=>a+(num(t.pnl)||0),0)};
}
function bars(id,groups){
  const box=$(id);const rows=groups.filter(g=>g.list.length);
  if(!rows.length){box.replaceChildren(el("div",{class:"empty",text:"Pas encore de données."}));return}
  box.replaceChildren(...rows.map(g=>{const a=agg(g.list);
    const tr=el("div",{class:"track"},el("div",{class:"fill",style:"width:"+(a.wr*100).toFixed(1)+"%"}),el("div",{class:"mark",style:"left:33.3%"}));
    return el("div",{class:"bar-row"},el("div",{text:g.label}),tr,el("div",{class:"n",text:fmt(a.wr*100,0)+" % · "+(a.avg>=0?"+":"−")+fmt(Math.abs(a.avg),2)+"R · "+a.n}));
  }));
}
function hourBucket(t){const h=parseInt((t.time||"").slice(0,2),10);if(!Number.isFinite(h))return "?";if(h<9)return "asie";if(h<14)return "londres";if(h<18)return "ny";return "soir"}
$("#sf-account").addEventListener("change",()=>{renderStats();mcNote()});
function renderStats(){
  const acc=$("#sf-account").value, T=TRADES.filter(t=>!acc||String(t.accountId)===acc);
  const a=agg(T);
  const k=[["Trades",String(a.n)],["Réussite",a.n?fmt(a.wr*100,0)+" %":"—"],["R moyen",a.n?(a.avg>=0?"+":"−")+fmt(Math.abs(a.avg),2):"—"],["Résultat",money(a.pnl)]];
  if(a.n)k.push(["Facteur de profit",a.pf===Infinity?"∞":fmt(a.pf,2)],["Total en R",(a.sum>=0?"+":"−")+fmt(Math.abs(a.sum),1)]);
  $("#kpis").replaceChildren(...k.map(([l,v])=>el("div",{class:"kpi"},el("div",{class:"v",text:v}),el("div",{class:"k",text:l}))));
  const list=T.slice().sort((x,y)=>tradeKey(x).localeCompare(tradeKey(y)));
  const cv=$("#curve");
  if(list.length<2){cv.replaceChildren(el("div",{class:"empty",text:"La courbe apparaît à partir de deux trades."}))}
  else{let c=0;const pts=[0,...list.map(t=>c+=(num(t.r)||0))];const mn=Math.min(...pts),mx=Math.max(...pts),W=600,H=150,pd=8;
    const X=i=>pd+i*(W-2*pd)/(pts.length-1),Y=v=>H-pd-(v-mn)*(H-2*pd)/((mx-mn)||1);
    const ns="http://www.w3.org/2000/svg";const svg=document.createElementNS(ns,"svg");svg.setAttribute("viewBox","0 0 "+W+" "+H);svg.setAttribute("class","curve");svg.setAttribute("preserveAspectRatio","none");
    svg.setAttribute("role","img");svg.setAttribute("aria-label","Courbe des R cumulés : "+(c>=0?"+":"−")+fmt(Math.abs(c),1)+"R sur "+list.length+" trades");
    const z=document.createElementNS(ns,"line");z.setAttribute("x1",pd);z.setAttribute("x2",W-pd);z.setAttribute("y1",Y(0));z.setAttribute("y2",Y(0));z.setAttribute("stroke","var(--line)");z.setAttribute("stroke-dasharray","4 4");svg.append(z);
    const p=document.createElementNS(ns,"polyline");p.setAttribute("points",pts.map((v,i)=>X(i).toFixed(1)+","+Y(v).toFixed(1)).join(" "));p.setAttribute("fill","none");p.setAttribute("stroke","var(--gold)");p.setAttribute("stroke-width","2.5");p.setAttribute("vector-effect","non-scaling-stroke");svg.append(p);
    cv.replaceChildren(svg)}
  bars("#by-setup",[{label:"Setup 1",list:T.filter(t=>t.setup==="1")},{label:"Setup 2",list:T.filter(t=>t.setup==="2")},{label:"Hors setup",list:T.filter(t=>t.setup==="hors")}]);
  const lv=[...new Set(T.map(t=>t.level).filter(Boolean))];
  bars("#by-level",lv.map(l=>({label:l,list:T.filter(t=>t.level===l)})).sort((x,y)=>y.list.length-x.list.length));
  bars("#by-hour",[["asie","Asie (0 h–9 h)"],["londres","Londres (9 h–14 h)"],["ny","New York (14 h–18 h)"],["soir","Soir (18 h–24 h)"]].map(([k,l])=>({label:l,list:T.filter(t=>hourBucket(t)===k)})));
  const days=["lundi","mardi","mercredi","jeudi","vendredi","samedi","dimanche"];
  bars("#by-day",days.map((d,i)=>({label:d.charAt(0).toUpperCase()+d.slice(1),list:T.filter(t=>{if(!t.date)return false;const w=(new Date(t.date+"T00:00:00").getDay()+6)%7;return w===i})})));
  bars("#by-filter",[{label:"Sur niveau clé",list:T.filter(t=>t.onLevel)},{label:"Hors niveau",list:T.filter(t=>!t.onLevel)},{label:"Avec le biais",list:T.filter(t=>t.withBias)},{label:"Contre / sans biais",list:T.filter(t=>!t.withBias)}]);
  bars("#by-rules",[{label:"Règles respectées",list:T.filter(t=>t.rules)},{label:"Hors règles",list:T.filter(t=>!t.rules)}]);
  bars("#by-emotion",[...Object.keys(EMO_L).map(k=>({label:EMO_L[k],list:T.filter(t=>t.emotion===k)})),{label:"Non renseignée",list:T.filter(t=>!t.emotion)}]);
}

/* ---------- simulateur Monte-Carlo ---------- */
const MC_RUNS=2000, MC_HORIZON=60;
function mcSample(){
  const acc=$("#sf-account").value, rs=TRADES.filter(t=>!acc||String(t.accountId)===acc).map(t=>num(t.r)).filter(Number.isFinite);
  return rs;
}
function mcNote(){
  const rs=mcSample(), src=$("#mc-src");
  if(rs.length<10&&src.value==="trades")src.value="hyp";
  src.querySelector("option[value=trades]").textContent="Mes trades ("+rs.length+")";
  src.querySelector("option[value=trades]").disabled=rs.length<10;
  const hyp=src.value==="hyp";
  $("#mc-wr").disabled=!hyp;$("#mc-avg").disabled=!hyp;
  let n;
  if(hyp)n=rs.length<10?"Le journal compte "+rs.length+" trade"+(rs.length>1?"s":"")+" : il en faut au moins 10 pour simuler à partir de vos vrais résultats. En attendant, hypothèse : chaque trade perd 1R ou gagne le gain moyen indiqué.":"Hypothèse : chaque trade perd 1R ou gagne le gain moyen indiqué.";
  else{const g=agg(rs.map(r=>({r})));n="Tirage au hasard parmi vos "+rs.length+" trades (réussite "+fmt(g.wr*100,0)+" %, R moyen "+(g.avg>=0?"+":"−")+fmt(Math.abs(g.avg),2)+")."+(rs.length<30?" Moins de 30 trades : résultat encore peu fiable.":"")}
  $("#mc-note").textContent=n;
}
$("#mc-src").addEventListener("change",mcNote);
// Un challenge (une phase) simulé jour par jour avec les règles du compte.
function mcPhase(o,pct,risk,draw){
  const size=o.size, target=size*pct/100, floor=size*(1-o.max/100), dailyLim=size*o.daily/100;
  let bal=size, days=0;
  for(let d=1;d<=MC_HORIZON;d++){
    const remaining=target-(bal-size);
    if(remaining<=0){if(days>=o.mindays)return {ok:true,days};days++;continue} // micro-trades jusqu’au jour minimum
    const dayTarget=remaining/Math.max(1,o.mindays-days);
    let pnl=0,losses=0;
    for(let t=0;t<o.tpd;t++){
      let rk=bal-size<=-o.redat?o.riskred:risk;const margin=bal-floor;if(rk>margin-5)rk=Math.max(0,margin-5);
      if(rk<=0)return {ok:false,days};
      const r=draw(),p=r*rk;bal+=p;pnl+=p;
      if(bal<=floor||-pnl>=dailyLim)return {ok:false,days:days+1};
      if(r<0&&++losses>=o.maxloss)break;
      if(pnl>=dayTarget)break;
    }
    days++;
  }
  return {ok:null,days};
}
function median(a){if(!a.length)return NaN;const s=a.slice().sort((x,y)=>x-y);return s[Math.floor(s.length/2)]}
$("#mc-run").addEventListener("click",()=>{
  if(!ACTIVE)return;
  const hyp=$("#mc-src").value==="hyp", rs=mcSample();
  const wr=num($("#mc-wr").value)/100, avg=num($("#mc-avg").value), tpd=Math.max(1,Math.round(num($("#mc-tpd").value)||1));
  if(hyp&&!(wr>0&&wr<1&&avg>0)){toast("Indiquez une réussite entre 1 et 99 % et un gain moyen positif.");return}
  const draw=hyp?()=>Math.random()<wr?avg:-1:()=>rs[Math.floor(Math.random()*rs.length)];
  const o=Object.assign({},S,{tpd:Math.min(tpd,S.maxtrades)});
  const funded=S.phase==="f", risks=[...new Set([25,35,50,75,100,S.risk])].sort((a,b)=>a-b);
  const rows=risks.map(risk=>{
    let p1=0,both=0,lost=0;const d1=[];
    for(let i=0;i<MC_RUNS;i++){
      const a=mcPhase(o,funded?S.tf:(S.phase==="2"?S.t2:S.t1),risk,draw);
      if(a.ok){p1++;d1.push(a.days);
        if(S.phase==="1"&&mcPhase(o,S.t2,risk,draw).ok)both++;
      }else if(a.ok===false)lost++;
    }
    return {risk,p1:p1/MC_RUNS,both:both/MC_RUNS,lost:lost/MC_RUNS,days:median(d1)};
  });
  const pc=v=>fmt(v*100,0)+" %";
  const cur=rows.find(r=>r.risk===S.risk);
  const phaseL=funded?"le cycle financé":S.phase==="2"?"la phase 2":"la phase 1";
  const sum=el("div",{class:"mc-sum"});
  sum.append("Avec votre risque actuel (",el("b",{text:money(S.risk)}),") : ",el("b",{text:pc(cur.p1)})," de réussir "+phaseL+", "+pc(cur.lost)+" de perdre le compte pendant "+phaseL+(S.phase==="1"?", "+pc(cur.both)+" de réussir les deux phases":"")+"."+(Number.isFinite(cur.days)?" Réussite en "+cur.days+" jours de trading en général.":""));
  const head=el("tr",null,el("th",{text:"Risque par trade"}),el("th",{text:"Réussite "+(funded?"du cycle":S.phase==="2"?"phase 2":"phase 1")}));
  if(S.phase==="1")head.append(el("th",{text:"Deux phases"}));
  head.append(el("th",{text:funded?"Compte perdu":S.phase==="2"?"Perdu en phase 2":"Perdu en phase 1"}),el("th",{text:"Jours (médiane)"}));
  const body=rows.map(r=>{const tr=el("tr",{class:r.risk===S.risk?"cur":""},el("td",{text:money(r.risk)+(r.risk===S.risk?" (actuel)":"")}),el("td",{class:"ok",text:pc(r.p1)}));
    if(S.phase==="1")tr.append(el("td",{class:"ok",text:pc(r.both)}));
    tr.append(el("td",{class:"ko",text:pc(r.lost)}),el("td",{text:Number.isFinite(r.days)?String(r.days):"—"}));return tr});
  $("#mc-out").replaceChildren(sum,el("div",{class:"scroll-x"},el("table",{class:"mc-table"},el("thead",null,head),el("tbody",null,...body))),
    el("p",{class:"hint",style:"margin-top:10px",text:MC_RUNS+" challenges simulés par niveau de risque, sur "+MC_HORIZON+" jours de trading au plus (au-delà : non conclu). Réussite + perte + non conclus = 100 % pour l’étape en cours ; « Deux phases » enchaîne la phase 2 après une phase 1 réussie. Résultat indicatif : il suppose que vos trades futurs ressemblent à ceux utilisés."}));
});

/* ---------- comptes et retraits ---------- */
function renderAccounts(){
  const cost=ACCOUNTS.reduce((a,x)=>a+(num(x.price)||0),0);
  const paidIds=new Set(PAYOUTS.map(p=>p.accountId));
  const funded=ACCOUNTS.filter(a=>a.status==="finance"||paidIds.has(a.id)).length;
  const share=PAYOUTS.reduce((a,p)=>a+(num(p.share)||0),0), fees=PAYOUTS.reduce((a,p)=>a+(num(p.fees)||0),0);
  const net=share-fees-cost;
  const k=[["Coût total des challenges",money(cost),""],["Comptes financés",funded+" / "+ACCOUNTS.length,""],["Coût moyen d’un compte financé",funded?money(cost/funded):"—",""],["Retraits cumulés",money(share),""],
    ["Frais de retrait",money(fees),""],["Bilan net",money(net),net>0?"pos":net<0?"neg":""]];
  $("#acc-kpis").replaceChildren(...k.map(([l,v,c])=>el("div",{class:"kpi"},el("div",{class:"v"+(c?" "+c:""),text:v}),el("div",{class:"k",text:l}))));

  const box=$("#accounts");
  if(!ACCOUNTS.length){box.replaceChildren(el("div",{class:"empty",text:"Aucun compte. Créez votre premier challenge."}))}
  else box.replaceChildren(...ACCOUNTS.slice().reverse().map(a=>{
    const T=accTrades(a.id), g=agg(T), paid=PAYOUTS.filter(p=>p.accountId===a.id).reduce((s,p)=>s+(num(p.share)||0),0);
    const head=el("div",{class:"row"},el("span",{class:"t1",text:a.name}),el("span",{class:"pill "+a.status,text:STATUS_L[a.status]||a.status}));
    if(a.active)head.append(el("span",{class:"tag",text:"actif"}));
    const sub=[a.firm,a.model,money(a.size),"challenge "+money2(a.price)].filter(Boolean).join(" · ")+(a.purchaseDate?" · acheté le "+new Date(a.purchaseDate+"T00:00:00").toLocaleDateString("fr-FR"):"");
    const nums=el("div",{class:"nums"},
      el("div",null,el("b",{text:String(g.n)}),el("span",{text:"trades"})),
      el("div",null,el("b",{text:(g.sum>=0?"+":"−")+fmt(Math.abs(g.sum),1)+"R"}),el("span",{text:"total"})),
      el("div",null,el("b",{text:money(paid)}),el("span",{text:"retiré"})));
    const acts=el("div",{class:"row"});
    if(!a.active){const b=el("button",{type:"button",class:"btn ghost sm",text:"Rendre actif"});b.addEventListener("click",()=>{$("#acct-switch").value=String(a.id);$("#acct-switch").dispatchEvent(new Event("change"))});acts.append(b)}
    const e=el("button",{type:"button",class:"btn ghost sm",text:"Modifier"});e.addEventListener("click",()=>openAccForm(a));
    const k=el("button",{type:"button",class:"btn ghost sm",text:"Clé MT5"});k.addEventListener("click",()=>mt5Key(a));
    const x=el("button",{type:"button",class:"btn ghost sm danger",text:"Supprimer"});x.addEventListener("click",()=>deleteAccount(a));
    acts.append(e,k,x);
    if(a.mt5){const r=el("button",{type:"button",class:"btn ghost sm",text:"Couper MT5"});r.addEventListener("click",()=>mt5Revoke(a));acts.insertBefore(r,x)}
    const sn=SNAPS[a.id];
    const mt=el("div",{class:"t2",text:a.mt5?("MT5 relié"+(sn?" · dernier envoi "+sn.at.slice(8,10)+"/"+sn.at.slice(5,7)+" "+sn.at.slice(11,16)+" · solde "+money2(sn.balance):" · aucun envoi reçu")):"MT5 non relié"});
    return el("div",{class:"acc"+(a.active?" on":"")},head,el("div",{class:"t2",text:sub}),mt,nums,acts);
  }));
  renderPayouts();
}
function openAccForm(a){
  ACC_EDIT=a?a.id:null;$("#acc-form").hidden=false;$("#af-title").textContent=a?"Modifier le compte":"Nouveau compte";
  $("#af-name").value=a?a.name:"";$("#af-firm").value=a?a.firm:"FundedNext";$("#af-model").value=a?a.model:"Stellar 2-Step";
  $("#af-size").value=a?a.size:(ACTIVE?ACTIVE.size:6000);$("#af-price").value=a?a.price:"";$("#af-status").value=a?a.status:"phase1";
  $("#af-date").value=a?(a.purchaseDate||""):todayStr();$("#af-phase").value=a?(a.phaseStart||""):todayStr();
  $("#acc-form").scrollIntoView({behavior:"smooth",block:"start"});$("#af-name").focus();
}
$("#acc-new").addEventListener("click",()=>openAccForm(null));
$("#af-cancel").addEventListener("click",()=>{$("#acc-form").hidden=true;ACC_EDIT=null});
$("#af-save").addEventListener("click",async()=>{
  const body={name:$("#af-name").value.trim(),firm:$("#af-firm").value.trim(),model:$("#af-model").value.trim(),size:$("#af-size").value,price:$("#af-price").value,
    status:$("#af-status").value,purchaseDate:$("#af-date").value,phaseStart:$("#af-phase").value};
  if(!body.name){toast("Donnez un nom au compte.");return}
  if(!ACC_EDIT&&ACTIVE)body.params=ACTIVE.params;
  const btn=$("#af-save");btn.disabled=true;
  try{const a=await api(ACC_EDIT?"accounts/"+ACC_EDIT:"accounts",{method:ACC_EDIT?"PUT":"POST",body});
    ACCOUNTS=await api("accounts");setActive();fillAccountSelects();$("#acc-form").hidden=true;
    toast(ACC_EDIT?"Compte modifié":"Compte « "+a.name+" » créé");ACC_EDIT=null;refreshAll()}
  catch(e){toast("Enregistrement impossible : "+errMsg(e))}
  btn.disabled=false;
});
async function deleteAccount(a){
  if(ACCOUNTS.length<=1){toast("Gardez au moins un compte.");return}
  const nt=accTrades(a.id).length, np=PAYOUTS.filter(p=>p.accountId===a.id).length;
  if(!confirm("Supprimer le compte « "+a.name+" » ? Ses "+nt+" trade"+(nt>1?"s":"")+" et "+np+" retrait"+(np>1?"s":"")+" seront aussi supprimés. Cette action est définitive."))return;
  try{await api("accounts/"+a.id,{method:"DELETE"});
    TRADES=TRADES.filter(t=>t.accountId!==a.id);PAYOUTS=PAYOUTS.filter(p=>p.accountId!==a.id);
    ACCOUNTS=await api("accounts");setActive();fillAccountSelects();refreshAll();toast("Compte supprimé")}
  catch(e){toast("Suppression impossible : "+errMsg(e))}
}
function resetPayoutForm(){
  PAY_EDIT=null;$("#pf-cancel").hidden=true;$("#pf-save").textContent="Enregistrer le retrait";
  if(ACTIVE)$("#pf-account").value=String(ACTIVE.id);
  $("#pf-date").value=todayStr();["gross","share","fees","method","conv"].forEach(k=>$("#pf-"+k).value="");
}
$("#pf-cancel").addEventListener("click",resetPayoutForm);
$("#pf-save").addEventListener("click",async()=>{
  const body={accountId:parseInt($("#pf-account").value,10),date:$("#pf-date").value,gross:$("#pf-gross").value,share:$("#pf-share").value,fees:$("#pf-fees").value,method:$("#pf-method").value.trim(),conversion:$("#pf-conv").value};
  if(!body.date||!Number.isFinite(num(body.share))){toast("Indiquez la date et la part reçue.");return}
  const btn=$("#pf-save");btn.disabled=true;const editing=PAY_EDIT;
  try{const p=await api(editing?"payouts/"+editing:"payouts",{method:editing?"PUT":"POST",body});
    const i=PAYOUTS.findIndex(x=>x.id===p.id);if(i>=0)PAYOUTS[i]=p;else PAYOUTS.push(p);
    PAYOUTS.sort((x,y)=>(y.date+String(y.id).padStart(10,"0")).localeCompare(x.date+String(x.id).padStart(10,"0")));
    resetPayoutForm();renderAccounts();toast(editing?"Retrait modifié":"Retrait enregistré")}
  catch(e){toast("Enregistrement impossible : "+errMsg(e))}
  btn.disabled=false;
});
function renderPayouts(){
  const box=$("#payouts");
  if(!PAYOUTS.length){box.replaceChildren(el("div",{class:"empty",text:"Aucun retrait pour l’instant."}));return}
  box.replaceChildren(...PAYOUTS.map(p=>{
    const d=new Date(p.date+"T00:00:00");
    const info=el("div",null,el("div",{class:"t1",text:accName(p.accountId)+(p.method?" · "+p.method:"")}),
      el("div",{class:"t2",text:d.toLocaleDateString("fr-FR",{day:"numeric",month:"short",year:"numeric"})+" · brut "+money2(p.gross)+" · frais "+money2(p.fees)}));
    if(p.conversion)info.append(el("div",{class:"t2",text:p.conversion}));
    const acts=el("div",{class:"row"});
    const e=el("button",{type:"button",class:"btn ghost sm",text:"Modifier"});e.addEventListener("click",()=>{
      PAY_EDIT=p.id;$("#pf-cancel").hidden=false;$("#pf-save").textContent="Enregistrer les modifications";
      $("#pf-account").value=String(p.accountId);$("#pf-date").value=p.date;$("#pf-gross").value=p.gross;$("#pf-share").value=p.share;$("#pf-fees").value=p.fees;$("#pf-method").value=p.method||"";$("#pf-conv").value=p.conversion||"";$("#pf-share").focus()});
    const x=el("button",{type:"button",class:"btn ghost sm danger",text:"Supprimer"});x.addEventListener("click",async()=>{if(!confirm("Supprimer ce retrait ?"))return;
      try{await api("payouts/"+p.id,{method:"DELETE"});PAYOUTS=PAYOUTS.filter(y=>y.id!==p.id);if(PAY_EDIT===p.id)resetPayoutForm();renderAccounts();toast("Retrait supprimé")}catch(err){toast("Suppression impossible : "+errMsg(err))}});
    acts.append(e,x);
    return el("div",{class:"trade"},el("div",{class:"r g",text:money(p.share)}),info,acts);
  }));
}
$("#btn-backup").addEventListener("click",async()=>{try{await download("export/backup.json");toast("Sauvegarde téléchargée")}catch(e){toast("Sauvegarde impossible : "+errMsg(e))}});

/* ---------- signaux TradingView ---------- */
async function loadSignals(){
  const box=$("#signals");
  try{const list=await api("signals",{params:{limit:100}});
    if(!list.length){box.replaceChildren(el("div",{class:"empty",text:"Aucun signal reçu pour l’instant."}));return}
    box.replaceChildren(...list.map(s=>{
      const at=new Date(s.receivedAt.replace(" ","T"));
      const head=[s.symbol,s.tf,s.type,s.price!=null?fmt(s.price,2):""].filter(Boolean).join(" · ")||"Alerte";
      const sub="reçu le "+at.toLocaleDateString("fr-FR",{day:"numeric",month:"short"})+" à "+at.toLocaleTimeString("fr-FR")+(s.barTime?" · bougie de "+s.barTime.slice(11,16)+" · écart "+(s.delay>=0?"+":"")+s.delay+" s":"");
      return el("div",{class:"sig"},el("div",{class:"when",text:at.toLocaleTimeString("fr-FR",{hour:"2-digit",minute:"2-digit"})}),
        el("div",null,el("div",{class:"t1",text:head}),el("div",{class:"t2",text:sub}),s.payload?el("pre",{text:s.payload}):null));
    }));
  }catch(e){box.replaceChildren(el("div",{class:"empty",text:"Lecture impossible : "+errMsg(e)}))}
}
$("#signals-panel").addEventListener("toggle",e=>{if(e.target.open)loadSignals()});
$("#sig-refresh").addEventListener("click",loadSignals);
$("#sig-clear").addEventListener("click",async()=>{if(!confirm("Vider tout le journal des signaux ?"))return;try{await api("signals",{method:"DELETE"});loadSignals();toast("Journal des signaux vidé")}catch(e){toast("Impossible : "+errMsg(e))}});

/* ---------- connexions : MT5, TradingView, Telegram ---------- */
function secretBox(id,children){const b=$(id);b.replaceChildren(...children);b.hidden=false;b.scrollIntoView({behavior:"smooth",block:"center"})}
async function mt5Key(a){
  if(a.mt5&&!confirm("Remplacer la clé MT5 de « "+a.name+" » ? L’EA devra recevoir la nouvelle clé."))return;
  try{const r=await api("accounts/"+a.id+"/mt5-key",{method:"POST"});a.mt5=true;renderAccounts();
    secretBox("#mt5-key-box",[el("div",null,el("b",{text:"Clé MT5 de « "+a.name+" »"}),document.createTextNode(" (affichée une seule fois, copiez-la dans l’EA) :")),el("code",{text:r.key}),el("div",null,"Adresse : ",el("code",{text:r.url}))])}
  catch(e){toast("Impossible : "+errMsg(e))}
}
async function mt5Revoke(a){
  if(!confirm("Couper la liaison MT5 de « "+a.name+" » ?"))return;
  try{await api("accounts/"+a.id+"/mt5-key",{method:"DELETE"});a.mt5=false;renderAccounts();toast("Liaison MT5 coupée")}catch(e){toast("Impossible : "+errMsg(e))}
}
let CONN=null;
async function loadConnections(){
  try{CONN=await api("connections")}catch(e){return}
  $("#ea-link").href=CFG.ea||"#";
  try{$("#mt5-host").textContent=new URL(CONN.mt5Url).origin}catch(e){}
  $("#mt5-url").textContent=CONN.mt5Url;
  $("#tv-state").textContent=CONN.tradingview.active?"Webhook actif. Générer une nouvelle adresse coupe l’ancienne.":"Aucun webhook actif.";
  $("#tv-revoke").hidden=!CONN.tradingview.active;
  $("#plan-state").textContent=CONN.plan.active?"Jeton actif : la tâche planifiée Claude peut publier le plan. Générer un nouveau jeton coupe l’ancien.":"Aucun jeton : personne ne peut publier le plan.";
  $("#plan-revoke").hidden=!CONN.plan.active;
  const tg=CONN.telegram;
  $("#tg-setup").hidden=tg.connected;$("#tg-on").hidden=!tg.connected;
  if(tg.connected){
    const st=$("#tg-state");
    if(tg.linked)st.replaceChildren("Bot ",el("b",{text:"@"+tg.bot}),document.createTextNode(" relié. Commandes : /jour, /plan, /aide."));
    else st.replaceChildren("Bot ",el("b",{text:"@"+tg.bot}),document.createTextNode(" connecté. Dernière étape : ouvrez "),el("a",{href:tg.startLink,target:"_blank",rel:"noopener",text:"ce lien"}),document.createTextNode(" puis appuyez sur « Démarrer » (ou envoyez au bot : /start "+tg.code+")."));
    $$("#tg-alerts input").forEach(i=>{i.checked=!!tg.alerts[i.dataset.a];i.disabled=!tg.linked});
    $("#tg-test").disabled=!tg.linked;
  }
  const rf=CONN.refusals||[];
  $("#refusals-box summary").textContent="Accès refusés aux points d’entrée ("+rf.length+")";
  $("#refusals").replaceChildren(...(rf.length?rf.map(r=>el("div",{class:"t2",style:"font-size:13px;color:var(--muted);margin-top:4px",text:r.time+" · "+r.entry+" · "+r.reason+" · "+r.ip})):[el("p",{class:"hint",style:"margin-top:8px",text:"Aucun refus."})]));
}
$("#tv-rotate").addEventListener("click",async()=>{
  if(CONN&&CONN.tradingview.active&&!confirm("Générer une nouvelle adresse ? L’ancienne cessera de fonctionner."))return;
  try{const r=await api("connections/tradingview",{method:"POST"});
    const tpl='{"symbol":"{{ticker}}","tf":"{{interval}}","signal":"triangle achat","price":{{close}},"bar_time":"{{time}}","fired":"{{timenow}}"}';
    secretBox("#tv-box",[el("div",null,el("b",{text:"Adresse du webhook"}),document.createTextNode(" (affichée une seule fois) — dans l’alerte TradingView › Notifications › URL du webhook :")),el("pre",{text:r.url}),
      el("div",{style:"margin-top:6px",text:"Message de l’alerte (adaptez « signal » à chaque alerte) :"}),el("pre",{text:tpl})]);
    loadConnections()}
  catch(e){toast("Impossible : "+errMsg(e))}
});
$("#plan-rotate").addEventListener("click",async()=>{
  if(CONN&&CONN.plan.active&&!confirm("Générer un nouveau jeton ? La tâche Claude devra recevoir le nouveau."))return;
  try{const r=await api("connections/plan",{method:"POST"});
    secretBox("#plan-box",[el("div",null,el("b",{text:"Jeton du plan"}),document.createTextNode(" (affiché une seule fois, à donner à la tâche planifiée Claude) :")),el("code",{text:r.token}),el("div",null,"Adresse : ",el("code",{text:r.url+"AAAA-MM-JJ"}),document.createTextNode(" (lundi de la semaine), en-tête X-CX-Key."))]);
    loadConnections()}
  catch(e){toast("Impossible : "+errMsg(e))}
});
$("#plan-revoke").addEventListener("click",async()=>{if(!confirm("Couper le jeton du plan ? Claude ne pourra plus publier."))return;try{await api("connections/plan",{method:"DELETE"});$("#plan-box").hidden=true;loadConnections();toast("Jeton coupé")}catch(e){toast("Impossible : "+errMsg(e))}});
$("#tv-revoke").addEventListener("click",async()=>{if(!confirm("Couper le webhook TradingView ?"))return;try{await api("connections/tradingview",{method:"DELETE"});$("#tv-box").hidden=true;loadConnections();toast("Webhook coupé")}catch(e){toast("Impossible : "+errMsg(e))}});
$("#tg-connect").addEventListener("click",async()=>{
  const token=$("#tg-token").value.trim();if(!token){toast("Collez le jeton du bot.");return}
  const b=$("#tg-connect");b.disabled=true;
  try{await api("connections/telegram",{method:"POST",body:{token}});$("#tg-token").value="";await loadConnections();toast("Bot connecté")}
  catch(e){toast(errMsg(e))}
  b.disabled=false;
});
$$("#tg-alerts input").forEach(i=>i.addEventListener("change",async()=>{
  try{await api("connections/telegram",{method:"PUT",body:{alerts:{[i.dataset.a]:i.checked}}});toast("Alerte "+(i.checked?"activée":"coupée"))}catch(e){i.checked=!i.checked;toast("Impossible : "+errMsg(e))}
}));
$("#tg-test").addEventListener("click",async()=>{try{await api("connections/telegram/test",{method:"POST"});toast("Message envoyé sur Telegram")}catch(e){toast(errMsg(e))}});
$("#tg-off").addEventListener("click",async()=>{if(!confirm("Déconnecter le bot Telegram ?"))return;try{await api("connections/telegram",{method:"DELETE"});loadConnections();toast("Bot déconnecté")}catch(e){toast("Impossible : "+errMsg(e))}});

/* ---------- mise à jour périodique (trades MT5, solde) ---------- */
setInterval(async()=>{
  if(document.hidden||EDIT_ID)return;
  try{const b=await api("bootstrap");TRADES=b.trades||[];SNAPS=b.snaps||{};refreshAll()}catch(e){}
},120000);

function refreshAll(){
  renderTrades();calc();
  if(!$("#tab-stats").hidden)renderStats();
  if(!$("#tab-comptes").hidden)renderAccounts();
}

/* ---------- démarrage ---------- */
async function start(){
  buildChecklist();
  try{
    const b=await api("bootstrap");
    ACCOUNTS=b.accounts||[];TRADES=b.trades||[];PAYOUTS=b.payouts||[];SNAPS=b.snaps||{};
  }catch(e){toast("Chargement impossible : "+errMsg(e));setStatus("err","Hors ligne")}
  setActive();fillAccountSelects();resetTradeForm();resetPayoutForm();
  try{THIS_WEEK_PLAN=await api("plans/"+weekId(planWeek()))}catch(e){THIS_WEEK_PLAN=null}
  renderPlanMini();loadPlan();renderTrades();calc();
  if("serviceWorker" in navigator&&CFG.sw&&window.isSecureContext){navigator.serviceWorker.register(CFG.sw,{scope:CFG.scope||"/"}).catch(()=>{})}
}
start();
})();
