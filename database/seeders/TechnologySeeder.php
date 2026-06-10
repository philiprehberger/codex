<?php

namespace Database\Seeders;

use App\Models\Technology;
use Illuminate\Support\Facades\Config;

class TechnologySeeder extends BaseSeeder
{
    public function run(): void
    {
        $rows = $this->rows();
        $this->assertWithinCap(count($rows));

        foreach ($rows as $row) {
            Technology::updateOrCreate(
                ['slug' => $row['slug']],
                ['name' => $row['name'], 'category' => $row['category']],
            );
        }
    }

    private function assertWithinCap(int $count): void
    {
        $cap = (int) Config::get('codex.vocabulary.technologies.cap', 120);
        if ($count > $cap) {
            throw new \RuntimeException(sprintf(
                'TechnologySeeder exceeds hard cap: %d > %d. Merge before adding.',
                $count, $cap,
            ));
        }
    }

    /** @return array<int, array{slug:string,name:string,category:string}> */
    private function rows(): array
    {
        return [
            // Languages
            ['slug' => 'php', 'name' => 'PHP', 'category' => 'language'],
            ['slug' => 'typescript', 'name' => 'TypeScript', 'category' => 'language'],
            ['slug' => 'javascript', 'name' => 'JavaScript', 'category' => 'language'],
            ['slug' => 'python', 'name' => 'Python', 'category' => 'language'],
            ['slug' => 'go', 'name' => 'Go', 'category' => 'language'],
            ['slug' => 'rust', 'name' => 'Rust', 'category' => 'language'],
            ['slug' => 'sql', 'name' => 'SQL', 'category' => 'language'],
            ['slug' => 'html', 'name' => 'HTML', 'category' => 'language'],
            ['slug' => 'css', 'name' => 'CSS', 'category' => 'language'],
            ['slug' => 'bash', 'name' => 'Bash', 'category' => 'language'],

            // Frameworks
            ['slug' => 'laravel', 'name' => 'Laravel', 'category' => 'framework'],
            ['slug' => 'filament', 'name' => 'Filament', 'category' => 'framework'],
            ['slug' => 'nextjs', 'name' => 'Next.js', 'category' => 'framework'],
            ['slug' => 'react', 'name' => 'React', 'category' => 'framework'],
            ['slug' => 'vue', 'name' => 'Vue', 'category' => 'framework'],
            ['slug' => 'svelte', 'name' => 'Svelte', 'category' => 'framework'],
            ['slug' => 'tailwind', 'name' => 'Tailwind CSS', 'category' => 'framework'],
            ['slug' => 'alpine', 'name' => 'Alpine.js', 'category' => 'framework'],
            ['slug' => 'livewire', 'name' => 'Livewire', 'category' => 'framework'],
            ['slug' => 'inertia', 'name' => 'Inertia.js', 'category' => 'framework'],
            ['slug' => 'codeigniter', 'name' => 'CodeIgniter', 'category' => 'framework'],
            ['slug' => 'fastapi', 'name' => 'FastAPI', 'category' => 'framework'],
            ['slug' => 'express', 'name' => 'Express', 'category' => 'framework'],

            // CMS
            ['slug' => 'wordpress', 'name' => 'WordPress', 'category' => 'cms'],
            ['slug' => 'acf', 'name' => 'Advanced Custom Fields', 'category' => 'cms'],
            ['slug' => 'gutenberg', 'name' => 'Gutenberg', 'category' => 'cms'],
            ['slug' => 'sanity', 'name' => 'Sanity', 'category' => 'cms'],
            ['slug' => 'contentful', 'name' => 'Contentful', 'category' => 'cms'],

            // Databases
            ['slug' => 'mysql', 'name' => 'MySQL', 'category' => 'database'],
            ['slug' => 'postgresql', 'name' => 'PostgreSQL', 'category' => 'database'],
            ['slug' => 'sqlite', 'name' => 'SQLite', 'category' => 'database'],
            ['slug' => 'redis', 'name' => 'Redis', 'category' => 'database'],
            ['slug' => 'mariadb', 'name' => 'MariaDB', 'category' => 'database'],

            // Infrastructure
            ['slug' => 'apache', 'name' => 'Apache', 'category' => 'infrastructure'],
            ['slug' => 'nginx', 'name' => 'Nginx', 'category' => 'infrastructure'],
            ['slug' => 'docker', 'name' => 'Docker', 'category' => 'infrastructure'],
            ['slug' => 'pm2', 'name' => 'PM2', 'category' => 'infrastructure'],
            ['slug' => 'systemd', 'name' => 'systemd', 'category' => 'infrastructure'],
            ['slug' => 'letsencrypt', 'name' => "Let's Encrypt", 'category' => 'infrastructure'],
            ['slug' => 'cloudflare', 'name' => 'Cloudflare', 'category' => 'infrastructure'],
            ['slug' => 'github-actions', 'name' => 'GitHub Actions', 'category' => 'infrastructure'],

            // Cloud
            ['slug' => 'aws-ec2', 'name' => 'AWS EC2', 'category' => 'cloud'],
            ['slug' => 'aws-s3', 'name' => 'AWS S3', 'category' => 'cloud'],
            ['slug' => 'aws-route53', 'name' => 'AWS Route 53', 'category' => 'cloud'],
            ['slug' => 'aws-ses', 'name' => 'AWS SES', 'category' => 'cloud'],
            ['slug' => 'vercel', 'name' => 'Vercel', 'category' => 'cloud'],
            ['slug' => 'digitalocean', 'name' => 'DigitalOcean', 'category' => 'cloud'],

            // Tooling
            ['slug' => 'vite', 'name' => 'Vite', 'category' => 'tooling'],
            ['slug' => 'webpack', 'name' => 'Webpack', 'category' => 'tooling'],
            ['slug' => 'phpunit', 'name' => 'PHPUnit', 'category' => 'tooling'],
            ['slug' => 'pest', 'name' => 'Pest', 'category' => 'tooling'],
            ['slug' => 'vitest', 'name' => 'Vitest', 'category' => 'tooling'],
            ['slug' => 'playwright', 'name' => 'Playwright', 'category' => 'tooling'],
            ['slug' => 'k6', 'name' => 'k6', 'category' => 'tooling'],
            ['slug' => 'composer', 'name' => 'Composer', 'category' => 'tooling'],
            ['slug' => 'npm', 'name' => 'npm', 'category' => 'tooling'],
            ['slug' => 'phpstan', 'name' => 'PHPStan', 'category' => 'tooling'],
            ['slug' => 'pint', 'name' => 'Laravel Pint', 'category' => 'tooling'],
            ['slug' => 'sentry', 'name' => 'Sentry', 'category' => 'tooling'],

            // API / 3rd-party
            ['slug' => 'stripe', 'name' => 'Stripe', 'category' => 'api'],
            ['slug' => 'paddle', 'name' => 'Paddle', 'category' => 'api'],
            ['slug' => 'hubspot', 'name' => 'HubSpot', 'category' => 'api'],
            ['slug' => 'mailgun', 'name' => 'Mailgun', 'category' => 'api'],
            ['slug' => 'postmark', 'name' => 'Postmark', 'category' => 'api'],
            ['slug' => 'twilio', 'name' => 'Twilio', 'category' => 'api'],
            ['slug' => 'openai-api', 'name' => 'OpenAI API', 'category' => 'api'],
            ['slug' => 'anthropic-api', 'name' => 'Anthropic API', 'category' => 'api'],

            // Libraries
            ['slug' => 'recharts', 'name' => 'Recharts', 'category' => 'library'],
            ['slug' => 'lucide-react', 'name' => 'Lucide React', 'category' => 'library'],
            ['slug' => 'react-hook-form', 'name' => 'React Hook Form', 'category' => 'library'],
            ['slug' => 'zod', 'name' => 'Zod', 'category' => 'library'],
            ['slug' => 'tiptap', 'name' => 'TipTap', 'category' => 'library'],
            ['slug' => 'scalar', 'name' => 'Scalar', 'category' => 'library'],
        ];
    }
}
