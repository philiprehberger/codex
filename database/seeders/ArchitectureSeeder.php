<?php

namespace Database\Seeders;

use App\Models\Architecture;

class ArchitectureSeeder extends BaseSeeder
{
    public function run(): void
    {
        $rows = [
            ['slug' => 'monolith', 'name' => 'Monolith', 'description' => 'Single deployable application — typical Laravel/Filament shape.'],
            ['slug' => 'headless', 'name' => 'Headless', 'description' => 'API backend + decoupled frontend (Next.js consumes Laravel or WP).'],
            ['slug' => 'spa', 'name' => 'SPA', 'description' => 'Single-page app with client-side routing.'],
            ['slug' => 'mpa', 'name' => 'MPA', 'description' => 'Multi-page server-rendered app.'],
            ['slug' => 'static', 'name' => 'Static', 'description' => 'Pre-rendered HTML, no runtime backend (Next.js SSG, plain HTML).'],
            ['slug' => 'serverless', 'name' => 'Serverless', 'description' => 'FaaS / edge runtime — Vercel functions, AWS Lambda.'],
            ['slug' => 'multi-tenant', 'name' => 'Multi-tenant SaaS', 'description' => 'Single deployment serving multiple tenants with per-tenant isolation.'],
            ['slug' => 'api-as-product', 'name' => 'API-as-Product', 'description' => 'The API itself is the product; SDKs + docs are deliverables.'],
            ['slug' => 'cms-driven', 'name' => 'CMS-driven', 'description' => 'WordPress / Sanity / Contentful as the editorial backbone.'],
            ['slug' => 'isr', 'name' => 'ISR / Incremental Static', 'description' => 'Static base with on-demand revalidation (Next.js revalidateTag).'],
        ];
        foreach ($rows as $row) {
            Architecture::updateOrCreate(['slug' => $row['slug']], [
                'name' => $row['name'], 'description' => $row['description'],
            ]);
        }
    }
}
