/// <reference types="vitest/config" />
import react from '@vitejs/plugin-react';
import { execSync } from 'node:child_process';
import { defineConfig } from 'vite';

// Version shown in the menu: the release tag (`v1.0.0`) when the build runs on a tagged commit, otherwise
// `v1.0.0-3-g1a2b3c4` (commits since the tag). Falls back to the package version when git is not available.
function appVersion(): string {
  try {
    return execSync('git describe --tags --always', { stdio: ['ignore', 'pipe', 'ignore'] }).toString().trim();
  } catch {
    return `v${process.env.npm_package_version ?? '0.0.0'}`;
  }
}

// V dev režimu se /api proxuje na Symfony backend, takže se frontend a API tváří jako jeden origin
// (session cookie bez CORS).
export default defineConfig(({ command }) => ({
  plugins: [react()],
  define: { __APP_VERSION__: JSON.stringify(appVersion()) },
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
