/**
 * Universal Multicurrency switcher frontend behavior.
 *
 * One presentation-aware controller for expand / popover / sheet strategies.
 * No currency-switch logic lives here — links keep the canonical ?currency= flow.
 *
 * @package UniversalMulticurrency
 */

(function () {
	'use strict';

	var MOBILE_QUERY = '(max-width: 767px)';
	var REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)';
	var OPEN_CLASS = 'umc-switcher--open';
	var OPEN_UP_CLASS = 'umc-switcher--open-up';
	var SHEET_CLASS = 'umc-switcher--sheet';
	var instances = [];
	var bodyOverflow = null;
	var sheetLockCount = 0;

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

	function isPreview(root) {
		return root.classList.contains('umc-switcher--preview');
	}

	function isUmlFamily(root) {
		return root.classList.contains('umc-switcher--uml-family');
	}

	function presentationOf(root) {
		return (root.getAttribute('data-umc-presentation') || 'classic-dropdown').replace(/_/g, '-');
	}

	function mobileBehaviorOf(root) {
		return (root.getAttribute('data-umc-mobile-behavior') || 'retain').replace(/_/g, '-');
	}

	function isMobileViewport() {
		return window.matchMedia(MOBILE_QUERY).matches;
	}

	function prefersReducedMotion() {
		return window.matchMedia(REDUCED_MOTION_QUERY).matches;
	}

	function safeAreaInsets() {
		var probe = document.createElement('div');
		probe.setAttribute(
			'style',
			'position:fixed;inset:env(safe-area-inset-top,0px) env(safe-area-inset-right,0px) env(safe-area-inset-bottom,0px) env(safe-area-inset-left,0px);visibility:hidden;pointer-events:none;'
		);
		document.body.appendChild(probe);
		var rect = probe.getBoundingClientRect();
		var insets = {
			left: rect.left,
			right: Math.max(0, window.innerWidth - rect.right),
			top: rect.top,
			bottom: Math.max(0, window.innerHeight - rect.bottom)
		};
		document.body.removeChild(probe);
		return insets;
	}

	function edgeOffsetPx(root) {
		var raw = window.getComputedStyle(root).getPropertyValue('--umc-switcher-edge-offset') || '0';
		return parseFloat(raw) || 0;
	}

	function parts(root) {
		return {
			trigger: root.querySelector('.umc-switcher__trigger'),
			panel: root.querySelector('.umc-switcher__panel'),
			menu: root.querySelector('.umc-switcher__menu'),
			backdrop: root.querySelector('.umc-switcher__backdrop'),
			close: root.querySelector('.umc-switcher__close'),
			title: root.querySelector('.umc-switcher__sheet-title'),
			divider: root.querySelector('.umc-switcher__sheet-divider')
		};
	}

	function isOpen(root) {
		var trigger = parts(root).trigger;
		return !!(trigger && trigger.getAttribute('aria-expanded') === 'true');
	}

	function isSheetOpen(root) {
		return root.classList.contains(SHEET_CLASS);
	}

	function focusablesInPanel(panel) {
		if (!panel) {
			return [];
		}

		var nodes = [];
		var close = panel.querySelector('.umc-switcher__close');
		if (close && !close.hidden) {
			nodes.push(close);
		}

		Array.prototype.forEach.call(panel.querySelectorAll('.umc-switcher__link'), function (link) {
			nodes.push(link);
		});

		return nodes;
	}

	function lockBodyScroll() {
		if (sheetLockCount === 0) {
			bodyOverflow = document.body.style.overflow;
			document.body.style.overflow = 'hidden';
		}
		sheetLockCount += 1;
	}

	function unlockBodyScroll() {
		if (sheetLockCount === 0) {
			return;
		}
		sheetLockCount -= 1;
		if (sheetLockCount === 0) {
			document.body.style.overflow = bodyOverflow || '';
			bodyOverflow = null;
		}
	}

	function clearSheetDialog(root) {
		var p = parts(root);
		if (p.panel) {
			p.panel.removeAttribute('role');
			p.panel.removeAttribute('aria-modal');
			p.panel.removeAttribute('aria-labelledby');
		}
		if (p.close) {
			p.close.hidden = true;
		}
		if (p.title) {
			p.title.hidden = true;
		}
		if (p.divider) {
			p.divider.hidden = true;
		}
		if (p.backdrop) {
			p.backdrop.hidden = true;
			p.backdrop.setAttribute('aria-hidden', 'true');
		}
		if (root.classList.contains(SHEET_CLASS)) {
			root.classList.remove(SHEET_CLASS);
			unlockBodyScroll();
		}
	}

	function applySheetDialog(root) {
		var p = parts(root);
		if (!p.panel || !p.title) {
			return;
		}

		p.panel.setAttribute('role', 'dialog');
		p.panel.setAttribute('aria-modal', 'true');
		p.panel.setAttribute('aria-labelledby', p.title.id || '');
		p.title.hidden = false;
		if (p.close) {
			p.close.hidden = false;
		}
		if (p.divider) {
			p.divider.hidden = false;
		}
		if (p.backdrop) {
			p.backdrop.hidden = false;
			p.backdrop.setAttribute('aria-hidden', 'true');
		}
		if (!root.classList.contains(SHEET_CLASS)) {
			root.classList.add(SHEET_CLASS);
			lockBodyScroll();
		}
	}

	function needsHorizontalSheetPromotion(root, menu) {
		var presentation = presentationOf(root);
		if (presentation !== 'floating-card' && presentation !== 'minimal-icon') {
			return false;
		}
		if (mobileBehaviorOf(root) !== 'retain') {
			return false;
		}

		var previousHidden = menu.hidden;
		menu.hidden = false;
		var menuWidth = Math.max(menu.scrollWidth, menu.offsetWidth, menu.getBoundingClientRect().width);
		menu.hidden = previousHidden;

		var available;
		var insets = safeAreaInsets();
		available = window.innerWidth - edgeOffsetPx(root) - insets.left - insets.right;
		return menuWidth > available;
	}

	function resolveOpenStrategy(root, menu) {
		var presentation = presentationOf(root);
		var behavior = mobileBehaviorOf(root);
		var mobile = isMobileViewport();

		if (isUmlFamily(root) && behavior === 'retain') {
			return 'expand';
		}

		if (presentation === 'sticky-footer') {
			if (mobile) {
				return 'sheet';
			}
			return 'popover';
		}

		if (mobile) {
			if (behavior === 'bottom-sheet') {
				return 'sheet';
			}
			if (behavior === 'sticky-compact') {
				return 'sheet';
			}
		}

		if (needsHorizontalSheetPromotion(root, menu)) {
			return 'sheet';
		}

		if (presentation === 'edge-pill' && !mobile) {
			return 'expand';
		}

		return 'popover';
	}

	function updateOpenDirection(root, menu, trigger) {
		root.classList.remove(OPEN_UP_CLASS);

		if (presentationOf(root) === 'sticky-footer') {
			root.classList.add(OPEN_UP_CLASS);
			return;
		}

		var rect = trigger.getBoundingClientRect();
		var menuHeight = menu.offsetHeight || 180;
		var spaceBelow = window.innerHeight - rect.bottom;
		var spaceAbove = rect.top;

		if (spaceBelow < menuHeight && spaceAbove > spaceBelow) {
			root.classList.add(OPEN_UP_CLASS);
		}
	}

	function closeOtherInstances(exceptRoot, openingSheet) {
		instances.forEach(function (root) {
			if (root === exceptRoot || !isOpen(root)) {
				return;
			}

			if (openingSheet) {
				closeMenu(root, false);
				return;
			}

			// Opening a non-sheet switcher closes other open non-sheet switchers only.
			if (!isSheetOpen(root)) {
				closeMenu(root, false);
			}
		});
	}

	function closeMenu(root, restoreFocus) {
		var p = parts(root);
		if (!p.trigger || !p.menu) {
			return;
		}

		var wasSheet = isSheetOpen(root);
		root.classList.remove(OPEN_CLASS);
		root.classList.remove(OPEN_UP_CLASS);
		root.setAttribute('data-umc-open', '0');
		p.trigger.setAttribute('aria-expanded', 'false');
		p.menu.hidden = true;
		clearSheetDialog(root);

		if (restoreFocus !== false && wasSheet) {
			p.trigger.focus();
		} else if (restoreFocus === true) {
			p.trigger.focus();
		}
	}

	function openMenu(root) {
		var p = parts(root);
		if (!p.trigger || !p.menu || !p.panel) {
			return;
		}

		if (isPreview(root)) {
			// Admin preview owns collapsed/open; do not fight it.
			return;
		}

		var strategy = resolveOpenStrategy(root, p.menu);
		closeOtherInstances(root, strategy === 'sheet');

		root.classList.add(OPEN_CLASS);
		root.setAttribute('data-umc-open', '1');
		p.trigger.setAttribute('aria-expanded', 'true');
		p.menu.hidden = false;

		if (strategy === 'sheet') {
			applySheetDialog(root);
			var focusables = focusablesInPanel(p.panel);
			if (focusables.length) {
				focusables[0].focus();
			}
			return;
		}

		clearSheetDialog(root);
		if (strategy === 'popover') {
			updateOpenDirection(root, p.menu, p.trigger);
		}
	}

	function toggleMenu(root) {
		if (isOpen(root)) {
			closeMenu(root, true);
			return;
		}
		openMenu(root);
	}

	function onDocumentClick(event) {
		var target = event.target;
		if (!target || !target.closest) {
			return;
		}

		var root = target.closest('.umc-switcher--dropdown');
		if (root && isPreview(root)) {
			return;
		}

		if (target.closest('.umc-switcher__backdrop')) {
			var sheetRoot = target.closest('.umc-switcher--dropdown');
			if (sheetRoot) {
				closeMenu(sheetRoot, true);
			}
			return;
		}

		if (target.closest('.umc-switcher__close')) {
			var closeRoot = target.closest('.umc-switcher--dropdown');
			if (closeRoot) {
				event.preventDefault();
				closeMenu(closeRoot, true);
			}
			return;
		}

		if (target.closest('.umc-switcher__trigger')) {
			var triggerRoot = target.closest('.umc-switcher--dropdown');
			if (triggerRoot && !isPreview(triggerRoot)) {
				event.preventDefault();
				toggleMenu(triggerRoot);
			}
			return;
		}

		instances.forEach(function (instance) {
			if (isOpen(instance) && !instance.contains(target)) {
				closeMenu(instance, false);
			}
		});
	}

	function onDocumentKeydown(event) {
		var activeRoot = null;
		instances.forEach(function (root) {
			if (isOpen(root)) {
				activeRoot = root;
			}
		});

		if (!activeRoot || isPreview(activeRoot)) {
			return;
		}

		var p = parts(activeRoot);

		if (event.key === 'Escape') {
			event.preventDefault();
			event.stopPropagation();
			closeMenu(activeRoot, true);
			return;
		}

		if (!isSheetOpen(activeRoot) || event.key !== 'Tab' || !p.panel) {
			return;
		}

		var focusables = focusablesInPanel(p.panel);
		if (!focusables.length) {
			return;
		}

		var first = focusables[0];
		var last = focusables[focusables.length - 1];
		var active = document.activeElement;

		if (event.shiftKey && active === first) {
			event.preventDefault();
			last.focus();
			return;
		}

		if (!event.shiftKey && active === last) {
			event.preventDefault();
			first.focus();
		}
	}

	function bindRootKeyboard(root) {
		var p = parts(root);
		if (!p.trigger || !p.menu) {
			return;
		}

		p.trigger.addEventListener('keydown', function (event) {
			if (isPreview(root)) {
				return;
			}

			if (event.key === 'Enter' || event.key === ' ') {
				event.preventDefault();
				toggleMenu(root);
				return;
			}

			if (event.key === 'ArrowDown' && isOpen(root) && !isSheetOpen(root)) {
				event.preventDefault();
				focusLink(p.menu, 'a');
				return;
			}

			if (event.key === 'ArrowUp' && isOpen(root) && !isSheetOpen(root)) {
				event.preventDefault();
				focusLink(p.menu, 'a:last-of-type');
			}
		});

		p.menu.addEventListener('keydown', function (event) {
			if (isSheetOpen(root)) {
				return;
			}

			var links = Array.prototype.slice.call(p.menu.querySelectorAll('a'));
			var currentIndex = links.indexOf(document.activeElement);

			if (event.key === 'Escape') {
				event.preventDefault();
				event.stopPropagation();
				closeMenu(root, true);
				return;
			}

			if (!links.length) {
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

		root.addEventListener('focusout', function (event) {
			if (isPreview(root) || isSheetOpen(root)) {
				return;
			}
			if (!root.contains(event.relatedTarget)) {
				closeMenu(root, false);
			}
		});
	}

	function focusLink(menu, selector) {
		var link = menu.querySelector(selector);
		if (link) {
			link.focus();
		}
	}

	function bindPreviewLinks(root) {
		if (!isPreview(root)) {
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
		instances = Array.prototype.slice.call(document.querySelectorAll('.umc-switcher--dropdown'));

		instances.forEach(function (root) {
			bindRootKeyboard(root);
			bindPreviewLinks(root);
			if (!isPreview(root)) {
				root.setAttribute('data-umc-enhanced', '1');
				root.setAttribute('data-umc-open', '0');
			}
		});

		document.addEventListener('click', onDocumentClick);
		document.addEventListener('keydown', onDocumentKeydown, true);

		window.addEventListener('resize', function () {
			instances.forEach(function (root) {
				if (!isOpen(root) || isSheetOpen(root) || isPreview(root)) {
					return;
				}
				var p = parts(root);
				if (!p.trigger || !p.menu) {
					return;
				}
				var strategy = resolveOpenStrategy(root, p.menu);
				if (strategy === 'sheet') {
					closeMenu(root, false);
					openMenu(root);
					return;
				}
				if (strategy === 'popover') {
					updateOpenDirection(root, p.menu, p.trigger);
				}
			});
		});

		// Honour reduced motion at runtime without overriding merchant CSS vars.
		window.matchMedia(REDUCED_MOTION_QUERY).addEventListener('change', function () {
			prefersReducedMotion();
		});
	}

	ready(init);
})();
