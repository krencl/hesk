/// <reference types="vitest/config" />
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

// V dev režimu se /api proxuje na Symfony backend, takže se frontend a API tváří jako jeden origin
// (session cookie bez CORS).
export default defineConfig(({ command }) => ({
  plugins: [react()],
  // Build jde do web/static/ a v index.html se na assety odkazuje jako na /static/assets/… (dev server běží na /).
  base: command === 'build' ? '/static/' : '/',
  build: { outDir: '../web/static', emptyOutDir: true },
  server: {
    port: 5173,
    proxy: Object.fromEntries(
      ['/api', '/oauth'].map((p) => [p, { target: process.env.API_URL ?? 'http://localhost:8000', changeOrigin: false }]),
    ),
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./src/test/setup.ts'],
    css: false,
    // jsdom se vytváří jednou na worker (testy ~25 % rychlejší); izolace souborů zůstává, při potížích (paměť) vrátit 'forks'
    pool: 'vmThreads',
  },
}));
