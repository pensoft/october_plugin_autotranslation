// Language Selection Management
(function() {
    'use strict';

    // Debug mode (enable with ?debug=1 in URL)
    const DEBUG = window.location.search.includes('debug=1');

    // Logging helpers
    function log(message, data) {
        if (DEBUG) {
            console.log('[AutoTranslation:Messages] ' + message, data || '');
        }
    }

    function logError(message, error) {
        console.error('[AutoTranslation:Messages] ' + message, error);
    }

    function logWarn(message, data) {
        console.warn('[AutoTranslation:Messages] ' + message, data || '');
    }

    // Check required browser features
    if (typeof Array.from !== 'function' || typeof document.querySelector !== 'function') {
        logError('Missing required browser features');
        return;
    }

    // Check jQuery and OctoberCMS
    if (typeof window.$ === 'undefined') {
        logError('jQuery not loaded');
        return;
    }

    // Safe initialization wrapper
    function safeInit() {
        try {
            initializeMessageTranslation();
        } catch (error) {
            logError('Failed to initialize message translation interface', error);
            showUserError('Failed to initialize translation interface. Please refresh the page.');
        }
    }

    // Show user-friendly error message
    function showUserError(message) {
        if (window.$.oc && window.$.oc.flashMsg) {
            $.oc.flashMsg({
                text: message,
                class: 'error',
                interval: 5
            });
        }
    }

    // Main initialization function
    function initializeMessageTranslation() {
        log('Initializing message translation interface');

        // Get DOM elements with null safety
        const checkboxes = document.querySelectorAll('.target-locale-checkbox');
        const selectAllBtn = document.getElementById('selectAllLanguages');
        const deselectAllBtn = document.getElementById('deselectAllLanguages');
        const selectedContainer = document.getElementById('selectedLanguagesBadges');
        const selectedList = document.getElementById('selectedLanguagesList');
        const selectedCount = document.getElementById('selectedCount');
        const translateBtn = document.getElementById('translateButton');
        const overwriteCheckbox = document.getElementById('overwrite_existing');
        const whenUnchecked = document.querySelector('.when-unchecked');
        const whenChecked = document.querySelector('.when-checked');
        const estimateCard = document.getElementById('translationEstimate');
        const estimateCount = document.getElementById('estimateCount');
        const loadingOverlay = document.getElementById('translationLoadingOverlay');
        const loadingTitle = document.querySelector('.translation-status-title');
        const loadingLanguages = document.querySelector('.translation-status-languages');
        const form = document.getElementById('translateMessagesForm');

        // Log found elements
        log('Elements found:', {
            checkboxes: checkboxes.length,
            selectAllBtn: !!selectAllBtn,
            deselectAllBtn: !!deselectAllBtn,
            translateBtn: !!translateBtn,
            form: !!form,
            loadingOverlay: !!loadingOverlay
        });

        // Get totalMessages safely
        const totalMessages = estimateCount ? parseInt(estimateCount.dataset.totalMessages || 0) : 0;
        log('Total messages:', totalMessages);

        // Timeout tracking for loading overlay
        let loadingTimeout = null;

        // Show loading overlay function with safety checks
        function showLoadingOverlay() {
            if (!loadingOverlay || !loadingTitle || !loadingLanguages) {
                logWarn('Loading overlay elements not found - skipping overlay display');
                return;
            }

            try {
                log('Showing loading overlay');
                const selected = Array.from(checkboxes).filter(cb => cb.checked);

                loadingTitle.textContent = 'Translating Interface Messages...';
                loadingLanguages.innerHTML = selected.map(cb => {
                    const name = cb.dataset.localeName || 'Unknown';
                    const code = cb.dataset.localeCode || 'XX';
                    return '<div class="translation-lang-item">' +
                           '<i class="icon-circle-o-notch icon-spin"></i>' +
                           '<span>Translating to ' + name + ' (' + code + ')</span>' +
                           '</div>';
                }).join('');

                loadingOverlay.style.display = 'flex';
                document.body.classList.add('translation-in-progress');

                // Safety timeout: auto-hide after 5 minutes
                loadingTimeout = setTimeout(function() {
                    logWarn('Loading overlay timeout - auto-hiding after 5 minutes');
                    hideLoadingOverlay();
                    showUserError('Translation took too long. Please check the results and try again if needed.');
                }, 5 * 60 * 1000);

            } catch (error) {
                logError('Failed to show loading overlay', error);
            }
        }

        // Hide loading overlay function with safety checks
        function hideLoadingOverlay() {
            // Clear timeout
            if (loadingTimeout) {
                clearTimeout(loadingTimeout);
                loadingTimeout = null;
            }

            if (!loadingOverlay) {
                logWarn('Loading overlay element not found - cannot hide');
                return;
            }

            try {
                log('Hiding loading overlay');
                loadingOverlay.style.display = 'none';
                document.body.classList.remove('translation-in-progress');
            } catch (error) {
                logError('Failed to hide loading overlay', error);
            }
        }

        // Update selected languages display
        function updateSelectedLanguages() {
            if (!selectedContainer || !selectedList || !selectedCount) {
                logWarn('Selected language display elements not found');
                return;
            }

            try {
                const selected = Array.from(checkboxes).filter(cb => cb.checked);
                selectedCount.textContent = selected.length;

                if (selected.length > 0) {
                    selectedContainer.style.display = 'block';
                    selectedList.innerHTML = selected.map(cb => {
                        const name = cb.dataset.localeName || 'Unknown';
                        const code = cb.dataset.localeCode || 'XX';
                        return '<div class="selected-language-badge">' +
                               '<span class="name">' + name + '</span>' +
                               '<span class="code">' + code + '</span>' +
                               '<span class="remove" data-locale="' + cb.value + '">×</span>' +
                               '</div>';
                    }).join('');

                    if (translateBtn) {
                        translateBtn.disabled = false;
                    }

                    // Update estimate
                    if (estimateCount && estimateCard) {
                        const estimate = totalMessages * selected.length;
                        estimateCount.textContent = estimate.toLocaleString();
                        estimateCard.style.display = 'block';
                    }
                } else {
                    selectedContainer.style.display = 'none';
                    if (translateBtn) {
                        translateBtn.disabled = true;
                    }
                    if (estimateCard) {
                        estimateCard.style.display = 'none';
                    }
                }
            } catch (error) {
                logError('Failed to update selected languages', error);
            }
        }

        // Attach event handlers with null safety

        // Checkbox change handlers
        if (checkboxes.length > 0) {
            checkboxes.forEach(function(cb) {
                cb.addEventListener('change', updateSelectedLanguages);
            });
            log('Attached ' + checkboxes.length + ' checkbox handlers');
        }

        // Select all handler
        if (selectAllBtn && checkboxes.length > 0) {
            selectAllBtn.addEventListener('click', function() {
                checkboxes.forEach(function(cb) { cb.checked = true; });
                updateSelectedLanguages();
            });
        }

        // Deselect all handler
        if (deselectAllBtn && checkboxes.length > 0) {
            deselectAllBtn.addEventListener('click', function() {
                checkboxes.forEach(function(cb) { cb.checked = false; });
                updateSelectedLanguages();
            });
        }

        // Remove badge handler (using event delegation)
        if (selectedList) {
            selectedList.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove')) {
                    const localeCode = e.target.dataset.locale;
                    const checkbox = document.querySelector('input[value="' + localeCode + '"]');
                    if (checkbox) {
                        checkbox.checked = false;
                        updateSelectedLanguages();
                    }
                }
            });
        }

        // Overwrite toggle handler
        if (overwriteCheckbox && whenUnchecked && whenChecked) {
            overwriteCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    whenUnchecked.style.display = 'none';
                    whenChecked.style.display = 'block';
                } else {
                    whenUnchecked.style.display = 'block';
                    whenChecked.style.display = 'none';
                }
            });
        }

        // Translate button click handler
        if (translateBtn) {
            translateBtn.addEventListener('click', function() {
                log('Translate button clicked');
                // Note: OctoberCMS handles the confirmation dialog
                // Overlay will be shown via ajaxPromise event
            });
        }

        // AJAX event handlers
        if (form) {
            log('Attaching AJAX event handlers');

            $(form).on('ajaxPromise', '[data-request="onTranslateMessages"]', function() {
                log('AJAX promise started');
                try {
                    showLoadingOverlay();
                } catch (error) {
                    logError('Failed to show loading overlay on AJAX start', error);
                }
            });

            $(form).on('ajaxDone', '[data-request="onTranslateMessages"]', function(event, data) {
                log('AJAX done', data);
                hideLoadingOverlay();
            });

            $(form).on('ajaxFail', '[data-request="onTranslateMessages"]', function(event, context, textStatus, errorThrown) {
                logError('AJAX failed', {textStatus: textStatus, error: errorThrown});
                hideLoadingOverlay();

                // Show user-friendly error if backend didn't
                if (!context.options.handleErrorMessage) {
                    showUserError('Translation failed. Please check your connection and try again.');
                }
            });

            // Always hide overlay on completion (success, error, or abort)
            $(form).on('ajaxAlways', '[data-request="onTranslateMessages"]', function() {
                log('AJAX always (cleanup)');
                hideLoadingOverlay();
            });
        } else {
            logWarn('Form not found - AJAX handlers not attached');
        }

        // Initial state
        try {
            updateSelectedLanguages();
            log('Initial state updated');
        } catch (error) {
            logError('Failed to set initial state', error);
        }

        log('Message translation interface initialized successfully');
    }

    // Wait for DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', safeInit);
    } else {
        // DOM already ready
        safeInit();
    }

})();
