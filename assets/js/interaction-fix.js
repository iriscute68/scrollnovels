/**
 * Global Interaction Fix
 * Ensures all clickable elements remain interactive across the entire website
 */

(function() {
    'use strict';

    // Wait for DOM to be fully loaded
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🔧 Global Interaction Fix: Loading...');

        // Fix 1: Ensure all links are clickable
        document.querySelectorAll('a').forEach(link => {
            link.style.pointerEvents = 'auto';
            link.addEventListener('click', function(e) {
                // Allow normal link behavior
                if (!this.href) e.preventDefault();
            });
        });

        // Fix 2: Ensure all buttons are clickable
        document.querySelectorAll('button').forEach(btn => {
            btn.style.pointerEvents = 'auto';
            btn.style.cursor = 'pointer';
        });

        // Fix 3: Ensure all form elements work
        document.querySelectorAll('input, textarea, select').forEach(input => {
            input.style.pointerEvents = 'auto';
        });

        // Fix 4: Ensure main content is interactive
        const main = document.querySelector('main');
        if (main) {
            main.style.pointerEvents = 'auto';
        }

        // Fix 5: Ensure header/nav is interactive
        const header = document.querySelector('header');
        if (header) {
            header.style.pointerEvents = 'auto';
            header.querySelectorAll('a, button').forEach(el => {
                el.style.pointerEvents = 'auto';
            });
        }

        // Fix 6: Ensure sidebars don't block interaction
        const sidebar = document.querySelector('#sidebar, aside');
        if (sidebar) {
            sidebar.style.pointerEvents = 'auto';
            sidebar.querySelectorAll('a, button').forEach(el => {
                el.style.pointerEvents = 'auto';
            });
        }

        // Fix 7: Ensure overlays allow interaction
        document.querySelectorAll('[class*="overlay"], [class*="modal"], [class*="popup"]').forEach(el => {
            el.style.pointerEvents = 'auto';
            el.querySelectorAll('a, button, input, textarea, select').forEach(interactive => {
                interactive.style.pointerEvents = 'auto';
            });
        });

        // Fix 8: Ensure book cards are clickable
        document.querySelectorAll('[class*="card"]').forEach(card => {
            card.style.pointerEvents = 'auto';
            card.querySelectorAll('a, button').forEach(el => {
                el.style.pointerEvents = 'auto';
            });
        });

        // Fix 9: Ensure carousel/slider buttons work
        document.querySelectorAll('[id*="prev"], [id*="next"], [class*="carousel"], [class*="slider"]').forEach(el => {
            el.style.pointerEvents = 'auto';
            if (el.tagName === 'BUTTON') {
                el.style.cursor = 'pointer';
            }
        });

        // Fix 10: Ensure admin login modal works
        const adminModal = document.getElementById('adminLoginModal');
        if (adminModal) {
            adminModal.style.pointerEvents = 'auto';
            adminModal.querySelectorAll('a, button, input, textarea, select').forEach(el => {
                el.style.pointerEvents = 'auto';
            });
        }

        // Fix 11: Make sure body doesn't have pointer-events-none
        document.body.style.pointerEvents = 'auto';
        document.documentElement.style.pointerEvents = 'auto';

        // Fix 12: Fix for hidden elements - they shouldn't block clicks
        const fixHiddenElements = () => {
            document.querySelectorAll('.hidden, [style*="display:none"]').forEach(el => {
                // Keep them hidden but allow pointer events on non-hidden siblings
                if (el.classList.contains('hidden')) {
                    el.style.pointerEvents = 'none';
                }
            });
        };
        fixHiddenElements();

        // Fix 13: Fix for any element with pointer-events-none that shouldn't have it
        const fixPointerEventsConflict = () => {
            // Find interactive elements with pointer-events-none
            document.querySelectorAll('a.pointer-events-none, button.pointer-events-none, input.pointer-events-none').forEach(el => {
                el.style.pointerEvents = 'auto';
                el.classList.remove('pointer-events-none');
                el.classList.add('pointer-events-auto');
            });
        };
        fixPointerEventsConflict();

        // Fix 14: Use MutationObserver to fix new dynamically added elements
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) { // Element node
                            // Fix new links
                            if (node.tagName === 'A') {
                                node.style.pointerEvents = 'auto';
                            }
                            // Fix new buttons
                            if (node.tagName === 'BUTTON') {
                                node.style.pointerEvents = 'auto';
                                node.style.cursor = 'pointer';
                            }
                            // Fix children of new nodes
                            node.querySelectorAll('a, button, input, textarea, select').forEach(el => {
                                el.style.pointerEvents = 'auto';
                                if (el.tagName === 'BUTTON') {
                                    el.style.cursor = 'pointer';
                                }
                            });
                        }
                    });
                }
            });
        });

        // Start observing the document for changes
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        console.log('✅ Global Interaction Fix: Initialized');
    });

    // Fix 15: Also run on window load to catch lazy-loaded elements
    window.addEventListener('load', function() {
        console.log('🔧 Global Interaction Fix: Running post-load fixes...');
        
        // Reapply all fixes after all resources are loaded
        document.querySelectorAll('a, button').forEach(el => {
            el.style.pointerEvents = 'auto';
            if (el.tagName === 'BUTTON') {
                el.style.cursor = 'pointer';
            }
        });
        
        console.log('✅ Global Interaction Fix: Post-load complete');
    });

    // Fix 16: Fix for SPA (Single Page Application) navigation
    if (window.history && window.history.pushState) {
        const originalPushState = window.history.pushState;
        window.history.pushState = function() {
            originalPushState.apply(window.history, arguments);
            setTimeout(function() {
                document.querySelectorAll('a, button').forEach(el => {
                    el.style.pointerEvents = 'auto';
                });
            }, 100);
        };
    }

})();
