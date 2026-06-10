<?php

namespace Database\Seeders;

use App\Models\Deliverable;

class DeliverableSeeder extends BaseSeeder
{
    public function run(): void
    {
        $rows = [
            ['slug' => 'website', 'name' => 'Website'],
            ['slug' => 'web-app', 'name' => 'Web Application'],
            ['slug' => 'api', 'name' => 'API'],
            ['slug' => 'cli-tool', 'name' => 'CLI Tool'],
            ['slug' => 'sdk', 'name' => 'SDK / Library'],
            ['slug' => 'wordpress-plugin', 'name' => 'WordPress Plugin'],
            ['slug' => 'wordpress-theme', 'name' => 'WordPress Theme'],
            ['slug' => 'dashboard', 'name' => 'Dashboard'],
            ['slug' => 'admin-portal', 'name' => 'Admin Portal'],
            ['slug' => 'documentation', 'name' => 'Documentation Site'],
            ['slug' => 'composer-package', 'name' => 'Composer Package'],
            ['slug' => 'npm-package', 'name' => 'npm Package'],
        ];
        foreach ($rows as $row) {
            Deliverable::updateOrCreate(['slug' => $row['slug']], ['name' => $row['name']]);
        }
    }
}
