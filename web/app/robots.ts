import type { MetadataRoute } from 'next';

export default function robots(): MetadataRoute.Robots {
    return {
        rules: [
            {
                userAgent: '*',
                allow: '/',
                disallow: [
                    '/admin', // The api host serves Filament; we still disallow the path on the dashboard host as defence-in-depth.
                    '/api/',
                    '/api/v1/assets/',
                ],
            },
        ],
        sitemap: 'https://codex.philiprehberger.com/sitemap.xml',
        host: 'https://codex.philiprehberger.com',
    };
}
