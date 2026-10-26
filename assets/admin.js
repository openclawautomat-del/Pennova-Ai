(function () {
	'use strict';
	var C = window.AIAP || {};

	function $(s, r) { return (r || document).querySelector(s); }
	function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
	function esc(s) { var d = document.createElement('div'); d.textContent = (s === null || s === undefined) ? '' : s; return d.innerHTML; }

	function post(data) {
		var fd = new FormData();
		fd.append('nonce', C.nonce);
		Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
		return fetch(C.ajax, { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(function (r) { return r.text(); })
			.then(function (t) {
				try { return JSON.parse(t); }
				catch (e) { return { success: false, data: 'Server ka jawab theek nahi (timeout ho sakta hai): ' + t.slice(0, 160) }; }
			})
			.catch(function (e) { return { success: false, data: 'Request fail: ' + e.message }; });
	}

	function collect(form) {
		var o = {};
		$$('[name]', form).forEach(function (el) {
			if (el.type === 'checkbox') { o[el.name] = el.checked ? 1 : 0; }
			else if (el.type === 'radio') { if (el.checked) { o[el.name] = el.value; } }
			else { o[el.name] = el.value; }
		});
		return o;
	}

	function logItem(box, cls, html) {
		var empty = $('.pn-empty', box);
		if (empty) { empty.remove(); }
		var d = document.createElement('div');
		d.className = 'pn-item ' + cls;
		d.innerHTML = html;
		box.appendChild(d);
		box.scrollTop = box.scrollHeight;
		return d;
	}

	/* ---------- Settings page ---------- */
	var panes = $$('.pn-pane');
	if (panes.length) {
		var tabs = $$('.pn-subnav button');
		var openPane = function (id) {
			if (!$('#pane-' + id)) { id = 'provider'; }
			panes.forEach(function (p) { p.classList.toggle('on', p.id === 'pane-' + id); });
			tabs.forEach(function (t) { t.classList.toggle('on', t.getAttribute('data-pane') === id); });
			if (history.replaceState) { history.replaceState(null, '', '#' + id); }
		};
		tabs.forEach(function (t) { t.addEventListener('click', function () { openPane(t.getAttribute('data-pane')); }); });
		openPane((location.hash || '').replace('#', '') || 'provider');

		var radios = $$('input[name="aiap_settings[provider]"]');
		var curProv = function () { var r = radios.filter(function (c) { return c.checked; })[0]; return r ? r.value : 'openai'; };
		var showProv = function () { var p = curProv(); $$('.aiap-prov').forEach(function (b) { b.style.display = (b.getAttribute('data-p') === p) ? '' : 'none'; }); };
		radios.forEach(function (r) { r.addEventListener('change', showProv); });
		showProv();

		var nf = function (n) { var el = document.querySelector('[name="aiap_settings[' + n + ']"]'); return el ? el.value : ''; };
		var overrides = function (p) {
			return { o_provider: p, o_key: nf('keys][' + p), o_model: nf('models][' + p), o_base: nf('custom_base'), o_format: nf('custom_format') };
		};
		var show = function (id, r) { $(id).innerHTML = r.success ? '✅ ' + esc(r.data) : '<span class="pn-err">❌ ' + esc(r.data) + '</span>'; };

		$('#aiap-test').addEventListener('click', function () {
			$('#aiap-test-out').textContent = 'Test ho raha hai (reasoning models mein thora time lag sakta hai)...';
			post(Object.assign({ action: 'aiap_test' }, overrides(curProv()))).then(function (r) { show('#aiap-test-out', r); });
		});

		$$('.aiap-fetch').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var p = btn.getAttribute('data-p');
				var old = btn.textContent;
				btn.textContent = '...';
				post(Object.assign({ action: 'aiap_models' }, overrides(p))).then(function (r) {
					btn.textContent = old;
					if (!r.success) { alert(r.data); return; }
					var dl = document.getElementById('aiap-models-' + p);
					dl.innerHTML = '';
					r.data.forEach(function (m) { var o = document.createElement('option'); o.value = m; dl.appendChild(o); });
					alert(r.data.length + ' models mile. Model field mein click karke chuno.');
				});
			});
		});

		$('#aiap-yt-test').addEventListener('click', function () {
			$('#aiap-yt-out').textContent = 'YouTube check ho raha hai...';
			post({ action: 'aiap_yt_test', o_yt_key: nf('yt_key') }).then(function (r) { show('#aiap-yt-out', r); });
		});

		$('#aiap-you-test').addEventListener('click', function () {
			$('#aiap-you-out').textContent = 'You.com check ho raha hai...';
			post({ action: 'aiap_you_test', o_you_key: nf('you_key') }).then(function (r) { show('#aiap-you-out', r); });
		});

		$('#aiap-tg-test').addEventListener('click', function () {
			$('#aiap-tg-out').textContent = 'Bhej raha hoon...';
			post({ action: 'aiap_notify_test', ch: 'tg', o_tg_token: nf('tg_token'), o_tg_chat_ids: nf('tg_chat_ids'), o_tg_api_base: nf('tg_api_base'), o_tg_webhook: nf('tg_webhook') }).then(function (r) { show('#aiap-tg-out', r); });
		});
		$('#aiap-tg-detect').addEventListener('click', function () {
			var out = $('#aiap-tg-out');
			var box = $('#aiap-tg-chats');
			out.textContent = 'Bot check ho raha hai...';
			box.innerHTML = '';
			post({ action: 'aiap_tg_detect', o_tg_token: nf('tg_token'), o_tg_api_base: nf('tg_api_base') }).then(function (r) {
				if (!r.success) { show('#aiap-tg-out', r); return; }
				var chats = r.data.chats || [];
				if (!chats.length) {
					out.innerHTML = '✅ Bot mila: <b>' + esc(r.data.bot) + '</b>. Lekin abhi tak kisi ne message nahi kiya: Telegram mein is bot ko kholo, <b>Start</b> dabao, phir dobara dhoondo.';
					return;
				}
				var input = document.querySelector('[name="aiap_settings[tg_chat_ids]"]');
				if (chats.length === 1) {
					input.value = chats[0].id;
					out.innerHTML = '✅ Bot <b>' + esc(r.data.bot) + '</b> · Chat mili: <b>' + esc(chats[0].label) + '</b> (<code>' + esc(chats[0].id) + '</code>). Ab Save karo.';
					return;
				}
				out.innerHTML = '✅ Bot <b>' + esc(r.data.bot) + '</b>. Apni chat chuno:';
				chats.forEach(function (c) {
					var b = document.createElement('button');
					b.type = 'button';
					b.className = 'pn-btn ghost sm pn-chat';
					b.textContent = c.label + ' (' + c.id + ')';
					b.addEventListener('click', function () { input.value = c.id; out.innerHTML = '✅ Chat ID bhar di: <code>' + esc(c.id) + '</code>. Ab Save karo.'; });
					box.appendChild(b);
				});
			});
		});
		$('#aiap-wa-test').addEventListener('click', function () {
			$('#aiap-wa-out').textContent = 'Bhej raha hoon...';
			post({ action: 'aiap_notify_test', ch: 'wa', o_wa_key: nf('wa_key'), o_wa_to: nf('wa_to') }).then(function (r) { show('#aiap-wa-out', r); });
		});
		$('#aiap-wa-detect').addEventListener('click', function () {
			$('#aiap-wa-out').textContent = 'Updates check ho rahe hain (10-20 sec)...';
			post({ action: 'aiap_wa_detect', o_wa_key: nf('wa_key') }).then(function (r) {
				if (r.success) {
					document.querySelector('[name="aiap_settings[wa_to]"]').value = r.data;
					$('#aiap-wa-out').innerHTML = '✅ ID mili: <code>' + esc(r.data) + '</code> (ab Save karo)';
				} else { show('#aiap-wa-out', r); }
			});
		});
	}

	/* ---------- Writer ---------- */
	var form = $('#aiap-form');
	if (form) {
		var log = $('#aiap-log');
		var runBtn = $('#aiap-run');

		runBtn.addEventListener('click', async function () {
			var d = collect(form);
			var kws = (d.keywords || '').split(/\r?\n/).map(function (s) { return s.trim(); }).filter(Boolean);
			if (!kws.length) { alert('Pehle keyword/topic likho.'); return; }
			if (kws.length > 1) { d.title = ''; }
			var quiet = !!C.summary && kws.length > 1 && d.mode !== 'queue';
			var okIds = [], failMsgs = [];
			runBtn.disabled = true;
			log.innerHTML = '';

			if (d.mode === 'queue') {
				var q = await post(Object.assign({}, d, { action: 'aiap_queue', op: 'add' }));
				logItem(log, q.success ? 'ok' : 'bad', q.success
					? '<div class="pn-item-t">✅ ' + q.data.added + ' keyword(s) queue mein add hue</div><div class="pn-item-m">Queue total: ' + q.data.total + '. Settings → Auto-pilot on karna na bhoolo.</div>'
					: '<div class="pn-item-t">❌ ' + esc(q.data) + '</div>');
				runBtn.disabled = false;
				return;
			}

			for (var i = 0; i < kws.length; i++) {
				var el = logItem(log, '', '<div class="pn-item-t"><span class="pn-spin"></span>(' + (i + 1) + '/' + kws.length + ') ' + esc(kws[i]) + '</div><div class="pn-item-m">Likh raha hai, 1-3 minute lag sakte hain...</div>');
				var r = await post(Object.assign({}, d, { action: 'aiap_generate', keyword: kws[i], index: i + 1, quiet: quiet ? 1 : 0 }));
				if (r.success) { okIds.push(r.data.id); } else { failMsgs.push(kws[i] + ': ' + r.data); }
				if (r.success) {
					var x = r.data;
					var meta = '<span class="pn-pill s-' + esc(x.status) + '">' + esc(x.status) + '</span><span>~' + x.words + ' words</span>';
					meta += x.image ? '<span>🖼️ image OK</span>' : '<span title="' + esc(x.image_note || '') + '">🖼️ nahi lagi' + (x.image_note ? ': ' + esc(x.image_note) : '') + '</span>';
					if (x.sources > 0) { meta += '<span>🧠 ' + x.sources + ' research sources</span>'; }
					else if (x.research_note) { meta += '<span>🧠 ' + esc(x.research_note) + '</span>'; }
					if (x.videos > 0) { meta += '<span>▶ ' + x.videos + ' video</span>'; }
					else if (x.video_note) { meta += '<span>▶ ' + esc(x.video_note) + '</span>'; }
					el.className = 'pn-item ok';
					el.innerHTML = '<div class="pn-item-t">✅ <a href="' + esc(x.edit) + '" target="_blank">' + esc(x.title) + '</a></div><div class="pn-item-m">' + meta + '</div>';
				} else {
					el.className = 'pn-item bad';
					el.innerHTML = '<div class="pn-item-t">❌ ' + esc(kws[i]) + '</div><div class="pn-item-m">' + esc(r.data) + '</div>';
				}
			}
			if (quiet && (okIds.length || failMsgs.length)) {
				var sm = await post({ action: 'aiap_notify_batch', ids: okIds.join(','), fails: JSON.stringify(failMsgs) });
				logItem(log, sm.success ? 'ok' : 'bad', '<div class="pn-item-t">' + (sm.success ? '📨 ' : '⚠️ Summary alert nahi gayi: ') + esc(sm.data) + '</div>');
			}
			runBtn.disabled = false;
		});

		$('#aiap-ideas-run').addEventListener('click', async function () {
			var seed = $('#aiap-seed').value.trim();
			var box = $('#aiap-ideas');
			if (!seed) { alert('Topic likho.'); return; }
			box.innerHTML = '<p class="pn-note"><span class="pn-spin"></span>Ideas aa rahe hain...</p>';
			var r = await post({ action: 'aiap_ideas', seed: seed, language: form.elements.language.value });
			if (!r.success) { box.innerHTML = '<p class="pn-err">❌ ' + esc(r.data) + '</p>'; return; }
			var h = '';
			r.data.forEach(function (it) {
				h += '<label class="pn-idea"><input type="checkbox" class="aiap-idea" value="' + esc(it.kw) + '" checked> <span>' + esc(it.kw) + (it.intent ? ' <em>(' + esc(it.intent) + ')</em>' : '') + '</span></label>';
			});
			h += '<p><button type="button" class="pn-btn ghost sm" id="aiap-ideas-add">Chune hue keywords upar daalo</button></p>';
			box.innerHTML = h;
			$('#aiap-ideas-add').addEventListener('click', function () {
				var ta = form.elements.keywords;
				var add = $$('.aiap-idea', box).filter(function (c) { return c.checked; }).map(function (c) { return c.value; });
				ta.value = (ta.value.trim() ? ta.value.trim() + '\n' : '') + add.join('\n');
			});
		});
	}

	/* ---------- Existing posts ---------- */
	var exBtn = $('#aiap-ex-run');
	if (exBtn) {
		exBtn.addEventListener('click', async function () {
			var box = $('#aiap-ex-log');
			var id = $('#aiap-ex-post').value;
			var op = ($('input[name="aiap-ex-action"]:checked') || {}).value;
			if (op === 'rewrite' && !confirm('Content rewrite hoga (Revisions mein purana version rahega). Jari rakhein?')) { return; }
			exBtn.disabled = true;
			var el = logItem(box, '', '<div class="pn-item-t"><span class="pn-spin"></span>Kaam ho raha hai...</div>');
			var r = await post({ action: 'aiap_existing', post_id: id, op: op });
			if (r.success) {
				el.className = 'pn-item ok';
				el.innerHTML = '<div class="pn-item-t">✅ ' + esc(r.data.title) + '</div><div class="pn-item-m">' + esc(r.data.note) + ' <a href="' + esc(r.data.edit) + '" target="_blank">Edit</a></div>';
			} else {
				el.className = 'pn-item bad';
				el.innerHTML = '<div class="pn-item-t">❌ ' + esc(r.data) + '</div>';
			}
			exBtn.disabled = false;
		});
	}

	/* ---------- Queue ---------- */
	$$('.aiap-q').forEach(function (btn) {
		btn.addEventListener('click', async function () {
			var op = btn.getAttribute('data-op');
			if (op === 'clear' && !confirm('Poori queue khali kar dein?')) { return; }
			btn.disabled = true;
			var box = $('#aiap-qlog');
			if (op === 'run_now') { logItem(box, '', '<div class="pn-item-t"><span class="pn-spin"></span>Process ho raha hai, thora intezar...</div>'); }
			var r = await post({ action: 'aiap_queue', op: op, i: btn.getAttribute('data-i') || 0 });
			logItem(box, r.success ? 'ok' : 'bad', '<div class="pn-item-t">' + (r.success ? '✅ ' : '❌ ') + esc(r.data) + '</div>');
			btn.disabled = false;
			if (r.success) { setTimeout(function () { location.reload(); }, 1200); }
		});
	});
})();
