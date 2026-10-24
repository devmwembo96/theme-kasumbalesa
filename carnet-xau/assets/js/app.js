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
// Espace des milliers insécable (« 4 044,70 » jamais coupé en fin de ligne sur téléphone).
const nb=s=>typeof s==="string"?s.replace(/(\d)[  ](?=\d{3}(?!\d))/g,"$1 "):s;
function el(tag,attrs,...kids){const e=document.createElement(tag);for(const k in (attrs||{})){if(k==="class")e.className=attrs[k];else if(k==="text")e.textContent=nb(attrs[k]);else e.setAttribute(k,attrs[k])}kids.forEach(c=>c!=null&&e.append(nb(c)));return e}
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
  if(CFG.readOnly&&(opts.method||"GET")!=="GET")throw {code:"cx_read_only",message:"lecture seule, connectez-vous pour modifier"};
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
const accName=id=>{const a=ACCOUNTS.find(x=>x.id===id);return a?a.name:"—"};

/* ---------- onglets ---------- */
$$(".cx-nav button").forEach(b=>b.addEventListener("click",()=>showTab(b.dataset.tab)));
function showTab(t){
  $$(".cx-nav button").forEach(b=>b.setAttribute("aria-selected",String(b.dataset.tab===t)));
  ["jour","plan","journal","comptes"].forEach(x=>{$("#tab-"+x).hidden=x!==t});
  if(t==="journal"){renderStats();loadReport();renderIdeas()}
  if(t==="comptes"){renderAccounts();loadConnections();mcNote()}
  const top=ROOT.getBoundingClientRect().top+window.scrollY;
  if(window.scrollY>top)window.scrollTo(0,Math.max(0,top-100));
}

/* ---------- comptes : sélection ---------- */
function fillAccountSelects(){
  const opts=(all)=>{const o=[];if(all)o.push(el("option",{value:"",text:"Tous les comptes"}));ACCOUNTS.forEach(a=>o.push(el("option",{value:String(a.id),text:a.name+" · "+accLabel(a)})));return o};
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
  QUICK_STOP="";$("#in-stop").value="";fillSettings();
}
$("#acct-switch").addEventListener("change",async e=>{
  const id=e.target.value;
  try{ACCOUNTS=await api("accounts/"+id+"/activate",{method:"POST"});setActive();fillAccountSelects();
    ["#jf-account","#sf-account","#tf-account","#pf-account"].forEach(s=>$(s).value=String(ACTIVE.id));
    renderTrades();calc();if(!$("#tab-journal").hidden)renderStats();if(!$("#tab-comptes").hidden)renderAccounts();
    toast("Compte actif : "+ACTIVE.name)}
  catch(err){toast("Changement impossible : "+errMsg(err));$("#acct-switch").value=ACTIVE?String(ACTIVE.id):""}
});

/* ---------- paramètres du compte actif ---------- */
const isOwn=a=>!!a&&a.params&&a.params.kind==="propre";
const accLabel=a=>isOwn(a)?(a.status==="retire"?"Retiré":"Fonds propres"):(STATUS_L[a.status]||a.status);
// Prop firm : champ de l'écran => paramètre. Le risque est en % de la taille ; le serveur en déduit les $.
const PROP_FIELDS={mindays:"mindays",t1:"t1",t2:"t2",tf:"tf",daily:"daily",max:"max",contract:"contract",riskp:"riskp",riskredp:"riskredp",redatp:"redatp","daystopr-p":"daystopr",maxtrades:"maxtrades",maxloss:"maxloss",spreadmax:"spreadmax",commission:"commission"};
// Fonds propres : champ de l'écran => paramètre (les limites communes ont leur propre champ).
const OWN_FIELDS={start:"start",budget:"budget",floor:"floor",withdraw:"withdraw","maxloss-o":"maxloss",daystopr:"daystopr",weekloss:"weekloss",weekstopr:"weekstopr","maxtrades-o":"maxtrades","contract-o":"contract","commission-o":"commission"};
function showKind(k){$("#set-own").hidden=k!=="propre";$("#set-prop").hidden=k==="propre"}
function fillSettings(){
  if(!ACTIVE)return;
  const p=ACTIVE.params;$("#s-kind").value=p.kind||"propfirm";showKind($("#s-kind").value);
  $("#s-phase").value=ACTIVE.status;$("#s-size").value=ACTIVE.size;
  Object.entries(PROP_FIELDS).forEach(([f,k])=>{$("#s-"+f).value=p[k]});
  $("#s-dailybase").value=p.dailybase||"initial";
  Object.entries(OWN_FIELDS).forEach(([f,k])=>{$("#s-"+f).value=p[k]});
  propHint();
}
// Équivalents en $ des % saisis, et contrôle : l'arrêt personnel doit tenir dans la perte journalière.
function propHint(){
  const size=num($("#s-size").value), g=k=>num($("#s-"+k).value), h=$("#s-prop-hint");
  if(!(size>0)){h.textContent="";return}
  const r=size*g("riskp")/100, rr=size*g("riskredp")/100, d=size*g("daily")/100, ds=g("daystopr-p")*r;
  h.textContent="Risque "+money2(r)+" · réduit "+money2(rr)+" sous "+money(size*g("redatp")/100)+" de perte · arrêt personnel "+money2(ds)+" par jour · perte journalière de la firme "+money(d)+($("#s-dailybase").value==="daystart"?" (sur la taille ; recalculée chaque matin sur le solde)":"")+" · plancher "+money(size*(1-g("max")/100))+(ds>d?" — attention : l’arrêt personnel dépasse la limite de la firme.":"");
}
["#s-size","#s-riskp","#s-riskredp","#s-redatp","#s-daystopr-p","#s-daily","#s-max","#s-dailybase"].forEach(id=>$(id).addEventListener("input",propHint));
$("#s-kind").addEventListener("change",e=>showKind(e.target.value));
$("#btn-settings").addEventListener("click",async()=>{
  if(!ACTIVE)return;
  const kind=$("#s-kind").value, params={kind};let body;
  if(kind==="propre"){
    for(const [f,k] of Object.entries(OWN_FIELDS)){params[k]=num($("#s-"+f).value);if(!Number.isFinite(params[k])){toast("Valeur manquante : vérifiez chaque champ.");return}}
    body={status:ACTIVE.status==="retire"?"retire":"finance",size:params.start,params};
  }else{
    for(const [f,k] of Object.entries(PROP_FIELDS)){params[k]=num($("#s-"+f).value);if(!Number.isFinite(params[k])){toast("Valeur manquante : vérifiez chaque champ.");return}}
    params.dailybase=$("#s-dailybase").value;
    const size=num($("#s-size").value);if(!Number.isFinite(size)||size<=0){toast("Taille du compte invalide.");return}
    body={status:$("#s-phase").value,size,params};
  }
  try{const a=await api("accounts/"+ACTIVE.id,{method:"PUT",body});
    replaceAccount(a);setActive();fillAccountSelects();toast("Paramètres enregistrés")}
  catch(e){toast("Enregistrement impossible : "+errMsg(e))}
  calc();
});
function replaceAccount(a){const i=ACCOUNTS.findIndex(x=>x.id===a.id);if(i>=0)ACCOUNTS[i]=a;else ACCOUNTS.push(a)}

/* ---------- ticket du jour : tout vient du compte sélectionné (MT5 et journal) ---------- */
function accTrades(id){return TRADES.filter(t=>t.accountId===id)}
// Envoi MT5 faussé par un dépôt ou un retrait du jour (EA jusqu'à 2.11 : compte financé aujourd'hui,
// solde du matin à 0) : solde du matin sous le plancher alors que l'équité est au-dessus, le journal prend le relais.
function snapSkewed(s){const floor=isOwn(ACTIVE)?0:ACTIVE.size*(1-ACTIVE.params.max/100);return s.equity+s.dailyLoss<=floor&&s.equity>floor}
function todaySnap(){const s=ACTIVE&&SNAPS[ACTIVE.id];return s&&s.at.slice(0,10)===todayStr()&&!snapSkewed(s)?s:null}
function renderMt5Line(){
  const box=$("#mt5-line");let s=ACTIVE&&SNAPS[ACTIVE.id];
  if(!s){box.hidden=true;return}
  // Dépôt du jour compté par l'EA : le résultat du jour vient du journal.
  if(snapSkewed(s))s=Object.assign({},s,{dailyLoss:-todayTrades().reduce((a,x)=>a+(num(x.pnl)||0),0)});
  const mins=Math.max(0,Math.round((Date.now()-new Date(s.at.replace(" ","T")).getTime())/60000));
  const ago=mins<1?"à l’instant":mins<60?"il y a "+mins+" min":mins<1440?"il y a "+Math.round(mins/60)+" h":"le "+new Date(s.at.replace(" ","T")).toLocaleDateString("fr-FR");
  box.replaceChildren(el("span",null,"MT5 "),el("span",null,"solde ",el("b",{text:money2(s.balance)})),el("span",null,"équité ",el("b",{text:money2(s.equity)})),
    el("span",null,s.dailyLoss>0?"perte du jour ":"gain du jour ",el("b",{text:money2(Math.abs(s.dailyLoss))})),el("span",{class:mins>10?"old":"",text:ago}));
  box.hidden=false;
}
// Solde du matin : MT5 du jour (équité + perte du jour), sinon taille + résultats avant aujourd'hui.
function morningBalance(){
  const sn=todaySnap();if(sn)return Math.round((sn.equity+sn.dailyLoss)*100)/100;
  const t=todayStr(), from=isOwn(ACTIVE)?"0000-00-00":(ACTIVE.phaseStart||"0000-00-00");
  return Math.round((ACTIVE.size+accTrades(ACTIVE.id).filter(x=>x.date>=from&&x.date<t).reduce((a,x)=>a+(num(x.pnl)||0),0))*100)/100;
}
// Capital actuel : solde MT5, sinon taille + tous les résultats.
// Prop firm : seuls les trades de l'étape en cours comptent (chaque étape repart de la taille du compte).
function currentCapital(){const sn=ACTIVE&&SNAPS[ACTIVE.id];if(sn)return sn.equity;
  const from=isOwn(ACTIVE)?"0000-00-00":(ACTIVE.phaseStart||"0000-00-00");
  return ACTIVE.size+accTrades(ACTIVE.id).filter(x=>x.date>=from).reduce((a,x)=>a+(num(x.pnl)||0),0)}
