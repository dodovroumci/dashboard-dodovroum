<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <title inertia>DodoVroum Admin</title>
        @vite([
            'resources/css/app.css',
            'resources/js/app.js',
            'resources/js/admin/AppAdmin.ts',
        ])
        @inertiaHead
    </head>
    <body class="font-sans antialiased bg-slate-100 text-slate-900">
        @inertia
        
        <script>
        // Fix optimisé pour les overlays qui bloquent les clics (sans reflow forcé)
        (function() {
            let rafId = null;
            let isProcessing = false;
            
            function closeVisibleModals() {
                if (isProcessing) return;
                isProcessing = true;
                
                // Utiliser requestAnimationFrame pour éviter les reflows forcés
                if (rafId) cancelAnimationFrame(rafId);
                rafId = requestAnimationFrame(() => {
                    // Trouver uniquement les overlays pertinents (sélecteur optimisé)
                    const visibleOverlays = document.querySelectorAll('[class*="fixed"][class*="inset-0"]:not(#modal-suppression-final):not(#modal-suppression-residence-final):not(#modal-suppression-offer-final):not(#modal-suppression-vehicle-show-final):not([data-modal="prevent-close"])');
                    
                    // Regrouper toutes les modifications de style en une seule opération
                    const stylesToApply = [];
                    
                    visibleOverlays.forEach((overlay) => {
                        // Vérifier rapidement avec les classes CSS avant d'appeler getComputedStyle
                        const hasDarkBg = overlay.classList.contains('bg-black') || overlay.classList.contains('bg-opacity');
                        
                        if (hasDarkBg) {
                            // Utiliser l'inline style directement pour éviter getComputedStyle
                            const inlineDisplay = overlay.style.display;
                            const inlineOpacity = overlay.style.opacity;
                            
                            // Si l'overlay est visible (pas display:none dans inline style)
                            if (inlineDisplay !== 'none' && inlineOpacity !== '0') {
                                stylesToApply.push({
                                    element: overlay,
                                    display: 'none'
                                });
                            }
                        }
                    });
                    
                    // Appliquer tous les styles en une seule passe (évite les reflows multiples)
                    stylesToApply.forEach(({ element, display }) => {
                        element.style.display = display;
                    });
                    
                    // Réactiver les clics une seule fois
                    if (stylesToApply.length > 0) {
                        document.body.style.pointerEvents = 'auto';
                        document.documentElement.style.pointerEvents = 'auto';
                    }
                    
                    isProcessing = false;
                });
            }
            
            function fixBlockingOverlays() {
                if (isProcessing) return;
                
                // Utiliser requestAnimationFrame pour éviter les reflows forcés
                if (rafId) cancelAnimationFrame(rafId);
                rafId = requestAnimationFrame(() => {
                    // Sélecteur optimisé : uniquement les éléments fixed avec inset-0
                    const fixedElements = document.querySelectorAll('[class*="fixed"][class*="inset-0"]:not(#modal-suppression-final):not(#modal-suppression-residence-final):not(#modal-suppression-offer-final):not(#modal-suppression-vehicle-show-final):not([data-modal="prevent-close"])');
                    
                    // Regrouper toutes les modifications
                    const stylesToApply = [];
                    
                    fixedElements.forEach((element) => {
                        // Vérifier l'inline style d'abord (pas de reflow)
                        const inlineDisplay = element.style.display;
                        const inlineOpacity = element.style.opacity;
                        const inlinePointerEvents = element.style.pointerEvents;
                        
                        // Si l'élément est invisible et n'a pas déjà pointer-events: none
                        if ((inlineDisplay === 'none' || inlineOpacity === '0') && inlinePointerEvents !== 'none') {
                            stylesToApply.push({
                                element: element,
                                pointerEvents: 'none'
                            });
                        }
                    });
                    
                    // Appliquer tous les styles en une seule passe
                    stylesToApply.forEach(({ element, pointerEvents }) => {
                        element.style.pointerEvents = pointerEvents;
                    });
                    
                    isProcessing = false;
                });
            }
            
            // Debouncer les appels pour éviter les exécutions multiples
            let debounceTimer = null;
            const debouncedClose = () => {
                if (debounceTimer) clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    closeVisibleModals();
                    fixBlockingOverlays();
                }, 100);
            };
            
            // Fermer les modals visibles une seule fois au chargement
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', debouncedClose, { once: true });
            } else {
                debouncedClose();
            }
            
            // Observer les changements avec debounce
            if (document.body) {
                const observer = new MutationObserver(debouncedClose);
                observer.observe(document.body, { 
                    childList: true, 
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['style', 'class']
                });
            }
            
            // Ajouter un raccourci clavier pour fermer les modals (Escape)
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    closeVisibleModals();
                }
            });
            
            // Exposer une fonction globale pour fermer les modals depuis la console
            window.closeAllModals = function() {
                console.log('Fermeture de tous les modals...');
                const overlays = document.querySelectorAll('[class*="fixed"][class*="inset-0"]:not(#modal-suppression-final):not(#modal-suppression-residence-final):not(#modal-suppression-offer-final):not(#modal-suppression-vehicle-show-final):not([data-modal="prevent-close"])');
                let closedCount = 0;
                
                // Utiliser requestAnimationFrame pour éviter les reflows
                requestAnimationFrame(() => {
                    overlays.forEach((overlay) => {
                        const inlineDisplay = overlay.style.display;
                        if (inlineDisplay !== 'none') {
                            overlay.style.display = 'none';
                            closedCount++;
                        }
                    });
                    
                    if (closedCount > 0) {
                        document.body.style.pointerEvents = 'auto';
                        document.documentElement.style.pointerEvents = 'auto';
                    }
                    console.log('Fermeture terminée. ' + closedCount + ' modal(s) fermé(s).');
                });
            };
        })();
        </script>
    </body>
</html>

