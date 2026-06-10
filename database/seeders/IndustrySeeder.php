<?php

namespace Database\Seeders;

use App\Models\Industry;
use Illuminate\Support\Facades\Config;

class IndustrySeeder extends BaseSeeder
{
    public function run(): void
    {
        $rows = [
            ['slug' => 'developer-tools', 'name' => 'Developer Tools'],
            ['slug' => 'saas', 'name' => 'SaaS'],
            ['slug' => 'professional-services', 'name' => 'Professional Services'],
            ['slug' => 'legal', 'name' => 'Legal'],
            ['slug' => 'healthcare', 'name' => 'Healthcare'],
            ['slug' => 'finance', 'name' => 'Finance'],
            ['slug' => 'accounting', 'name' => 'Accounting'],
            ['slug' => 'e-commerce', 'name' => 'E-commerce'],
            ['slug' => 'education', 'name' => 'Education'],
            ['slug' => 'nonprofit', 'name' => 'Nonprofit'],
            ['slug' => 'real-estate', 'name' => 'Real Estate'],
            ['slug' => 'hospitality', 'name' => 'Hospitality'],
            ['slug' => 'trades', 'name' => 'Trades / Home Services'],
            ['slug' => 'recruiting', 'name' => 'Recruiting / HR'],
            ['slug' => 'agency-marketing', 'name' => 'Marketing / Agency'],
            ['slug' => 'personal-brand', 'name' => 'Personal Brand'],
            ['slug' => 'events', 'name' => 'Events'],
            ['slug' => 'logistics', 'name' => 'Logistics / Fleet'],
            ['slug' => 'media', 'name' => 'Media / Publishing'],
            ['slug' => 'governmental', 'name' => 'Government / Public Sector'],
        ];

        $cap = (int) Config::get('codex.vocabulary.industries.cap', 30);
        if (count($rows) > $cap) {
            throw new \RuntimeException('IndustrySeeder exceeds hard cap.');
        }

        foreach ($rows as $row) {
            Industry::updateOrCreate(['slug' => $row['slug']], ['name' => $row['name']]);
        }
    }
}
