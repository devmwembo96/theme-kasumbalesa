/**
 * Kasumbalesa — interactions du site (JavaScript natif, sans dépendance).
 */
(function () {
	'use strict';

	var doc = document;
	var root = doc.documentElement;
	var body = doc.body;
	var config = window.kbConfig || { heroInterval: 7000, i18n: {} };
	var i18n = config.i18n || {};
	var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	function $(selector, context) { return (context || doc).querySelector(selector); }
	function $$(selector, context) { return Array.prototype.slice.call((context || doc).querySelectorAll(selector)); }

	function storageGet(key) {
		try { return window.sessionStorage.getItem(key); } catch (e) { return null; }
	}
	function storageSet(key) {
		try { window.sessionStorage.setItem(key, '1'); } catch (e) { /* stockage indisponible */ }
	}

	var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])';

	function trapFocus(container, event) {
		if (event.key !== 'Tab') { return; }
		var items = $$(FOCUSABLE, container).filter(function (el) { return el.offsetParent !== null; });
		if (!items.length) { return; }
		var first = items[0];
		var last = items[items.length - 1];
		if (event.shiftKey && doc.activeElement === first) {
			event.preventDefault();
			last.focus();
		} else if (!event.shiftKey && doc.activeElement === last) {
			event.preventDefault();
			first.focus();
		}
	}

	/* ------------------------------------------------------------------
	 * En-tête : devient opaque au défilement
	 * ------------------------------------------------------------------ */
	var header = $('#kb-header');

	if (header) {
		var ticking = false;
		var onScroll = function () {
			if (ticking) { return; }
			ticking = true;
			window.requestAnimationFrame(function () {
				header.classList.toggle('is-scrolled', window.scrollY > 40);
				ticking = false;
			});
		};
		window.addEventListener('scroll', onScroll, { passive: true });
		onScroll();
	}

	/* ------------------------------------------------------------------
	 * Menu mobile plein écran
	 * ------------------------------------------------------------------ */
	var menu = $('#kb-mobile-menu');
	var menuToggle = $('.kb-menu-toggle');

	function closeMenu() {
		if (!menu || menu.hidden) { return; }
		menu.classList.remove('is-open');
		menu.hidden = true;
		body.classList.remove('kb-lock');
		menuToggle.setAttribute('aria-expanded', 'false');
		menuToggle.focus();
	}

	function openMenu() {
		menu.hidden = false;
		body.classList.add('kb-lock');
		menuToggle.setAttribute('aria-expanded', 'true');
		$$('.kb-mobile-menu__list > li', menu).forEach(function (li, i) { li.style.setProperty('--i', i); });
		window.requestAnimationFrame(function () { menu.classList.add('is-open'); });
		var close = $('[data-close-menu]', menu);
		if (close) { close.focus(); }
	}

	if (menu && menuToggle) {
		menuToggle.addEventListener('click', openMenu);
		$('[data-close-menu]', menu).addEventListener('click', closeMenu);
		menu.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') { closeMenu(); }
			trapFocus(menu, event);
		});
		menu.addEventListener('click', function (event) {
			var link = event.target.closest('a');
			if (link && link.getAttribute('href') && link.getAttribute('href').charAt(0) === '#') { closeMenu(); }
		});
		window.matchMedia('(min-width: 1101px)').addEventListener('change', function (mq) {
			if (mq.matches) { closeMenu(); }
		});
	}

	/* ------------------------------------------------------------------
	 * Recherche
	 * ------------------------------------------------------------------ */
	var search = $('#kb-search');
	var searchToggle = $('.kb-search-toggle');

	function closeSearch() {
		if (!search || search.hidden) { return; }
		search.hidden = true;
		searchToggle.setAttribute('aria-expanded', 'false');
		searchToggle.focus();
	}

	if (search && searchToggle) {
		searchToggle.addEventListener('click', function () {
			if (!search.hidden) { closeSearch(); return; }
			search.hidden = false;
			searchToggle.setAttribute('aria-expanded', 'true');
			var input = $('input[type="search"]', search);
			if (input) { input.focus(); }
		});
		$('[data-close-search]', search).addEventListener('click', closeSearch);
		search.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') { closeSearch(); }
		});
		doc.addEventListener('click', function (event) {
			if (!search.hidden && !search.contains(event.target) && !searchToggle.contains(event.target)) { closeSearch(); }
		});
	}

	/* ------------------------------------------------------------------
	 * Animation d'introduction (une fois par session, 2 s maximum)
	 * ------------------------------------------------------------------ */
	var introDone = false;

	function finishIntro() {
		if (introDone) { return; }
		introDone = true;
		root.classList.remove('kb-intro-active');
		doc.dispatchEvent(new CustomEvent('kb:introdone'));
	}

	function runIntro() {
		var intro = $('#kb-intro');
		var visible = intro && window.getComputedStyle(intro).display !== 'none';

		if (!visible) {
			finishIntro();
			return;
		}

		storageSet('kbIntroSeen');
		root.classList.add('kb-intro-active');

		var skip = $('[data-intro-skip]', intro);
		var leaveTimer;

		var leave = function () {
			window.clearTimeout(leaveTimer);
			if (intro.classList.contains('is-leaving')) { return; }
			intro.classList.add('is-leaving');
			finishIntro();
			window.setTimeout(function () { intro.parentNode && intro.parentNode.removeChild(intro); }, 500);
		};

		if (skip) {
			skip.addEventListener('click', leave);
			skip.focus({ preventScroll: true });
		}
		intro.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') { leave(); }
		});
		// Le compte à rebours part de l'affichage de la page, pas du chargement du script.
		var elapsed = window.performance && performance.now ? performance.now() : 0;
		leaveTimer = window.setTimeout(leave, Math.max(0, 1550 - elapsed));
	}

	/* ------------------------------------------------------------------
	 * Pop-up du Maire (après l'intro, une fois par session)
	 * ------------------------------------------------------------------ */
	function initModal() {
		var modal = $('#kb-mayor-modal');
		if (!modal || typeof modal.showModal !== 'function' || storageGet('kbMayorSeen')) { return; }

		var close = function () {
			if (!modal.open || modal.classList.contains('is-closing')) { return; }
			if (reducedMotion) { modal.close(); return; }
			modal.classList.add('is-closing');
			window.setTimeout(function () {
				modal.classList.remove('is-closing');
				modal.close();
			}, 280);
		};

		modal.addEventListener('cancel', function (event) {
			event.preventDefault();
			close();
		});
		modal.addEventListener('click', function (event) {
			// Clic sur le fond (en dehors de la fenêtre).
			if (event.target === modal) { close(); }
		});
		$$('[data-modal-close]', modal).forEach(function (btn) { btn.addEventListener('click', close); });
		modal.addEventListener('close', function () { body.classList.remove('kb-lock'); });

		window.setTimeout(function () {
			storageSet('kbMayorSeen');
			body.classList.add('kb-lock');
			modal.showModal();
			var inner = $('.kb-modal__inner', modal);
			if (inner) { inner.scrollTop = 0; }
		}, reducedMotion ? 0 : 450);
	}

	/* ------------------------------------------------------------------
	 * Bande défilante : pause au survol (CSS), au toucher et via le bouton
	 * ------------------------------------------------------------------ */
	$$('.kb-ticker').forEach(function (ticker) {
		var toggle = $('.kb-ticker__toggle', ticker);
		var label = toggle ? $('.screen-reader-text', toggle) : null;
		var pausedByUser = false;

		var setPaused = function (paused) {
			ticker.classList.toggle('is-paused', paused);
			if (toggle) {
				toggle.setAttribute('aria-pressed', paused ? 'true' : 'false');
				if (label) { label.textContent = paused ? i18n.play : i18n.pause; }
			}
		};

		if (toggle) {
			toggle.addEventListener('click', function () {
				pausedByUser = !pausedByUser;
				setPaused(pausedByUser);
			});
		}

		// Au toucher : pause tant que le doigt est posé, puis reprise (sauf pause volontaire).
		var viewport = $('.kb-ticker__viewport', ticker);
		viewport.addEventListener('touchstart', function () { setPaused(true); }, { passive: true });
		viewport.addEventListener('touchend', function () {
			window.setTimeout(function () { if (!pausedByUser) { setPaused(false); } }, 1200);
		}, { passive: true });
	});

	/* ------------------------------------------------------------------
	 * Héros : diaporama en fondu + Ken Burns (images chargées à la demande)
	 * ------------------------------------------------------------------ */
	function loadSlide(slide) {
		var img = slide && $('img[data-src]', slide);
		if (!img) { return; }
		if (img.dataset.srcset) { img.srcset = img.dataset.srcset; }
		img.src = img.dataset.src;
		img.removeAttribute('data-src');
		img.removeAttribute('data-srcset');
	}

	function initHero() {
		var hero = $('[data-hero]');
		if (!hero) { return; }

		hero.style.setProperty('--kb-hero-interval', (config.heroInterval / 1000) + 's');
		hero.classList.add('is-ready');

		var slides = $$('.kb-hero__slide', hero);
		var bars = $$('.kb-hero__progress li', hero);
		var toggle = $('.kb-hero__toggle', hero);
		if (slides.length < 2) { return; }

		var current = 0;
		var timer = null;
		var paused = reducedMotion;

		loadSlide(slides[1]);

		var show = function (index) {
			var prev = slides[current];
			current = (index + slides.length) % slides.length;
			var next = slides[current];

			loadSlide(next);
			loadSlide(slides[(current + 1) % slides.length]);

			prev.classList.remove('is-active');
			prev.classList.add('is-leaving');
			prev.setAttribute('aria-hidden', 'true');
			window.setTimeout(function () { prev.classList.remove('is-leaving'); }, 1700);

			next.classList.add('is-active');
			next.setAttribute('aria-hidden', 'false');

			bars.forEach(function (bar, i) {
				bar.classList.toggle('is-active', i === current);
				bar.classList.toggle('is-done', i < current);
			});
		};

		var schedule = function () {
			window.clearTimeout(timer);
			if (!paused) { timer = window.setTimeout(function () { show(current + 1); schedule(); }, config.heroInterval); }
		};

		var setPaused = function (value) {
			paused = value;
			hero.classList.toggle('is-paused', paused);
			if (toggle) {
				toggle.setAttribute('aria-pressed', paused ? 'true' : 'false');
				$('.screen-reader-text', toggle).textContent = paused ? i18n.play : i18n.pause;
			}
			if (paused) {
				window.clearTimeout(timer);
			} else {
				// Redémarre la barre de progression proprement.
				var bar = bars[current];
				if (bar) { bar.classList.remove('is-active'); void bar.offsetWidth; bar.classList.add('is-active'); }
				schedule();
			}
		};

		if (toggle) {
			toggle.addEventListener('click', function () { setPaused(!paused); });
		}

		doc.addEventListener('visibilitychange', function () {
			if (doc.hidden) { window.clearTimeout(timer); } else if (!paused) { schedule(); }
		});

		setPaused(paused);
	}

	/* ------------------------------------------------------------------
	 * Carrousel des actualités
	 * ------------------------------------------------------------------ */
	function initCarousels() {
		$$('[data-carousel]').forEach(function (carousel) {
			var slides = $$('.kb-carousel__slide', carousel);
			var dots = $$('.kb-carousel__dot', carousel);
			var prevBtn = $('[data-carousel-prev]', carousel);
			var nextBtn = $('[data-carousel-next]', carousel);
			var toggle = $('[data-carousel-toggle]', carousel);
			var live = $('.kb-carousel__viewport', carousel);
			var delay = parseInt(carousel.getAttribute('data-interval'), 10) || 6000;
			var current = 0;
			var timer = null;
			var paused = reducedMotion;
			var hovering = false;

			if (slides.length < 2) { return; }

			var go = function (index, fromUser) {
				slides[current].classList.remove('is-active');
				slides[current].setAttribute('aria-hidden', 'true');
				slides[current].inert = true;
				dots[current] && dots[current].removeAttribute('aria-current');

				current = (index + slides.length) % slides.length;

				slides[current].classList.add('is-active');
				slides[current].setAttribute('aria-hidden', 'false');
				slides[current].inert = false;
				dots[current] && dots[current].setAttribute('aria-current', 'true');
				if (fromUser && live) { live.setAttribute('aria-live', 'polite'); }
				restart();
			};

			var restart = function () {
				window.clearTimeout(timer);
				carousel.classList.remove('is-ticking');
				if (paused || hovering) { return; }
				void carousel.offsetWidth;
				carousel.classList.add('is-ticking');
				timer = window.setTimeout(function () { go(current + 1, false); }, delay);
			};

			var setPaused = function (value) {
				paused = value;
				carousel.classList.toggle('is-paused', paused);
				if (toggle) {
					toggle.setAttribute('aria-pressed', paused ? 'true' : 'false');
					$('.screen-reader-text', toggle).textContent = paused ? i18n.play : i18n.pause;
				}
				if (live) { live.setAttribute('aria-live', paused ? 'polite' : 'off'); }
				restart();
			};

			slides.forEach(function (slide, i) { if (i !== 0) { slide.inert = true; } });
			carousel.style.setProperty('--kb-carousel-interval', delay + 'ms');

			prevBtn && prevBtn.addEventListener('click', function () { go(current - 1, true); });
			nextBtn && nextBtn.addEventListener('click', function () { go(current + 1, true); });
			dots.forEach(function (dot, i) { dot.addEventListener('click', function () { go(i, true); }); });
			toggle && toggle.addEventListener('click', function () { setPaused(!paused); });

			carousel.addEventListener('mouseenter', function () { hovering = true; restart(); });
			carousel.addEventListener('mouseleave', function () { hovering = false; restart(); });
			carousel.addEventListener('focusin', function () { hovering = true; restart(); });
			carousel.addEventListener('focusout', function (event) {
				if (!carousel.contains(event.relatedTarget)) { hovering = false; restart(); }
			});
			carousel.addEventListener('keydown', function (event) {
				if (event.key === 'ArrowLeft') { go(current - 1, true); }
				if (event.key === 'ArrowRight') { go(current + 1, true); }
			});

			// Balayage tactile.
			var startX = null;
			carousel.addEventListener('touchstart', function (event) { startX = event.touches[0].clientX; }, { passive: true });
			carousel.addEventListener('touchend', function (event) {
				if (startX === null) { return; }
				var dx = event.changedTouches[0].clientX - startX;
				if (Math.abs(dx) > 45) { go(current + (dx < 0 ? 1 : -1), true); }
				startX = null;
			}, { passive: true });

			setPaused(paused);
		});
	}

	/* ------------------------------------------------------------------
	 * Chiffres clés animés à l'apparition
	 * ------------------------------------------------------------------ */
	function animateCounter(el) {
		var target = parseFloat(el.getAttribute('data-count'));
		var decimals = parseInt(el.getAttribute('data-decimals'), 10) || 0;
		var formatter = new Intl.NumberFormat(root.lang || 'fr-FR', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
		var duration = 1800;
		var start = null;

		var step = function (time) {
			if (!start) { start = time; }
			var progress = Math.min((time - start) / duration, 1);
			var eased = 1 - Math.pow(1 - progress, 4);
			el.textContent = formatter.format(target * eased);
			if (progress < 1) { window.requestAnimationFrame(step); }
		};
		window.requestAnimationFrame(step);
	}

	/* ------------------------------------------------------------------
	 * Apparition au défilement
	 * ------------------------------------------------------------------ */
	function initReveal() {
		var items = $$('[data-reveal]');
		var counters = $$('[data-count]');

		if (!('IntersectionObserver' in window) || reducedMotion) {
			items.forEach(function (el) { el.classList.add('is-revealed'); });
			return;
		}

		root.classList.add('kb-reveal-ready');

		var observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (!entry.isIntersecting) { return; }
				entry.target.classList.add('is-revealed');
				observer.unobserve(entry.target);
			});
		}, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

		items.forEach(function (el) {
			var group = el.parentElement ? el.parentElement.querySelectorAll(':scope > [data-reveal]') : [];
			var index = Array.prototype.indexOf.call(group, el);
			if (index > 0) { el.style.setProperty('--kb-reveal-delay', Math.min(index, 6) * 90 + 'ms'); }
			observer.observe(el);
		});

		var counterObserver = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (!entry.isIntersecting) { return; }
				animateCounter(entry.target);
				counterObserver.unobserve(entry.target);
			});
		}, { threshold: 0.6 });
		counters.forEach(function (el) { counterObserver.observe(el); });
	}

	/* ------------------------------------------------------------------
	 * Mot du Maire : « Lire la suite » (mobile)
	 * ------------------------------------------------------------------ */
	$$('[data-readmore]').forEach(function (button) {
		var target = doc.getElementById(button.getAttribute('aria-controls'));
		if (!target) { return; }
		var label = $('span', button);
		button.addEventListener('click', function () {
			var expanded = button.getAttribute('aria-expanded') === 'true';
			button.setAttribute('aria-expanded', expanded ? 'false' : 'true');
			target.classList.toggle('is-expanded', !expanded);
			if (label) { label.textContent = expanded ? i18n.readMore : i18n.readLess; }
			if (expanded) { target.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' }); }
		});
	});

	/* ------------------------------------------------------------------
	 * Partage : copie du lien / partage natif
	 * ------------------------------------------------------------------ */
	$$('.kb-share__copy').forEach(function (button) {
		button.addEventListener('click', function () {
			var url = button.getAttribute('data-copy');
			var status = button.closest('.kb-share').querySelector('.kb-share__status');
			var done = function () {
				if (status) {
					status.textContent = i18n.copied;
					window.setTimeout(function () { status.textContent = ''; }, 2500);
				}
			};
			if (navigator.share && window.matchMedia('(pointer: coarse)').matches) {
				navigator.share({ title: button.getAttribute('data-share-title'), url: url }).catch(function () {});
			} else if (navigator.clipboard) {
				navigator.clipboard.writeText(url).then(done, function () {});
			}
		});
	});

	/* ------------------------------------------------------------------
	 * Démarrage
	 * ------------------------------------------------------------------ */
	doc.addEventListener('kb:introdone', function () {
		initHero();
		initModal();
	}, { once: true });

	initReveal();
	initCarousels();
	runIntro();
})();
