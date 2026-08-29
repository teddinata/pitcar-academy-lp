import { defineConfig } from 'astro/config';
import tailwind from '@astrojs/tailwind';

export default defineConfig({
  site: process.env.SITE_URL || 'https://academy.pitcar.co.id',
  integrations: [tailwind()],
  build: {
    // One 22KB stylesheet for a two-page site: inlining removes a
    // render-blocking round trip on mobile, which is where FCP is tightest.
    inlineStylesheets: 'always',
  },
});
