/**
 * Universal Multicurrency switcher frontend behavior.
 *
 * @package UniversalMulticurrency
 */

(function () {
	'use strict';

	function ready(fn) {
		if (document.readyState !== 'loading') {
			fn();
			return;
		}

		document.addEventListener('DOMContentLoaded', fn);
	}

	function removeNoJsClass() {
		document.documentElement.classList.remove('no-js');
	}

	function closeMenu(root) {
		var trigger = root.querySelector('.umc-switcher__trigger');
		var menu = root.querySelector('.umc-switcher__menu');

		if (!trigger || !menu) {
			return;
		}

		root.classList.remove('umc-switcher--open');
		trigger.setAttribute('aria-expanded', 'false');
		menu.hidden = true;
	}

	function openMenu(root) {
		var trigger = root.querySelector('.umc-switcher__trigger');
		var menu = root.querySelector('.umc-switcher__menu');

		if (!trigger || !menu) {
			return;
		}

		updateOpenDirection(root, menu, trigger);
		root.classList.add('umc-switcher--open');
		trigger.setAttribute('aria-expanded', 'true');
		menu.hidden = false;
	}

	function toggleMenu(root) {
		var trigger = root.querySelector('.umc-switcher__trigger');

		if (!trigger) {
			return;
		}

		if (trigger.getAttribute('aria-expanded') === 'true') {
			closeMenu(root);
			return;
		}

		openMenu(root);
	}

	function updateOpenDirection(root, menu, trigger) {
		root.classList.remove('umc-switcher--open-up');

		var rect = trigger.getBoundingClientRect();
		var menuHeight = menu.offsetHeight || 180;
		var spaceBelow = window.innerHeight - rect.bottom;
		var spaceAbove = rect.top;

		if (spaceBelow < menuHeight && spaceAbove > spaceBelow) {
			root.classList.add('umc-switcher--open-up');
		}
	}

	function focusLink(menu, selector) {
		var link = menu.querySelector(selector);

		if (link) {
			link.focus();
		}
	}

	function bindDropdown(root) {
		var trigger = root.querySelector('.umc-switcher__trigger');
		var menu = root.querySelector('.umc-switcher__menu');

		if (!trigger || !menu) {
			return;
		}

		trigger.addEventListener('click', function () {
			toggleMenu(root);
		});

		trigger.addEventListener('keydown', function (event) {
			if (event.key === 'Enter' || event.key === ' ') {
				event.preventDefault();
				toggleMenu(root);
				return;
			}

			if (event.key === 'ArrowDown' && trigger.getAttribute('aria-expanded') === 'true') {
				event.preventDefault();
				focusLink(menu, 'a');
				return;
			}

			if (event.key === 'ArrowUp' && trigger.getAttribute('aria-expanded') === 'true') {
				event.preventDefault();
				focusLink(menu, 'a:last-of-type');
			}
		});

		menu.addEventListener('keydown', function (event) {
			var links = Array.prototype.slice.call(menu.querySelectorAll('a'));
			var currentIndex = links.indexOf(document.activeElement);

			if (event.key === 'Escape') {
				event.preventDefault();
				closeMenu(root);
				trigger.focus();
				return;
			}

			if (event.key === 'ArrowDown') {
				event.preventDefault();
				links[(currentIndex + 1 + links.length) % links.length].focus();
				return;
			}

			if (event.key === 'ArrowUp') {
				event.preventDefault();
				links[(currentIndex - 1 + links.length) % links.length].focus();
				return;
			}

			if (event.key === 'Home') {
				event.preventDefault();
				links[0].focus();
				return;
			}

			if (event.key === 'End') {
				event.preventDefault();
				links[links.length - 1].focus();
			}
		});

		document.addEventListener('click', function (event) {
			if (!root.contains(event.target)) {
				closeMenu(root);
			}
		});

		root.addEventListener('focusout', function (event) {
			if (!root.contains(event.relatedTarget)) {
				closeMenu(root);
			}
		});
	}

	function bindPreviewLinks(root) {
		if (!root.classList.contains('umc-switcher--preview')) {
			return;
		}

		root.querySelectorAll('a.umc-switcher__link').forEach(function (link) {
			link.addEventListener('click', function (event) {
				event.preventDefault();
			});
		});
	}

	function init() {
		removeNoJsClass();

		document.querySelectorAll('.umc-switcher--dropdown').forEach(function (root) {
			bindDropdown(root);
			bindPreviewLinks(root);
		});

		document.querySelectorAll('.umc-switcher--preview .umc-switcher--horizontal-list').forEach(function (root) {
			bindPreviewLinks(root);
		});

		window.addEventListener('resize', function () {
			document.querySelectorAll('.umc-switcher--open').forEach(function (root) {
				var trigger = root.querySelector('.umc-switcher__trigger');
				var menu = root.querySelector('.umc-switcher__menu');

				if (trigger && menu) {
					updateOpenDirection(root, menu, trigger);
				}
			});
		});
	}

	ready(init);
})();
