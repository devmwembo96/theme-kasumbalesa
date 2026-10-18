<?php
/**
 * Balisage de l'application (repris du prototype carnet-xau.html), rempli par assets/js/app.js.
 *
 * @package CarnetXAU
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="cx-app" id="cx-app">
<header class="cx-bar">
	<div class="cx-bar-in">
		<div class="brand"><span class="ingot" aria-hidden="true"></span>Carnet XAU</div>
		<label class="acct"><span class="sr">Compte actif</span><select id="acct-switch" aria-label="Compte actif"></select></label>
		<div class="status" id="status"><i></i><span>Connexion…</span></div>
		<nav class="cx-nav" role="tablist">
			<button type="button" role="tab" data-tab="jour" aria-selected="true">Jour</button>
			<button type="button" role="tab" data-tab="plan" aria-selected="false">Plan</button>
			<button type="button" role="tab" data-tab="journal" aria-selected="false">Journal</button>
			<button type="button" role="tab" data-tab="stats" aria-selected="false">Stats</button>
			<button type="button" role="tab" data-tab="comptes" aria-selected="false">Comptes</button>
		</nav>
	</div>
</header>

<div class="cx-main">
<!-- ============ JOUR ============ -->
<section id="tab-jour">
	<div class="ticket">
		<div class="ticket-head">
			<span class="phase" id="t-phase">Phase 1</span>
			<span class="meta" id="t-meta"></span>
		</div>
		<div class="mt5-line" id="mt5-line" hidden></div>
		<div class="ticket-in grid g4">
			<label class="f">Solde du matin ($)<input id="in-balance" type="number" step="0.01" inputmode="decimal"></label>
			<label class="f">Jours déjà tradés<input id="in-days" type="number" min="0" step="1" inputmode="numeric"></label>
			<label class="f">Stop (distance en $)<input id="in-stop" type="number" step="0.01" min="0" inputmode="decimal" placeholder="ex. 1,80"></label>
			<label class="f">Spread actuel ($)<input id="in-spread" type="number" step="0.01" min="0" inputmode="decimal" placeholder="ex. 0,25"></label>
		</div>
		<div class="ticket-out">
			<div class="cell hero"><div class="k">Lot à prendre</div><div class="v" id="o-lot">—</div><div class="s" id="o-lot-s"></div></div>
			<div class="cell"><div class="k">Objectif du jour</div><div class="v" id="o-target">—</div><div class="s" id="o-target-s"></div></div>
			<div class="cell"><div class="k">Take-profit du jour</div><div class="v" id="o-tp">—</div><div class="s" id="o-tp-s"></div></div>
			<div class="cell"><div class="k">Sortie partielle à 2R</div><div class="v" id="o-2r">—</div><div class="s" id="o-2r-s"></div></div>
			<div class="cell"><div class="k">Marge avant perte max</div><div class="v" id="o-margin">—</div><div class="s" id="o-margin-s"></div></div>
			<div class="cell"><div class="k">Aujourd’hui</div><div class="v" id="o-today">—</div><div class="s" id="o-today-s"></div></div>
		</div>
		<div class="alerts" id="alerts"></div>
	</div>

	<div class="grid g2">
		<div class="panel">
			<h3>Check-list avant d’entrer</h3>
			<div id="checklist"></div>
			<div class="verdict no" id="verdict">Cochez les six points pour valider le trade.</div>
			<div class="row" style="margin-top:12px"><button type="button" class="btn gold" id="btn-log" disabled>Noter ce trade dans le journal</button></div>
		</div>
		<div class="panel planmini" id="planmini"></div>
	</div>

	<details class="panel" id="settings">
		<summary>Paramètres du compte</summary>
		<div class="grid g3" style="margin-top:12px">
			<label class="f">Étape<select id="s-phase"><option value="phase1">Phase 1</option><option value="phase2">Phase 2</option><option value="finance">Compte financé</option><option value="echoue">Échoué</option><option value="retire">Retiré</option></select></label>
			<label class="f">Taille du compte ($)<input id="s-size" type="number" step="1"></label>
			<label class="f">Jours minimum par phase<input id="s-mindays" type="number" step="1"></label>
			<label class="f">Objectif phase 1 (%)<input id="s-t1" type="number" step="0.1"></label>
			<label class="f">Objectif phase 2 (%)<input id="s-t2" type="number" step="0.1"></label>
			<label class="f">Objectif par cycle financé (%)<input id="s-tf" type="number" step="0.1"></label>
			<label class="f">Perte journalière (%)<input id="s-daily" type="number" step="0.1"></label>
			<label class="f">Perte maximale (%)<input id="s-max" type="number" step="0.1"></label>
			<label class="f">Contrat or (onces par lot)<input id="s-contract" type="number" step="1"></label>
			<label class="f">Risque par trade ($)<input id="s-risk" type="number" step="1"></label>
			<label class="f">Risque réduit ($)<input id="s-riskred" type="number" step="1"></label>
			<label class="f">Réduire si le compte est à −… ($)<input id="s-redat" type="number" step="1"></label>
			<label class="f">Trades max par jour<input id="s-maxtrades" type="number" step="1"></label>
			<label class="f">Pertes max par jour<input id="s-maxloss" type="number" step="1"></label>
			<label class="f">Spread max (% du stop)<input id="s-spreadmax" type="number" step="1"></label>
		</div>
		<div class="row" style="margin-top:12px"><button type="button" class="btn" id="btn-settings">Enregistrer les paramètres</button></div>
	</details>
</section>

<!-- ============ PLAN ============ -->
<section id="tab-plan" hidden>
	<div class="weeknav">
		<button type="button" class="btn ghost sm" id="wk-prev" aria-label="Semaine précédente">‹</button>
		<strong id="wk-label"></strong>
		<button type="button" class="btn ghost sm" id="wk-next" aria-label="Semaine suivante">›</button>
		<span class="spacer"></span>
		<button type="button" class="btn ghost sm" id="wk-today">Cette semaine</button>
	</div>
	<div id="plan-view"></div>
</section>

<!-- ============ JOURNAL ============ -->
<section id="tab-journal" hidden>
	<div class="panel" id="trade-form">
		<h3 id="tf-title">Nouveau trade</h3>
		<div class="grid g4">
			<label class="f">Compte<select id="tf-account"></select></label>
			<label class="f">Date<input id="tf-date" type="date"></label>
			<label class="f">Heure<input id="tf-time" type="time"></label>
			<label class="f">Sens<select id="tf-dir"><option value="achat">Achat</option><option value="vente">Vente</option></select></label>
			<label class="f">Setup<select id="tf-setup"><option value="1">Setup 1 — liquidité</option><option value="2">Setup 2 — continuation</option><option value="hors">Hors setup</option></select></label>
			<label class="f">Niveau ou déclencheur<select id="tf-level">
				<option value="EQL">EQL</option><option value="EQH">EQH</option><option value="2 Bottom Liq">2 Bottom Liq</option><option value="2 Top Liq">2 Top Liq</option>
				<option value="PDH">PDH</option><option value="PDL">PDL</option><option value="Session fermée">Extrême de session fermée</option><option value="BoS">BoS / MSB</option><option value="Autre">Autre</option></select></label>
			<label class="f">Stop ($)<input id="tf-stop" type="number" step="0.01" inputmode="decimal"></label>
			<label class="f">Lot<input id="tf-lot" type="number" step="0.01" inputmode="decimal"></label>
			<label class="f">Risque ($)<input id="tf-risk" type="number" step="0.01" inputmode="decimal"></label>
			<label class="f">Résultat (R)<input id="tf-r" type="number" step="0.1" inputmode="decimal" placeholder="−1, 0, 2, 3,5…"></label>
			<label class="f">Résultat ($)<input id="tf-pnl" type="number" step="0.01" inputmode="decimal"></label>
			<label class="f">Émotion<select id="tf-emotion"><option value="">—</option><option value="calme">Calme</option><option value="presse">Pressé</option><option value="revanche">Revanche</option><option value="fatigue">Fatigue</option></select></label>
			<label class="check"><input type="checkbox" id="tf-onlevel"><span>Sur un niveau clé du plan</span></label>
			<label class="check"><input type="checkbox" id="tf-bias"><span>Dans le sens du biais</span></label>
			<label class="check"><input type="checkbox" id="tf-rules"><span>Règles respectées</span></label>
		</div>
		<div class="grid g2" style="margin-top:10px">
			<label class="f">Lien de la capture (TradingView)<input id="tf-link" type="url" placeholder="https://www.tradingview.com/x/…"></label>
			<div class="f">
				<span>Capture d’écran</span>
				<div class="row capture-row">
					<label class="btn ghost sm file-btn">Choisir une image<input id="tf-file" type="file" accept="image/jpeg,image/png,image/webp,image/gif"></label>
					<span class="capture-name" id="tf-file-name">Aucune image</span>
					<button type="button" class="btn ghost sm" id="tf-file-clear" hidden>Retirer</button>
				</div>
			</div>
		</div>
		<label class="f" style="margin-top:10px">Notes<textarea id="tf-notes"></textarea></label>
		<div class="row" style="margin-top:12px">
			<button type="button" class="btn gold" id="tf-save">Enregistrer le trade</button>
			<button type="button" class="btn ghost" id="tf-cancel" hidden>Annuler la modification</button>
		</div>
	</div>
	<div class="row" style="margin-bottom:10px">
		<h2 style="margin:0">Trades</h2><span class="spacer"></span>
		<select id="jf-account" class="auto" aria-label="Filtrer par compte"></select>
		<select id="jf-setup" class="auto" aria-label="Filtrer par setup"><option value="">Tous les setups</option><option value="1">Setup 1</option><option value="2">Setup 2</option><option value="hors">Hors setup</option><option value="todo">MT5 à compléter</option></select>
		<button type="button" class="btn ghost sm" id="btn-csv">Exporter en CSV</button>
	</div>
	<div class="trades" id="trades"></div>

	<details class="panel" id="signals-panel" style="margin-top:14px">
		<summary>Signaux TradingView reçus</summary>
		<p class="hint" style="margin-top:10px">Chaque alerte de l’indicateur est notée avec son heure de réception. Un signal qui arrive bien après la bougie, ou qui disparaît du graphique ensuite, trahit un indicateur qui repeint.</p>
		<div class="row" style="margin-bottom:10px"><button type="button" class="btn ghost sm" id="sig-refresh">Actualiser</button><span class="spacer"></span><button type="button" class="btn ghost sm danger" id="sig-clear">Vider le journal des signaux</button></div>
		<div class="trades" id="signals"></div>
	</details>
</section>

<!-- ============ STATS ============ -->
<section id="tab-stats" hidden>
	<div class="row" style="margin-bottom:12px">
		<h2 style="margin:0">Statistiques</h2><span class="spacer"></span>
		<select id="sf-account" class="auto" aria-label="Compte analysé"></select>
	</div>
	<div class="kpis" id="kpis"></div>
	<div class="panel"><h3>Fiabilité des prévisions de Claude</h3><div id="reliability"></div></div>
	<div class="panel"><h3>Signaux Claude (EA MT5)</h3><p class="hint">Chaque signal est suivi comme un trade virtuel, pris ou non : 50 % à 2R puis stop au prix d’entrée. Résultats séparés de vos trades.</p><div id="csignals"></div></div>
	<div class="panel" id="mc-panel">
		<h3>Simulateur de challenge (Monte-Carlo)</h3>
		<p class="hint">Rejoue des milliers de challenges avec vos résultats et les règles du compte actif : objectif, perte journalière, perte maximale, jours minimum, trades et pertes maximum par jour, risque réduit.</p>
		<div class="grid g4">
			<label class="f">Résultats utilisés<select id="mc-src"><option value="trades">Mes trades</option><option value="hyp">Hypothèse</option></select></label>
			<label class="f">Réussite (%)<input id="mc-wr" type="number" value="40" min="1" max="99" step="1" inputmode="numeric"></label>
			<label class="f">Gain moyen d’un trade gagnant (R)<input id="mc-avg" type="number" value="2" min="0.1" step="0.1" inputmode="decimal"></label>
			<label class="f">Trades par jour<input id="mc-tpd" type="number" value="2" min="1" step="1" inputmode="numeric"></label>
		</div>
		<p class="hint" id="mc-note" style="margin-top:10px"></p>
		<div class="row"><button type="button" class="btn gold" id="mc-run">Lancer la simulation</button></div>
		<div id="mc-out"></div>
	</div>
	<div class="panel"><h3>Courbe des résultats (en R cumulés)</h3><div id="curve"></div></div>
	<div class="panel"><h3>Par setup</h3><p class="hint">Barre = taux de réussite. Le trait marque 33 %, le seuil de rentabilité à 2R.</p><div class="bars" id="by-setup"></div></div>
	<div class="panel"><h3>Par niveau ou déclencheur</h3><div class="bars" id="by-level"></div></div>
	<div class="panel"><h3>Par moment de la journée (heure de Lubumbashi)</h3><div class="bars" id="by-hour"></div></div>
	<div class="panel"><h3>Par jour de la semaine</h3><div class="bars" id="by-day"></div></div>
	<div class="panel"><h3>Filtres du plan</h3><div class="bars" id="by-filter"></div></div>
	<div class="panel"><h3>Discipline</h3><div class="bars" id="by-rules"></div></div>
	<div class="panel"><h3>Par émotion</h3><div class="bars" id="by-emotion"></div></div>
</section>

<!-- ============ COMPTES ============ -->
<section id="tab-comptes" hidden>
	<div class="kpis" id="acc-kpis"></div>

	<div class="row" style="margin-bottom:10px">
		<h2 style="margin:0">Comptes</h2><span class="spacer"></span>
		<button type="button" class="btn ghost sm" id="acc-new">Nouveau compte</button>
	</div>
	<div class="accounts" id="accounts"></div>

	<div class="panel" id="acc-form" hidden>
		<h3 id="af-title">Nouveau compte</h3>
		<div class="grid g3">
			<label class="f">Nom<input id="af-name" type="text" maxlength="100" placeholder="Challenge 2"></label>
			<label class="f">Prop firm<input id="af-firm" type="text" maxlength="100" placeholder="FundedNext"></label>
			<label class="f">Modèle<input id="af-model" type="text" maxlength="100" placeholder="Stellar 2-Step"></label>
			<label class="f">Taille ($)<input id="af-size" type="number" step="1" min="1"></label>
			<label class="f">Prix du challenge ($)<input id="af-price" type="number" step="0.01" min="0"></label>
			<label class="f">Statut<select id="af-status"><option value="phase1">Phase 1</option><option value="phase2">Phase 2</option><option value="finance">Financé</option><option value="echoue">Échoué</option><option value="retire">Retiré</option></select></label>
			<label class="f">Date d’achat<input id="af-date" type="date"></label>
			<label class="f">Début de l’étape en cours<input id="af-phase" type="date"></label>
		</div>
		<p class="hint" style="margin-top:10px">Les paramètres de risque d’un nouveau compte reprennent ceux du compte actif. Ils se modifient dans l’onglet Jour.</p>
		<div class="row">
			<button type="button" class="btn gold" id="af-save">Enregistrer le compte</button>
			<button type="button" class="btn ghost" id="af-cancel">Annuler</button>
		</div>
	</div>

	<div class="panel">
		<h3>Retraits</h3>
		<div class="grid g4">
			<label class="f">Compte<select id="pf-account"></select></label>
			<label class="f">Date<input id="pf-date" type="date"></label>
			<label class="f">Montant brut ($)<input id="pf-gross" type="number" step="0.01" min="0"></label>
			<label class="f">Part reçue ($)<input id="pf-share" type="number" step="0.01" min="0"></label>
			<label class="f">Frais ($)<input id="pf-fees" type="number" step="0.01" min="0"></label>
			<label class="f">Moyen<input id="pf-method" type="text" maxlength="50" list="pf-methods" placeholder="USDT TRC20"></label>
			<label class="f" style="grid-column:span 2">Conversion (Binance P2P…)<input id="pf-conv" type="text" placeholder="Taux, frais, montant reçu en CDF…"></label>
		</div>
		<datalist id="pf-methods"><option value="USDT TRC20"><option value="Rise"><option value="Virement"><option value="Crypto (autre)"></datalist>
		<div class="row" style="margin-top:12px">
			<button type="button" class="btn gold" id="pf-save">Enregistrer le retrait</button>
			<button type="button" class="btn ghost" id="pf-cancel" hidden>Annuler la modification</button>
		</div>
		<div class="trades" id="payouts" style="margin-top:14px"></div>
	</div>

	<div class="panel" id="connections">
		<h3>Connexions</h3>
		<div class="conn">
			<h4>MetaTrader 5</h4>
			<p class="hint">L’EA envoie chaque trade fermé et, toutes les minutes, le solde, l’équité et la perte du jour. Une clé par compte : bouton « Clé MT5 » sur la carte du compte.</p>
			<ol class="steps">
				<li>Téléchargez l’EA : <a id="ea-link" download="CarnetXAU.mq5">CarnetXAU.mq5</a>, puis placez-le dans MQL5/Experts et compilez-le dans MetaEditor.</li>
				<li>Dans MT5 : Outils › Options › Expert Advisors, cochez « Autoriser WebRequest » et ajoutez l’adresse <code id="mt5-host"></code>.</li>
				<li>Glissez l’EA sur un graphique XAUUSD, collez l’adresse <code id="mt5-url"></code> et la clé du compte.</li>
			</ol>
			<div class="secret" id="mt5-key-box" hidden></div>
		</div>
		<div class="conn">
			<h4>TradingView</h4>
			<p class="hint" id="tv-state"></p>
			<div class="row"><button type="button" class="btn ghost sm" id="tv-rotate">Générer l’adresse du webhook</button><button type="button" class="btn ghost sm danger" id="tv-revoke" hidden>Couper</button></div>
			<div class="secret" id="tv-box" hidden></div>
		</div>
		<div class="conn">
			<h4>Plan de la semaine (Claude)</h4>
			<p class="hint" id="plan-state"></p>
			<div class="row"><button type="button" class="btn ghost sm" id="plan-rotate">Générer le jeton du plan</button><button type="button" class="btn ghost sm danger" id="plan-revoke" hidden>Couper</button></div>
			<div class="secret" id="plan-box" hidden></div>
		</div>
		<div class="conn">
			<h4>Telegram</h4>
			<div id="tg-setup">
				<p class="hint">Créez un bot avec @BotFather (commande /newbot), puis collez ici le jeton qu’il vous donne.</p>
				<div class="row"><input id="tg-token" type="text" autocomplete="off" placeholder="123456789:AA…" style="flex:1;min-width:220px"><button type="button" class="btn gold sm" id="tg-connect">Connecter le bot</button></div>
			</div>
			<div id="tg-on" hidden>
				<p id="tg-state"></p>
				<div class="row" id="tg-alerts">
					<label class="check"><input type="checkbox" data-a="daily"><span>Limite journalière</span></label>
					<label class="check"><input type="checkbox" data-a="news"><span>Annonce dans 15 min</span></label>
					<label class="check"><input type="checkbox" data-a="weekend"><span>Rappel du plan le week-end</span></label>
					<label class="check"><input type="checkbox" data-a="signals"><span>Signaux et invalidation (EA MT5)</span></label>
				</div>
				<div class="row"><button type="button" class="btn ghost sm" id="tg-test">Envoyer un message de test</button><button type="button" class="btn ghost sm danger" id="tg-off">Déconnecter</button></div>
			</div>
		</div>
		<details class="conn" id="refusals-box"><summary>Accès refusés aux points d’entrée</summary><div id="refusals"></div></details>
	</div>

	<div class="panel">
		<h3>Sauvegarde</h3>
		<p class="hint">Export complet (comptes, trades, plans, retraits) au format JSON. À faire avant toute désinstallation du plugin.</p>
		<button type="button" class="btn ghost" id="btn-backup">Télécharger la sauvegarde</button>
	</div>
</section>
</div>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<noscript><p class="panel">Le Carnet XAU a besoin de JavaScript.</p></noscript>
</div>
