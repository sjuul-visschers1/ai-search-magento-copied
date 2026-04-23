(function() {
    'use strict';

    // Wait for config to be available
    if (typeof window.SSAI_CONFIG === 'undefined') {
        console.warn('Site Search AI: Configuration not found.');
        return;
    }

    const config = window.SSAI_CONFIG;
    let debounceTimer = null;
    let activeInput = null;
    let resultsContainer = null;

    /**
     * Initialize the search widget
     */
    function init() {
        // Get results page selectors to exclude from live search
        const resultsPageSelectors = config.resultsPageSelector ? config.resultsPageSelector.split(',').map(s => s.trim()).filter(s => s) : [];
        
        // Enhance coupled search inputs
        if (config.searchSelector && Array.isArray(config.searchSelector)) {
            config.searchSelector.forEach(coupling => {
                const searchInputs = document.querySelectorAll(coupling.input);
                searchInputs.forEach(input => {
                    // Skip if this input matches a results page selector
                    const isResultsPageInput = resultsPageSelectors.some(selector => input.matches(selector));
                    if (!isResultsPageInput) {
                        enhanceSearchInput(input, coupling.container);
                    }
                });
            });
        }

        // Also enhance our shortcode inputs (default behavior)
        const ssaiInputs = document.querySelectorAll('.ssai-search-input');
        ssaiInputs.forEach(input => enhanceSearchInput(input));

        // Initialize AI Overview containers (for search results pages)
        const overviewContainers = document.querySelectorAll('.ssai-overview-container');
        overviewContainers.forEach(initOverview);

        // Watch for late-inserted overview containers (e.g. Algolia injects async)
        const overviewObserver = new MutationObserver(() => {
            document.querySelectorAll('.ssai-overview-container:not([data-ssai-inited])').forEach(initOverview);
        });
        overviewObserver.observe(document.body, { childList: true, subtree: true });

        // Set CSS custom properties for colors and styling
        document.documentElement.style.setProperty('--ssai-primary-color', config.primaryColor);
        
        // Apply results styling
        if (config.styling) {
            document.documentElement.style.setProperty('--ssai-bg-color', config.styling.resultBgColor);
            document.documentElement.style.setProperty('--ssai-text-color', config.styling.resultTextColor);
            document.documentElement.style.setProperty('--ssai-font-family', config.styling.resultFontFamily);
            document.documentElement.style.setProperty('--ssai-font-size', config.styling.resultFontSize);
            document.documentElement.style.setProperty('--ssai-border-radius', config.styling.resultBorderRadius);
            document.documentElement.style.setProperty('--ssai-border-width', config.styling.resultBorderWidth);
            document.documentElement.style.setProperty('--ssai-border-color', config.styling.resultBorderColor);
            document.documentElement.style.setProperty('--ssai-box-shadow', config.styling.resultBoxShadow);
            if (config.styling.overviewContainerMaxWidth) {
                document.documentElement.style.setProperty(
                    '--ssai-overview-container-max-width',
                    config.styling.overviewContainerMaxWidth
                );
            }
            if (config.styling.overviewProductCardMaxWidth) {
                document.documentElement.style.setProperty(
                    '--ssai-product-card-max-width',
                    config.styling.overviewProductCardMaxWidth
                );
            }
        }

        // Close results when clicking outside
        document.addEventListener('click', handleOutsideClick);
    }

    /**
     * Fade out the overview glow line (no match or error).
     */
    function fadeOutOverview(container) {
        container.className = container.className
            .replace(/\bssai-overview-loading\b/, '')
            .replace(/\bssai-overview-expanding\b/, '')
            .replace(/\bssai-overview-placeholder\b/, '');
        container.classList.add('ssai-overview-no-match');
        container.addEventListener('transitionend', function handler() {
            container.style.display = 'none';
            container.removeEventListener('transitionend', handler);
        });
    }

    /**
     * Transition the overview from glow line to expanded container with content.
     */
    function expandOverview(container, onReady) {
        container.className = container.className
            .replace(/\bssai-overview-loading\b/, '')
            .replace(/\bssai-overview-placeholder\b/, '');
        container.classList.add('ssai-overview-expanding');

        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                container.classList.add('ssai-expand-active');
            });
        });

        container.addEventListener('transitionend', function handler(e) {
            if (e.propertyName !== 'max-height') return;
            container.removeEventListener('transitionend', handler);
            container.classList.remove('ssai-overview-expanding', 'ssai-expand-active');
            container.classList.add('ssai-overview-loaded');
            if (onReady) onReady();
        });

        setTimeout(() => {
            if (!container.classList.contains('ssai-overview-loaded')) {
                container.classList.remove('ssai-overview-expanding', 'ssai-expand-active');
                container.classList.add('ssai-overview-loaded');
                if (onReady) onReady();
            }
        }, 800);
    }

    /**
     * Initialize AI Overview container (for search results pages)
     */
    function initOverview(container) {
        if (container.getAttribute('data-ssai-inited') === 'true') return;
        container.setAttribute('data-ssai-inited', 'true');

        const query = container.getAttribute('data-query');
        if (!query || query.length < 3) {
            fadeOutOverview(container);
            return;
        }

        const contentContainer = container.querySelector('.ssai-overview-content');
        if (!contentContainer) return;

        const titleElement = container.querySelector('.ssai-overview-title');
        if (titleElement) {
            const emoji = config.styling?.showAiEmoji ? `<span class="ssai-emoji">${config.styling.aiEmojiChar}</span> ` : '';
            titleElement.innerHTML = emoji + config.i18n.aiOverviewTitle;
        }

        performOverviewSearch(query, contentContainer, container);
    }

    /**
     * Perform search for AI Overview
     */
    async function performOverviewSearch(query, contentContainer, mainContainer) {
        if (!config.customerId) {
            console.error('Site Search AI: Customer ID is missing.');
            fadeOutOverview(mainContainer);
            return;
        }

        const requestBody = {
            user_search_query: query,
            logged_in: config.isLoggedIn,
            ...(config.namespace ? { namespace: config.namespace } : {})
        };

        try {
            const headers = {
                'Content-Type': 'application/json',
            };
            
            if (config.customerId) {
                headers['X-Customer-ID'] = config.customerId;
            }
            
            console.log('Site Search AI: Fetching from URL:', config.apiUrl);
            console.log('Site Search AI: Request body:', requestBody);
            
            const response = await fetch(config.apiUrl, {
                method: 'POST',
                headers: headers,
                body: JSON.stringify(requestBody)
            });

            console.log('Site Search AI: Response status:', response.status, response.statusText);

            if (!response.ok) {
                const responseText = await response.text();
                console.error('Site Search AI: Non-OK response body:', responseText);
                let errorData = {};
                try {
                    errorData = JSON.parse(responseText);
                } catch (e) {
                    console.error('Site Search AI: Failed to parse error response as JSON:', e);
                }
                if (response.status === 422) {
                    console.error('Site Search AI Validation Error:', errorData);
                }
                const message = errorData.error || errorData.detail || `HTTP error! status: ${response.status}`;
                throw new Error(message);
            }

            const responseText = await response.text();
            console.log('Site Search AI: Response body:', responseText);
            
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                console.error('Site Search AI: Failed to parse response as JSON:', e);
                throw new Error('Invalid JSON response from server');
            }
            
            console.log('Site Search AI: Parsed data:', data);
            
            try {
                displayOverviewResults(data, contentContainer, mainContainer);
            } catch (displayError) {
                console.error('Site Search AI: Error in displayOverviewResults:', displayError);
                fadeOutOverview(mainContainer);
            }

        } catch (error) {
            console.error('Site Search AI Error:', error);
            fadeOutOverview(mainContainer);
        }
    }

    /**
     * Type text into an element in fast chunks for a smooth rapid-reveal effect.
     */
    function typeText(element, text, charDelayMs = 4, onComplete) {
        let i = 0;
        const len = text.length;
        const chunkSize = 3;
        element.textContent = '';

        function typeChunk() {
            if (i < len) {
                const end = Math.min(i + chunkSize, len);
                element.textContent += text.substring(i, end);
                i = end;
                setTimeout(typeChunk, charDelayMs);
            } else if (onComplete) {
                onComplete();
            }
        }
        typeChunk();
    }

    /**
     * Display AI Overview results.
     * Expands the glowing line into a full container, then uses a typing effect.
     */
    function displayOverviewResults(data, contentContainer, mainContainer) {
        console.log('Site Search AI: displayOverviewResults called with data:', data);

        if (!data) {
            console.error('Site Search AI: No data received');
            fadeOutOverview(mainContainer);
            return;
        }

        const answerText = data.answer ? String(data.answer).trim() : '';
        if (!answerText || answerText === '') {
            console.warn('Site Search AI: Empty answer received, hiding overview. Answer value:', data.answer);
            fadeOutOverview(mainContainer);
            return;
        }

        // Build content before expanding so the container knows its target size
        const answerDiv = document.createElement('div');
        answerDiv.className = 'ssai-answer';

        const staticContent = document.createElement('div');
        staticContent.className = 'ssai-overview-static';
        staticContent.style.opacity = '0';
        staticContent.style.transition = 'opacity 0.2s ease';

        if (data.cta && data.lead_generating_url) {
            const ctaDiv = document.createElement('div');
            ctaDiv.className = 'ssai-cta-buttons';
            ctaDiv.innerHTML = `<a href="${escapeHtml(data.lead_generating_url)}" class="ssai-cta-button ssai-cta-primary">${escapeHtml(data.cta)}</a>`;
            staticContent.appendChild(ctaDiv);
        }

        const productHits = data.product_hits || [];
        if (productHits.length > 0) {
            const productsHtml = buildProductCardsHtml(productHits);
            if (productsHtml) {
                const productsDiv = document.createElement('div');
                productsDiv.className = 'ssai-products-wrapper';
                productsDiv.innerHTML = productsHtml;
                staticContent.appendChild(productsDiv);
            }
        }

        const sourceUrls = data.source_url || data.source_urls || [];
        if (Array.isArray(sourceUrls) && sourceUrls.length > 0) {
            const sourcesDiv = document.createElement('div');
            sourcesDiv.className = 'ssai-sources';
            let sourcesHtml = `<span class="ssai-sources-label">${escapeHtml(config.i18n.sourcesLabel)}</span>`;
            sourceUrls.forEach(url => {
                if (!url || typeof url !== 'string') return;
                if (!url.startsWith('http://') && !url.startsWith('https://')) return;
                let displayUrl = url;
                try {
                    displayUrl = new URL(url).pathname || url;
                } catch (e) {
                    displayUrl = url;
                }
                sourcesHtml += `<a href="${escapeHtml(url)}" target="_blank" rel="noopener noreferrer" class="ssai-source-link">${escapeHtml(displayUrl)}</a>`;
            });
            sourcesDiv.innerHTML = sourcesHtml;
            staticContent.appendChild(sourcesDiv);
        }

        const prominence = config.styling?.poweredByProminence || 'small';
        const poweredBy = document.createElement('div');
        poweredBy.className = `ssai-powered-by ssai-powered-by--${prominence}`;
        poweredBy.textContent = config.i18n.poweredBy;
        staticContent.appendChild(poweredBy);

        attachFeedbackRow(staticContent, mainContainer.getAttribute('data-query') || '', answerText);

        contentContainer.innerHTML = '';
        contentContainer.appendChild(answerDiv);
        contentContainer.appendChild(staticContent);

        // Expand the glow line into the full container, then start typing
        expandOverview(mainContainer, () => {
            typeText(answerDiv, answerText, 4, () => {
                staticContent.style.opacity = '1';
            });
        });

        console.log('Site Search AI: Successfully displayed overview results');
    }

    /**
     * Enhance a search input with AI capabilities (non-invasive)
     * Does NOT modify the original search bar structure
     */
    function enhanceSearchInput(input, customContainerSelector = null) {
        // Skip if already enhanced
        if (input.getAttribute('data-ssai-enhanced') === 'true') {
            return;
        }

        let container = null;
        
        // Check if a custom container is provided and exists
        if (customContainerSelector) {
            // Support multiple selectors separated by commas (OR logic)
            const selectors = customContainerSelector.split(',').map(s => s.trim()).filter(s => s);
            
            for (const selector of selectors) {
                const target = document.querySelector(selector);
                if (target) {
                    container = target;
                    // Mark this container as an SSAI results container
                    container.classList.add('ssai-results-container', 'ssai-coupled-container');
                    break; // Use the first one found
                }
            }
        }

        // If no custom container, use default dropdown behavior
        if (!container) {
            // Find the best parent to attach results to
            // Try form first, then parent element
            const form = input.closest('form');
            const parentElement = form || input.parentElement;
            
            // Create results container if it doesn't exist
            container = parentElement.querySelector('.ssai-results-container:not(.ssai-coupled-container)');
            if (!container) {
                container = document.createElement('div');
                container.className = 'ssai-results-container';
                container.setAttribute('data-position', config.position);
                
                // Make parent relative for positioning, only if not already positioned
                const parentPosition = window.getComputedStyle(parentElement).position;
                if (parentPosition === 'static') {
                    parentElement.style.position = 'relative';
                }
                
                parentElement.appendChild(container);
            }
        }

        // Add event listeners based on live search setting
        if (config.liveSearch) {
            input.addEventListener('input', function(e) {
                handleInput(e, container);
            });
        }

        // Add Enter key handler to trigger search (don't prevent default to allow form submit)
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideResults(container);
            }
        });

        // Trigger search on input (with debounce) or when form would submit
        input.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                const query = input.value.trim();
                if (query.length >= 3) {
                    performSearch(query, container);
                }
            }
        });

        input.addEventListener('focus', function() {
            activeInput = input;
            resultsContainer = container;
        });

        // Mark as enhanced
        input.setAttribute('data-ssai-enhanced', 'true');
    }

    /**
     * Handle input changes with debouncing
     */
    function handleInput(event, container) {
        const query = event.target.value.trim();

        clearTimeout(debounceTimer);

        if (query.length < 3) {
            hideResults(container);
            return;
        }

        debounceTimer = setTimeout(() => {
            performSearch(query, container);
        }, 120);
    }

    /**
     * Perform the search API call
     */
    async function performSearch(query, container) {
        if (!config.customerId) {
            console.error('Site Search AI: Customer ID is missing. Please visit the plugin settings page to generate one.');
            showError(container);
            return;
        }
        
        showLoading(container);

        const requestBody = {
            user_search_query: query,
            logged_in: config.isLoggedIn,
            ...(config.namespace ? { namespace: config.namespace } : {})
        };

        try {
            const headers = {
                'Content-Type': 'application/json',
            };
            
            // Add customer ID header if available
            if (config.customerId) {
                headers['X-Customer-ID'] = config.customerId;
            }
            
            console.log('Site Search AI: Fetching from URL:', config.apiUrl);
            console.log('Site Search AI: Request body:', requestBody);
            
            const response = await fetch(config.apiUrl, {
                method: 'POST',
                headers: headers,
                body: JSON.stringify(requestBody)
            });

            console.log('Site Search AI: Response status:', response.status, response.statusText);

            if (!response.ok) {
                const responseText = await response.text();
                console.error('Site Search AI: Non-OK response body:', responseText);
                let errorData = {};
                try {
                    errorData = JSON.parse(responseText);
                } catch (e) {
                    console.error('Site Search AI: Failed to parse error response as JSON:', e);
                }
                if (response.status === 422) {
                    console.error('Site Search AI Validation Error:', errorData);
                }
                const message = errorData.error || errorData.detail || `HTTP error! status: ${response.status}`;
                throw new Error(message);
            }

            const responseText = await response.text();
            console.log('Site Search AI: Response body:', responseText);
            
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                console.error('Site Search AI: Failed to parse response as JSON:', e);
                console.error('Site Search AI: Response text:', responseText);
                throw new Error('Invalid JSON response from server');
            }
            
            console.log('Site Search AI: Parsed data:', data);
            displayResults(data, container, query);

        } catch (error) {
            console.error('Site Search AI Error:', error);
            console.error('Site Search AI Error Stack:', error.stack);
            showError(container, error.message);
        }
    }

    /**
     * Display search results
     */
    function displayResults(data, container, searchQuery) {
        console.log('Site Search AI: displayResults called with data:', data);
        
        if (!data) {
            console.error('Site Search AI: No data received');
            hideResults(container);
            return;
        }
        
        // Check if answer exists and is not empty (handle single space case from API)
        const answerText = data.answer ? String(data.answer).trim() : '';
        if (!answerText || answerText === '') {
            console.warn('Site Search AI: Empty answer received, hiding results. Answer value:', data.answer);
            container.innerHTML = `
                <div class="ssai-no-results">${escapeHtml(config.i18n.noResults)}</div>
            `;
            showResults(container);
            return;
        }

        let html = `
            <div class="ssai-result">
                <div class="ssai-answer">${escapeHtml(answerText)}</div>
        `;

        // Add CTA button if LLM selected one (from custom CTAs or generated)
        if (data.cta && data.lead_generating_url) {
            html += `
                <div class="ssai-cta-buttons">
                    <a href="${escapeHtml(data.lead_generating_url)}" class="ssai-cta-button ssai-cta-primary">
                        ${escapeHtml(data.cta)}
                    </a>
                </div>
            `;
        }

        // Add product cards if available
        const productHits = data.product_hits || [];
        if (productHits.length > 0) {
            const productsHtml = buildProductCardsHtml(productHits);
            if (productsHtml) {
                html += `<div class="ssai-products-wrapper">${productsHtml}</div>`;
            }
        }

        // Add source links if available (handle both source_url and source_urls for compatibility)
        const sourceUrls = data.source_url || data.source_urls || [];
        if (Array.isArray(sourceUrls) && sourceUrls.length > 0) {
            html += `<div class="ssai-sources"><span class="ssai-sources-label">${escapeHtml(config.i18n.sourcesLabel)}</span>`;
            sourceUrls.forEach(url => {
                if (!url || typeof url !== 'string') return;
                
                // Skip non-HTTP URLs (like pdf://, file://, etc.) - they can't be linked
                if (!url.startsWith('http://') && !url.startsWith('https://')) {
                    console.warn('Site Search AI: Skipping non-HTTP URL:', url);
                    return;
                }
                
                let displayUrl = url;
                try {
                    const urlObj = new URL(url);
                    displayUrl = urlObj.pathname || url;
                } catch (e) {
                    // If URL parsing fails, use the original URL
                    console.warn('Site Search AI: Invalid URL format:', url);
                    displayUrl = url;
                }
                html += `<a href="${escapeHtml(url)}" target="_blank" rel="noopener noreferrer" class="ssai-source-link">${escapeHtml(displayUrl)}</a>`;
            });
            html += '</div>';
        }

        // Add powered by with prominence class
        const prominence = config.styling?.poweredByProminence || 'small';
        html += `
                <div class="ssai-powered-by ssai-powered-by--${prominence}">${config.i18n.poweredBy}</div>
            </div>
        `;

        container.innerHTML = html;
        const resultEl = container.querySelector('.ssai-result');
        if (resultEl) {
            attachFeedbackRow(resultEl, searchQuery || '', answerText);
        }
        showResults(container);
    }

    /**
     * Show loading state
     */
    function showLoading(container) {
        container.innerHTML = `
            <div class="ssai-loading">
                <div class="ssai-spinner"></div>
                <span>${config.i18n.loading}</span>
            </div>
        `;
        showResults(container);
    }

    /**
     * Show error state
     */
    function showError(container, errorMessage = null) {
        const errorText = errorMessage ? `${config.i18n.error}<br><small>${escapeHtml(errorMessage)}</small>` : config.i18n.error;
        container.innerHTML = `
            <div class="ssai-error">
                ${errorText}
            </div>
        `;
    }

    /**
     * Show results container
     */
    function showResults(container) {
        container.classList.add('ssai-visible');
    }

    /**
     * Hide results container
     */
    function hideResults(container) {
        container.classList.remove('ssai-visible');
    }

    /**
     * Handle clicks outside the search widget
     */
    function handleOutsideClick(event) {
        if (resultsContainer && 
            !resultsContainer.contains(event.target) && 
            activeInput && 
            !activeInput.contains(event.target)) {
            hideResults(resultsContainer);
        }
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        if (text == null || text === '') return '';
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    /**
     * Thumbs up/down row for AI answers (AI Overview and live search dropdown).
     */
    function attachFeedbackRow(parentElement, searchQuery, answerText) {
        if (!parentElement) {
            return;
        }
        const feedbackDiv = document.createElement('div');
        feedbackDiv.className = 'ssai-feedback';
        feedbackDiv.innerHTML = `
            <span class="ssai-feedback-label">${escapeHtml(config.i18n.wasThisHelpful)}</span>
            <button type="button" class="ssai-feedback-btn ssai-feedback-up" aria-label="${escapeHtml(config.i18n.feedbackThumbsUpAria)}">👍</button>
            <button type="button" class="ssai-feedback-btn ssai-feedback-down" aria-label="${escapeHtml(config.i18n.feedbackThumbsDownAria)}">👎</button>
            <span class="ssai-feedback-thanks" style="display:none;">${escapeHtml(config.i18n.feedbackThanks)}</span>
        `;

        const upBtn = feedbackDiv.querySelector('.ssai-feedback-up');
        const downBtn = feedbackDiv.querySelector('.ssai-feedback-down');
        const thanksSpan = feedbackDiv.querySelector('.ssai-feedback-thanks');
        const answerSnippet = (answerText || '').substring(0, 200);

        function handleFeedback(value) {
            upBtn.disabled = true;
            downBtn.disabled = true;
            upBtn.style.opacity = value === 'up' ? '1' : '0.4';
            downBtn.style.opacity = value === 'down' ? '1' : '0.4';
            thanksSpan.style.display = 'inline';
            if (config.feedbackUrl) {
                const headers = { 'Content-Type': 'application/json' };
                if (config.customerId) {
                    headers['X-Customer-ID'] = config.customerId;
                }
                fetch(config.feedbackUrl, {
                    method: 'POST',
                    headers: headers,
                    body: JSON.stringify({
                        query: searchQuery || '',
                        feedback: value,
                        answer: answerSnippet
                    })
                }).catch(() => {});
            }
        }

        upBtn.addEventListener('click', () => handleFeedback('up'));
        downBtn.addEventListener('click', () => handleFeedback('down'));
        parentElement.appendChild(feedbackDiv);
    }

    /**
     * Build product cards HTML (image, title, link) for product_hits from API
     */
    function buildProductCardsHtml(productHits) {
        if (!Array.isArray(productHits) || productHits.length === 0) return '';
        const viewLabel = config.i18n.viewProduct;
        let html = '<div class="ssai-product-cards">';
        productHits.forEach(p => {
            let title = p.title || '';
            if (!title && p.text) {
                const firstLine = String(p.text).split('\n')[0] || '';
                title = firstLine.length > 80 ? firstLine.substring(0, 77) + '...' : firstLine;
            }
            const imgUrl = p.image && (p.image.startsWith('http://') || p.image.startsWith('https://')) ? p.image : null;
            const productUrl = p.url && (p.url.startsWith('http://') || p.url.startsWith('https://')) ? p.url : null;
            html += '<div class="ssai-product-card">';
            html += '<div class="ssai-product-card-image">';
            if (imgUrl) {
                html += `<img src="${escapeHtml(imgUrl)}" alt="${escapeHtml(title)}" loading="lazy" />`;
            } else {
                html += '<div class="ssai-product-card-placeholder"></div>';
            }
            html += '</div>';
            if (title) {
                html += `<div class="ssai-product-card-title">${escapeHtml(title)}</div>`;
            }
            if (productUrl) {
                html += `<a href="${escapeHtml(productUrl)}" class="ssai-product-card-link" target="_blank" rel="noopener noreferrer">${escapeHtml(viewLabel)}</a>`;
            }
            html += '</div>';
        });
        html += '</div>';
        return html;
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();