// Coût d'un lot pour un stop : mouvement × contrat + commission aller-retour.
const lotCost=(stop,p)=>stop*p.contract+(num(p.commission)||0);
// Jours tradés de l'étape : un jour compte dès qu'il porte un trade, aujourd'hui compris.
function tradedDays(){const from=ACTIVE.phaseStart||"0000-00-00";return new Set(accTrades(ACTIVE.id).filter(x=>x.date>=from).map(x=>x.date))}
// Risque du palier (fonds propres), comme le Calculateur XAU : départ ÷ N jusqu'à 500 $, puis 4, 2 et 1 %.
function ownRisk(cap,p){
  if(cap<500)return{risk:Math.min(cap,p.start)/Math.max(1,p.budget),tier:1};
  if(cap<1000)return{risk:cap*0.04,tier:2};
  if(cap<2500)return{risk:cap*0.02,tier:3};
  return{risk:cap*0.01,tier:4};
}
function mondayStr(){const d=new Date();d.setDate(d.getDate()-((d.getDay()+6)%7));return d.toISOString().slice(0,10)}
let QUICK_STOP="";
$("#in-stop").addEventListener("input",e=>{QUICK_STOP=e.target.value;calc()});
function todayTrades(){const t=todayStr();return ACTIVE?accTrades(ACTIVE.id).filter(x=>x.date===t):[]}
const cellEl=(k,v,sub,cls)=>el("div",{class:"cell"+(cls?" "+cls:"")},el("div",{class:"k",text:k}),el("div",{class:"v",text:v}),el("div",{class:"s",text:sub||""}));
let DAY_RISK=NaN, DAY_STATE={light:"go",why:[]};
function calc(){
  if(!ACTIVE)return;
  renderMt5Line();
  const own=isOwn(ACTIVE), p=ACTIVE.params, alerts=[], cells=[];
  const tt=todayTrades(), netToday=tt.reduce((a,x)=>a+(num(x.pnl)||0),0), lossesToday=tt.filter(x=>num(x.r)<=-0.5).length;
  const bal=morningBalance();
  $("#t-phase").textContent=accLabel(ACTIVE)+" · "+ACTIVE.name;
  if(ACTIVE.status==="echoue"||ACTIVE.status==="retire")alerts.push(["warn","Ce compte est « "+accLabel(ACTIVE).toLowerCase()+" ». Choisissez un autre compte actif pour trader."]);
  let risk;
  if(own){
    const cap=currentCapital(), r=ownRisk(bal,p);risk=r.risk;
    const dayLim=p.daystopr*risk, dayLeft=dayLim+Math.min(0,netToday);
    const wk=accTrades(ACTIVE.id).filter(x=>x.date>=mondayStr()), wkNet=wk.reduce((a,x)=>a+(num(x.pnl)||0),0), wkLoss=wk.filter(x=>num(x.r)<=-0.5).length;
    const mo=accTrades(ACTIVE.id).filter(x=>x.date.slice(0,7)===todayStr().slice(0,7)), moNet=mo.reduce((a,x)=>a+(num(x.pnl)||0),0);
    const toFloor=cap-p.floor;
    $("#t-meta").textContent=(ACTIVE.firm?ACTIVE.firm+" · ":"")+"départ "+money(p.start)+" · plancher "+money(p.floor)+" · retrait "+fmt(p.withdraw,0)+" % du gain du mois";
    cells.push(cellEl("Capital",money2(cap),"Début de journée "+money2(bal),"hero"));
    cells.push(cellEl("Risque par trade",money2(risk),r.tier===1?"Palier 1 : "+money(p.start)+" ÷ "+p.budget:"Palier "+r.tier+" : "+fmt(100*risk/bal,0)+" % du capital"));
    cells.push(cellEl("Aujourd’hui",(netToday>=0?"+":"−")+money2(Math.abs(netToday)),tt.length+"/"+p.maxtrades+" trades · "+lossesToday+"/"+p.maxloss+" pertes · reste "+money(Math.max(0,dayLeft))));
    cells.push(cellEl("Semaine",(wkNet>=0?"+":"−")+money2(Math.abs(wkNet)),wkLoss+"/"+p.weekloss+" pertes · limite "+fmt(p.weekstopr,0)+" R ("+money(p.weekstopr*risk)+")"));
    cells.push(cellEl("Avant le plancher",money(toFloor),"≈ "+(risk>0?Math.max(0,Math.floor(toFloor/risk)):0)+" pertes pleines"));
    cells.push(cellEl("Mois",(moNet>=0?"+":"−")+money2(Math.abs(moNet)),moNet>0?"Retrait conseillé en fin de mois : "+money2(moNet*p.withdraw/100):"Pas de retrait ce mois-ci"));
    if(lossesToday>=p.maxloss)alerts.push(["stop","Arrêt pour aujourd’hui : "+lossesToday+" pertes."]);
    else if(dayLeft<=0)alerts.push(["stop","Perte maximum du jour atteinte ("+fmt(p.daystopr,0)+" R) : arrêt jusqu’à demain."]);
    else if(tt.length>=p.maxtrades)alerts.push(["stop","Limite de "+p.maxtrades+" trades atteinte pour aujourd’hui."]);
    if(wkLoss>=p.weekloss||wkNet<=-p.weekstopr*risk)alerts.push(["stop","Limite de la semaine atteinte : arrêt jusqu’à lundi, relecture complète."]);
    if(cap<=p.floor)alerts.push(["stop","Plancher de "+money(p.floor)+" atteint : plus de trade réel, retour en démo."]);
    else if(toFloor<=2*risk)alerts.push(["warn","Plus que "+Math.max(0,Math.floor(toFloor/risk))+" perte(s) avant le plancher."]);
  }else{
    // Prop firm. Tout est en % de la taille du compte, pour qu'un compte de 6 000 $ et un de 100 000 $
    // se calculent chacun avec leurs propres montants.
    const size=ACTIVE.size, phase=S.phase, pct=phase==="1"?p.t1:phase==="2"?p.t2:p.tf;
    const sn=todaySnap(), lossToday=sn?sn.dailyLoss:-netToday; // > 0 : perte du jour (MT5 : flottant compris)
    const cur=currentCapital(), floor=size*(1-p.max/100), maxLeft=cur-floor;
    const firmDaily=(p.dailybase==="daystart"?bal:size)*p.daily/100, firmLeft=firmDaily-lossToday;
    const baseRisk=size*p.riskp/100, reduced=bal-size<=-size*p.redatp/100;
    risk=reduced?size*p.riskredp/100:baseRisk;
    const dayStop=p.daystopr*baseRisk, personalLeft=dayStop-Math.max(0,lossToday);
    // Plafond : une perte pleine ne doit jamais toucher la limite du jour ni la perte max (10 % de marge pour glissement et spread).
    const room=Math.min(firmLeft,maxLeft), capped=risk>0.9*room;if(capped)risk=Math.max(0,0.9*room);
    const target=size*pct/100, profit=cur-size, remaining=target-profit, hasTarget=pct>0;
    const days=tradedDays(), daysDone=days.size, daysBefore=[...days].filter(d=>d<todayStr()).length;
    const daysLeft=Math.max(1,p.mindays-daysBefore), dayTarget=hasTarget?Math.max(0,(target-(bal-size))/daysLeft):0;
    $("#t-meta").textContent=[ACTIVE.firm,ACTIVE.model].filter(Boolean).join(" ")+" · "+money(size)+(hasTarget?" · objectif "+fmt(pct,1)+" % ("+money(target)+")":" · pas d’objectif")+" · plancher "+money(floor);
    cells.push(cellEl("Risque par trade",money2(risk),capped?"Plafonné par la marge restante":fmt(100*risk/size,2)+" % de la taille"+(reduced?" · risque réduit":""),"hero"));
    if(hasTarget){
      cells.push(cellEl("Objectif de l’étape",remaining>0?money(remaining):"Atteint",remaining>0?"Progression "+(profit>=0?"+":"−")+money(Math.abs(profit))+" sur "+money(target):"Micro-trades jusqu’au jour minimum"));
      cells.push(cellEl("Objectif du jour",remaining>0?money(dayTarget):"—",remaining>0?"Reste de l’objectif ÷ "+daysLeft+" jour"+(daysLeft>1?"s":"")+(p.mindays-daysBefore>0?" minimum restant"+(daysLeft>1?"s":""):""):""));
    }else cells.push(cellEl("Résultat du cycle",(profit>=0?"+":"−")+money2(Math.abs(profit)),"Compte financé : pas d’objectif"));
    cells.push(cellEl("Jours tradés",phase==="f"?String(daysDone):daysDone+" / "+p.mindays,tt.length?"Aujourd’hui compté":"Compté au premier trade du jour"));
    cells.push(cellEl("Perte du jour (firme)","reste "+money(Math.max(0,firmLeft)),"Limite "+money(firmDaily)+(p.dailybase==="daystart"?" sur le solde du matin":" sur la taille")+" · "+(lossToday>0?"perdu ":"gagné ")+money2(Math.abs(lossToday))+(sn?" (MT5)":" (journal)")));
    cells.push(cellEl("Arrêt personnel","reste "+money(Math.max(0,personalLeft)),fmt(p.daystopr,1)+" R = "+money(dayStop)+" par jour"));
    cells.push(cellEl("Marge avant perte max",money(maxLeft),"Plancher "+money(floor)+" · ≈ "+(risk>0?Math.max(0,Math.floor(maxLeft/risk)):0)+" pertes pleines"));
    cells.push(cellEl("Aujourd’hui",(netToday>=0?"+":"−")+money2(Math.abs(netToday)),tt.length+"/"+p.maxtrades+" trades · "+lossesToday+"/"+p.maxloss+" pertes"));
    if(hasTarget&&remaining<=0)alerts.push(["go",daysDone>=p.mindays?"Objectif de l’étape atteint et jours minimum faits : demandez le passage à l’étape suivante.":"Objectif de l’étape atteint. Faites seulement des micro-trades jusqu’au nombre de jours minimum ("+daysDone+"/"+p.mindays+")."]);
    if(reduced)alerts.push(["warn","Le compte est à "+money(bal-size)+" : risque réduit à "+fmt(p.riskredp,2)+" % ("+money2(size*p.riskredp/100)+")."]);
    if(maxLeft<=0)alerts.push(["stop","Perte maximale atteinte : le compte est perdu selon les règles de la firme."]);
    else if(firmLeft<=0)alerts.push(["stop","Limite de perte journalière de la firme atteinte : plus aucun trade aujourd’hui."]);
    else if(capped&&risk<baseRisk*0.5)alerts.push(["stop","Marge trop faible pour un trade normal aujourd’hui : une perte ferait toucher une limite de la firme."]);
    else if(lossToday>=firmDaily*0.8)alerts.push(["stop","Vous êtes à plus de 80 % de la perte journalière de la firme."]);
    if(personalLeft<=0)alerts.push(["stop","Arrêt personnel atteint ("+fmt(p.daystopr,1)+" R) : arrêt jusqu’à demain."]);
    if(lossesToday>=p.maxloss)alerts.push(["stop","Arrêt pour aujourd’hui : "+lossesToday+" pertes."]);
    else if(tt.length>=p.maxtrades)alerts.push(["stop","Limite de "+p.maxtrades+" trades atteinte pour aujourd’hui."]);
    if(hasTarget&&remaining>0&&netToday>=dayTarget&&tt.length>0)alerts.push(["go","Objectif du jour atteint : arrêtez de trader aujourd’hui."]);
  }
  $("#ticket-out").replaceChildren(...cells);
  DAY_RISK=risk;
  renderToday();
  // Lot rapide : le seul calcul à saisir (le Calculateur XAU le fait dans MT5).
  const stop=num(QUICK_STOP), q=$("#quick-out");CALC={lot:NaN,stop:NaN};
  if(Number.isFinite(stop)&&stop>0&&risk>0){
    const lot=Math.floor(risk/lotCost(stop,p)*100+1e-9)/100;CALC={lot,stop};
    q.replaceChildren(el("b",{text:fmt(lot,2)+" lot"}),document.createTextNode(" · risque réel "+money2(lot*lotCost(stop,p))+((num(p.commission)||0)>0?" commission comprise":"")+" · 2R à "+fmt(stop*2,2)+" $ de mouvement"));
    if(lot<0.01)alerts.push(["stop","Stop trop large pour ce risque : le lot tombe sous 0,01."]);
    if(stop<1.5||stop>8)alerts.push(["warn","Stop hors de la plage 1,50 – 8,00 $ : passez votre tour ou attendez l’ordre limite."]);
  }else q.textContent="Risque "+money2(risk)+" par trade : saisissez la distance du stop pour le lot.";
  const box=$("#alerts");box.replaceChildren(...alerts.map(([c,t])=>el("div",{class:"alert "+c,text:t})));box.hidden=!alerts.length;
  DAY_STATE={light:alerts.some(a=>a[0]==="stop")?"stop":alerts.some(a=>a[0]==="warn")?"warn":"go",why:alerts.filter(a=>a[0]!=="go").map(a=>a[1])};
  renderFeu();
}

