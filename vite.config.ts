import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
  server: {
    host: '127.0.0.1',
    port: 8021,
    strictPort: true,
    hmr: {
      host: '127.0.0.1',
      port: 8021,
      protocol: 'ws',
    },
  },
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/js/app.ts'],
      refresh: true,
    }),
    vue(),
  ],
  resolve: {
    alias: {
      '@': '/resources/js',
    },
  },
});
