(function () {
	'use strict';

	var cfg  = window.eljConfig || {};
	var base = typeof cfg.base === 'string' ? cfg.base.replace(/\/+$/, '') : '';

	// クリック時の遷移先URLを組み立てる。
	// data-f(広告ファイル名。別名変換時にPHPが自動付与)+ data-p(リファラ)から
	// 「共通URL+ファイル名?リファラ」を作る。data-f がURL全体ならそのまま使う
	function destinationFor(a) {
		var file = a.getAttribute('data-f');
		var key  = a.getAttribute('data-p');
		if (!file || !key) {
			return null;
		}
		var url;
		if (/^https?:\/\//i.test(file)) {
			url = file;
		} else if (base) {
			url = base + '/' + file.replace(/^\/+/, '');
		} else {
			return null;
		}
		return url.indexOf('?') === -1 ? url + '?' + key : url;
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
		var to = destinationFor(a);
		if (to) {
			preconnectUrl(to);
		}
	}

	// ページ表示直後に、共通URLのドメインへ接続準備を済ませておく
	function preconnectAll() {
		if (base) {
			preconnectUrl(base + '/');
		}
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