/* ---------- feu du jour : peut-on trader, dernier signal, plan de la séance ---------- */
let LAST_SIGNALS=[];
async function loadFeuSignals(){try{LAST_SIGNALS=await api("claude-signals")}catch(e){LAST_SIGNALS=[]}renderFeu()}
function renderFeu(){
  const out=[], st=DAY_STATE;
  out.push(el("div",{class:"feu "+st.light},el("b",{text:st.light==="stop"?"Arrêt":st.light==="warn"?"Prudence":"Feu vert"}),
    document.createTextNode(st.light==="go"?" : vous pouvez prendre les signaux du plan, risque "+(Number.isFinite(DAY_RISK)?money2(DAY_RISK):"—")+" par trade.":" : "+st.why[0])));
  const t=todayStr(), sig=LAST_SIGNALS.filter(s=>s.kind!=="retest"&&s.at.slice(0,10)===t)[0];
  const SL={ouvert:"en cours",attente:"ordre limite en attente",annule:"annulé",gagne:"gagnant",perdu:"perdant",be:"sorti à zéro"};
  out.push(el("p",null,el("span",{class:"k",text:"Dernier signal : "}),document.createTextNode(sig?sig.at.slice(11,16)+" · "+(sig.side==="sell"?"vente":"achat")+(sig.setup?" · setup "+sig.setup:"")+" · entrée "+px(sig.entry)+", stop "+px(sig.sl)+", objectif "+px(sig.tp)+" · "+(SL[sig.status]||sig.status)+(sig.r!=null?" ("+(sig.r>0?"+":"")+fmt(sig.r,1)+"R)":""):"aucun aujourd’hui")));
  $("#feu").replaceChildren(...out);
}
$("#btn-log").addEventListener("click",()=>{
  resetTradeForm();
  if(Number.isFinite(CALC.stop))$("#tf-stop").value=CALC.stop;
  if(Number.isFinite(CALC.lot)){$("#tf-lot").value=CALC.lot;if(Number.isFinite(CALC.stop))$("#tf-risk").value=(CALC.lot*lotCost(CALC.stop,ACTIVE.params)).toFixed(2)}
  showTab("journal");openTradeForm();$("#tf-r").focus();
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
// Graphique en bougies du courtier (EA MT5) avec les zones et niveaux du plan.
let CANDLES=null,CANDLES_AT=0,CHART_TF="H4";
const TF_BARS={H1:96,H4:90,D1:90},tfNorm=t=>{t=String(t||"").toUpperCase();return t==="D"?"D1":t},TF_L={H1:"1 heure",H4:"4 heures",D1:"1 jour"};
async function loadCandles(){
  if(CANDLES&&Date.now()-CANDLES_AT<5*60e3)return CANDLES;
  try{CANDLES=await api("candles");CANDLES_AT=Date.now()}catch(e){CANDLES=CANDLES||{}}
  return CANDLES;
}
// Graphique de TradingView (Lightweight Charts) : bougies du courtier, zones et niveaux du plan.
let CUR_CHART=null;
function cssVar(n,f){const v=getComputedStyle(document.getElementById("cx-app")||document.body).getPropertyValue(n).trim();return v||f}
function rgba(c,a){const m=/^#([0-9a-f]{6})$/i.exec(c);if(!m)return c;const n=parseInt(m[1],16);return "rgba("+(n>>16)+","+(n>>8&255)+","+(n&255)+","+a+")"}
// Bougie d'origine d'une zone ou d'un niveau : premier « jj/mm » de `origin` et l'heure « hh:mm » qui le suit
// (avant la date suivante), en heure de Lubumbashi (UTC+2) ; sinon la dernière bougie dont un extrême touche
// la borne à quelques centimes près.
function originIndex(bars,text,prices){
  const s=String(text||""),d=/(\d{1,2})\/(\d{1,2})/.exec(s),rest=d?s.slice(d.index+d[0].length):"",nx=rest.search(/\d{1,2}\/\d{1,2}/),
    h=/(\d{1,2})[:h](\d{2})/.exec(nx<0?rest:rest.slice(0,nx));
  if(d){const y=new Date(bars[bars.length-1][0]*1000).getUTCFullYear(),t=Date.UTC(y,+d[2]-1,+d[1],h?+h[1]:0,h?+h[2]:0)/1000-7200;
    if(t<=bars[bars.length-1][0]){let i=bars.length-1;while(i>0&&bars[i][0]>t)i--;if(bars[i][0]<=t&&t-bars[i][0]<7*86400)return i}}
  for(let i=bars.length-4;i>=0;i--){const b=bars[i];if(prices.some(p=>[b[1],b[2],b[3],b[4]].some(v=>Math.abs(v-p)<=.3)))return i}
  return 0;
}
// Dessin des zones et niveaux du plan de Claude, sous les bougies.
function planPrimitive(bars,items){
  let chart=null,series=null;
  const draw=target=>target.useBitmapCoordinateSpace(({context:ctx,bitmapSize:sz,horizontalPixelRatio:hr,verticalPixelRatio:vr})=>{
    if(!series)return;const ts=chart.timeScale(),X=i=>{const x=ts.timeToCoordinate(bars[Math.max(0,Math.min(bars.length-1,i))][0]);return x==null?null:x*hr},Y=p=>{const y=series.priceToCoordinate(p);return y==null?null:y*vr};
    const font=(s,w)=>(w||"")+" "+Math.round(s*vr)+"px "+items.font;ctx.save();ctx.textBaseline="middle";
    // Zones du plan : rectangle plein, de la bougie d'origine au bord droit.
    items.zones.forEach(z=>{const x=X(z.from),y1=Y(z.high),y2=Y(z.low);if(x==null||y1==null||y2==null)return;
      ctx.fillStyle=z.fill;ctx.fillRect(x,Math.min(y1,y2),sz.width-x,Math.max(2,Math.abs(y2-y1)));
      ctx.fillStyle=z.text;ctx.font=font(10,"600");ctx.textAlign="left";ctx.fillText(z.label,Math.max(x,0)+6*hr,Math.min(y1,y2)+Math.min(9*vr,Math.abs(y2-y1)/2))});
    const right=[];
    // Niveaux du plan : trait fin de l'origine au bord droit, étiquette en bout de trait.
    items.levels.forEach(l=>{const x=X(l.from),y=Y(l.price);if(x==null||y==null)return;ctx.strokeStyle=l.color;ctx.lineWidth=hr;ctx.setLineDash([]);
      ctx.globalAlpha=.75;ctx.beginPath();ctx.moveTo(x,Math.round(y)+.5);ctx.lineTo(sz.width,Math.round(y)+.5);ctx.stroke();ctx.globalAlpha=1;right.push({y:y-7*vr,t:l.label,c:l.color})});
    // Étiquettes du bord droit écartées pour ne pas se chevaucher.
    right.sort((a,b)=>a.y-b.y);let ly=-1e9;ctx.font=font(9.5);ctx.textAlign="right";right.forEach(r=>{const y=Math.max(r.y,ly+11*vr);ly=y;ctx.fillStyle=r.c;ctx.fillText(r.t,sz.width-6*hr,y)});
    ctx.restore()});
  const view={zOrder:()=>"bottom",renderer:()=>({draw:()=>{},drawBackground:draw})};
  return {attached:p=>{chart=p.chart;series=p.series},detached:()=>{chart=series=null},updateAllViews:()=>{},paneViews:()=>[view]};
}
function candleChart(c,tf,wid){
  const all=((CANDLES||{}).tf||{})[tf]||[];if(all.length<10||!window.LightweightCharts)return null;
  if(CUR_CHART){CUR_CHART.remove();CUR_CHART=null}
  const LW=window.LightweightCharts,small=innerWidth<600,p=c.prediction||{};
  const bull="#089981",bear="#F23645",muted=cssVar("--muted","#5A6676"),line=cssVar("--line","#D3D9E1"),bg=cssVar("--surface","#FFFFFF"),font=cssVar("--sans","sans-serif");
  const box=el("div",{class:"lwc",style:"height:"+(small?440:420)+"px"}),info=el("p",{class:"hint cinfo"});
  const chart=LW.createChart(box,{autoSize:true,layout:{background:{type:"solid",color:bg},textColor:muted,fontFamily:font,fontSize:11},
    grid:{vertLines:{visible:false},horzLines:{visible:false}},rightPriceScale:{borderVisible:false,scaleMargins:{top:.08,bottom:.08}},
    timeScale:{borderVisible:false,timeVisible:tf!=="D1",secondsVisible:false,rightOffset:12},crosshair:{mode:LW.CrosshairMode.Normal},handleScroll:{vertTouchDrag:false},
    localization:{locale:"fr-FR",priceFormatter:v=>fmt(v,2)}});
  CUR_CHART=chart;
  const ser=chart.addCandlestickSeries({upColor:bull,downColor:bear,borderVisible:false,wickUpColor:bull,wickDownColor:bear,priceLineStyle:LW.LineStyle.Dotted});
  const data=all.map(b=>({time:b[0],open:b[1],high:b[2],low:b[3],close:b[4]}));ser.setData(data);
  const zones=(c.zones||[]).map(z=>({low:num(z.low),high:num(z.high),z})).filter(o=>Number.isFinite(o.low)&&Number.isFinite(o.high));
  const levels=(c.levels||[]).map(l=>({price:num(l.price),l})).filter(o=>Number.isFinite(o.price));
  const inv=num(p.invalidation);
  // Échelle automatique : bougies visibles, plus les zones et niveaux proches.
  ser.applyOptions({autoscaleInfoProvider:orig=>{const r=orig();if(!r||!r.priceRange)return r;let {minValue:lo,maxValue:hi}=r.priceRange;const span=hi-lo,near=v=>v>lo-span*.5&&v<hi+span*.5;
    zones.forEach(o=>{if(near(o.low)||near(o.high)){lo=Math.min(lo,o.low);hi=Math.max(hi,o.high)}});levels.forEach(o=>{if(near(o.price)){lo=Math.min(lo,o.price);hi=Math.max(hi,o.price)}});
    if(Number.isFinite(inv)&&near(inv)){lo=Math.min(lo,inv);hi=Math.max(hi,inv)}return {priceRange:{minValue:lo,maxValue:hi},margins:r.margins}}});
  // Zones de Claude : bleu pour l'achat, rouge pour la vente.
  const items={font,bull,bear,
    zones:zones.map(({low,high,z})=>{const buy=z.side!=="vente",dead=z.state==="cassee",mine=tfNorm(z.tf)===tf;
      return {low,high,from:originIndex(all,z.origin,[low,high]),fill:dead?rgba(muted,.08):buy?(mine?"rgba(49,121,245,.30)":"rgba(49,121,245,.14)"):(mine?"rgba(247,124,128,.34)":"rgba(247,124,128,.16)"),
        text:dead?muted:buy?"#3179f5":"#e5484d",label:(z.type||"Zone")+" "+(z.tf||"")+(dead?" cassée":"")}}),
    levels:levels.map(({price,l})=>({price,from:originIndex(all,l.origin,[price]),color:/H$|haut|high/i.test(l.type||"")?bear:/L$|bas|low/i.test(l.type||"")?bull:muted,label:(l.type||"Niveau")}))};
  ser.attachPrimitive(planPrimitive(all,items));
  // Bornes des zones de l'unité de temps affichée et invalidation sur l'échelle de prix, comme TradingView.
  items.zones.forEach((z,i)=>{if(tfNorm(zones[i].z.tf)!==tf)return;[z.high,z.low].forEach(v=>ser.createPriceLine({price:v,color:"#787b86",lineVisible:false,axisLabelVisible:true,title:""}))});
  if(Number.isFinite(inv))ser.createPriceLine({price:inv,color:"#9c27b0",lineWidth:1,lineStyle:LW.LineStyle.Solid,axisLabelVisible:true,title:"Invalidation"});
  const n=Math.round(TF_BARS[tf]*(small?.7:1)),fit=()=>chart.timeScale().setVisibleLogicalRange({from:data.length-n,to:data.length+8});
  // Le graphique n’a sa largeur qu’une fois affiché : on cadre au premier redimensionnement.
  const ro=new ResizeObserver(()=>{if(box.clientWidth){fit();ro.disconnect()}});ro.observe(box);
  const show=b=>{if(!b)return;const d=new Date(b.time*1000);info.textContent=d.toLocaleString("fr-FR",{weekday:"short",day:"numeric",month:"short",hour:tf==="D1"?undefined:"2-digit",minute:tf==="D1"?undefined:"2-digit",timeZone:"UTC"})+" · O "+fmt(b.open,2)+" · H "+fmt(b.high,2)+" · B "+fmt(b.low,2)+" · C "+fmt(b.close,2)};
  show(data[data.length-1]);chart.subscribeCrosshairMove(e=>show(e&&e.seriesData&&e.seriesData.get(ser)||data[data.length-1]));
  return el("div",null,box,info);
}
// Lecture du plan pour l'unité de temps affichée : biais, invalidation et zones de cette unité.
function chartAnalysis(c,tf){
  const p=c.prediction||{},out=el("div",{class:"canal"});
  const probs=[["Haussier",p.bull],["Baissier",p.bear],["Range",p.range]].filter(x=>x[1]>0).sort((a,b)=>b[1]-a[1]);
  const head=[probs.length?"Biais "+probs[0][0].toLowerCase()+" "+probs[0][1]+" %":"",Number.isFinite(num(p.invalidation))?"invalidation "+px(p.invalidation):""].filter(Boolean).join(" · ");
  if(head)out.append(el("p",{class:"canal-head",text:head}));
  const zs=(c.zones||[]).filter(z=>tfNorm(z.tf)===tf).sort((a,b)=>num(b.high)-num(a.high));
  if(!zs.length){out.append(el("p",{class:"hint",text:"Aucune zone du plan en "+tf+"."}));return out}
  out.append(el("ul",{class:"canal-z"},...zs.map(z=>{const buy=z.side!=="vente",st={testee:"testée",cassee:"cassée"}[z.state];
    return el("li",{class:buy?"buy":"sell"},el("b",{text:(buy?"Achat":"Vente")+" · "+(z.type||"Zone")+" "+(z.tf||"")+" · "+px(z.low)+" – "+px(z.high)+(st?" ("+st+")":"")}),z.note?el("span",{text:z.note}):null)})));
  return out;
}
function chartCard(c,wid){
  const box=el("div",{class:"cbox"}),bar=el("div",{class:"tfbar",role:"group","aria-label":"Unité de temps"}),ana=el("div");
  const tfs=["H1","H4","D1"].filter(t=>(((CANDLES||{}).tf||{})[t]||[]).length>=10);if(!tfs.length||!window.LightweightCharts)return null;
  if(!tfs.includes(CHART_TF))CHART_TF=tfs.includes("H4")?"H4":tfs[0];
  const counts=t=>(c.zones||[]).filter(z=>tfNorm(z.tf)===t).length;
  const draw=()=>{bar.querySelectorAll("button").forEach(b=>b.setAttribute("aria-pressed",String(b.dataset.tf===CHART_TF)));box.replaceChildren(candleChart(c,CHART_TF,wid)||el("div",{class:"empty",text:"Pas assez de bougies."}));ana.replaceChildren(chartAnalysis(c,CHART_TF))};
  tfs.forEach(t=>{const b=el("button",{type:"button",class:"tf","data-tf":t},el("b",{text:t}),counts(t)?el("span",{text:counts(t)+" zone"+(counts(t)>1?"s":"")}):null);b.addEventListener("click",()=>{CHART_TF=t;draw()});bar.append(b)});
  draw();
  return card("Graphique des niveaux",bar,box,ana);
}
let VIEW_INITIAL=false;
const zoneKey=z=>Number(z.low).toFixed(2)+"-"+Number(z.high).toFixed(2)+"-"+(z.side==="vente"?"s":"b");
function liveState(p,z){const l=((p.content||{}).live||{}).zones||{};return (l[zoneKey(z)]||{}).state||z.state||"vierge"}
// Rejeu de la journée par Claude : opportunités manquées par l'EA et signaux à éviter.
function backtestView(bt){
  const line=x=>(x.time||"")+" · "+(x.side==="vente"?"vente":"achat")+(x.setup?" · S"+x.setup:"")+" · "+px(x.entry)+" / "+px(x.sl)+" / "+px(x.tp)+(x.r!=null?" → "+(x.r>0?"+":"")+fmt(x.r,1)+"R":"");
  const lst=(arr,cls,h)=>(arr||[]).length?el("div",null,el("p",null,el("span",{class:"k",text:h})),el("ul",{class:"rules"},...arr.map(x=>el("li",{class:cls},el("b",{text:line(x)}),x.why?el("span",{text:" — "+x.why}):null)))):null;
  return el("div",{class:"bt"},el("p",null,el("b",{text:"Rejeu de la journée : "}),document.createTextNode(bt.summary||"")),
    bt.ea_check?el("p",{class:"k",text:"Contrôle de l’EA : "+bt.ea_check}):null,
    lst(bt.missed,"buy","Opportunités manquées"),lst(bt.wrong,"sell","Signaux à éviter"),
    bt.tuning?el("p",null,el("span",{class:"k",text:"Réglage : "}),document.createTextNode(bt.tuning)):null);
}
// Réglages de l'EA ajustés par Claude (bornes fixes ; risque et plage de stop exclus).
async function eaParamsBody(){
  let d;try{d=await api("ea-params")}catch(e){return null}
  const rows=Object.keys(d.params).map(k=>{const p=d.params[k],v=d.values[k];return [p.label,fmt(v,k==="fvg"||k==="slbuf"?2:0)+(v!==p.default?" (défaut "+fmt(p.default,k==="fvg"||k==="slbuf"?2:0)+")":""),fmt(p.min,k==="fvg"||k==="slbuf"?2:0)+" – "+fmt(p.max,k==="fvg"||k==="slbuf"?2:0)]});
  return el("div",{style:"margin-top:10px"},table(["Réglage","Valeur","Bornes"],rows),
    (d.history||[]).length?el("div",null,el("h4",{class:"sub",text:"Ajustements de Claude"}),table(["Date","Réglage","Changement","Raison"],d.history.slice(0,10).map(h=>[new Date(h.at.replace(" ","T")).toLocaleDateString("fr-FR",{day:"numeric",month:"short"}),(d.params[h.key]||{}).label||h.key,fmt(h.from,2)+" → "+fmt(h.to,2)+(h.revert?" (retour)":""),h.reason]))):el("p",{class:"hint",text:"Aucun ajustement pour l’instant : Claude en propose au plus un par jour, après le rejeu de 23:20."}));
}
// Fiche d'exécution, la même partout (Aujourd'hui, Plan, Telegram) : attendre → déclencheur → ordre → lot → annulé si.
// Le lot est celui du compte actif, avec son risque du jour (onglet Aujourd'hui).
function entryOf(v){
  if(typeof v==="number")return {v,txt:px(v),range:false};
  const ns=(String(v||"").replace(/[\s  ]/g,"").replace(/,/g,".").match(/\d+(?:\.\d+)?/g)||[]).map(Number).filter(x=>x>100);
  if(!ns.length)return {v:NaN,txt:String(v||"—"),range:false};
  const lo=Math.min(...ns),hi=Math.max(...ns);
  return {v:(lo+hi)/2,txt:String(v),range:hi-lo>0.001};
}
function execCard(x,i){
  const sell=x.side==="vente", en=entryOf(x.entry), sl=num(x.sl), tp=num(x.tp), ap=en.range?"≈ ":"";
  const stop=Number.isFinite(en.v)&&Number.isFinite(sl)?Math.abs(en.v-sl):NaN, rr=stop>0&&Number.isFinite(tp)?Math.abs(tp-en.v)/stop:NaN;
  const step=(k,v,cls)=>el("li",cls?{class:cls}:null,el("span",{class:"k",text:k}),el("span",{text:v}));
  const head=el("div",{class:"xc-head"},el("span",{class:"xc-side",text:(i!=null?(i+1)+". ":"")+(sell?"Vente":"Achat")}),
    x.setup?el("span",{class:"tag",text:"setup "+x.setup}):null,Number.isFinite(num(x.prob))?el("span",{class:"tag",text:x.prob+" %"}):null);
  const ol=el("ol",{class:"xc-steps"});
  if(x.wait)ol.append(step("Attendre",x.wait));
  if(x.trigger)ol.append(step("Déclencheur",x.trigger));
  ol.append(step("Ordre",(sell?"Vente ":"Achat ")+(en.range?"dans ":"")+en.txt+(Number.isFinite(sl)?" · stop "+px(sl)+(Number.isFinite(stop)&&!en.range?" ("+fmt(stop,2)+" $)":""):"")+(Number.isFinite(tp)?" · objectif "+px(tp)+(Number.isFinite(rr)?" ("+ap+fmt(rr,1)+"R)":""):""),"order"));
  const p=ACTIVE?ACTIVE.params:null;
  // Entrée en fourchette (scénario de la semaine) : l'ordre exact se décide au déclenchement.
  if(p&&en.range)ol.append(step("Lot","Au déclenchement : stop derrière la mèche (1,50 – 8,00 $), "+(DAY_RISK>0?money2(DAY_RISK)+" de risque ; le Calculateur XAU donne le lot.":"mais une limite du jour est atteinte : pas de trade."),"lot"));
  else if(p&&stop>0){
    let t;
    if(!(DAY_RISK>0))t="Pas de trade : une limite du jour est atteinte.";
    else{const lot=Math.floor(DAY_RISK/lotCost(stop,p)*100+1e-9)/100;t=lot>=0.01?ap+fmt(lot,2)+" lot pour "+money2(DAY_RISK)+" de risque ("+ACTIVE.name+")":"Stop trop large pour le risque du jour : passe ton tour."}
    if(stop<1.5||stop>8)t+=" Écart hors de 1,50 – 8,00 $ : ordre limite au prix où le stop revient à 8,00 $, sinon passer.";
    ol.append(step("Lot",t,"lot"));
  }
  if(x.cancel)ol.append(step("Annulé si",x.cancel,"ko"));
  return el("div",{class:"xcard "+(sell?"sell":"buy")},head,x.label?el("p",{class:"xc-label",text:x.label}):null,ol);
}
const posCard=q=>({side:q.side,setup:q.setup,label:q.label,wait:q.wait,trigger:q.trigger,entry:num(q.entry),sl:q.sl,tp:q.tp,cancel:q.cancel});
const scnCard=s=>({side:s.side,setup:s.setup,prob:s.prob,label:s.then,wait:s.if?"Si "+s.if:"",trigger:s.trigger,entry:s.entry,sl:s.sl,tp:s.tp,cancel:s.invalidation});
const SES_K={asia:"Session Asie",london:"Plan de Londres",premarket:"Pré-marché US (Asie et Londres)",us:"Plan de New York"},SES_S={asia:"Asie",london:"Londres",premarket:"pré-marché",us:"New York"},SES_O={asia:0,london:1,premarket:2,us:3},SES_B={h:"haussier",b:"baissier",n:"neutre"};
// Une analyse de séance : résumé, fiches à exécuter, consignes, ce qui annule ; le « pourquoi » est replié.
function sessionBlock(x){
  const b=el("div",{class:"sess "+x.kind},el("div",{class:"row"},el("b",{text:SES_K[x.kind]||x.kind}),x.bias?el("span",{class:"bias "+x.bias,text:"biais "+SES_B[x.bias]}):null,x.at?el("span",{class:"k",text:"publiée à "+x.at.slice(11,16)}):null));
  if(x.summary)b.append(el("p",{text:x.summary}));
  const pos=(x.positions||[]).filter(q=>Number.isFinite(num(q.entry)));
  b.append(pos.length?el("div",{class:"xcards"},...pos.map((q,i)=>execCard(posCard(q),i))):el("p",{class:"hint",text:"Aucun scénario exécutable : on ne force pas de trade."}));
  if((x.execution||[]).length)b.append(el("div",{class:"xc-rules"},el("span",{class:"k",text:"Consignes"}),el("ul",{class:"rules"},...x.execution.map(t=>el("li",{text:t})))));
  if(x.invalid)b.append(el("p",{class:"xc-inv"},el("b",{text:"Annule le plan : "}),document.createTextNode(x.invalid)));
  if(x.idea)b.append(el("p",{class:"xc-idea"},el("b",{text:"Idée de Claude : "}),document.createTextNode(x.idea)));
  if(x.chart)b.append(el("a",{href:x.chart,target:"_blank",rel:"noopener",class:"shot"},el("img",{src:x.chart,alt:"Graphique de la séance : zones et scénarios",loading:"lazy"})));
  const why=[["facts","Faits"],["liquidity","Liquidité"]].filter(([k])=>(x[k]||[]).length);
  if(why.length||(x.zones||[]).length){
    const d=el("details",{class:"why"},el("summary",{text:"Pourquoi : faits, liquidité et zones"}));
    why.forEach(([k,h])=>d.append(el("p",null,el("span",{class:"k",text:h})),el("ul",{class:"rules"},...x[k].map(t=>el("li",{text:t})))));
    if((x.zones||[]).length)d.append(el("p",null,el("span",{class:"k",text:"Zones"})),el("ul",{class:"rules"},...x.zones.map(z=>el("li",{class:z.side==="vente"?"sell":"buy",text:(z.type||"Zone")+" "+px(z.low)+" – "+px(z.high)+(z.note?" : "+z.note:"")}))));
    b.append(d);
  }
  const sc=scoreLine(x.score);if(sc)b.append(sc);
  return b;
}
// Note d'une analyse par le carnet : biais annoncé contre mouvement réel jusqu'à la fin de la séance visée.
function scoreLine(sc){
  if(!sc)return null;
  const BI={h:"haussier",b:"baissier",n:"neutre"};
  if(sc.ok==null)return el("p",{class:"score na",text:"Non notée : "+(sc.why||"données manquantes")+"."});
  return el("p",{class:"score "+(sc.ok?"ok":"ko")},el("b",{text:sc.ok?"✔ Biais juste":"✘ Biais faux"}),
    document.createTextNode(" — jusqu’à "+sc.until+" : "+(sc.move>0?"+":"")+fmt(sc.move,2)+" $ ("+BI[sc.real]+", seuil ± "+fmt(sc.thr,2)+" $), de "+px(sc.from)+" à "+px(sc.to)+"."));
}
// Fiabilité de Claude : part des biais justes par séance, sur 12 semaines.
async function scoreCard(){
  let d;try{d=await api("claude-score")}catch(e){return null}
  const K={asia:"Session Asie",london:"Plan de Londres",premarket:"Pré-marché US",us:"Plan de New York"},rows=[];let n=0,ok=0;
  for(const k of ["asia","london","premarket","us"]){const v=(d.kinds||{})[k];if(!v)continue;n+=v.n;ok+=v.ok;rows.push([K[k],String(v.n),String(v.ok),Math.round(100*v.ok/v.n)+" %"])}
  if(n)rows.push([el("b",{text:"Total"}),String(n),String(ok),el("b",{text:Math.round(100*ok/n)+" %"})]);
  const pc=v=>v==null?"—":v+" %",ca=(d.cards||{}).all||{},zo=(d.zones||{}).all||{},sc=d.scenarios||{};
  const cardRow=(name,x)=>[name,String(x.published||0),String(x.triggered||0),String(x.tp||0),String(x.sl||0),x.triggered?(x.sum_r>0?"+":"")+fmt(x.sum_r,1)+"R":"—"];
  const cr=[];for(const k of ["london","us"]){const x=((d.cards||{}).kinds||{})[k];if(x)cr.push(cardRow(K[k],x))}
  if(sc.published)cr.push(cardRow("Scénarios de la semaine",sc));
  const zr=Object.entries((d.zones||{}).kinds||{}).filter(([,x])=>x.n).map(([k,x])=>[k,String(x.n),String(x.reached),String(x.reacted),String(x.broken),pc(x.react_pc)]);
  const les=(d.lessons||[]);
  return card("Fiabilité de Claude",
    el("p",{class:"hint",text:"Tout est mesuré par le carnet sur les bougies du courtier, sans avis de Claude. Claude lit ces chiffres avant chaque plan."}),
    el("h4",{class:"sub",text:"Fiches et scénarios rejoués (sans gestion : objectif ou stop)"}),
    cr.length?table(["Plan","Publiées","Déclenchées","Objectif","Stop","Total"],cr):el("p",{class:"hint",text:"Pas encore de fiche rejouée : chaque fiche l’est après sa séance."}),
    el("h4",{class:"sub",text:"Zones données à l’EA"+(zo.n?" : "+zo.reached+" atteintes sur "+zo.n+", "+pc(zo.react_pc)+" ont donné 2R avant le stop":"")}),
    zr.length?table(["Zone","Données","Atteintes","2R","Cassées","Réussite"],zr):el("p",{class:"hint",text:"Pas encore de zone rejouée."}),
    el("h4",{class:"sub",text:"Leçons apprises (8 cas au moins)"}),
    les.length?el("ul",null,...les.map(x=>el("li",{class:x.verdict==="eviter"?"ko":"ok",text:x.text}))):el("p",{class:"hint",text:"Pas encore assez de cas : une leçon apparaît dès 8 fiches ou 8 zones de la même famille."}),
    el("h4",{class:"sub",text:"Biais des analyses de séance"}),
    n?table(["Analyse","Notées","Biais justes","Réussite"],rows):el("p",{class:"hint",text:"Pas encore d’analyse notée : chaque plan est noté par le carnet après sa séance (Londres à "+tzAt("America/New_York",8,15)+", New York à "+tzAt("America/New_York",16,0)+", heure de Lubumbashi)."}),
    el("p",{class:"hint",style:"margin-top:8px",text:"Biais : mouvement entre la publication et la fin de la séance visée. Au-delà de ± "+fmt(100*d.neutral,0)+" % de l’ATR journalier il est haussier ou baissier, sinon neutre. Zone : entrée au bord touché en premier, stop 1 $ derrière l’autre bord ; elle « réagit » si le prix fait 2R avant le stop."}));
}
function renderPlanView(p){
  const box=$("#plan-view");
  if(!p){box.replaceChildren(el("div",{class:"empty",text:"Le plan de cette semaine n’a pas encore été publié par Claude."}));
    const g=box._g=(box._g||0)+1;scoreCard().then(sc=>{if(box._g===g&&sc)box.append(sc)});return}
  let c=p.content;
  if(!c){ // plan d'origine (avant le plan élargi)
    const lv=(p.levels||[]).filter(x=>x.price);
    box.replaceChildren(card("Plan (ancien format)",el("div",null,"Biais : ",el("span",{class:"bias "+(p.bias||"n"),text:BIAS_L[p.bias]||"Non défini"})),
      p.struct?el("p",{class:"pre",text:p.struct}):null,lv.length?table(["Prix","Type","Note"],lv.map(l=>[px(l.price),l.type,l.note])):null,
      (p.sc||[]).length?el("ul",null,...p.sc.map(s=>el("li",{text:s.text}))):null,p.review?el("p",{class:"pre",text:"Bilan : "+p.review}):null));return}
  const updates=c.updates||[], lastUpd=updates.filter(u=>u.status==="mis_a_jour").pop();
  if(VIEW_INITIAL&&c.initial)c=Object.assign({},c,c.initial);
  c=Object.assign({},c,{zones:(c.zones||[]).map(z=>Object.assign({},z,{state:liveState(p,z)}))});
  const pr=c.prediction||{}, refs=c.refs||{}, out=[];
  if(lastUpd&&p.content.initial){
    const d=new Date(lastUpd.date+"T12:00:00").toLocaleDateString("fr-FR",{weekday:"long",day:"numeric",month:"long"});
    const tg=el("button",{type:"button",class:"btn ghost sm",text:VIEW_INITIAL?"Voir la version actuelle":"Voir la version du dimanche"});
    tg.addEventListener("click",()=>{VIEW_INITIAL=!VIEW_INITIAL;renderPlanView(p)});
    out.push(el("div",{class:"alert "+(VIEW_INITIAL?"warn":"go"),style:"display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:12px"},
      el("span",{style:"flex:1;min-width:200px",text:VIEW_INITIAL?"Version publiée le dimanche (avant les mises à jour).":"Plan mis à jour "+d+(lastUpd.reason?" : "+lastUpd.reason:".")}),tg));
  }
  const live=p.content.live||{};
  if(live.invalidated)out.push(el("div",{class:"alert stop",style:"margin-bottom:12px",text:"Invalidation touchée (EA MT5) le "+new Date(live.invalidated.at.replace(" ","T")).toLocaleString("fr-FR",{weekday:"long",hour:"2-digit",minute:"2-digit"})+(live.invalidated.price!=null?" à "+px(live.invalidated.price):"")+" : plus de signal dans le sens du biais jusqu’à la prochaine relecture de Claude."}));
  // 1. La semaine en bref : biais, résumé, invalidation, range, et ce que la journée demande.
  const hasPred=[pr.bull,pr.bear,pr.range].some(x=>x>0)||pr.summary;
  if(hasPred){
    const h=card("La semaine en bref",probBar(pr));
    if(pr.summary)h.append(el("p",{class:"lead",text:pr.summary}));
    const kp=el("div",{class:"kpis",style:"margin:12px 0 0"});
    if(pr.invalidation!=null)kp.append(kv("Invalidation",px(pr.invalidation)));
    if(pr.range_low!=null||pr.range_high!=null)kp.append(kv("Range attendu",px(pr.range_low)+" – "+px(pr.range_high)));
    if(refs.atr_d!=null)kp.append(kv("ATR journalier",px(refs.atr_d)+" $"));
    if(kp.childNodes.length)h.append(kp);
    const day=(c.days||[]).find(d=>d.day===todayFr());
    if(day&&day.focus)h.append(el("p",{class:"today"},el("b",{text:"Aujourd’hui"+(day.bias?" ("+(BIAS_L[day.bias]||"").toLowerCase()+")":"")+" : "}),document.createTextNode(day.focus)));
    if(p.publishedAt)h.append(el("p",{class:"hint byline",text:"Rédigé par Claude le "+new Date(p.publishedAt.replace(" ","T")).toLocaleDateString("fr-FR",{weekday:"long",day:"numeric",month:"long"})+" à "+p.publishedAt.slice(11,16)+"."}));
    out.push(h);
  }
  // 2. Graphique de Claude (le même que sur Telegram)
  if(p.content.chart)out.push(el("a",{href:p.content.chart,target:"_blank",rel:"noopener",class:"shot",style:"margin-bottom:14px"},el("img",{src:p.content.chart,alt:"Graphique du plan de la semaine",loading:"lazy"})));
  // 3. Scénarios : une fiche d'exécution chacun
  if((c.scenarios||[]).length)out.push(card("Scénarios de la semaine",el("div",{class:"xcards"},...c.scenarios.map((x,i)=>execCard(scnCard(x),i)))));
  // 4. Séances de la semaine, la plus récente ouverte
  const ses=p.content.sessions||[], dates=[...new Set(ses.map(x=>x.date))].sort().reverse();
  if(dates.length)out.push(card("Séances de la semaine",...dates.map((d,n)=>{const l=ses.filter(x=>x.date===d).sort((a,b)=>SES_O[b.kind]-SES_O[a.kind]);
    const det=el("details",{class:"sday"},el("summary",{text:new Date(d+"T12:00:00").toLocaleDateString("fr-FR",{weekday:"long",day:"numeric",month:"long"})+" · "+l.map(x=>SES_S[x.kind]||x.kind).join(", ")}),...l.map(sessionBlock));
    if(!n)det.open=true;return det})));
  // 5. Zones et niveaux, du plus haut au plus bas
  const zl=[...(c.zones||[]).map(z=>({v:num(z.high),r:[px(z.low)+" – "+px(z.high),(z.type||"Zone")+" "+(z.tf||""),el("span",{class:"bias "+(z.side==="vente"?"b":"h"),text:z.side==="vente"?"Vente":"Achat"}),{testee:"Testée",cassee:"Cassée"}[z.state]||"Vierge",z.note]})),
    ...(c.levels||[]).map(l=>({v:num(l.price),r:[px(l.price),l.type||"Niveau",el("span",{class:"bias n",text:"Liquidité"}),"",l.note]}))].sort((a,b)=>b.v-a.v);
  if(zl.length)out.push(card("Zones et niveaux",table(["Prix","Zone ou niveau","Rôle","État","Note"],zl.map(x=>x.r))));
  // 6. Règles de la semaine
  if((c.rules||[]).length)out.push(card("Règles de la semaine",el("ul",{class:"rules"},...c.rules.map(r=>el("li",{text:r})))));
  // 12. Bilan et verdict
  const rv=c.review;
  if(rv&&(rv.verdict||rv.text||rv.score!=null)){
    const yn=v=>v==null?"—":v?"Oui":"Non";
    const bl=card("Bilan de fin de semaine");
    const k=el("div",{class:"kpis"});
    if(rv.verdict)k.append(el("div",{class:"kpi verdict-"+rv.verdict},el("div",{class:"v",text:{bonne:"Bonne",partielle:"Partielle",mauvaise:"Mauvaise"}[rv.verdict]}),el("div",{class:"k",text:"Verdict de l’analyse"})));
    if(rv.score!=null)k.append(kv("Note",fmt(rv.score,1).replace(",0","")+" / 10"));
    if(rv.bias_ok!=null)k.append(kv("Biais juste",yn(rv.bias_ok)));
    if(rv.range_held!=null)k.append(kv("Range respecté",yn(rv.range_held)));
    if(rv.invalidation_hit!=null)k.append(kv("Invalidation touchée",yn(rv.invalidation_hit)));
    bl.append(k);
    if((rv.zones||[]).length)bl.append(el("h4",{class:"sub",text:"Zones"}),table(["Zone","Atteinte","A réagi"],rv.zones.map(z=>[z.label,yn(z.reached),yn(z.reacted)])));
    if((rv.scenarios||[]).length)bl.append(el("h4",{class:"sub",text:"Scénarios"}),table(["Scénario","Déclenché","Résultat"],rv.scenarios.map(s=>[s.label,yn(s.triggered),s.result])));
    const wf=el("div",{class:"grid g2",style:"margin-top:10px"});
    if((rv.worked||[]).length)wf.append(el("div",{class:"scn ok"},el("div",{class:"k",text:"Ce qui a marché"}),el("ul",{class:"rules"},...rv.worked.map(x=>el("li",{text:x})))));
    if((rv.failed||[]).length)wf.append(el("div",{class:"scn ko"},el("div",{class:"k",text:"Ce qui n’a pas marché"}),el("ul",{class:"rules"},...rv.failed.map(x=>el("li",{text:x})))));
    if(wf.childNodes.length)bl.append(wf);
    if(rv.lesson)bl.append(el("p",{class:"lead"},el("b",{text:"Leçon : "}),document.createTextNode(rv.lesson)));
    if(rv.text)bl.append(el("p",{class:"pre",text:rv.text}));
    out.push(bl);
  }
  // 7. Job list de Claude, à la fin, en lignes courtes.
  const jobs=jobList(c);if(jobs)out.push(card("Ce que fait Claude",jobs));
  // 8. Le reste, replié : utile pour comprendre, pas pour exécuter.
  const det=el("details",{class:"panel"},el("summary",{text:"Détails de l’analyse : structure, fondamentaux, annonces, relectures"}));
  const sub=(t,...k)=>det.append(el("h4",{class:"sub",text:t}),...k);
  if(pr.main||pr.alt)sub("Scénario principal et alternatif",...[pr.main?el("p",null,el("b",{text:"Principal : "}),document.createTextNode(pr.main)):null,pr.alt?el("p",null,el("b",{text:"Alternatif : "}),document.createTextNode(pr.alt)):null].filter(Boolean));
  const rk=[["Prix de référence",refs.price],["Ouverture hebdo",refs.week_open],["Haut semaine passée",refs.prev_week_high],["Bas semaine passée",refs.prev_week_low],["Haut mois passé",refs.prev_month_high],["Bas mois passé",refs.prev_month_low],["ATR hebdo",refs.atr_w]].filter(x=>x[1]!=null);
  if(rk.length)sub("Repères de prix",el("div",{class:"kpis"},...rk.map(([k,v])=>kv(k,px(v)))));
  if((c.structure||[]).length)sub("Structure par unité de temps",table(["UT","Tendance","Dernier BoS / ChoCh","Zone","Note"],c.structure.map(x=>[x.tf,el("span",{class:"bias "+({h:"h",b:"b",r:"n"}[x.trend]||"n"),text:TREND_L[x.trend]||"—"}),x.last,PD_L[x.pd]||"",x.note])));
  if((c.fundamentals||[]).length||c.fund_notes){sub("Fondamentaux");if((c.fundamentals||[]).length)det.append(el("div",{class:"kpis"},...c.fundamentals.map(x=>el("div",{class:"kpi"},el("div",{class:"k",text:x.label||FUND_L[x.key]||x.key}),el("div",{class:"eff "+(x.effect||""),text:EFFECT_L[x.effect]||"—"}),x.value?el("div",{class:"k",text:x.value}):null))));if(c.fund_notes)det.append(el("p",{class:"pre",text:c.fund_notes}))}
  if((c.calendar||[]).length)sub("Annonces de la semaine (pas de trade 15 min avant, 30 min après)",table(["Jour","Heure","Annonce","Impact","Prévision","Précédent"],c.calendar.map(e=>[e.day,e.time,e.event,el("span",{class:"imp "+(e.impact||""),text:{high:"Fort",medium:"Moyen",low:"Faible"}[e.impact]||"—"}),e.forecast,e.previous])));
  if((c.days||[]).length)sub("Plan jour par jour",el("ul",{class:"rules"},...c.days.map(d=>el("li",{class:d.day===todayFr()?"today":""},el("b",{text:d.day.charAt(0).toUpperCase()+d.day.slice(1)+(d.bias?" ("+(BIAS_L[d.bias]||"").toLowerCase()+")":"")+" : "}),document.createTextNode((d.session?d.session+" — ":"")+(d.focus||""))))));
  const chart=chartCard(c,p.week);if(chart){chart.classList.remove("panel");sub("Graphique interactif",chart)}
  if(updates.length)sub("Relectures du soir et rejeu de l’EA",el("div",{class:"trades"},...updates.slice().reverse().map(u=>el("div",{class:"upd "+(u.status==="mis_a_jour"?"chg":"keep")},
    el("div",{class:"row"},el("b",{text:new Date(u.date+"T12:00:00").toLocaleDateString("fr-FR",{weekday:"long",day:"numeric",month:"short"})}),el("span",{class:"spacer"}),el("span",{class:"tag "+(u.status==="mis_a_jour"?"bad":""),text:u.status==="mis_a_jour"?"Mis à jour":"Maintenu"})),
    u.reason?el("p",{class:"k",text:u.reason}):null,u.summary?el("p",{text:u.summary}):null,
    (u.changes||[]).length?el("ul",{class:"rules"},...u.changes.map(x=>el("li",{text:x}))):null,
    u.trades?el("p",null,el("b",{text:"Vos trades : "}),document.createTextNode(u.trades)):null,
    u.tomorrow?el("p",null,el("b",{text:"Lendemain : "}),document.createTextNode(u.tomorrow)):null,
    u.backtest?backtestView(u.backtest):null))));
  if(c.sources)det.append(el("p",{class:"hint",style:"margin-top:10px",text:"Sources : "+c.sources}));
  out.push(det);
  box.replaceChildren(...out);
  const gen=box._g=(box._g||0)+1;
  scoreCard().then(sc=>{if(box._g!==gen||!sc)return;box.prepend(sc)});
}
/* ---------- aujourd'hui : ce qu'il faut exécuter maintenant ---------- */
function renderToday(){
  const box=$("#execnow");if(!box)return;
  const p=THIS_WEEK_PLAN,c=p&&p.content,t=todayStr(),out=[];
  if(!c){box.replaceChildren(el("h3",{text:"À exécuter"}),el("p",{class:"hint",text:"Le plan de cette semaine n’a pas encore été publié par Claude."}));return}
  const live=c.live||{};
  if(live.invalidated)out.push(el("div",{class:"alert stop",style:"margin-bottom:10px",text:"Invalidation du plan de la semaine touchée"+(live.invalidated.price!=null?" à "+px(live.invalidated.price):"")+" : plus de signal dans le sens du biais jusqu’à la prochaine relecture de Claude."}));
  const ses=(c.sessions||[]).filter(x=>x.date===t).sort((a,b)=>SES_O[b.kind]-SES_O[a.kind]);
  if(ses.length){
    out.push(el("h3",{text:"À exécuter maintenant"}),sessionBlock(ses[0]));
    if(ses.length>1)out.push(el("p",{class:"hint",style:"margin-top:8px",text:"Analyses précédentes du jour : onglet Plan, Séances de la semaine."}));
  }else{
    const day=(c.days||[]).find(d=>d.day===todayFr()), sc=(c.scenarios||[]).slice(0,3);
    out.push(el("h3",{text:"À exécuter : plan de la semaine"}));
    if(day&&day.focus)out.push(el("p",{class:"lead"},el("b",{text:"Aujourd’hui : "}),document.createTextNode(day.focus)));
    if(sc.length)out.push(el("div",{class:"xcards"},...sc.map((x,i)=>execCard(scnCard(x),i))));
    out.push(el("p",{class:"hint",style:"margin-top:8px",text:"Claude publie le plan de Londres vers "+tzAt("Europe/London",7,0)+" et le plan de New York vers "+tzAt("America/New_York",8,30)+" (heure de Lubumbashi), 1 h avant chaque ouverture : ils remplacent ces scénarios."}));
  }
  box.replaceChildren(...out);
}
/* ---------- journal : propositions de Claude et erreurs repérées ---------- */
const IDEA_ST={proposee:"proposée",test:"en test",appliquee:"appliquée",abandonnee:"abandonnée"};
// Job list de Claude : ce qu'il fait ou applique maintenant, en lignes courtes (fin du plan et Journal).
function jobList(c){const j=(c&&c.jobs)||[];return j.length?el("div",{class:"jobs"},el("b",{text:"Job list de Claude"}),el("ol",null,...j.map(t=>el("li",{text:t})))):null}
function renderIdeas(){
  const box=$("#ideas");if(!box)return;
  const c=(THIS_WEEK_PLAN&&THIS_WEEK_PLAN.content)||{}, ideas=c.ideas||[];
  const tips=(c.sessions||[]).filter(x=>x.idea).sort((a,b)=>(b.date+SES_O[b.kind]).localeCompare(a.date+SES_O[a.kind])).slice(0,3);
  if(!ideas.length&&!tips.length&&!(c.jobs||[]).length){box.replaceChildren(el("p",{class:"hint",text:"Claude publie ses propositions avec le plan du dimanche et les complète à chaque relecture du soir."}));return}
  const out=[];
  const jobs=jobList(c);if(jobs)out.push(jobs);
  if(ideas.length)out.push(el("div",{class:"ideas"},...ideas.map(x=>el("div",{class:"idea"},
    el("div",{class:"row"},el("b",{text:x.title||"Proposition"}),x.area?el("span",{class:"tag",text:x.area}):null,x.status?el("span",{class:"tag st-"+x.status,text:IDEA_ST[x.status]}):null),
    x.flaw?el("p",null,el("span",{class:"k",text:"Faille : "}),document.createTextNode(x.flaw)):null,
    x.solution?el("p",null,el("span",{class:"k",text:"Solution : "}),document.createTextNode(x.solution)):null,
    x.test?el("p",null,el("span",{class:"k",text:"Test : "}),document.createTextNode(x.test)):null,
    x.result?el("p",null,el("span",{class:"k",text:"Résultat : "}),document.createTextNode(x.result)):null))));
  if(tips.length)out.push(el("p",{style:"margin-top:12px"},el("span",{class:"k",text:"Idées nées des séances"})),el("ul",{class:"rules"},...tips.map(x=>el("li",null,el("b",{text:new Date(x.date+"T12:00:00").toLocaleDateString("fr-FR",{weekday:"long"})+" · "+(SES_S[x.kind]||x.kind)+" : "}),document.createTextNode(x.idea)))));
  box.replaceChildren(...out);
}
// Ce qui coûte dans le journal, en R : ce qu'il faut corriger en premier.
function renderErrors(T){
  const box=$("#errors");if(!box)return;
  const p=ACTIVE?ACTIVE.params:{}, byDay={};T.forEach(t=>{(byDay[t.date]=byDay[t.date]||[]).push(t)});
  const over=Object.values(byDay).filter(l=>l.length>(p.maxtrades||99)||l.filter(t=>num(t.r)<=-0.5).length>(p.maxloss||99));
  const rows=[["Trades hors règles",T.filter(t=>!t.rules)],["Trades hors setup",T.filter(t=>t.setup==="hors")],
    ["Émotion « pressé », « revanche » ou « fatigue »",T.filter(t=>t.emotion&&t.emotion!=="calme")],
    ["Jours au-delà des limites ("+(p.maxtrades||"?")+" trades ou "+(p.maxloss||"?")+" pertes)",over.flat()],
    ["Trades MT5 à compléter (setup, émotion, règles)",T.filter(t=>t.toComplete)]].filter(([,l])=>l.length);
  if(!T.length){box.replaceChildren(el("p",{class:"hint",text:"Pas encore de trade dans le journal."}));return}
  if(!rows.length){box.replaceChildren(el("p",{class:"hint",text:"Aucune erreur repérée sur les "+T.length+" trades : continuez ainsi."}));return}
  box.replaceChildren(el("div",{class:"errs"},...rows.map(([l,list])=>{const r=list.reduce((a,t)=>a+(num(t.r)||0),0);
    return el("div",{class:"err"},el("span",{text:l}),el("b",{class:r<0?"neg":"",text:list.length+" trade"+(list.length>1?"s":"")+" · "+(r>=0?"+":"−")+fmt(Math.abs(r),1)+"R"}))})));
}
/* ---------- calendrier économique (ForexFactory, USD fort et moyen impact) ---------- */
let CAL=null,CAL_SKEW=0;
const CAL_TZ="Africa/Lubumbashi";
// Heure de Lubumbashi (HH:MM) d’une heure locale de Londres ou de New York aujourd’hui, changements d’heure compris.
const tzAt=(tz,h,m)=>{const now=new Date(),p={};new Intl.DateTimeFormat("en-CA",{timeZone:tz,year:"numeric",month:"2-digit",day:"2-digit",hour:"2-digit",minute:"2-digit",hourCycle:"h23"}).formatToParts(now).forEach(x=>p[x.type]=+x.value);
  const off=Date.UTC(p.year,p.month-1,p.day,p.hour,p.minute)-Math.floor(now.getTime()/60000)*60000;
  return new Date(Date.UTC(p.year,p.month-1,p.day,h,m)-off).toLocaleTimeString("fr-FR",{timeZone:CAL_TZ,hour:"2-digit",minute:"2-digit"})};
const calDay=d=>new Intl.DateTimeFormat("en-CA",{timeZone:CAL_TZ,year:"numeric",month:"2-digit",day:"2-digit"}).format(d);
const calLong=t=>new Intl.DateTimeFormat("fr-FR",{timeZone:CAL_TZ,weekday:"long",day:"numeric",month:"short"}).format(new Date(t*1000));
const calIn=s=>{const m=Math.round(s/60);if(m<60)return m+" min";const h=Math.floor(m/60),r=m%60;return h+" h"+(r?" "+String(r).padStart(2,"0"):"")};
async function loadCalendar(){
  try{CAL=await api("calendar");CAL_SKEW=CAL.now*1000-Date.now()}catch(e){if(!CAL)CAL={events:[],error:errMsg(e)}}
  renderCalendar();
}
function calRow(e,now){
  const past=now>e.t+30*60, row=el("div",{class:"news-row"+(past?" past":"")+(e.impact==="high"?" high":"")});
  row.append(el("span",{class:"news-time",text:e.time}),el("span",{class:"news-imp "+e.impact,text:e.impact==="high"?"Fort":"Moyen"}));
  const txt=el("span",{class:"news-txt"},el("b",{text:e.fr||e.title}));
  if(e.fr)txt.append(el("span",{class:"news-en",text:e.title}));
  const fp=[e.forecast&&"prévu "+e.forecast,e.previous&&"précédent "+e.previous].filter(Boolean).join(" · ");
  if(fp)txt.append(el("span",{class:"news-fp",text:fp}));
  if(!past&&e.t>now)txt.append(el("span",{class:"news-in",text:"dans "+calIn(e.t-now)}));
  row.append(txt);return row;
}
function renderCalendar(){
  const box=$("#news-today");if(!box||!CAL)return;box.replaceChildren();
  const now=(Date.now()+CAL_SKEW)/1000, today=calDay(new Date(now*1000)), ev=CAL.events||[];
  const day=ev.filter(e=>e.date===today), later=ev.filter(e=>e.date>today);
  // Fenêtre sans trade : 15 min avant, 30 min après une annonce.
  const hot=day.find(e=>now>=e.t-15*60&&now<=e.t+30*60);
  if(hot){const end=new Date((hot.t+30*60)*1000).toLocaleTimeString("fr-FR",{timeZone:CAL_TZ,hour:"2-digit",minute:"2-digit"});
    box.append(el("div",{class:"alert "+(hot.impact==="high"?"stop":"warn"),style:"margin-bottom:10px",text:(hot.impact==="high"?"Annonce à fort impact à ":"Annonce à impact moyen à ")+hot.time+" — "+(hot.fr||hot.title)+" : "+(hot.impact==="high"?"pas de trade jusqu’à ":"prudence jusqu’à ")+end+"."}))}
  if(day.length){const l=el("div",{class:"news-list"});day.forEach(e=>l.append(calRow(e,now)));box.append(l)}
  else{
    box.append(el("p",{class:"news-none",text:"Aucune annonce USD importante aujourd’hui."}));
    const n=later[0];
    if(n)box.append(el("p",{class:"hint",style:"margin:4px 0 0",text:"Prochaine : "+calLong(n.t)+" à "+n.time+" — "+(n.fr||n.title)+" ("+(n.impact==="high"?"fort":"moyen")+" impact)."}));
  }
  if(later.length){
    const d=el("details",{class:"news-week"},el("summary",{text:"Reste de la semaine ("+later.length+")"}));
    let cur="";later.forEach(e=>{if(e.date!==cur){cur=e.date;d.append(el("div",{class:"news-day",text:calLong(e.t)}))}d.append(calRow(e,now))});
    box.append(d);
  }
  const ago=CAL.fetched?calIn(Math.max(60,now-CAL.fetched)):"";
  box.append(el("p",{class:"news-src",text:"Source : ForexFactory · heures de Lubumbashi"+(ago?" · mis à jour il y a "+ago:"")+(CAL.error?" · dernière lecture en échec, nouvel essai dans l’heure":"")+". Pas de trade 15 min avant ni 30 min après une annonce à fort impact."}));
}
setInterval(()=>{if(!document.hidden)renderCalendar()},60000);
setInterval(()=>{if(!document.hidden)loadCalendar()},1800000);
$("#wk-prev").addEventListener("click",()=>{PLAN_WEEK.setDate(PLAN_WEEK.getDate()-7);loadPlan()});
$("#wk-next").addEventListener("click",()=>{PLAN_WEEK.setDate(PLAN_WEEK.getDate()+7);loadPlan()});
$("#wk-today").addEventListener("click",()=>{PLAN_WEEK=planWeek();loadPlan()});
async function loadPlan(){
  $("#wk-label").textContent=weekLabel(PLAN_WEEK);
  const wid=weekId(PLAN_WEEK);
  let p=null;try{p=await api("plans/"+wid)}catch(e){toast("Lecture du plan impossible : "+errMsg(e))}
  if(wid!==weekId(PLAN_WEEK))return; // semaine changée entre-temps
  await loadCandles();if(wid!==weekId(PLAN_WEEK))return;
  CUR_PLAN=p;VIEW_INITIAL=false;renderPlanView(p);
  if(wid===weekId(planWeek())){THIS_WEEK_PLAN=p;renderToday();renderIdeas()}
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
function openTradeForm(){$("#trade-form").hidden=false;$("#trade-form").scrollIntoView({behavior:"smooth",block:"start"})}
$("#tf-open").addEventListener("click",()=>{resetTradeForm();openTradeForm()});
function resetTradeForm(){
  EDIT_ID=null;pnlTouched=false;$("#tf-title").textContent="Nouveau trade";$("#tf-cancel").hidden=false;$("#tf-cancel").textContent="Fermer";$("#tf-save").textContent="Enregistrer le trade";
  if(ACTIVE)$("#tf-account").value=String(ACTIVE.id);
  $("#tf-date").value=todayStr();$("#tf-time").value=nowTime();$("#tf-dir").value="achat";$("#tf-setup").value="1";$("#tf-level").value="EQL";$("#tf-emotion").value="";
  ["stop","lot","risk","r","pnl","link","notes"].forEach(k=>$("#tf-"+k).value="");
  ["onlevel","bias","rules"].forEach(k=>$("#tf-"+k).checked=false);
  setCapture(null);
  if(ACTIVE)$("#tf-risk").value=Number.isFinite(DAY_RISK)?DAY_RISK.toFixed(2):S.risk;
}
$("#tf-pnl").addEventListener("input",()=>{pnlTouched=true});
function autoPnl(){const r=num($("#tf-r").value),k=num($("#tf-risk").value);if(!pnlTouched&&Number.isFinite(r)&&Number.isFinite(k))$("#tf-pnl").value=(r*k).toFixed(2)}
$("#tf-r").addEventListener("input",autoPnl);$("#tf-risk").addEventListener("input",autoPnl);
$("#tf-cancel").addEventListener("click",()=>{resetTradeForm();$("#trade-form").hidden=true});
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
    toast(editing?"Trade modifié":"Trade enregistré");resetTradeForm();$("#trade-form").hidden=true;refreshAll();
  }catch(e){toast("Enregistrement impossible : "+errMsg(e))}
  btn.disabled=false;
});
$("#jf-setup").addEventListener("change",renderTrades);
$("#jf-account").addEventListener("change",renderTrades);
$("#btn-csv").addEventListener("click",async()=>{try{await download("export/trades.csv",{account:$("#jf-account").value})}catch(e){toast("Export impossible : "+errMsg(e))}});
const SETUP_L={"1":"Setup 1","2":"Setup 2","3":"Setup 3 (iFVG)","hors":"Hors setup"};
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
  EDIT_ID=t.id;$("#tf-title").textContent="Modifier le trade";$("#tf-cancel").hidden=false;$("#tf-cancel").textContent="Annuler la modification";$("#tf-save").textContent="Enregistrer les modifications";$("#trade-form").hidden=false;
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
$("#sf-account").addEventListener("change",()=>{renderStats();mcNote();loadReport()});
$("#detail-stats").addEventListener("toggle",e=>{if(e.target.open){loadReliability();loadClaudeSignals()}});
function renderStats(){
  const acc=$("#sf-account").value, T=TRADES.filter(t=>!acc||String(t.accountId)===acc);
  const a=agg(T);
  const k=[["Trades",String(a.n)],["Réussite",a.n?fmt(a.wr*100,0)+" %":"—"],["R moyen",a.n?(a.avg>=0?"+":"−")+fmt(Math.abs(a.avg),2):"—"],["Résultat",money(a.pnl)]];
  if(a.n)k.push(["Facteur de profit",a.pf===Infinity?"∞":fmt(a.pf,2)],["Total en R",(a.sum>=0?"+":"−")+fmt(Math.abs(a.sum),1)]);
  $("#kpis").replaceChildren(...k.map(([l,v])=>el("div",{class:"kpi"},el("div",{class:"v",text:v}),el("div",{class:"k",text:l}))));
  renderErrors(T);
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
  bars("#by-setup",[{label:"Setup 1",list:T.filter(t=>t.setup==="1")},{label:"Setup 2",list:T.filter(t=>t.setup==="2")},{label:"Setup 3 (iFVG)",list:T.filter(t=>t.setup==="3")},{label:"Hors setup",list:T.filter(t=>t.setup==="hors")}]);
  const lv=[...new Set(T.map(t=>t.level).filter(Boolean))];
  bars("#by-level",lv.map(l=>({label:l,list:T.filter(t=>t.level===l)})).sort((x,y)=>y.list.length-x.list.length));
  bars("#by-hour",[["asie","Asie (0 h–9 h)"],["londres","Londres (9 h–14 h)"],["ny","New York (14 h–18 h)"],["soir","Soir (18 h–24 h)"]].map(([k,l])=>({label:l,list:T.filter(t=>hourBucket(t)===k)})));
  const days=["lundi","mardi","mercredi","jeudi","vendredi","samedi","dimanche"];
  bars("#by-day",days.map((d,i)=>({label:d.charAt(0).toUpperCase()+d.slice(1),list:T.filter(t=>{if(!t.date)return false;const w=(new Date(t.date+"T00:00:00").getDay()+6)%7;return w===i})})));
  bars("#by-filter",[{label:"Sur niveau clé",list:T.filter(t=>t.onLevel)},{label:"Hors niveau",list:T.filter(t=>!t.onLevel)},{label:"Avec le biais",list:T.filter(t=>t.withBias)},{label:"Contre / sans biais",list:T.filter(t=>!t.withBias)}]);
  bars("#by-rules",[{label:"Règles respectées",list:T.filter(t=>t.rules)},{label:"Hors règles",list:T.filter(t=>!t.rules)}]);
  bars("#by-emotion",[...Object.keys(EMO_L).map(k=>({label:EMO_L[k],list:T.filter(t=>t.emotion===k)})),{label:"Non renseignée",list:T.filter(t=>!t.emotion)}]);
}

/* ---------- rapport de la semaine ---------- */
let RP_WEEK=mondayOf(new Date());
const sR=v=>v==null?"—":(v>0?"+":v<0?"−":"")+fmt(Math.abs(v),2)+"R";
const sPct=v=>v==null?"—":fmt(v*100,0)+" %";
$("#rp-prev").addEventListener("click",()=>{RP_WEEK.setDate(RP_WEEK.getDate()-7);loadReport()});
$("#rp-next").addEventListener("click",()=>{RP_WEEK.setDate(RP_WEEK.getDate()+7);loadReport()});
async function loadReport(){
  const wid=weekId(RP_WEEK), acc=$("#sf-account").value||(ACTIVE?String(ACTIVE.id):""), box=$("#report");
  $("#rp-label").textContent=weekLabel(RP_WEEK);
  $("#rp-next").disabled=RP_WEEK>=mondayOf(new Date());
  let d;try{d=await api("report?week="+wid+(acc?"&account="+acc:""))}catch(e){box.replaceChildren(el("div",{class:"empty",text:"Lecture impossible : "+errMsg(e)}));return}
  if(wid!==weekId(RP_WEEK))return;
  const t=d.totals, p=d.progress, q=d.discipline, co=d.claude&&d.claude.coaching;
  const k=[[String(t.n),"Trades"],[sPct(t.wr),"Réussite"],[t.n?sR(t.sumR):"—","Total"],[money(t.pnl),"Résultat"]];
  const out=[el("div",{class:"kpis"},...k.map(([v,l])=>el("div",{class:"kpi"},el("div",{class:"v",text:v}),el("div",{class:"k",text:l}))))];
  if(t.n)out.push(el("p",{class:"hint",style:"margin:8px 0 0",text:"Règles respectées "+q.rules+"/"+t.n+" · sur niveau clé "+q.onLevel+"/"+t.n+" · avec le biais "+q.withBias+"/"+t.n+" · série de pertes max "+q.streak+(q.overDays.length?" · limites dépassées : "+q.overDays.join(", "):"")}));
  out.push(el("p",{class:"hint",style:"margin:6px 0 0",text:"Étape : "+money(p.profit)+" sur "+money(p.target)+" (reste "+money(p.left)+", "+(p.source==="mt5"?"solde MT5":"journal")+")."}));
  const cb=el("div",{class:"coach"});
  if(co&&(co.summary||(co.actions||[]).length)){
    cb.append(el("div",{class:"coach-h"},el("b",{text:"Analyse de Claude"}),co.discipline!=null&&co.discipline!==""?el("span",{class:"tag",text:"Discipline "+fmt(co.discipline,1)+"/10"}):null));
    if(co.summary)cb.append(el("p",{text:co.summary}));
    [["strengths","Points forts"],["weaknesses","À corriger"],["actions","Pour la semaine prochaine"]].forEach(([key,label])=>{const l=co[key]||[];if(l.length)cb.append(el("div",{class:"coach-l"},el("b",{text:label}),el("ul",null,...l.map(x=>el("li",{text:x})))))});
  }else cb.append(el("p",{class:"hint",style:"margin:0",text:"Analyse du journal par Claude : publiée avec le bilan de la semaine."}));
  out.push(cb);
  const a=el("a",{class:"btn gold",href:d.url,target:"_blank",rel:"noopener",text:"Ouvrir le rapport (PDF)"});
  out.push(el("div",{class:"row",style:"margin-top:12px"},a));
  box.replaceChildren(...out);
}

/* ---------- fiabilité des prévisions de Claude ---------- */
async function loadReliability(){
  const box=$("#reliability");let list=[];
  try{list=await api("plans")}catch(e){box.replaceChildren(el("div",{class:"empty",text:"Lecture impossible : "+errMsg(e)}));return}
  const rv=list.filter(p=>p.review&&p.review.verdict);
  if(!rv.length){box.replaceChildren(el("div",{class:"empty",text:list.length?"Aucun verdict pour l’instant : il arrive à la fin de chaque semaine.":"Aucun plan publié par Claude pour l’instant."}));return}
  const cnt=v=>rv.filter(p=>p.review.verdict===v).length, rate=f=>{const xs=rv.map(p=>p.review[f]).filter(v=>v!=null);return xs.length?xs.filter(Boolean).length/xs.length:null};
  const zs=rv.flatMap(p=>p.review.zones||[]).filter(z=>z.reached);const zr=zs.length?zs.filter(z=>z.reacted).length/zs.length:null;
  const sc=rv.map(p=>p.review.score).filter(v=>v!=null);
  const pc=v=>v==null?"—":fmt(v*100,0)+" %";
  box.replaceChildren(el("div",{class:"kpis"},
    kv("Verdicts",cnt("bonne")+" bonne · "+cnt("partielle")+" partielle · "+cnt("mauvaise")+" mauvaise"),
    kv("Note moyenne",sc.length?fmt(sc.reduce((a,b)=>a+b,0)/sc.length,1)+" / 10":"—"),
    kv("Biais juste",pc(rate("bias_ok"))),kv("Range respecté",pc(rate("range_held"))),
    kv("Zones atteintes qui ont réagi",pc(zr)),kv("Invalidation touchée",pc(rate("invalidation_hit")))),
    table(["Semaine","Verdict","Note","Biais juste"],rv.slice().reverse().map(p=>[new Date(p.week+"T12:00:00").toLocaleDateString("fr-FR",{day:"numeric",month:"short",year:"numeric"}),{bonne:"Bonne",partielle:"Partielle",mauvaise:"Mauvaise"}[p.review.verdict],p.review.score!=null?p.review.score+" / 10":"—",p.review.bias_ok==null?"—":p.review.bias_ok?"Oui":"Non"])),
    el("p",{class:"hint",style:"margin-top:8px",text:"Sur "+rv.length+" semaine"+(rv.length>1?"s":"")+". Fiable à partir d’une dizaine de semaines."}));
}

/* ---------- signaux Claude (trades virtuels de l’EA) ---------- */
const CS_STATUS={attente:"Limite en attente",ouvert:"En cours",gagne:"Gagné",perdu:"Perdu",be:"À zéro",annule:"Annulé",inconnu:"Issue inconnue"};
async function loadClaudeSignals(){
  const box=$("#csignals");let list=[];
  try{list=await api("claude-signals")}catch(e){box.replaceChildren(el("div",{class:"empty",text:"Lecture impossible : "+errMsg(e)}));return}
  if(!list.length){box.replaceChildren(el("div",{class:"empty",text:"Aucun signal pour l’instant : ils arriveront quand l’EA tournera sur MT5."}));return}
  // Deux EA (deux comptes) voient les mêmes zones : un même signal, à 3 minutes près, n'est compté qu'une fois.
  const tms=s=>new Date(s.at.replace(" ","T")).getTime(), uniq=[];
  list.forEach(s=>{if(!uniq.some(u=>u.account!==s.account&&u.side===s.side&&u.kind===s.kind&&u.zone===s.zone&&Math.abs(tms(u)-tms(s))<=180000))uniq.push(s)});
  list=uniq;
  // Les signaux « retour FVG » sont une variante d'entrée des mêmes signaux : comptés à part.
  const closed=list.filter(s=>["gagne","perdu","be"].includes(s.status)),done=closed.filter(s=>s.kind!=="retest"),ret=closed.filter(s=>s.kind==="retest");
  list=list.filter(s=>s.kind!=="retest");
  const st=arr=>{const w=arr.filter(s=>s.r>0).length,l=arr.filter(s=>s.r<0).length,sum=arr.reduce((a,s)=>a+(s.r||0),0);
    return {n:arr.length,w,l,wr:w+l?w/(w+l):null,avg:arr.length?sum/arr.length:null,sum,hit2r:arr.length?arr.filter(s=>s.hit2r).length/arr.length:null}};
  const a=st(done), pc=v=>v==null?"—":fmt(v*100,0)+" %", rs=v=>v==null?"—":(v>=0?"+":"−")+fmt(Math.abs(v),2)+"R";
  const cancelled=list.filter(s=>s.status==="annule").length, open=list.filter(s=>s.status==="ouvert"||s.status==="attente").length, taken=list.filter(s=>s.taken).length;
  const grp=(label,arr)=>{const g=st(arr);return [label,String(g.n),pc(g.wr),rs(g.avg),rs(g.sum)]};
  const rows=[grp("Setup 1 (liquidité)",done.filter(s=>s.setup==="1")),grp("Setup 2 (continuation)",done.filter(s=>s.setup==="2")),grp("Setup 3 (iFVG)",done.filter(s=>s.setup==="3")),grp("Entrée au marché (NOW)",done.filter(s=>s.kind==="now")),grp("Entrée au retour FVG",ret),grp("LIMIT",done.filter(s=>s.kind==="limit")),grp("Achat",done.filter(s=>s.side==="buy")),grp("Vente",done.filter(s=>s.side==="sell"))].filter(r=>r[1]!=="0");
  box.replaceChildren(
    el("div",{class:"kpis"},kv("Taux de réussite",pc(a.wr),a.w+" gagnés · "+a.l+" perdus"),kv("R moyen",rs(a.avg)),kv("Total",rs(a.sum),done.length+" signaux clôturés"),kv("2R atteint",pc(a.hit2r)),
      kv("En cours",String(open)),kv("Limites annulées",String(cancelled)),kv("Pris par vous",list.length?fmt(taken/list.length*100,0)+" %":"—",taken+" sur "+list.length)),
    rows.length?table(["Groupe","Clôturés","Réussite","R moyen","Total"],rows):null,
    el("h4",{class:"sub",text:"Derniers signaux"}),
    table(["Date","Signal","Zone","Entrée / stop / objectif","Statut","Résultat","Pris"],list.slice(0,30).map(s=>[
      new Date(s.at.replace(" ","T")).toLocaleString("fr-FR",{day:"numeric",month:"short",hour:"2-digit",minute:"2-digit"}),
      el("span",{class:"bias "+(s.side==="sell"?"b":"h"),text:(s.side==="sell"?"SELL ":"BUY ")+(s.kind==="limit"?"LIMIT":"NOW")+(s.setup?" · S"+s.setup:"")}),
      s.zone,px(s.entry)+" / "+px(s.sl)+" / "+px(s.tp),CS_STATUS[s.status]||s.status,
      s.r==null?"—":rs(s.r)+(s.hit2r?" (2R)":"")+(s.settled&&s.settled!=="—"?" · rejoué "+s.settled:""),s.taken?"Oui":"Non"])),
    el("p",{class:"hint",style:"margin-top:8px",text:"Taux de réussite calculé sur les signaux clôturés (gagnés et perdus) ; les limites annulées n’en font pas partie. « Entrée au retour FVG » : les mêmes signaux, entrés par un ordre limite au bord du FVG (même stop, même objectif), pour comparer avec l’entrée au marché. « Pris » : un trade de votre journal dans le même sens, à 15 minutes près. « Rejoué M5 » : signal que l’EA n’a pas soldé (EA arrêté, changement de compte), rejoué par le carnet sur les bougies avec la même gestion. Fiable à partir d’une trentaine de signaux."}));
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
      let rk=bal-pnl-size<=-size*o.redatp/100?risk*o.riskredp/o.riskp:risk;const room=Math.min(bal-floor,dailyLim+pnl);if(rk>0.9*room)rk=Math.max(0,0.9*room);
      if(rk<=0)break;
      const r=draw(),p=r*rk;bal+=p;pnl+=p;
      if(bal<=floor||-pnl>=dailyLim)return {ok:false,days:days+1};
      if(r<0&&++losses>=o.maxloss)break;
      if(-pnl>=o.daystopr*risk)break;
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
  const funded=S.phase==="f", risks=[...new Set([0.25,0.5,0.75,1,1.5,2].map(x=>Math.round(S.size*x)/100).concat(S.risk))].sort((a,b)=>a-b);
  if(!funded&&!((S.phase==="2"?S.t2:S.t1)>0)){toast("Objectif de l’étape à 0 % : rien à simuler.");return}
  if(funded&&!(S.tf>0)){toast("Compte financé sans objectif : la simulation demande un objectif par cycle.");return}
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
  const body=rows.map(r=>{const tr=el("tr",{class:r.risk===S.risk?"cur":""},el("td",{text:money(r.risk)+" ("+fmt(100*r.risk/S.size,2)+" %)"+(r.risk===S.risk?" (actuel)":"")}),el("td",{class:"ok",text:pc(r.p1)}));
    if(S.phase==="1")tr.append(el("td",{class:"ok",text:pc(r.both)}));
    tr.append(el("td",{class:"ko",text:pc(r.lost)}),el("td",{text:Number.isFinite(r.days)?String(r.days):"—"}));return tr});
  $("#mc-out").replaceChildren(sum,el("div",{class:"scroll-x"},el("table",{class:"mc-table"},el("thead",null,head),el("tbody",null,...body))),
    el("p",{class:"hint",style:"margin-top:10px",text:MC_RUNS+" challenges simulés par niveau de risque, sur "+MC_HORIZON+" jours de trading au plus (au-delà : non conclu). Réussite + perte + non conclus = 100 % pour l’étape en cours ; « Deux phases » enchaîne la phase 2 après une phase 1 réussie. Résultat indicatif : il suppose que vos trades futurs ressemblent à ceux utilisés."}));
});

