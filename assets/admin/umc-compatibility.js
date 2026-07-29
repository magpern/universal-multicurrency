(function () {
	'use strict';

	function strings() {
		return window.umcCompatibility || {};
	}

	function init(root) {
		var copyStrings = strings();

		root.querySelectorAll('[data-umc-compat-evidence-toggle]').forEach(function (button) {
			button.addEventListener('click', function () {
				var panelId = button.getAttribute('aria-controls');
				var panel = panelId ? document.getElementById(panelId) : null;
				if (!panel) {
					return;
				}

				var expanded = button.getAttribute('aria-expanded') === 'true';
				button.setAttribute('aria-expanded', expanded ? 'false' : 'true');
				panel.hidden = expanded;
				button.textContent = expanded
					? (copyStrings.showEvidence || button.getAttribute('data-label-show'))
					: (copyStrings.hideEvidence || button.getAttribute('data-label-hide'));
			});

			button.setAttribute('data-label-show', copyStrings.showEvidence || button.textContent.trim());
			button.setAttribute('data-label-hide', copyStrings.hideEvidence || button.getAttribute('data-label-show'));
		});

		var copyButton = root.querySelector('[data-umc-compat-copy-report]');
		var reportField = root.querySelector('[data-umc-compat-report]');
		var status = root.querySelector('[data-umc-compat-copy-status]');

		if (!copyButton || !reportField || !status) {
			return;
		}

		copyButton.setAttribute('data-success', copyStrings.copySuccess || '');
		copyButton.setAttribute('data-failed', copyStrings.copyFailed || '');

		copyButton.addEventListener('click', function () {
			var text = reportField.value || reportField.textContent || '';

			function announce(message) {
				status.textContent = message;
			}

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(function () {
					announce(copyButton.getAttribute('data-success'));
				}).catch(function () {
					fallbackCopy(reportField, announce, copyButton);
				});
				return;
			}

			fallbackCopy(reportField, announce, copyButton);
		});
	}

	function fallbackCopy(field, announce, button) {
		field.focus();
		field.select();

		try {
			var copied = document.execCommand('copy');
			announce(copied ? button.getAttribute('data-success') : button.getAttribute('data-failed'));
		} catch (error) {
			announce(button.getAttribute('data-failed'));
		}
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('[data-umc-compatibility-root]').forEach(init);
	});
})();
