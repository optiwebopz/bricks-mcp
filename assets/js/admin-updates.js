/**
 * Bricks MCP Admin Updates & Onboarding JS.
 *
 * Handles: tab switching, copy to clipboard, Check Now AJAX, Test Connection AJAX.
 * Data passed via bricksMcpUpdates global from wp_localize_script.
 *
 * @package BricksMCP
 */

(function() {
	'use strict';

	// -------------------------------------------------------------------------
	// Tab switching (event delegation)
	// -------------------------------------------------------------------------

	document.addEventListener('click', function(e) {
		var tab = e.target.closest('[data-tab]');
		if (!tab) {
			return;
		}

		var container = tab.closest('.bricks-mcp-tabs');
		if (!container) {
			return;
		}

		var target = tab.getAttribute('data-tab');

		// Toggle active state on tab buttons.
		container.querySelectorAll('[data-tab]').forEach(function(t) {
			if (t === tab) {
				t.classList.add('active');
				t.style.borderBottomColor = '#2271b1';
				t.style.color = '';
			} else {
				t.classList.remove('active');
				t.style.borderBottomColor = 'transparent';
				t.style.color = '#666';
			}
		});

		// Show/hide panels.
		container.querySelectorAll('[data-panel]').forEach(function(p) {
			p.style.display = p.getAttribute('data-panel') === target ? '' : 'none';
		});
	});

	// -------------------------------------------------------------------------
	// Copy to clipboard (event delegation on .bricks-mcp-copy-btn)
	// -------------------------------------------------------------------------

	/**
	 * Copy text to clipboard with button feedback.
	 *
	 * @param {string}      text   Text to copy.
	 * @param {HTMLElement}  button Button element for feedback.
	 */
	function copyToClipboard(text, button) {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(function() {
				showCopyFeedback(button);
			}).catch(function() {
				fallbackCopy(text, button);
			});
		} else {
			fallbackCopy(text, button);
		}
	}

	/**
	 * Fallback copy using hidden textarea.
	 *
	 * @param {string}      text   Text to copy.
	 * @param {HTMLElement}  button Button element for feedback.
	 */
	function fallbackCopy(text, button) {
		var textarea = document.createElement('textarea');
		textarea.value = text;
		textarea.style.position = 'fixed';
		textarea.style.opacity = '0';
		document.body.appendChild(textarea);
		textarea.select();
		document.execCommand('copy');
		document.body.removeChild(textarea);
		showCopyFeedback(button);
	}

	/**
	 * Show "Copied!" feedback on button for 2 seconds.
	 *
	 * @param {HTMLElement} button Button element.
	 */
	function showCopyFeedback(button) {
		var original = button.textContent;
		button.textContent = 'Copied!';
		setTimeout(function() {
			button.textContent = original;
		}, 2000);
	}

	document.addEventListener('click', function(e) {
		var btn = e.target.closest('.bricks-mcp-copy-btn');
		if (!btn) {
			return;
		}

		var targetId = btn.getAttribute('data-target');
		if (!targetId) {
			return;
		}

		var codeEl = document.getElementById(targetId);
		if (!codeEl) {
			return;
		}

		copyToClipboard(codeEl.textContent, btn);
	});

	// -------------------------------------------------------------------------
	// Check Now button
	// -------------------------------------------------------------------------

	function initCheckNow() {
		var btn = document.getElementById('bricks-mcp-check-update-btn');
		var spinner = document.getElementById('bricks-mcp-check-update-spinner');
		var versionText = document.getElementById('bricks-mcp-version-text');
		var versionCard = document.querySelector('.bricks-mcp-version-card');

		if (!btn) {
			return;
		}

		btn.addEventListener('click', function() {
			btn.disabled = true;
			if (spinner) {
				spinner.classList.add('is-active');
			}

			var formData = new FormData();
			formData.append('action', 'bricks_mcp_check_update');
			formData.append('nonce', bricksMcpUpdates.nonce);

			fetch(bricksMcpUpdates.ajaxUrl, {
				method: 'POST',
				body: formData
			})
			.then(function(response) {
				return response.json();
			})
			.then(function(data) {
				if (data.success && data.data && versionText) {
					var remoteVersion = data.data.version || '';
					var currentVersion = bricksMcpUpdates.currentVersion;
					var html = '<strong>v' + escapeHtml(currentVersion) + '</strong>';

					if (remoteVersion && isNewer(currentVersion, remoteVersion)) {
						html += ' &mdash; <span style="color:#dba617;font-weight:600;">v' +
							escapeHtml(remoteVersion) + ' available</span> ' +
							'<a href="' + escapeAttr(bricksMcpUpdates.updateCoreUrl) + '">Update</a>';
						if (versionCard) {
							versionCard.style.borderLeftColor = '#dba617';
						}
					} else {
						html += ' &mdash; <span style="color:#00a32a;">up to date</span>';
						if (versionCard) {
							versionCard.style.borderLeftColor = '#2271b1';
						}
					}

					versionText.innerHTML = html;
				}
			})
			.catch(function() {
				// Silent fail — button re-enables regardless.
			})
			.finally(function() {
				btn.disabled = false;
				if (spinner) {
					spinner.classList.remove('is-active');
				}
			});
		});
	}

	// -------------------------------------------------------------------------
	// Test Connection button
	// -------------------------------------------------------------------------

	function initTestConnection() {
		var btn = document.getElementById('bricks-mcp-test-connection-btn');
		var spinner = document.getElementById('bricks-mcp-test-spinner');
		var resultDiv = document.getElementById('bricks-mcp-test-result');

		if (!btn) {
			return;
		}

		btn.addEventListener('click', function() {
			var usernameInput = document.getElementById('bricks-mcp-test-username');
			var passwordInput = document.getElementById('bricks-mcp-test-app-password');

			var username = usernameInput ? usernameInput.value.trim() : '';
			var appPassword = passwordInput ? passwordInput.value.trim() : '';

			if (!appPassword) {
				if (resultDiv) {
					resultDiv.innerHTML = '<span style="color:#d63638;">Please enter an Application Password.</span>';
				}
				return;
			}

			btn.disabled = true;
			if (spinner) {
				spinner.classList.add('is-active');
			}
			if (resultDiv) {
				resultDiv.innerHTML = '';
			}

			var formData = new FormData();
			formData.append('action', 'bricks_mcp_test_connection');
			formData.append('nonce', bricksMcpUpdates.nonce);
			formData.append('username', username);
			formData.append('app_password', appPassword);

			fetch(bricksMcpUpdates.ajaxUrl, {
				method: 'POST',
				body: formData
			})
			.then(function(response) {
				return response.json();
			})
			.then(function(data) {
				if (resultDiv) {
					if (data.success) {
						resultDiv.innerHTML = '<span style="color:#00a32a;font-weight:600;">' +
							escapeHtml(data.data.message) + '</span>';
					} else {
						var message = (data.data && data.data.message) ? data.data.message : 'Connection test failed.';
						resultDiv.innerHTML = '<span style="color:#d63638;">' +
							escapeHtml(message) + '</span>';
					}
				}
			})
			.catch(function() {
				if (resultDiv) {
					resultDiv.innerHTML = '<span style="color:#d63638;">Network error. Please try again.</span>';
				}
			})
			.finally(function() {
				btn.disabled = false;
				if (spinner) {
					spinner.classList.remove('is-active');
				}
			});
		});
	}

	// -------------------------------------------------------------------------
	// Utility functions
	// -------------------------------------------------------------------------

	/**
	 * Compare two version strings. Returns true if remote is newer.
	 *
	 * @param {string} current Current version string.
	 * @param {string} remote  Remote version string.
	 * @returns {boolean}
	 */
	function isNewer(current, remote) {
		var a = current.split('.').map(Number);
		var b = remote.split('.').map(Number);
		var len = Math.max(a.length, b.length);

		for (var i = 0; i < len; i++) {
			var av = a[i] || 0;
			var bv = b[i] || 0;
			if (bv > av) {
				return true;
			}
			if (bv < av) {
				return false;
			}
		}
		return false;
	}

	/**
	 * Escape HTML entities.
	 *
	 * @param {string} str String to escape.
	 * @returns {string}
	 */
	function escapeHtml(str) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str));
		return div.innerHTML;
	}

	/**
	 * Escape a string for use in an HTML attribute.
	 *
	 * @param {string} str String to escape.
	 * @returns {string}
	 */
	function escapeAttr(str) {
		return str
			.replace(/&/g, '&amp;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}

	// -------------------------------------------------------------------------
	// Initialize
	// -------------------------------------------------------------------------

	function init() {
		initCheckNow();
		initTestConnection();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
