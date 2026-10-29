/* JWS kolačići: logika obavijesti o kolačićima. Izgled je Bricks popup sajta, ugrađena traka plugina (bilo koji builder)
   ili vlastiti HTML sajta; skripta ga otvara dok posjetilac ne izabere i čita samo data-atribute (prefiks data-jwsk, ili stariji data-dvk):
     [data-jwsk-traka]                    omotač u popupu (dobija klasu is-postavke kad su postavke otvorene)
     [data-jwsk="sve|nuzni|postavke|sacuvaj|otvori"]  dugmad
     [data-jwsk-switch="statistika|marketing"]        prekidači (role="switch", aria-checked)
     [data-jwsk-grupa="statistika|marketing"]         red grupe; sakriva se ako grupa nije uključena u postavkama
     a[href$="#kolacici"]                 link „Postavke kolačića" (npr. stavka WP menija u podnožju)
   Izbor: kolačić jws_kolacici = sve | nuzni | stat | mark, kolačić jws_kolacici_v = važeća verzija.
   Ide i u evidenciju pristanaka, u Google Consent Mode (update + dataLayer jws_consent_update) i kao događaj jws:kolacici
   (detail.grupe, detail.izbor). Google (GA4 ili GTM) i Meta Pixel se učitavaju tek uz saglasnost.
   Consent Mode default i podatke (window.jwsKolaciciPodaci) postavlja <head> (inc/frontend.php).
   Javno: window.jwsKolacici.dozvoljeno(g), .kad(g, fn), .otvori() */
