/// <reference types="vite/client" />

declare module '*.vue' {
    import type { DefineComponent } from 'vue';
    const component: DefineComponent<{}, {}, any>;
    export default component;
}

// Suppression des erreurs JSX pour les templates Vue
declare global {
    namespace JSX {
        interface IntrinsicElements {
            [elem: string]: any;
        }
    }
}

// Déclaration pour que TypeScript reconnaisse les variables dans les templates Vue
declare module '@vue/runtime-core' {
    interface ComponentCustomProperties {
        // Permet l'accès aux variables définies dans <script setup>
        [key: string]: any;
    }
}

