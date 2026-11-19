import type { APIRoute } from 'astro';

export const GET: APIRoute = ({ site }) => {
  const origin = site ?? new URL('https://academy.pitcar.co.id');
  const sitemapUrl = new URL('/sitemap.xml', origin).href;
  const body = [
    'User-agent: *',
    'Allow: /',
    '',
    `Host: ${origin.host}`,
    `Sitemap: ${sitemapUrl}`,
    '',
  ].join('\n');

  return new Response(body, {
    headers: { 'Content-Type': 'text/plain; charset=utf-8' },
  });
};
