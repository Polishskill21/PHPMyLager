import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/pages/customers.css',
                'resources/css/pages/dashboard.css',
                'resources/css/pages/login.css',
                'resources/css/pages/orders.css',
                'resources/css/pages/products.css',
                'resources/css/pages/purchase-orders.css',
                'resources/css/pages/suppliers.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