/* ---------- comptes et retraits ---------- */
function renderAccounts(){
  const cost=ACCOUNTS.filter(x=>!isOwn(x)).reduce((a,x)=>a+(num(x.price)||0),0);
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
    const head=el("div",{class:"row"},el("span",{class:"t1",text:a.name}),el("span",{class:"pill "+(isOwn(a)?"own":a.status),text:accLabel(a)}));
    if(a.active)head.append(el("span",{class:"tag",text:"actif"}));
    const sub=isOwn(a)?[a.firm,"fonds propres","départ "+money(a.size)].filter(Boolean).join(" · ")+(a.purchaseDate?" · ouvert le "+new Date(a.purchaseDate+"T00:00:00").toLocaleDateString("fr-FR"):"")
      :[a.firm,a.model,money(a.size),"challenge "+money2(a.price)].filter(Boolean).join(" · ")+(a.purchaseDate?" · acheté le "+new Date(a.purchaseDate+"T00:00:00").toLocaleDateString("fr-FR"):"");
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
// Règles publiques des modèles (à vérifier sur le tableau de bord de la firme : elles changent) et gestion conseillée.
const AF_PRESETS={
  fn2:{model:"Stellar 2-Step",t1:8,t2:5,tf:0,daily:5,dailybase:"initial",max:10,mindays:5,riskp:0.5,riskredp:0.25,redatp:3,daystopr:2},
  fn1:{model:"Stellar 1-Step",t1:10,t2:10,tf:0,daily:3,dailybase:"initial",max:6,mindays:2,riskp:0.5,riskredp:0.25,redatp:2,daystopr:2},
  fnl:{model:"Stellar Lite",t1:8,t2:4,tf:0,daily:4,dailybase:"initial",max:8,mindays:5,riskp:0.5,riskredp:0.25,redatp:3,daystopr:2}};
const AF_RULES=["t1","t2","tf","daily","max","mindays","commission","riskp","riskredp","redatp","daystopr","maxtrades","maxloss"];
function afFill(p){AF_RULES.forEach(k=>{if(p[k]!=null)$("#af-"+k).value=p[k]});if(p.dailybase)$("#af-dailybase").value=p.dailybase;afCheck()}
// Contrôle immédiat : montants en $ pour la taille saisie, et arrêt personnel contre la limite de la firme.
function afCheck(){
  const size=num($("#af-size").value), g=k=>num($("#af-"+k).value), h=$("#af-check");
  if(!(size>0)){h.textContent="";return}
  const r=size*g("riskp")/100, d=size*g("daily")/100, ds=g("daystopr")*r;
  h.className="hint"+(ds>d||g("riskredp")>g("riskp")||g("redatp")>=g("max")?" warn":"");
  h.textContent="Sur "+money(size)+" : risque "+money2(r)+" par trade, réduit à "+money2(size*g("riskredp")/100)+" sous −"+money(size*g("redatp")/100)+", arrêt personnel "+money2(ds)+" par jour, perte journalière de la firme "+money(d)+", plancher "+money(size*(1-g("max")/100))+"."+(ds>d?" L’arrêt personnel dépasse la limite de la firme : baissez le risque ou le nombre de R.":"")+(g("riskredp")>g("riskp")?" Le risque réduit dépasse le risque normal.":"")+(g("redatp")>=g("max")?" Le seuil de réduction doit être sous la perte maximale.":"");
}
$("#af-preset").addEventListener("change",e=>{const p=AF_PRESETS[e.target.value];if(!p)return;afFill(p);$("#af-model").value=p.model;if(!$("#af-firm").value)$("#af-firm").value="FundedNext"});
["#af-size",...AF_RULES.map(k=>"#af-"+k)].forEach(id=>$(id).addEventListener("input",afCheck));
function openAccForm(a){
  ACC_EDIT=a?a.id:null;$("#acc-form").hidden=false;$("#af-title").textContent=a?"Modifier le compte":"Nouveau compte";
  $("#af-kind").value=a?(isOwn(a)?"propre":"propfirm"):"propre";afKind();
  $("#af-name").value=a?a.name:"";$("#af-firm").value=a?a.firm:($("#af-kind").value==="propre"?"Deriv":"FundedNext");$("#af-model").value=a?a.model:"Stellar 2-Step";
  $("#af-size").value=a?a.size:($("#af-kind").value==="propre"?250:6000);$("#af-price").value=a?a.price:"";$("#af-status").value=a?a.status:"phase1";
  $("#af-date").value=a?(a.purchaseDate||""):todayStr();$("#af-phase").value=a?(a.phaseStart||""):todayStr();
  $("#af-preset").value="";
  afFill(a&&!isOwn(a)?a.params:Object.assign({commission:0,maxtrades:3,maxloss:2},AF_PRESETS.fn2));
  $("#acc-form").scrollIntoView({behavior:"smooth",block:"start"});$("#af-name").focus();
}
// Le formulaire s'adapte au type : courtier et capital de départ, ou prop firm, modèle, prix et étape.
function afKind(){const own=$("#af-kind").value==="propre";
  $$("#acc-form .af-prop").forEach(e=>{e.hidden=own});
  $("#af-firm-l").textContent=own?"Courtier":"Prop firm";$("#af-size-l").textContent=own?"Capital de départ ($)":"Taille ($)";
  $("#af-date-l").textContent=own?"Date d’ouverture":"Date d’achat";$("#af-firm").placeholder=own?"Deriv, Exness…":"FundedNext";
  $("#af-hint").textContent=own?"Fonds propres : risque = capital de départ ÷ 12, plancher à la moitié du départ, retrait de 50 % du gain chaque mois. Modifiables dans l’onglet Jour.":"Chaque compte a ses propres règles et son propre risque, en % de sa taille. Modifiables ensuite dans l’onglet Jour."}
$("#af-kind").addEventListener("change",()=>{afKind();if(!ACC_EDIT){const own=$("#af-kind").value==="propre";$("#af-size").value=own?250:6000;$("#af-firm").value=own?"Deriv":"FundedNext";afCheck()}});
$("#acc-new").addEventListener("click",()=>openAccForm(null));
$("#af-cancel").addEventListener("click",()=>{$("#acc-form").hidden=true;ACC_EDIT=null});
$("#af-save").addEventListener("click",async()=>{
  const body={name:$("#af-name").value.trim(),firm:$("#af-firm").value.trim(),model:$("#af-model").value.trim(),size:$("#af-size").value,price:$("#af-price").value,
    status:$("#af-status").value,purchaseDate:$("#af-date").value,phaseStart:$("#af-phase").value};
  if(!body.name){toast("Donnez un nom au compte.");return}
  const own=$("#af-kind").value==="propre";
  if(own){const sz=num(body.size);body.status=body.status==="retire"?"retire":"finance";
    const base=ACC_EDIT?(ACCOUNTS.find(x=>x.id===ACC_EDIT)||{}).params:(ACTIVE?ACTIVE.params:{});
    body.params=Object.assign({},base,{kind:"propre"},ACC_EDIT&&isOwn(ACCOUNTS.find(x=>x.id===ACC_EDIT))?{start:sz}:{start:sz,budget:12,floor:Math.round(sz/2),withdraw:50,daystopr:2,weekloss:4,weekstopr:4,maxloss:2,maxtrades:3});
    body.price=0;}
  else{
    // Prop firm : les règles viennent du formulaire, jamais du compte actif.
    const rules={kind:"propfirm",dailybase:$("#af-dailybase").value};
    for(const k of AF_RULES){rules[k]=num($("#af-"+k).value);if(!Number.isFinite(rules[k])){toast("Règle du challenge manquante : vérifiez chaque champ.");return}}
    const old=ACC_EDIT?(ACCOUNTS.find(x=>x.id===ACC_EDIT)||{}).params:{};
    body.params=Object.assign({},isOwn({params:old})?{}:old,rules);
  }
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
  if(!confirm("Couper la liaison MT5 de « "+a.name+" » ? La clé cesse de fonctionner et les soldes reçus de MT5 pour ce compte sont effacés (les trades restent dans le journal)."))return;
  try{await api("accounts/"+a.id+"/mt5-key",{method:"DELETE"});a.mt5=false;delete SNAPS[a.id];renderAccounts();refreshAll();toast("Liaison MT5 coupée")}catch(e){toast("Impossible : "+errMsg(e))}
}
let CONN=null;
async function loadConnections(){
  try{CONN=await api("connections")}catch(e){return}
  $("#ea-link").href=CFG.ea||"#";$("#calc-link").href=CFG.calc||"#";
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
    $("#tg-channel-state").textContent=tg.channel?"Chaîne « "+tg.channel+" » : tous les messages du carnet y sont aussi publiés.":tg.linked?"Chaîne Telegram : ajoutez @"+tg.bot+" comme administrateur de votre chaîne (droit « Publier des messages ») ; elle sera reliée automatiquement.":"";
    $("#tg-channel-off").hidden=!tg.channel;
  }
  // Bougies de l'analyse : un seul courtier, celui choisi ici (par défaut le compte actif).
  const cs=CONN.candles||{sources:[],ref:0}, src=new Map((cs.sources||[]).map(x=>[x.id,x])), sel=$("#candles-ref");
  const when=at=>at?new Date(at.replace(" ","T")).toLocaleString("fr-FR",{day:"numeric",month:"short",hour:"2-digit",minute:"2-digit"}):"";
  sel.replaceChildren(el("option",{value:"0",text:"Le compte actif"+(ACTIVE?" ("+ACTIVE.name+")":"")}),...ACCOUNTS.map(a=>{const x=src.get(a.id);return el("option",{value:String(a.id),text:a.name+(a.firm?" · "+a.firm:"")+(x?" · reçues le "+when(x.at):" · aucune bougie reçue")})}));
  sel.value=String(cs.ref||0);
  const cur=(cs.sources||[]).find(x=>x.ref);
  $("#candles-hint").textContent=cur?"Claude analyse les bougies de « "+cur.name+" »"+(cur.firm?" ("+cur.firm+")":"")+", reçues le "+when(cur.at)+". Chaque courtier a ses propres prix et ses propres bougies journalières (heure de son serveur) : un seul sert à l’analyse, jamais un mélange.":"Aucune bougie reçue de ce compte : l’EA Carnet doit tourner sur son MT5 (envoi toutes les 15 minutes).";
  eaParamsBody().then(b=>{$("#ea-params").replaceChildren(b||el("p",{class:"hint",text:"Réglages indisponibles."}))});
  const rf=CONN.refusals||[];
  $("#refusals-box summary").textContent="Accès refusés aux points d’entrée ("+rf.length+")";
  $("#refusals").replaceChildren(...(rf.length?rf.map(r=>el("div",{class:"t2",style:"font-size:13px;color:var(--muted);margin-top:4px",text:r.time+" · "+r.entry+" · "+r.reason+" · "+r.ip})):[el("p",{class:"hint",style:"margin-top:8px",text:"Aucun refus."})]));
}
$("#candles-ref").addEventListener("change",async e=>{
  try{CONN=await api("connections/candles",{method:"PUT",body:{account:parseInt(e.target.value,10)||0}});CANDLES=null;loadConnections();toast("Bougies de l’analyse : "+e.target.selectedOptions[0].textContent.split(" · ")[0])}
  catch(err){toast("Impossible : "+errMsg(err));loadConnections()}
});
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
$("#tg-channel-off").addEventListener("click",async()=>{if(!confirm("Ne plus publier sur la chaîne ?"))return;try{await api("connections/telegram/channel",{method:"DELETE"});loadConnections();toast("Chaîne retirée")}catch(e){toast("Impossible : "+errMsg(e))}});
$("#tg-off").addEventListener("click",async()=>{if(!confirm("Déconnecter le bot Telegram ?"))return;try{await api("connections/telegram",{method:"DELETE"});loadConnections();toast("Bot déconnecté")}catch(e){toast("Impossible : "+errMsg(e))}});

/* ---------- mise à jour périodique (trades MT5, solde) ---------- */
setInterval(async()=>{
  if(document.hidden||EDIT_ID)return;
  try{const b=await api("bootstrap");TRADES=b.trades||[];SNAPS=b.snaps||{};refreshAll()}catch(e){}
},120000);

function refreshAll(){
  renderTrades();calc();
  if(!$("#tab-journal").hidden)renderStats();
  if(!$("#tab-comptes").hidden)renderAccounts();
}

/* ---------- démarrage ---------- */
// Carnet public en lecture seule : commandes de modification masquées, bandeau de connexion.
function readOnlyMode(){
  if(!CFG.readOnly)return;
  ROOT.classList.add("ro");
  $("#acct-switch").disabled=true;
  const bar=el("div",{class:"ro-bar"},el("span",{text:"Lecture seule"}),document.createTextNode(" · Vous consultez le carnet. "));
  if(CFG.login)bar.append(el("a",{href:CFG.login,text:"Se connecter pour modifier"}));
  ROOT.querySelector(".cx-bar").after(bar);
}
// Lecture seule : les champs de calcul du jour, la check-list et les paramètres sont figés.
function lockReadOnly(){
  if(!CFG.readOnly)return;
  ROOT.querySelectorAll("#tab-jour input, #tab-jour select, #tab-jour textarea, #tab-jour button").forEach(i=>{i.disabled=true;i.setAttribute("aria-disabled","true")});
}
async function start(){
  readOnlyMode();
  try{
    const b=await api("bootstrap");
    ACCOUNTS=b.accounts||[];TRADES=b.trades||[];PAYOUTS=b.payouts||[];SNAPS=b.snaps||{};
  }catch(e){toast("Chargement impossible : "+errMsg(e));setStatus("err","Hors ligne")}
  setActive();fillAccountSelects();resetTradeForm();resetPayoutForm();
  try{THIS_WEEK_PLAN=await api("plans/"+weekId(planWeek()))}catch(e){THIS_WEEK_PLAN=null}
  renderToday();loadPlan();renderTrades();calc();loadFeuSignals();setInterval(loadFeuSignals,120000);
  // Les plans de séance arrivent dans la journée (06:45 à Londres, 08:15 à New York) : relecture toutes les 5 minutes.
  setInterval(async()=>{if(document.hidden)return;try{THIS_WEEK_PLAN=await api("plans/"+weekId(planWeek()));renderToday();if(weekId(PLAN_WEEK)===weekId(planWeek())&&!$("#tab-plan").hidden)renderPlanView(THIS_WEEK_PLAN)}catch(e){}},300000);
  loadCalendar();
  lockReadOnly();
  if("serviceWorker" in navigator&&CFG.sw&&window.isSecureContext){navigator.serviceWorker.register(CFG.sw,{scope:CFG.scope||"/"}).catch(()=>{})}
}
start();
})();
