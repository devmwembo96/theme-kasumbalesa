/* RevNefty — comportements du site : menu mobile, en-tête au défilement, bandeau des cours. */
(function () {
	'use strict';

	var header = document.getElementById('rn-header');
	var drawer = document.getElementById('rn-drawer');
	var toggle = document.querySelector('.rn-menu-toggle');

	// En-tête plus opaque dès qu'on défile.
	if (header) {
		var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 8); };
		onScroll();
		window.addEventListener('scroll', onScroll, { passive: true });
	}

	// Menu plein écran (mobile).
	function setMenu(open) {
		if (!drawer || !toggle) { return; }
		drawer.hidden = !open;
		toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		document.body.classList.toggle('rn-menu-open', open);
		if (open) {
			var first = drawer.querySelector('a, button');
			if (first) { first.focus(); }
		} else {
			toggle.focus();
		}
	}
	if (toggle && drawer) {
		toggle.addEventListener('click', function () { setMenu(drawer.hidden); });
		drawer.addEventListener('click', function (e) {
			if (e.target.closest('[data-close-menu]') || e.target.closest('a[href*="#"]')) { setMenu(false); }
		});
		document.addEventListener('keydown', function (e) {
			if ('Escape' === e.key && !drawer.hidden) { setMenu(false); }
		});
		window.matchMedia('(min-width: 1024px)').addEventListener('change', function (m) { if (m.matches && !drawer.hidden) { setMenu(false); } });
	}

	// Bandeau des cours TradingView : chargé seulement quand il approche de l'écran.
	var ticker = document.querySelector('[data-rn-ticker]');
	if (ticker) {
		var load = function () {
			if (ticker.dataset.loaded) { return; }
			ticker.dataset.loaded = '1';
			var s = document.createElement('script');
			s.src = 'https://s3.tradingview.com/external-embedding/embed-widget-ticker-tape.js';
			s.async = true;
			s.text = ticker.getAttribute('data-rn-ticker');
			ticker.appendChild(s);
		};
		if ('IntersectionObserver' in window) {
			var io = new IntersectionObserver(function (entries) {
				if (entries[0].isIntersecting) { load(); io.disconnect(); }
			}, { rootMargin: '200px' });
			io.observe(ticker);
		} else {
			load();
		}
	}
})();
