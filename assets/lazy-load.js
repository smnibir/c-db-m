/**
 * Lazy Load Handler for Dashboard Tabs
 * Loads tab content via AJAX when the tab is clicked for the first time
 */

(function() {
    'use strict';

    // Track which tabs have been loaded
    const loadedTabs = new Set(['home']); // Home tab is always loaded initially
    const loadingTabs = new Set();

    // Tab to AJAX action mapping
    const tabLoadActions = {
        'task-list': 'load_tasks_tab',
        'meeting-notes': 'load_meeting_notes_tab',
        'performance-summary': 'load_performance_tab',
        'campaign-strategy': 'load_campaign_tab'
    };

    // Initialize lazy loading on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        const tabs = document.querySelectorAll('.clickup-sidebar li');
        
        tabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                const slug = this.dataset.slug;
                const tabId = this.dataset.tab;
                const tabContent = document.getElementById(tabId);
                
                // Check if this tab needs lazy loading
                if (tabLoadActions[slug] && !loadedTabs.has(slug) && !loadingTabs.has(slug)) {
                    loadTabContent(slug, tabContent);
                }
            });
        });
    });

    /**
     * Load tab content via AJAX
     * @param {string} slug Tab slug
     * @param {HTMLElement} container Tab content container
     */
    function loadTabContent(slug, container) {
        // Mark as loading
        loadingTabs.add(slug);
        
        // Show loading indicator
        showLoadingIndicator(container);
        
        // Get AJAX action
        const action = tabLoadActions[slug];
        
        // Make AJAX request
        fetch(clickupLazyLoad.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: action,
                nonce: clickupLazyLoad.nonce
            })
        })
        .then(response => response.json())
        .then(data => {
            loadingTabs.delete(slug);
            
            if (data.success) {
                // Replace loading indicator with actual content
                container.innerHTML = data.data.html;
                loadedTabs.add(slug);
                
                // Reinitialize any scripts that the loaded content needs
                reinitializeTabScripts(container, slug);
            } else {
                showErrorMessage(container, data.data?.message || 'Failed to load content');
            }
        })
        .catch(error => {
            loadingTabs.delete(slug);
            console.error('Error loading tab content:', error);
            showErrorMessage(container, 'Network error. Please try again.');
        });
    }

    /**
     * Show loading indicator in container
     * @param {HTMLElement} container
     */
    function showLoadingIndicator(container) {
        container.innerHTML = `
            <div class="clickup-loading-indicator">
                <div class="loading-spinner"></div>
                <p>Loading...</p>
            </div>
        `;
    }

    /**
     * Show error message in container
     * @param {HTMLElement} container
     * @param {string} message
     */
    function showErrorMessage(container, message) {
        container.innerHTML = `
            <div class="clickup-error-message">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <p>${message}</p>
                <button onclick="location.reload()" class="retry-button">Retry</button>
            </div>
        `;
    }

    /**
     * Reinitialize any scripts needed by the loaded content
     * @param {HTMLElement} container
     * @param {string} slug
     */
    function reinitializeTabScripts(container, slug) {
        // Reinitialize AOS animations if present
        if (typeof AOS !== 'undefined') {
            AOS.refresh();
        }

        // For task list, reinitialize task list functionality
        if (slug === 'task-list') {
            // Task list scripts are inline in the template, they will auto-execute
            // But we need to trigger a custom event in case other scripts need to know
            const event = new CustomEvent('taskListLoaded', { detail: { container } });
            document.dispatchEvent(event);
        }
    }

    // Expose utility function to clear cache and reload tab
    window.clickupReloadTab = function(slug) {
        if (loadedTabs.has(slug)) {
            loadedTabs.delete(slug);
            const tab = document.querySelector(`[data-slug="${slug}"]`);
            if (tab) {
                tab.click();
            }
        }
    };
})();
