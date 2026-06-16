/* global window, document, navigator */
(function () {
	'use strict';

	var CFG = window.NAASE_APP || {};
	var root = document.getElementById('naase-app');
	if (!root || !CFG.restUrl) {
		return;
	}

	var state = {
		token: null,
		total: CFG.total || 12,
		clientStart: 0,
		selected: null,
		currentQuestionId: null,
		timer: null,
		done: false
	};

	/* ---------------- helpers ---------------- */

	function screen(name) {
		return root.querySelector('[data-screen="' + name + '"]');
	}

	function show(name) {
		root.querySelectorAll('.naase-screen').forEach(function (s) {
			s.classList.remove('is-active');
			s.hidden = true;
		});
		var el = screen(name);
		if (el) {
			el.hidden = false;
			el.classList.add('is-active');
		}
		if (window.scrollTo) {
			var top = root.getBoundingClientRect().top + window.pageYOffset - 20;
			window.scrollTo({ top: top, behavior: 'smooth' });
		}
	}

	function api(path, body) {
		return fetch(CFG.restUrl + path, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': CFG.nonce
			},
			credentials: 'same-origin',
			body: JSON.stringify(body || {})
		}).then(function (res) {
			return res.json().then(function (data) {
				if (!res.ok) {
					var msg = (data && data.message) ? data.message : 'Something went wrong.';
					throw new Error(msg);
				}
				return data;
			});
		});
	}

	function esc(s) {
		var d = document.createElement('div');
		d.textContent = s == null ? '' : String(s);
		return d.innerHTML;
	}

	// Escape everything, then re-allow only bare <em>/<i> emphasis. Because the
	// whole string is escaped first, any tag with attributes (e.g. <em onclick>)
	// stays escaped and cannot inject — only the exact bare tags are restored.
	function escRich(s) {
		return esc(s)
			.replace(/&lt;(\/?)(em|i)&gt;/gi, '<$1$2>');
	}

	function fmtTime(totalSeconds) {
		totalSeconds = Math.max(0, Math.floor(totalSeconds));
		var h = Math.floor(totalSeconds / 3600);
		var m = Math.floor((totalSeconds % 3600) / 60);
		var s = totalSeconds % 60;
		var mm = (m < 10 && h > 0 ? '0' : '') + m;
		var ss = (s < 10 ? '0' : '') + s;
		return (h > 0 ? h + ':' : '') + mm + ':' + ss;
	}

	function elapsed() {
		return (Date.now() - state.clientStart) / 1000;
	}

	/* ---------------- session persistence (revive) ---------------- */

	var SESSION_KEY = 'naase_session';

	function saveSession(token) {
		try { window.localStorage.setItem(SESSION_KEY, token); } catch (e) { /* noop */ }
	}

	function loadSession() {
		try { return window.localStorage.getItem(SESSION_KEY); } catch (e) { return null; }
	}

	function clearSession() {
		try { window.localStorage.removeItem(SESSION_KEY); } catch (e) { /* noop */ }
	}

	/* ---------------- timer ---------------- */

	function startTimer() {
		stopTimer();
		state.timer = window.setInterval(function () {
			var e = elapsed();
			var node = root.querySelector('.naase-side-time');
			if (node) { node.textContent = fmtTime(e); }
			if (e >= (CFG.timeoutSeconds || 3600)) {
				handleTimeout();
			}
		}, 1000);
	}

	function stopTimer() {
		if (state.timer) {
			window.clearInterval(state.timer);
			state.timer = null;
		}
	}

	function handleTimeout() {
		stopTimer();
		if (state.token && !state.done) {
			beacon('abandon', { token: state.token, reason: 'timeout' });
		}
		state.done = true;
		clearSession();
		show('timeout');
	}

	function beacon(path, body) {
		try {
			var blob = new Blob([JSON.stringify(body)], { type: 'application/json' });
			if (navigator.sendBeacon) {
				navigator.sendBeacon(CFG.restUrl + path + '?_wpnonce=' + encodeURIComponent(CFG.nonce), blob);
				return;
			}
		} catch (e) { /* noop */ }
		api(path, body).catch(function () {});
	}

	/* ---------------- start ---------------- */

	function start() {
		setBusy('start', true);
		api('start', {}).then(function (data) {
			state.token = data.token;
			state.total = data.question.total || state.total;
			state.clientStart = Date.now();
			state.done = false;
			saveSession(data.token);
			renderQuestion(data.question);
			startTimer();
		}).catch(function (err) {
			showError(err.message);
		}).finally(function () {
			setBusy('start', false);
		});
	}

	function setBusy(action, busy) {
		root.querySelectorAll('[data-action="' + action + '"]').forEach(function (b) {
			b.disabled = !!busy;
		});
	}

	function showError(msg) {
		var el = screen('error');
		var p = el ? el.querySelector('.naase-error-text') : null;
		if (p) { p.textContent = msg || 'Something went wrong.'; }
		show('error');
	}

	/* ---------------- question ---------------- */

	function renderQuestion(q) {
		state.selected = null;
		state.currentQuestionId = q.id;

		var pct = Math.round((q.number - 1) / q.total * 100);
		var letters = ['A', 'B', 'C', 'D'];

		var optionsHtml = letters.map(function (l) {
			return '' +
				'<button type="button" class="naase-option" data-letter="' + l + '">' +
				'<span class="naase-option-letter">' + l + '</span>' +
				'<span class="naase-option-text">' + escRich(q.answers[l]) + '</span>' +
				'<span class="naase-option-radio" aria-hidden="true"></span>' +
				'</button>';
		}).join('');

		var sideContext = '';
		if (q.helpful_context) {
			sideContext = '' +
				'<div class="naase-side-card naase-side-card--context">' +
				'<div class="naase-side-context-head"><span class="naase-side-ico naase-side-ico--info"></span> Helpful context</div>' +
				'<div class="naase-side-context-body">' + escRich(q.helpful_context) + '</div>' +
				'</div>';
		}

		var sideArea = '';
		if (q.knowledge_area) {
			sideArea = '' +
				'<div class="naase-side-card">' +
				'<span class="naase-side-ico naase-side-ico--book"></span>' +
				'<div><div class="naase-side-value">' + esc(q.knowledge_area) + '</div>' +
				'<div class="naase-side-label">Knowledge area</div></div>' +
				'</div>';
		}

		var html = '' +
			'<div class="naase-q">' +
			'<div class="naase-q-main">' +
			'<div class="naase-q-progress">' +
			'<span class="naase-q-count">Question ' + q.number + ' of ' + q.total + '</span>' +
			'<span class="naase-bar"><span class="naase-bar-fill" style="width:' + pct + '%"></span></span>' +
			'</div>' +
			'<h2 class="naase-q-text">' + escRich(q.question) + '</h2>' +
			'<div class="naase-options">' + optionsHtml + '</div>' +
			'</div>' +
			'<aside class="naase-q-side">' +
			'<div class="naase-side-card">' +
			'<span class="naase-side-ico naase-side-ico--clock"></span>' +
			'<div><div class="naase-side-time">' + fmtTime(elapsed()) + '</div>' +
			'<div class="naase-side-label">Elapsed Time</div></div>' +
			'</div>' +
			sideArea +
			sideContext +
			'<button type="button" class="naase-btn naase-btn--primary naase-q-next" data-action="next" disabled>Next Question</button>' +
			'</aside>' +
			'</div>';

		var el = screen('question');
		el.innerHTML = html;
		show('question');

		el.querySelectorAll('.naase-option').forEach(function (opt) {
			opt.addEventListener('click', function () {
				el.querySelectorAll('.naase-option').forEach(function (o) { o.classList.remove('is-selected'); });
				opt.classList.add('is-selected');
				state.selected = opt.getAttribute('data-letter');
				var nextBtn = el.querySelector('[data-action="next"]');
				if (nextBtn) { nextBtn.disabled = false; }
			});
		});

		var btn = el.querySelector('[data-action="next"]');
		if (btn) { btn.addEventListener('click', submitAnswer); }
	}

	function submitAnswer() {
		if (!state.selected) { return; }
		var btn = screen('question').querySelector('[data-action="next"]');
		if (btn) { btn.disabled = true; }

		api('answer', {
			token: state.token,
			question_id: state.currentQuestionId,
			choice: state.selected
		}).then(function (data) {
			if (data.status === 'timeout') {
				handleTimeout();
			} else if (data.status === 'completed') {
				stopTimer();
				state.done = true;
				clearSession();
				renderForm(data.result);
			} else {
				renderQuestion(data.question);
			}
		}).catch(function (err) {
			if (btn) { btn.disabled = false; }
			showError(err.message);
		});
	}

	/* ---------------- contact form ---------------- */

	function renderForm(result) {
		var html = '' +
			'<div class="naase-screen-inner naase-text-center">' +
			'<h1 class="naase-title-completing naase-title--md">Thank you for completing the challenge</h1>' +
			'<div class="naase-result-strip">' +
			stat('result', 'Your result', result.score + '/' + result.total) +
			stat('tiers', 'Tier', result.tier) +
			stat('clock', 'Time', result.duration_text) +
			'</div>' +
			'<form class="naase-form" novalidate>' +
			'<p class="naase-form-intro">' + esc(CFG.settings.postCompletion) + '</p>' +
			'<div class="naase-form-error" hidden></div>' +
			'<input class="naase-field" type="text" name="first_name" placeholder="First Name" autocomplete="given-name" required>' +
			'<input class="naase-field" type="text" name="last_name" placeholder="Last Name" autocomplete="family-name" required>' +
			'<input class="naase-field" type="email" name="email" placeholder="Email" autocomplete="email" required>' +
			'<input class="naase-hp" type="text" name="company_website" tabindex="-1" autocomplete="off" aria-hidden="true">' +
			'<label class="naase-check"><input type="checkbox" name="join_leaderboard" checked> Join the Leaderboard</label>' +
			'<label class="naase-check"><input type="checkbox" name="membership_interest"> I’m interested in free NAASE associate membership</label>' +
			'<input class="naase-field naase-linkedin" type="url" name="linkedin" placeholder="LinkedIn Profile URL" hidden>' +
			'<div class="naase-actions"><button type="submit" class="naase-btn naase-btn--primary">Receive my badge and full results</button></div>' +
			'<div class="naase-privacy"><img class="naase-privacy-ico" src="' + lockIcon() + '" alt="" aria-hidden="true"><span>' + esc(CFG.settings.privacyText) + '</span></div>' +
			'</form>' +
			'</div>';

		var el = screen('form');
		el.innerHTML = html;
		show('form');

		var form = el.querySelector('form');
		var membership = form.querySelector('[name="membership_interest"]');
		var linkedin = form.querySelector('.naase-linkedin');

		membership.addEventListener('change', function () {
			linkedin.hidden = !membership.checked;
			linkedin.required = membership.checked;
		});

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			submitForm(form, result);
		});
	}

	function stat(icon, label, value) {
		var svg = sideStatIcon(icon);
		return '' +
			'<div class="naase-stat">' + svg +
			'<div><div class="naase-stat-label">' + esc(label) + '</div>' +
			'<div class="naase-stat-value">' + esc(value) + '</div></div></div>';
	}

	function sideStatIcon(name) {
		var color = '%23127edc';
		var paths = {
			result: "%3Cpath d='M15 32.078L9.376 39L6 36.692M15 20.538L9.376 27.462L6 25.154M15 9L9.376 15.924L6 13.616M22 35H42M22 24H42M22 13H42' stroke='" + color + "' stroke-width='3' stroke-linecap='round' stroke-linejoin='round' fill='none'/%3E",
			tiers: "%3Cpath d='M33.4995 42.5H42.4995V5.5H33.4995V11.5M33.4995 42.5H24.1885M33.4995 42.5V11.5M24.1885 42.5H14.8774M24.1885 42.5V17.5M14.8774 42.5V23.5M14.8774 42.5H5.56641V23.5H14.8774M14.8774 23.5V17.5H24.1885M33.4995 11.5H24.1885V17.5' stroke='" + color + "' stroke-width='3' stroke-linejoin='round' fill='none'/%3E",
			clock: "%3Cpath d='M24 12V24L32 20' stroke='" + color + "' stroke-width='3' stroke-linecap='round' stroke-linejoin='round' fill='none'/%3E%3Cpath d='M42 24C42 26.3638 41.5344 28.7044 40.6298 30.8883C39.7252 33.0722 38.3994 35.0565 36.7279 36.7279C35.0565 38.3994 33.0722 39.7252 30.8883 40.6298C28.7044 41.5344 26.3638 42 24 42C21.6362 42 19.2956 41.5344 17.1117 40.6298C14.9278 39.7252 12.9435 38.3994 11.2721 36.7279C9.60062 35.0565 8.27475 33.0722 7.37017 30.8883C6.46558 28.7044 6 26.3638 6 24C6 19.2261 7.89642 14.6477 11.2721 11.2721C14.6477 7.89642 19.2261 6 24 6C28.7739 6 33.3523 7.89642 36.7279 11.2721C40.1036 14.6477 42 19.2261 42 24Z' stroke='" + color + "' stroke-width='3' stroke-linecap='round' stroke-linejoin='round' fill='none'/%3E"
		};
		var svg = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 48 48'%3E" + (paths[name] || '') + '%3C/svg%3E';
		return '<img class="naase-stat-ico" src="' + svg + '" alt="" />';
	}

	function lockIcon() {
		var d = "M8 10V8C8 5.239 9.239 3 12 3C14.761 3 16 5.239 16 8V10M12 14V17M3.5 17.8V13.2C3.5 12.08 3.5 11.52 3.718 11.093C3.90957 10.7163 4.21554 10.41 4.592 10.218C5.02 10.001 5.58 10.001 6.7 10.001H17.3C18.42 10.001 18.98 10.001 19.408 10.218C19.7843 10.4097 20.0903 10.7157 20.282 11.092C20.5 11.52 20.5 12.08 20.5 13.2V17.8C20.5 18.92 20.5 19.48 20.282 19.908C20.0903 20.2843 19.7843 20.5903 19.408 20.782C18.98 21 18.42 21 17.3 21H6.7C5.58 21 5.02 21 4.592 20.782C4.21569 20.5903 3.90974 20.2843 3.718 19.908C3.5 19.481 3.5 18.921 3.5 17.8Z";
		return "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='" + d + "' stroke='%23333' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round' fill='none'/%3E%3C/svg%3E";
	}

	function submitForm(form, result) {
		var errBox = form.querySelector('.naase-form-error');
		errBox.hidden = true;
		var btn = form.querySelector('button[type="submit"]');
		btn.disabled = true;

		var payload = {
			token: state.token,
			first_name: form.first_name.value,
			last_name: form.last_name.value,
			email: form.email.value,
			join_leaderboard: form.join_leaderboard.checked ? 1 : 0,
			membership_interest: form.membership_interest.checked ? 1 : 0,
			linkedin: form.linkedin.value,
			company_website: form.company_website.value
		};

		api('submit-form', payload).then(function (data) {
			window.location.href = data.result_url;
		}).catch(function (err) {
			btn.disabled = false;
			errBox.textContent = err.message;
			errBox.hidden = false;
		});
	}

	/* ---------------- session revive (on return) ---------------- */

	// We deliberately do NOT abandon the attempt when the page is left: the token is
	// persisted in localStorage so a returning visitor can pick up where they left off.
	// Sessions that are never resumed are closed server-side — immediately when touched
	// (the timeout check in /resume and /answer) and otherwise by the hourly cron sweep.
	function revive() {
		var token = loadSession();
		if (!token) { return; }

		api('resume', { token: token }).then(function (data) {
			if (data.status === 'in_progress') {
				state.token = token;
				state.total = (data.question && data.question.total) || state.total;
				state.clientStart = Date.now() - (data.elapsed || 0) * 1000;
				state.done = false;
				renderQuestion(data.question);
				startTimer();
			} else if (data.status === 'timeout') {
				clearSession();
				state.done = true;
				show('timeout');
			} else if (data.status === 'completed' && data.result_url) {
				clearSession();
				window.location.href = data.result_url;
			} else {
				clearSession(); // gone → start screen (already active)
			}
		}).catch(function () {
			clearSession();
		});
	}

	/* ---------------- wire start buttons ---------------- */

	root.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-action="start"]');
		if (btn) {
			e.preventDefault();
			start();
		}
	});

	revive();
})();
