/*
 * Comportamiento de la página "Renova MCP": copiar al portapapeles y
 * generación en vivo del header de autorización (Basic base64).
 */
(function () {
	'use strict';

	function b64(value) {
		try {
			return btoa(unescape(encodeURIComponent(value)));
		} catch (e) {
			return '';
		}
	}

	function updateSnippets() {
		var user = document.getElementById('renova-mcp-user');
		var pass = document.getElementById('renova-mcp-pass');
		var auth = document.getElementById('renova-mcp-auth');

		var u = user ? user.value.trim() : '';
		var p = pass ? pass.value.trim() : '';
		var token = (u || p) ? b64(u + ':' + p) : '';

		if (auth) {
			auth.textContent = token ? 'Basic ' + token : 'Basic __AUTH__';
		}

		var snippets = document.querySelectorAll('.renova-mcp-snippet');
		for (var i = 0; i < snippets.length; i++) {
			var el = snippets[i];
			var template = el.getAttribute('data-template');
			if (!template) {
				continue;
			}
			var text = '';
			try {
				text = atob(template);
			} catch (e) {
				text = '';
			}
			el.textContent = text.split('__AUTH__').join(token || '__AUTH__');
		}
	}

	function flash(btn, label) {
		var original = btn.textContent;
		btn.textContent = label;
		setTimeout(function () {
			btn.textContent = original;
		}, 1200);
	}

	function copy(text, btn) {
		var done = function () {
			flash(btn, '¡Copiado!');
		};
		var fail = function () {
			flash(btn, 'Error');
		};

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(done, function () {
				fallbackCopy(text) ? done() : fail();
			});
		} else {
			fallbackCopy(text) ? done() : fail();
		}
	}

	function fallbackCopy(text) {
		var ta = document.createElement('textarea');
		ta.value = text;
		ta.style.position = 'fixed';
		ta.style.opacity = '0';
		document.body.appendChild(ta);
		ta.select();
		var ok = false;
		try {
			ok = document.execCommand('copy');
		} catch (e) {
			ok = false;
		}
		document.body.removeChild(ta);
		return ok;
	}

	document.addEventListener('DOMContentLoaded', function () {
		var user = document.getElementById('renova-mcp-user');
		var pass = document.getElementById('renova-mcp-pass');
		if (user && pass) {
			user.addEventListener('input', updateSnippets);
			pass.addEventListener('input', updateSnippets);
		}

		var copies = document.querySelectorAll('.renova-mcp-copy');
		for (var i = 0; i < copies.length; i++) {
			(function (btn) {
				btn.addEventListener('click', function () {
					var selector = btn.getAttribute('data-copy');
					if (!selector) {
						return;
					}
					var target = document.querySelector(selector);
					if (!target) {
						return;
					}
					copy(target.textContent, btn);
				});
			})(copies[i]);
		}
	});
})();
