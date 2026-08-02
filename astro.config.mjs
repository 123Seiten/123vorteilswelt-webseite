// @ts-check
import { defineConfig } from 'astro/config';

// https://astro.build/config
export default defineConfig({
  site: 'https://123Seiten.github.io',
  base: '/123vorteilswelt-webseite/',
  server: {
    host: true,
  },
});