(function () {
	var D = window.jwsKolaciciPodaci || {};
	var GRUPE = D.grupe || [];
	var K = 'jws_kolacici', KV = 'jws_kolacici_v', KID = 'jws_kolacici_id';
	var IZBORI = { sve: ['statistika', 'marketing'], stat: ['statistika'], mark: ['marketing'], nuzni: [] };
	var cekaju = [];
	window.dataLayer = window.dataLayer || [];
	window.gtag = window.gtag || function () { dataLayer.push(arguments); };

	/* --- Atributi: data-jwsk* ili data-dvk* --- */
	function sel(dio, v) {
		var x = v ? '="' + v + '"' : '';
		return '[data-jwsk' + dio + x + '],[data-dvk' + dio + x + ']';
	}
	function atr(el, dio) { var v = el.getAttribute('data-jwsk' + dio); return v !== null ? v : el.getAttribute('data-dvk' + dio); }

	/* --- Kolačići --- */
	function kolacic(n) { var m = document.cookie.match('(?:^|; )' + n + '=([^;]*)'); return m ? decodeURIComponent(m[1]) : null; }
	function upisi(n, v) {
		document.cookie = n + '=' + encodeURIComponent(v) + '; Max-Age=' + (D.dana || 180) * 86400 + '; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
	}
	function obrisi(n) {
		var dom = location.hostname.replace(/^www\./, '');
		document.cookie = n + '=; Max-Age=0; Path=/';
		document.cookie = n + '=; Max-Age=0; Path=/; Domain=' + location.hostname;
		document.cookie = n + '=; Max-Age=0; Path=/; Domain=.' + dom;
	}
	// Briše kolačiće po imenima; zvjezdica na kraju = prefiks
	function obrisiImena(imena) {
		var postoje = document.cookie ? document.cookie.split('; ').map(function (c) { return c.split('=')[0]; }) : [];
		(imena || []).forEach(function (ime) {
			if (ime.slice(-1) === '*') {
				var pre = ime.slice(0, -1);
				postoje.forEach(function (n) { if (n.indexOf(pre) === 0) obrisi(n); });
			} else if (postoje.indexOf(ime) > -1) obrisi(ime);
		});
	}
	function noviId() {
		if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
		return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) { var r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 3 | 8)).toString(16); });
	}

	// null = nema važećeg izbora (nikad birao ili se verzija promijenila), inače niz prihvaćenih i uključenih grupa
	function izbor() {
		var v = kolacic(K);
		if (kolacic(KV) !== D.verzija || !IZBORI.hasOwnProperty(v)) return null;
		return IZBORI[v].filter(function (g) { return GRUPE.indexOf(g) > -1; });
	}
	function izGrupa(g) {
		var s = g.indexOf('statistika') > -1, m = g.indexOf('marketing') > -1;
		return s && m ? 'sve' : s ? 'stat' : m ? 'mark' : 'nuzni';
	}
	function dozvoljeno(g) { if (g === 'neophodni') return true; var iz = izbor(); return !!iz && iz.indexOf(g) > -1; }

	// Pokreće fn odmah ako je grupa dozvoljena, inače kad posjetilac dozvoli (bez osvježavanja stranice)
	function kad(g, fn) { if (dozvoljeno(g)) fn(); else cekaju.push([g, fn]); }
	function pokreniCekaju() {
		cekaju = cekaju.filter(function (c) { if (dozvoljeno(c[0])) { try { c[1](); } catch (e) {} return false; } return true; });
	}

	/* --- Google: GA4 (gtag) ILI Tag Manager. Basic: učitava se tek uz saglasnost. Advanced (opcija): odmah, uz default denied. --- */
	var G = D.google;
	function googleStanje() {
		var s = function (g) { return dozvoljeno(g) ? 'granted' : 'denied'; };
		return { analytics_storage: s('statistika'), ad_storage: s('marketing'), ad_user_data: s('marketing'), ad_personalization: s('marketing') };
	}
	function googleSmije() {
		if (!G) return false;
		if (G.napredno) return true;
		return G.nacin === 'gtm' ? dozvoljeno('statistika') || dozvoljeno('marketing') : dozvoljeno('statistika');
	}
	function googleUcitaj() {
		if (!googleSmije() || window.jwskGoogle) return; window.jwskGoogle = true;
		var s = document.createElement('script'); s.async = true;
		if (G.nacin === 'gtm') {
			dataLayer.push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
			s.src = 'https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(G.id);
		} else {
			s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(G.id);
			gtag('js', new Date());
			gtag('config', G.id);
		}
		document.head.appendChild(s);
	}
	// Događaji prodavnice (inc/ga4.php): tek uz Statistiku; GTM ih dobija kao standardni ecommerce dataLayer
	function googleDogadjaj(ime, podaci) {
		if (G.nacin === 'gtm') { dataLayer.push({ ecommerce: null }); dataLayer.push({ event: ime, ecommerce: podaci }); }
		else gtag('event', ime, podaci);
	}
	function googleAtc() {
		var v = kolacic('jws_ga_atc'); if (!v) return;
		obrisi('jws_ga_atc');
		try { googleDogadjaj('add_to_cart', JSON.parse(v)); } catch (e) {}
	}
	if (G) kad('statistika', function () {
		googleUcitaj();
		(G.ev || []).forEach(function (e) { googleDogadjaj(e[0], e[1]); });
		googleAtc();
		if (window.jQuery) jQuery(document.body).on('added_to_cart', function () { setTimeout(googleAtc, 50); });
	});

	/* --- Meta Pixel: tek uz marketing. Događaji stranice dolaze iz PHP-a; prazan event_id (artikal) pravi preglednik,
	   jer keširana stranica ne smije dijeliti isti ID među posjetiocima. Dodavanje u korpu čita kratki kolačić jws_meta_atc
	   koji je server postavio sa istim event_id koji je otišao na Conversions API. --- */
	var M = D.meta;
	function metaAtc() {
		var v = kolacic('jws_meta_atc'); if (!v || !window.fbq) return;
		obrisi('jws_meta_atc');
		try { var a = JSON.parse(v); fbq('track', 'AddToCart', a.data, { eventID: a.eid }); } catch (e) {}
	}
	function metaUcitaj() {
		if (!M || window.jwskMeta) return; window.jwskMeta = true;
		!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
		fbq('consent', 'grant');
		fbq('init', M.id);
		fbq('track', 'PageView');
		(M.ev || []).forEach(function (e) { fbq('track', e[0], e[2], { eventID: e[1] || e[0].toLowerCase() + '_' + noviId() }); });
		metaAtc();
		if (window.jQuery) jQuery(document.body).on('added_to_cart', function () { setTimeout(metaAtc, 50); });
	}

	// Kolačići prethodne trake (Complianz cmplz_*, stara verzija iz child teme) ne smiju ostati
	obrisiImena(D.stari);
	// Advanced način, ili vraćeni posjetilac (kad() iznad ga je možda već učitao). Tek poslije load događaja: gtag (177 KB) ne smije da se nadmeće sa LCP slikom i fontovima.
	if (document.readyState === 'complete') googleUcitaj(); else window.addEventListener('load', googleUcitaj, { once: true });
	if (M) kad('marketing', metaUcitaj);

	function izvorNarudzbe(da) {
		var w = window.wc_order_attribution;
		if (w && typeof w.setOrderTracking === 'function') w.setOrderTracking(da);
	}

	/* --- Popup --- */
	// Ugrađena traka ima svoju klasu; tako je ne zamijeni neki drugi [data-jwsk-traka] (npr. stari Bricks popup koji je ostao na sajtu)
	function traka() { return (D.prikaz === 'ugradjena' && document.querySelector('.jwsk-traka[data-jwsk-traka]')) || document.querySelector(sel('-traka')); }
	function osvjezi() {
		var t = traka(); if (!t) return;
		var iz = izbor() || [];
		t.querySelectorAll(sel('-switch')).forEach(function (s) {
			s.setAttribute('aria-checked', iz.indexOf(atr(s, '-switch')) > -1 ? 'true' : 'false');
		});
		t.querySelectorAll(sel('-grupa')).forEach(function (r) {
			var g = atr(r, '-grupa');
			r.hidden = g !== 'neophodni' && GRUPE.indexOf(g) < 0;
		});
		var p = t.querySelector(sel('', 'postavke'));
		if (p) p.setAttribute('aria-expanded', t.classList.contains('is-postavke') ? 'true' : 'false');
	}
	/* Prikaz: bricks = Bricks popup (bricksOpenPopup); ugradjena (traka iz plugina) i vlastiti (element sajta sa
	   data-jwsk-traka, bilo koji builder) = plugin sam skida i vraća atribut hidden */
	function otvori(postavke) {
		if (D.prikaz === 'bricks') {
			if (typeof window.bricksOpenPopup !== 'function' || !D.popup) return;
			window.bricksOpenPopup(D.popup);
		} else {
			var el = traka(); if (!el) return;
			el.hidden = false;
		}
		var t = traka();
		if (t) t.classList.toggle('is-postavke', !!postavke);
		osvjezi();
	}
	function zatvori() {
		if (D.prikaz === 'bricks') {
			if (typeof window.bricksClosePopup === 'function' && D.popup) window.bricksClosePopup(D.popup);
		} else {
			var el = traka(); if (el) { el.hidden = true; el.classList.remove('is-postavke'); }
		}
	}

	function izaberi(grupe) {
		var prije = izbor() || [];
		grupe = grupe.filter(function (g) { return GRUPE.indexOf(g) > -1; });
		var vrijednost = izGrupa(grupe);
		var id = kolacic(KID) || noviId();
		upisi(K, vrijednost);
		upisi(KV, D.verzija);
		upisi(KID, id);
		if (D.ajax && window.fetch) {
			fetch(D.ajax, { method: 'POST', credentials: 'same-origin', keepalive: true,
				body: new URLSearchParams({ action: 'jws_kolacici', cid: id, izbor: vrijednost }) }).catch(function () {});
		}
		var st = googleStanje();
		gtag('consent', 'update', st);
		dataLayer.push({ event: 'jws_consent_update', jws_statistika: st.analytics_storage, jws_marketing: st.ad_storage });
		if (D.woo) izvorNarudzbe(grupe.indexOf(D.woo) > -1);
		GRUPE.forEach(function (g) { if (grupe.indexOf(g) < 0) obrisiImena(D.brisi && D.brisi[g]); });
		if (window.fbq && grupe.indexOf('marketing') < 0) fbq('consent', 'revoke');
		document.dispatchEvent(new CustomEvent('jws:kolacici', { detail: { grupe: grupe.slice(), izbor: vrijednost } }));
		googleUcitaj();
		pokreniCekaju();
		zatvori();
		// Povučena saglasnost: skripta je već učitana na ovoj stranici, pa je osvježavanje jedini način da je nema
		if (prije.some(function (g) { return grupe.indexOf(g) < 0; })) location.reload();
	}

	document.addEventListener('click', function (e) {
		var sw = e.target.closest(sel('-switch'));
		if (sw) { sw.setAttribute('aria-checked', sw.getAttribute('aria-checked') === 'true' ? 'false' : 'true'); return; }
		var l = e.target.closest('a[href$="#kolacici"]');
		if (l) { e.preventDefault(); otvori(true); return; }
		var b = e.target.closest(sel(''));
		if (!b) return;
		var a = atr(b, '');
		if (a === 'sve') izaberi(GRUPE.slice());
		else if (a === 'nuzni') izaberi([]);
		else if (a === 'postavke') { var t = traka(); if (t) { t.classList.toggle('is-postavke'); osvjezi(); } }
		else if (a === 'sacuvaj') {
			var g = [];
			((b.closest(sel('-traka')) || document)).querySelectorAll(sel('-switch')).forEach(function (s) {
				if (s.getAttribute('aria-checked') === 'true') g.push(atr(s, '-switch'));
			});
			izaberi(g);
		}
		else if (a === 'otvori') { e.preventDefault(); otvori(true); }
	});
	// Prekidač tastaturom (Space/Enter) ako u Bricksu nije <button>
	document.addEventListener('keydown', function (e) {
		var sw = e.target.closest && e.target.closest(sel('-switch'));
		if (sw && sw.tagName !== 'BUTTON' && (e.key === ' ' || e.key === 'Enter')) { e.preventDefault(); sw.click(); }
	});

	window.jwsKolacici = { dozvoljeno: dozvoljeno, kad: kad, otvori: function () { otvori(true); } };

	function start() { if (!izbor()) otvori(false); }
	if (document.readyState === 'complete') setTimeout(start, 0);
	else window.addEventListener('load', start);
})();
