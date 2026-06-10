<?php

namespace Database\Seeders;

use App\Models\DesignStyle;

class DesignStyleSeeder extends BaseSeeder
{
    public function run(): void
    {
        $rows = [
            ['slug' => 'saas', 'name' => 'SaaS'],
            ['slug' => 'corporate', 'name' => 'Corporate'],
            ['slug' => 'editorial', 'name' => 'Editorial'],
            ['slug' => 'minimalist', 'name' => 'Minimalist'],
            ['slug' => 'brutalist', 'name' => 'Brutalist'],
            ['slug' => 'developer-focused', 'name' => 'Developer-focused'],
            ['slug' => 'marketing-funnel', 'name' => 'Marketing Funnel'],
            ['slug' => 'e-commerce', 'name' => 'E-commerce'],
            ['slug' => 'data-dense', 'name' => 'Data-dense'],
            ['slug' => 'service-business', 'name' => 'Service-business'],
        ];
        foreach ($rows as $row) {
            DesignStyle::updateOrCreate(['slug' => $row['slug']], ['name' => $row['name']]);
        }
    }
}
