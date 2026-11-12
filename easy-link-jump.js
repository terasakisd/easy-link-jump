(function () {
	'use strict';

	var cfg  = window.eljConfig || {};
	var rows = Array.isArray(cfg.links) ? cfg.links : [];
	var base = typeof cfg.base === 'string' ? cfg.base.replace(/\/+$/, '') : '';

	// リファラ(data-p の値)→ 本当のリンク先URL の対応表。
	// 同じリファラに複数登録されている場合はランダム振り分け用に配列で持つ
	var map = {};
	rows.forEach(function (row) {
		if (!row || !row.key || !row.url) {
			return;
		}
		(map[row.key] = map[row.key] || []).push(row.url);
	});

	function destinationFor(a) {
		var key  = a.getAttribute('data-p');
		if (!key) {
			return null;
		}
		var list = map[key];
		if (list && list.length) {
			return list.length === 1 ? list[0] : list[Math.floor(Math.random() * list.length)];
		}
		// data-p が「ファイル名?リファラ」形式の場合、リファラ部分だけの登録でも反応させる。
		// ファイル名は data-p 側の指定を使い、共通URLと組み立てる
		// (同じリファラを複数ASPで共有していても、data-p のファイル名どおりに遷移する)
		var q = key.indexOf('?');
		if (base && q > 0 && map[key.slice(q + 1)]) {
			return base + '/' + key.slice(0, q).replace(/^\/+/, '') + '?' + key.slice(q + 1);
		}
		return null;
	}

	// ホバー時に飛び先ドメインへ接続準備(DNS解決・TLSハンドシェイク)を先に走らせ、
	// クリック後の遷移を速くする。ソースには何も出力されない
	var preconnected = {};
	function preconnectUrl(url) {
		var origin;
		try {
			origin = new URL(url, window.location.href).origin;
		} catch (err) {
			return;
		}
		if (preconnected[origin]) {
			return;
		}
		preconnected[origin] = true;
		['preconnect', 'dns-prefetch'].forEach(function (rel) {
			var link = document.createElement('link');
			link.rel  = rel;
			link.href = origin;
			document.head.appendChild(link);
		});
	}

	function preconnect(a) {
		var key  = a.getAttribute('data-p');
		var list = key && map[key];
		if (list) {
			list.forEach(preconnectUrl);
		}
	}

	// ページ表示直後に、登録されている全ドメインへ接続準備を済ませておく
	function preconnectAll() {
		if (base) {
			preconnectUrl(base + '/');
		}
		Object.keys(map).forEach(function (key) {
			map[key].forEach(preconnectUrl);
		});
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', preconnectAll);
	} else {
		preconnectAll();
	}

	function swap(a) {
		var to = destinationFor(a);
		if (!to) {
			return;
		}
		// 復元タイマーが動作中なら href はすでに本物に差し替わっているため、
		// ダミーの保存は最初の1回だけ行う(mousedown→click が連続しても本物を「ダミー」として誤保存しない)
		if (a._eljTimer) {
			clearTimeout(a._eljTimer);
		} else {
			a._eljDummy = a.getAttribute('href');
		}
		a.setAttribute('href', to);
		// 遷移処理が済んだ直後に href をダミーへ戻す
		// (新しいタブで開いた場合、元ページに本物のURLを残さないため)
		a._eljTimer = setTimeout(function () {
			a.setAttribute('href', a._eljDummy);
			a._eljTimer = null;
		}, 100);
	}

	// ページ表示時には何もしない。クリックが成立した瞬間に href を差し替え、
	// そのままブラウザ標準の遷移に任せる(左クリック・中クリック・タップ・Enter すべて対応)。
	// mousedown では差し替えない:ドラッグ開始時に本物のURLを掴ませないため
	['click', 'auxclick'].forEach(function (type) {
		document.addEventListener(type, function (e) {
			var a = e.target && e.target.closest ? e.target.closest('a[data-p]') : null;
			if (a) {
				swap(a);
			}
		}, true);
	});

	// マウスが乗った瞬間・タップ開始の瞬間に接続準備を始める
	['mouseover', 'touchstart'].forEach(function (type) {
		document.addEventListener(type, function (e) {
			var a = e.target && e.target.closest ? e.target.closest('a[data-p]') : null;
			if (a) {
				preconnect(a);
			}
		}, true);
	});
})();
