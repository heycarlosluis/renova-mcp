/*
 * Interacciones de la guía: credencial local, copiado y selector de cliente.
 */
(function () {
	'use strict';

	function renderIcons() {
		if (!window.RenovaLucide) {
			return;
		}
		window.RenovaLucide.createIcons({
			attrs: {
				'aria-hidden': 'true',
				'stroke-width': 1.8,
			},
			icons: window.RenovaLucide.icons,
		});
	}

	function replaceIcon(container, name) {
		var current = container.querySelector('.lucide, [data-lucide]');
		if (!current) {
			return;
		}
		var replacement = document.createElement('i');
		replacement.setAttribute('data-lucide', name);
		replacement.setAttribute('aria-hidden', 'true');
		current.parentNode.replaceChild(replacement, current);
		renderIcons();
	}

	function b64(value) {
		try {
			var bytes = new TextEncoder().encode(value);
			var binary = '';
			for (var i = 0; i < bytes.length; i++) {
				binary += String.fromCharCode(bytes[i]);
			}
			return btoa(binary);
		} catch (error) {
			return '';
		}
	}

	function decodeTemplate(template) {
		try {
			return atob(template);
		} catch (error) {
			return '';
		}
	}

	function updateSnippets() {
		var user = document.getElementById('renova-mcp-user');
		var pass = document.getElementById('renova-mcp-pass');
		var auth = document.getElementById('renova-mcp-auth');
		var username = user ? user.value.trim() : '';
		var password = pass ? pass.value.trim() : '';
		var token = username && password ? b64(username + ':' + password) : '';

		if (auth) {
			auth.textContent = token ? 'Basic ' + token : 'Basic __AUTH__';
		}

		var snippets = document.querySelectorAll('.renova-mcp-snippet');
		for (var i = 0; i < snippets.length; i++) {
			var template = snippets[i].getAttribute('data-template');
			if (template) {
				snippets[i].textContent = decodeTemplate(template).split('__AUTH__').join(token || '__AUTH__');
			}
		}
	}

	function announce(message) {
		var region = document.querySelector('.renova-mcp-announcer');
		if (!region) {
			return;
		}
		region.textContent = '';
		window.setTimeout(function () {
			region.textContent = message;
		}, 20);
	}

	function flash(button, success) {
		var label = button.querySelector('.renova-mcp-copy-label');
		var originalLabel = button.getAttribute('data-original-label') || (label ? label.textContent : '');
		var originalAria = button.getAttribute('data-original-aria') || button.getAttribute('aria-label') || '';
		var message = success ? 'Copiado' : 'No se pudo copiar';

		button.setAttribute('data-original-label', originalLabel);
		button.setAttribute('data-original-aria', originalAria);
		button.classList.toggle('is-copied', success);
		button.classList.toggle('has-error', !success);
		if (label) {
			label.textContent = message;
		}
		if (button.hasAttribute('aria-label')) {
			button.setAttribute('aria-label', message);
		}
		announce(success ? 'Contenido copiado al portapapeles.' : 'No se pudo copiar. Selecciona el texto y cópialo manualmente.');
		window.clearTimeout(button.renovaCopyTimer);
		button.renovaCopyTimer = window.setTimeout(function () {
			button.classList.remove('is-copied', 'has-error');
			if (label) {
				label.textContent = originalLabel;
			}
			if (button.hasAttribute('aria-label')) {
				button.setAttribute('aria-label', originalAria);
			}
		}, 1600);
	}

	function fallbackCopy(text) {
		var activeElement = document.activeElement;
		var textarea = document.createElement('textarea');
		textarea.value = text;
		textarea.readOnly = true;
		textarea.setAttribute('aria-hidden', 'true');
		textarea.style.position = 'fixed';
		textarea.style.insetInlineStart = '-9999px';
		document.body.appendChild(textarea);
		textarea.select();

		var copied = false;
		try {
			copied = document.execCommand('copy');
		} catch (error) {
			copied = false;
		}

		document.body.removeChild(textarea);
		if (activeElement && activeElement.focus) {
			activeElement.focus();
		}
		return copied;
	}

	function copy(text, button) {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(
				function () {
					flash(button, true);
				},
				function () {
					flash(button, fallbackCopy(text));
				}
			);
			return;
		}
		flash(button, fallbackCopy(text));
	}

	function activateTab(tab, moveFocus) {
		var tabs = document.querySelectorAll('.renova-mcp-client-tab');
		var panels = document.querySelectorAll('.renova-mcp-code-panel');
		var panelId = tab.getAttribute('aria-controls');

		for (var i = 0; i < tabs.length; i++) {
			var selected = tabs[i] === tab;
			tabs[i].classList.toggle('is-active', selected);
			tabs[i].setAttribute('aria-selected', selected ? 'true' : 'false');
			tabs[i].setAttribute('tabindex', selected ? '0' : '-1');
		}

		for (var j = 0; j < panels.length; j++) {
			var active = panels[j].id === panelId;
			panels[j].hidden = !active;
			panels[j].classList.toggle('is-active', active);
		}

		if (moveFocus) {
			tab.focus();
		}
	}

	function handleTabKeydown(event, tabs, index) {
		var nextIndex = index;
		if (event.key === 'ArrowDown' || event.key === 'ArrowRight') {
			nextIndex = (index + 1) % tabs.length;
		} else if (event.key === 'ArrowUp' || event.key === 'ArrowLeft') {
			nextIndex = (index - 1 + tabs.length) % tabs.length;
		} else if (event.key === 'Home') {
			nextIndex = 0;
		} else if (event.key === 'End') {
			nextIndex = tabs.length - 1;
		} else {
			return;
		}

		event.preventDefault();
		activateTab(tabs[nextIndex], true);
	}

	function setupTabs() {
		var tabs = Array.prototype.slice.call(document.querySelectorAll('.renova-mcp-client-tab'));
		for (var i = 0; i < tabs.length; i++) {
			(function (tab, index) {
				tab.addEventListener('click', function () {
					activateTab(tab, false);
				});
				tab.addEventListener('keydown', function (event) {
					handleTabKeydown(event, tabs, index);
				});
			})(tabs[i], i);
		}
	}

	function setupPasswordVisibility() {
		var button = document.querySelector('.renova-mcp-visibility');
		var input = document.getElementById('renova-mcp-pass');
		if (!button || !input) {
			return;
		}

		button.addEventListener('click', function () {
			var visible = input.type === 'text';
			input.type = visible ? 'password' : 'text';
			button.setAttribute('aria-pressed', visible ? 'false' : 'true');
			button.setAttribute('aria-label', visible ? 'Mostrar contraseña' : 'Ocultar contraseña');
			replaceIcon(button, visible ? 'eye' : 'eye-off');
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		renderIcons();
		var user = document.getElementById('renova-mcp-user');
		var pass = document.getElementById('renova-mcp-pass');
		if (user && pass) {
			user.addEventListener('input', updateSnippets);
			pass.addEventListener('input', updateSnippets);
			updateSnippets();
		}

		var copyButtons = document.querySelectorAll('.renova-mcp-copy');
		for (var i = 0; i < copyButtons.length; i++) {
			(function (button) {
				button.addEventListener('click', function () {
					var selector = button.getAttribute('data-copy');
					var target = selector ? document.querySelector(selector) : null;
					if (!target) {
						flash(button, false);
						return;
					}
					copy(target.textContent.trim(), button);
				});
			})(copyButtons[i]);
		}

		setupTabs();
		setupPasswordVisibility();
	});
})();
