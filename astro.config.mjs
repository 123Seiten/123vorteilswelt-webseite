// @ts-check
import { defineConfig } from 'astro/config';
import sitemap from '@astrojs/sitemap';

export default defineConfig({
  site: 'https://123vorteilswelt.at',
  integrations: [sitemap()],
  server: { host: true },
});
