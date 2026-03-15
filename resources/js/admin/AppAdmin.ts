import '../../css/admin.css';
import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import AdminLayout from './Components/Layouts/AdminLayout.vue';
import VueApexCharts from 'vue3-apexcharts';

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        const pagePath = `./Pages/${name}.vue`;
        // Sous Windows, les clés du glob peuvent utiliser \ au lieu de /
        let page = pages[pagePath];
        if (!page) {
            const altPath = pagePath.replace(/\//g, '\\');
            page = pages[altPath];
        }
        if (!page) {
            const altPath = pagePath.replace(/\//g, '\\');
            const normalized = (p: string) => p.replace(/\\/g, '/');
            const match = Object.keys(pages).find((k) => normalized(k) === pagePath || normalized(k) === normalized(altPath));
            if (match) page = pages[match];
        }
        if (!page) {
            const availablePages = Object.keys(pages).map((p) => p.replace(/^\.\\(Pages\\)?|^\.\/(Pages\/)?/, '').replace(/\.vue$/, '').replace(/\\/g, '/'));
            console.error(`Page ${name} introuvable. Chemin recherché: ${pagePath}`);
            console.error('Pages disponibles:', availablePages);
            throw new Error(`Page ${name} introuvable dans resources/js/admin/Pages. Chemin recherché: ${pagePath}`);
        }
        page.default.layout = page.default.layout || AdminLayout;
        return page;
    },
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .component('VueApexCharts', VueApexCharts)
            .component('apexchart', VueApexCharts);
        
        // Debug: vérifier que l'app est bien montée
        console.log('Inertia app setup', { el, props });
        
        app.mount(el);
        
        // Debug: vérifier après le montage
        console.log('Inertia app mounted', el);
        
        // Fix immédiat pour les overlays invisibles qui bloquent les clics
        const fixBlockingOverlays = () => {
            // Trouver tous les éléments fixed qui pourraient bloquer
            const allElements = document.querySelectorAll('*');
            allElements.forEach((element) => {
                const style = window.getComputedStyle(element);
                const isFixed = style.position === 'fixed';
                const hasFullCoverage = 
                    (style.top === '0px' && style.bottom === '0px' && style.left === '0px' && style.right === '0px') ||
                    style.inset === '0px' ||
                    (element.classList.contains('fixed') && (element.classList.contains('inset-0') || element.classList.contains('inset-[0]')));
                
                // Vérifier si l'élément est visible et actif (ne pas le corriger si c'est un modal actif)
                const isVisible = 
                    style.display !== 'none' && 
                    style.visibility !== 'hidden' &&
                    parseFloat(style.opacity) > 0;
                
                // Vérifier si l'élément a explicitement pointer-events: auto (modal actif)
                const hasExplicitPointerEvents = (element as HTMLElement).style.pointerEvents === 'auto';
                
                // Ne pas corriger les modals actifs
                if (isVisible && hasExplicitPointerEvents) {
                    return; // Ignorer les modals actifs
                }
                
                const isInvisible = 
                    parseFloat(style.opacity) === 0 || 
                    style.display === 'none' || 
                    style.visibility === 'hidden' ||
                    (style.pointerEvents === 'none' && !hasExplicitPointerEvents);
                
                // Si c'est un élément fixed qui couvre tout l'écran et qui est invisible, désactiver pointer-events
                if (isFixed && hasFullCoverage && isInvisible) {
                    (element as HTMLElement).style.pointerEvents = 'none';
                    // Ne logger que si ce n'est pas déjà corrigé
                    if (style.display !== 'none') {
                        console.warn('Overlay bloquant corrigé:', element);
                    }
                }
                
                // Si c'est un élément fixed avec z-index élevé mais invisible, aussi le corriger
                const zIndex = parseInt(style.zIndex);
                if (isFixed && hasFullCoverage && zIndex > 100 && isInvisible && !hasExplicitPointerEvents) {
                    (element as HTMLElement).style.pointerEvents = 'none';
                    // Ne logger que si ce n'est pas déjà corrigé
                    if (style.display !== 'none') {
                        console.warn('Overlay z-index élevé corrigé:', element);
                    }
                }
            });
            
            // Vérifier aussi les éléments avec bg-black bg-opacity qui pourraient être des backdrops
            const backdrops = document.querySelectorAll('[class*="bg-black"][class*="opacity"], [class*="backdrop"], [class*="overlay"]');
            backdrops.forEach((backdrop) => {
                const backdropEl = backdrop as HTMLElement;
                const style = window.getComputedStyle(backdrop);
                const hasExplicitPointerEvents = backdropEl.style.pointerEvents === 'auto';
                
                // PRIORITÉ ABSOLUE : Ignorer complètement les modals protégés
                const hasDataModal = backdropEl.hasAttribute('data-modal');
                const hasDataProtected = backdropEl.hasAttribute('data-protected');
                const hasPreventClose = backdropEl.hasAttribute('data-modal') && backdropEl.getAttribute('data-modal') === 'prevent-close';
                
                // Ne JAMAIS toucher aux modals protégés
                if (hasDataModal || hasDataProtected || hasPreventClose) {
                    return; // Ignorer complètement
                }
                
                // Ne pas corriger les backdrops actifs (modals visibles)
                if (hasExplicitPointerEvents && style.display !== 'none' && parseFloat(style.opacity) > 0) {
                    return;
                }
                
                if (parseFloat(style.opacity) === 0 || style.display === 'none') {
                    backdropEl.style.pointerEvents = 'none';
                    // Ne logger que si ce n'est pas déjà corrigé
                    if (style.display !== 'none') {
                        console.warn('Backdrop invisible corrigé:', backdrop);
                    }
                }
            });
        };
        
        // Exécuter immédiatement et plusieurs fois pour être sûr
        setTimeout(fixBlockingOverlays, 0);
        setTimeout(fixBlockingOverlays, 100);
        setTimeout(fixBlockingOverlays, 500);
        setTimeout(fixBlockingOverlays, 1000);
        
        // Observer les changements du DOM pour corriger les nouveaux éléments
        const observer = new MutationObserver(() => {
            fixBlockingOverlays();
        });
        observer.observe(document.body, { childList: true, subtree: true });
    },
    progress: {
        delay: 250,
        color: '#29d',
        includeCSS: true,
        showSpinner: false,
    },
});

