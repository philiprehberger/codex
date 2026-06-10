import type { MetadataRoute } from 'next';
import { listProjects, listCapabilities, listPackages } from '@/lib/codex-api';

export const revalidate = 3600;

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
    const BASE = 'https://codex.philiprehberger.com';
    const now = new Date();

    const staticEntries: MetadataRoute.Sitemap = [
        { url: `${BASE}/`, lastModified: now, changeFrequency: 'daily', priority: 1.0 },
        { url: `${BASE}/heatmap`, lastModified: now, changeFrequency: 'daily', priority: 0.9 },
        { url: `${BASE}/gaps`, lastModified: now, changeFrequency: 'daily', priority: 0.8 },
        { url: `${BASE}/projects`, lastModified: now, changeFrequency: 'daily', priority: 0.9 },
        { url: `${BASE}/packages`, lastModified: now, changeFrequency: 'daily', priority: 0.9 },
        { url: `${BASE}/capabilities`, lastModified: now, changeFrequency: 'daily', priority: 0.9 },
        { url: `${BASE}/resume-bullets`, lastModified: now, changeFrequency: 'weekly', priority: 0.7 },
        { url: `${BASE}/about`, lastModified: now, changeFrequency: 'monthly', priority: 0.5 },
    ];

    try {
        const [
            { data: projects },
            { data: capabilities },
            { data: packages },
        ] = await Promise.all([
            listProjects({ per_page: '100' }),
            listCapabilities(),
            listPackages({ per_page: '1000' }),
        ]);

        const projectEntries = projects.map((p) => ({
            url: `${BASE}/projects/${p.slug}`,
            lastModified: now,
            changeFrequency: 'weekly' as const,
            priority: 0.7,
        }));

        const capabilityEntries = capabilities.map((c) => ({
            url: `${BASE}/capabilities/${c.slug}`,
            lastModified: now,
            changeFrequency: 'weekly' as const,
            priority: 0.6,
        }));

        const packageEntries = packages.map((p) => ({
            url: `${BASE}/packages/${p.slug}`,
            lastModified: now,
            changeFrequency: 'weekly' as const,
            priority: 0.5,
        }));

        return [...staticEntries, ...projectEntries, ...capabilityEntries, ...packageEntries];
    } catch {
        // If the API is unreachable, ship the static skeleton — better than 500.
        return staticEntries;
    }
}
