<?php

namespace Database\Seeders;

use App\Models\Architecture;
use App\Models\Capability;
use App\Models\Deliverable;
use App\Models\DesignStyle;
use App\Models\Industry;
use App\Models\Project;
use App\Models\Technology;
use Illuminate\Support\Facades\DB;

/**
 * Redacted rows for paid client work (Cairnstack engagements, ScopeForged
 * contracts, etc). visibility=redacted means:
 *   - client_name set, but stripped by RedactedScope on public reads
 *   - client_industry visible (the proof-of-portfolio shape — buyer can
 *     see industry/capability/technology coverage without learning who
 *     the client was)
 *   - internal_notes set, but $hidden suppresses from serialisation AND
 *     RedactedScope strips on read
 *
 * Per the plan §"Open questions" #3, written portfolio-use permission
 * must be confirmed per engagement before going public — these are
 * placeholders shaped after Philip's real engagement profile. Real client
 * names go in via Filament after admin confirms per-engagement permission.
 */
class ClientWorkSeeder extends BaseSeeder
{
    public function run(): void
    {
        foreach ($this->rows() as $row) {
            $this->seedOne($row);
        }
    }

    /** @param array<string, mixed> $row */
    private function seedOne(array $row): void
    {
        DB::transaction(function () use ($row) {
            $project = Project::updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'project_type' => 'client',
                    'status' => 'shipped',
                    'visibility' => 'redacted',
                    'short_description' => $row['short_description'],
                    'long_description' => $row['long_description'] ?? null,
                    'long_description_reviewed' => true,
                    'client_name' => $row['client_name'],
                    'client_industry' => $row['client_industry'],
                    'shipped_date' => $row['shipped_date'],
                    'hours_estimated' => $row['hours_actual'],
                    'hours_actual' => $row['hours_actual'],
                    'team_size' => $row['team_size'] ?? 3,
                    'internal_notes' => $row['internal_notes'] ?? null,
                ],
            );

            $project->capabilities()->sync(Capability::whereIn('slug', $row['capability_slugs'])->pluck('id')->all());
            $project->technologies()->sync(Technology::whereIn('slug', $row['technology_slugs'])->pluck('id')->all());
            $project->industries()->sync(Industry::whereIn('slug', [$row['industry_slug']])->pluck('id')->all());
            $project->architectures()->sync(Architecture::whereIn('slug', $row['architecture_slugs'])->pluck('id')->all());
            $project->deliverables()->sync(Deliverable::whereIn('slug', $row['deliverable_slugs'])->pluck('id')->all());
            $project->designStyles()->sync(DesignStyle::whereIn('slug', $row['design_style_slugs'])->pluck('id')->all());
        });
    }

    /** @return array<int, array<string, mixed>> */
    private function rows(): array
    {
        return [
            [
                'slug' => 'client-legal-intake',
                'name' => 'Legal intake portal',
                'client_name' => '[Cairnstack — redacted legal client]',
                'client_industry' => 'legal',
                'industry_slug' => 'legal',
                'short_description' => 'Multi-tenant intake portal for a law firm — case-type wizards, document upload, e-signature flow.',
                'shipped_date' => '2024-09-15', 'hours_actual' => 320,
                'capability_slugs' => ['multi-tenant', 'authentication', 'authorization', 'lead-capture', 'file-upload-pipeline', 'notifications'],
                'technology_slugs' => ['php', 'laravel', 'mysql', 'apache', 'tailwind'],
                'architecture_slugs' => ['multi-tenant', 'monolith'],
                'deliverable_slugs' => ['web-app'],
                'design_style_slugs' => ['saas', 'data-dense'],
            ],
            [
                'slug' => 'client-healthcare-scheduler',
                'name' => 'Healthcare scheduling app',
                'client_name' => '[ScopeForged — redacted healthcare client]',
                'client_industry' => 'healthcare',
                'industry_slug' => 'healthcare',
                'short_description' => 'Provider-side scheduling + intake forms with insurance verification flow.',
                'shipped_date' => '2024-11-20', 'hours_actual' => 280,
                'capability_slugs' => ['authentication', 'authorization', 'scheduled-jobs', 'notifications', 'lead-capture', 'gdpr-compliance'],
                'technology_slugs' => ['php', 'laravel', 'filament', 'mysql', 'apache'],
                'architecture_slugs' => ['monolith'],
                'deliverable_slugs' => ['web-app', 'admin-portal'],
                'design_style_slugs' => ['saas'],
            ],
            [
                'slug' => 'client-finance-dashboard',
                'name' => 'Finance reporting dashboard',
                'client_name' => '[Cairnstack — redacted finance client]',
                'client_industry' => 'finance',
                'industry_slug' => 'finance',
                'short_description' => 'Operational dashboard with KPI widgets, scheduled exports, audit trail across teams.',
                'shipped_date' => '2024-12-10', 'hours_actual' => 240,
                'capability_slugs' => ['dashboards', 'reporting', 'authorization', 'audit_log' /* maps */, 'scheduled-jobs', 'observability'],
                'technology_slugs' => ['php', 'laravel', 'filament', 'mysql', 'redis', 'apache'],
                'architecture_slugs' => ['monolith'],
                'deliverable_slugs' => ['dashboard', 'admin-portal'],
                'design_style_slugs' => ['data-dense', 'saas'],
            ],
            [
                'slug' => 'client-ecom-portal',
                'name' => 'E-commerce merchant portal',
                'client_name' => '[ScopeForged — redacted retail client]',
                'client_industry' => 'e-commerce',
                'industry_slug' => 'e-commerce',
                'short_description' => 'Per-merchant SaaS portal — orders, inventory, payouts, Stripe-backed billing.',
                'shipped_date' => '2025-02-15', 'hours_actual' => 360,
                'capability_slugs' => ['multi-tenant', 'authentication', 'cart-checkout', 'payments', 'subscriptions', 'dashboards', 'notifications'],
                'technology_slugs' => ['php', 'laravel', 'filament', 'mysql', 'redis', 'apache', 'stripe'],
                'architecture_slugs' => ['multi-tenant', 'monolith'],
                'deliverable_slugs' => ['web-app', 'admin-portal'],
                'design_style_slugs' => ['saas', 'data-dense'],
            ],
            [
                'slug' => 'client-education-lms',
                'name' => 'Custom LMS',
                'client_name' => '[ScopeForged — redacted education client]',
                'client_industry' => 'education',
                'industry_slug' => 'education',
                'short_description' => 'Multi-tenant LMS with course progress, quizzes, certificates, instructor dashboards.',
                'shipped_date' => '2025-04-01', 'hours_actual' => 420,
                'capability_slugs' => ['multi-tenant', 'authentication', 'cms', 'media-library', 'dashboards', 'notifications', 'authorization'],
                'technology_slugs' => ['php', 'laravel', 'filament', 'mysql', 'apache'],
                'architecture_slugs' => ['multi-tenant', 'monolith'],
                'deliverable_slugs' => ['web-app', 'admin-portal'],
                'design_style_slugs' => ['saas'],
            ],
            [
                'slug' => 'client-realestate-listings',
                'name' => 'Real-estate listings portal',
                'client_name' => '[Cairnstack — redacted real estate client]',
                'client_industry' => 'real-estate',
                'industry_slug' => 'real-estate',
                'short_description' => 'Listings + lead-tracking portal with MLS sync, photo gallery, agent dashboards.',
                'shipped_date' => '2025-05-15', 'hours_actual' => 260,
                'capability_slugs' => ['cms', 'crm-sync', 'lead-capture', 'media-library', 'search', 'dashboards'],
                'technology_slugs' => ['php', 'laravel', 'mysql', 'apache'],
                'architecture_slugs' => ['monolith'],
                'deliverable_slugs' => ['web-app'],
                'design_style_slugs' => ['saas'],
            ],
            [
                'slug' => 'client-nonprofit-fundraising',
                'name' => 'Nonprofit fundraising portal',
                'client_name' => '[ScopeForged — redacted nonprofit client]',
                'client_industry' => 'nonprofit',
                'industry_slug' => 'nonprofit',
                'short_description' => 'Donor management, recurring donations, campaign dashboards, receipt generation.',
                'shipped_date' => '2025-07-10', 'hours_actual' => 220,
                'capability_slugs' => ['authentication', 'payments', 'subscriptions', 'crm-sync', 'invoicing', 'email-campaigns'],
                'technology_slugs' => ['php', 'laravel', 'filament', 'mysql', 'apache', 'stripe'],
                'architecture_slugs' => ['monolith'],
                'deliverable_slugs' => ['web-app'],
                'design_style_slugs' => ['saas'],
            ],
            [
                'slug' => 'client-recruiting-ats',
                'name' => 'Applicant tracking system',
                'client_name' => '[ScopeForged — redacted recruiting client]',
                'client_industry' => 'recruiting',
                'industry_slug' => 'recruiting',
                'short_description' => 'ATS with pipeline kanban, candidate notes, interview scheduling, GDPR-compliant data subject flows.',
                'shipped_date' => '2025-08-15', 'hours_actual' => 300,
                'capability_slugs' => ['multi-tenant', 'authentication', 'authorization', 'gdpr-compliance', 'crm-sync', 'notifications', 'scheduled-jobs'],
                'technology_slugs' => ['php', 'laravel', 'filament', 'mysql', 'apache'],
                'architecture_slugs' => ['multi-tenant', 'monolith'],
                'deliverable_slugs' => ['web-app', 'admin-portal'],
                'design_style_slugs' => ['saas', 'data-dense'],
            ],
            [
                'slug' => 'client-trades-fieldservice',
                'name' => 'Field service mobile-first portal',
                'client_name' => '[Cairnstack — redacted trades client]',
                'client_industry' => 'trades',
                'industry_slug' => 'trades',
                'short_description' => 'Mobile-first field service portal — job assignment, GPS check-in, photo upload, invoice generation.',
                'shipped_date' => '2025-09-01', 'hours_actual' => 280,
                'capability_slugs' => ['authentication', 'file-upload-pipeline', 'invoicing', 'scheduled-jobs', 'notifications', 'media-library'],
                'technology_slugs' => ['php', 'laravel', 'filament', 'mysql', 'apache'],
                'architecture_slugs' => ['monolith'],
                'deliverable_slugs' => ['web-app'],
                'design_style_slugs' => ['saas', 'service-business'],
            ],
            [
                'slug' => 'client-accounting-integration',
                'name' => 'QuickBooks integration layer',
                'client_name' => '[ScopeForged — redacted accounting client]',
                'client_industry' => 'accounting',
                'industry_slug' => 'accounting',
                'short_description' => 'QuickBooks Online integration — invoice sync, customer sync, payment reconciliation with conflict resolution.',
                'shipped_date' => '2025-10-15', 'hours_actual' => 180,
                'capability_slugs' => ['third-party-sync', 'oauth-connectors', 'invoicing', 'webhook-receiving', 'rest-api'],
                'technology_slugs' => ['php', 'laravel', 'mysql', 'apache'],
                'architecture_slugs' => ['monolith'],
                'deliverable_slugs' => ['web-app'],
                'design_style_slugs' => ['data-dense'],
            ],
            [
                'slug' => 'client-saas-billing',
                'name' => 'SaaS billing migration',
                'client_name' => '[ScopeForged — redacted SaaS client]',
                'client_industry' => 'saas',
                'industry_slug' => 'saas',
                'short_description' => 'Stripe Billing migration from a custom invoice system — proration, dunning, customer portal.',
                'shipped_date' => '2025-11-01', 'hours_actual' => 160,
                'capability_slugs' => ['subscriptions', 'payments', 'invoicing', 'webhook-receiving', 'notifications'],
                'technology_slugs' => ['php', 'laravel', 'mysql', 'apache', 'stripe'],
                'architecture_slugs' => ['multi-tenant', 'monolith'],
                'deliverable_slugs' => ['web-app'],
                'design_style_slugs' => ['saas'],
            ],
            [
                'slug' => 'client-hospitality-booking',
                'name' => 'Hospitality booking platform',
                'client_name' => '[Cairnstack — redacted hospitality client]',
                'client_industry' => 'hospitality',
                'industry_slug' => 'hospitality',
                'short_description' => 'Booking + reservations platform with calendar UI, deposits, customer messaging.',
                'shipped_date' => '2025-11-20', 'hours_actual' => 200,
                'capability_slugs' => ['payments', 'notifications', 'authentication', 'scheduled-jobs', 'email-campaigns'],
                'technology_slugs' => ['php', 'laravel', 'filament', 'mysql', 'apache', 'stripe'],
                'architecture_slugs' => ['monolith'],
                'deliverable_slugs' => ['web-app'],
                'design_style_slugs' => ['saas'],
            ],
            [
                'slug' => 'client-ci-laravel-port',
                'name' => 'CodeIgniter → Laravel port (MorTrack)',
                'client_name' => '[Cairnstack — redacted SaaS client]',
                'client_industry' => 'saas',
                'industry_slug' => 'saas',
                'short_description' => 'Full port of a legacy CodeIgniter SaaS to Laravel — keeping data shape, modernizing the runtime + auth layer.',
                'shipped_date' => '2024-06-15', 'hours_actual' => 480,
                'capability_slugs' => ['authentication', 'authorization', 'multi-tenant', 'dashboards', 'reporting', 'observability'],
                'technology_slugs' => ['php', 'laravel', 'codeigniter', 'mysql', 'apache'],
                'architecture_slugs' => ['multi-tenant', 'monolith'],
                'deliverable_slugs' => ['web-app'],
                'design_style_slugs' => ['saas', 'data-dense'],
            ],
            [
                'slug' => 'client-ci-laravel-strangler',
                'name' => 'CodeIgniter → Laravel strangler (PTIprint API)',
                'client_name' => '[Cairnstack — redacted print SaaS client]',
                'client_industry' => 'saas',
                'industry_slug' => 'saas',
                'short_description' => 'Strangler-pattern migration of a CodeIgniter API to Laravel — new routes in Laravel, legacy routes proxied until cut-over.',
                'shipped_date' => '2024-08-01', 'hours_actual' => 360,
                'capability_slugs' => ['rest-api', 'authentication', 'multi-tenant', 'observability'],
                'technology_slugs' => ['php', 'laravel', 'codeigniter', 'mysql', 'apache'],
                'architecture_slugs' => ['monolith', 'api-as-product'],
                'deliverable_slugs' => ['api'],
                'design_style_slugs' => ['developer-focused'],
            ],
            [
                'slug' => 'client-hubspot-integration',
                'name' => 'HubSpot full-platform integration',
                'client_name' => '[ScopeForged — redacted agency client]',
                'client_industry' => 'agency-marketing',
                'industry_slug' => 'agency-marketing',
                'short_description' => 'HubSpot custom objects + pipelines + workflows + dashboards integration for an agency client.',
                'shipped_date' => '2025-03-15', 'hours_actual' => 180,
                'capability_slugs' => ['crm-sync', 'oauth-connectors', 'webhook-receiving', 'workflow-engine', 'dashboards'],
                'technology_slugs' => ['php', 'laravel', 'mysql', 'apache', 'hubspot'],
                'architecture_slugs' => ['monolith'],
                'deliverable_slugs' => ['web-app'],
                'design_style_slugs' => ['saas'],
            ],
        ];
    }
}
