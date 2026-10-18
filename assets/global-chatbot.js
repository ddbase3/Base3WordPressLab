(function() {
	'use strict';

	function init() {
		const root = document.getElementById('base3-global-chatbot');
		if (!root) return;

		// Keep fixed positioning independent from transformed WordPress theme wrappers.
		if (root.parentNode !== document.body) document.body.appendChild(root);

		const launcher = document.getElementById('base3-global-chatbot-open');
		const panel = document.getElementById('base3-global-chatbot-panel');
		const closeButton = document.getElementById('base3-global-chatbot-close');
		const content = document.getElementById('base3-global-chatbot-content');
		let frame = null;

		function close() {
			panel.hidden = true;
			launcher.hidden = false;
			launcher.setAttribute('aria-expanded', 'false');
			launcher.focus({ preventScroll: true });
		}

		function open() {
			panel.hidden = false;
			launcher.hidden = true;
			launcher.setAttribute('aria-expanded', 'true');
			closeButton.focus({ preventScroll: true });
			if (frame) return;

			frame = document.createElement('iframe');
			frame.className = 'base3-global-chatbot-frame';
			frame.title = panel.getAttribute('aria-label') || 'Chat';
			frame.setAttribute('allow', 'microphone');
			frame.referrerPolicy = 'same-origin';
			frame.addEventListener('load', function() {
				const doc = frame.contentDocument;
				if (!doc) return;
				doc.documentElement.lang = root.dataset.language || 'en';
				doc.documentElement.style.height = '100%';
				doc.body.style.margin = '0';
				doc.body.style.height = '100%';
				doc.body.style.display = 'flex';
				doc.body.style.minWidth = '0';
				doc.body.style.overflow = 'hidden';
				const chatbot = doc.querySelector('.base3-chatbot');
				if (chatbot) {
					chatbot.style.flex = '1 1 auto';
					chatbot.style.minWidth = '0';
					chatbot.style.minHeight = '0';
					chatbot.style.setProperty('--base3-chatbot-height', '100%');
				}
				doc.addEventListener('keydown', function(event) {
					if (event.key === 'Escape') close();
				});
				const loading = content.querySelector('.base3-global-chatbot-loading');
				if (loading) loading.remove();
			});
			frame.src = root.dataset.chatbotUrl;
			content.appendChild(frame);
		}

		launcher.addEventListener('click', open);
		closeButton.addEventListener('click', close);
		document.addEventListener('keydown', function(event) {
			if (event.key === 'Escape' && !panel.hidden) close();
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init, { once: true });
	} else {
		init();
	}
})();
