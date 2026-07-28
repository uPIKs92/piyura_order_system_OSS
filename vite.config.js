import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import path from 'path';
import fs from 'fs';
import crypto from 'crypto';

/** Hash public/landing PNGs so rebuild auto-busts browser cache when shots change. */
function landingShotVersion() {
    const dir = path.resolve(__dirname, 'public/landing');
    const hash = crypto.createHash('md5');
    if (!fs.existsSync(dir)) {
        return 'missing';
    }
    for (const name of fs.readdirSync(dir).sort()) {
        if (!name.endsWith('.png')) continue;
        hash.update(name);
        hash.update(fs.readFileSync(path.join(dir, name)));
    }
    return hash.digest('hex').slice(0, 10);
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/main.tsx'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    define: {
        __LANDING_SHOT_V__: JSON.stringify(landingShotVersion()),
    },
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    build: {
        rolldownOptions: {
            output: {
                codeSplitting: true,
                manualChunks(id) {
                    if (!id.includes('node_modules')) {
                        return;
                    }
                    if (id.includes('react-dom') || id.includes('/react/')) {
                        return 'vendor-react';
                    }
                    if (id.includes('react-router')) {
                        return 'vendor-router';
                    }
                    if (id.includes('lucide-react')) {
                        return 'vendor-icons';
                    }
                },
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
