<?php

namespace Tests\Feature\Api;

use App\Models\Architecture;
use App\Models\Capability;
use App\Models\Industry;
use App\Models\Package;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /api/v1/drill-down — one endpoint, five scopes.
 *
 *  - capability: rolls up via canonical_id, includes projects + packages
 *  - industry: projects only (packages don't carry industries)
 *  - architecture: projects only
 *  - category: projects + packages
 *  - cell (category × industry): projects only
 *  - validates that exactly one scope must be supplied (422 otherwise)
 */
class DrillDownTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_scope_returns_422(): void
    {
        $this->getJson('/api/v1/drill-down')
            ->assertStatus(422)
            ->assertJsonFragment(['title' => 'Missing scope']);
    }

    public function test_unknown_scope_value_returns_404(): void
    {
        $this->getJson('/api/v1/drill-down?capability=does-not-exist')
            ->assertStatus(404)
            ->assertJsonFragment(['title' => 'Not found']);
    }

    public function test_capability_scope_rolls_up_aliases(): void
    {
        // Project tagged with alias "user-auth" should appear under
        // canonical "auth" when we drill in.
        $canonical = Capability::factory()->create(['slug' => 'auth', 'name' => 'Authentication']);
        $alias = Capability::factory()->create(['slug' => 'user-auth', 'canonical_id' => $canonical->id]);
        $p1 = Project::factory()->create(['name' => 'Aliased project']);
        $p1->capabilities()->attach($alias->id);
        $p2 = Project::factory()->create(['name' => 'Canonical project']);
        $p2->capabilities()->attach($canonical->id);
        $pkg = Package::factory()->create(['name' => 'auth-pkg']);
        $pkg->capabilities()->attach($alias->id);

        $this->getJson('/api/v1/drill-down?capability=auth')
            ->assertOk()
            ->assertJsonPath('data.title', 'Authentication')
            ->assertJsonPath('data.scope.type', 'capability')
            ->assertJsonCount(2, 'data.projects')
            ->assertJsonCount(1, 'data.packages')
            ->assertJsonFragment(['name' => 'Aliased project'])
            ->assertJsonFragment(['name' => 'Canonical project'])
            ->assertJsonFragment(['name' => 'auth-pkg']);
    }

    public function test_industry_scope_returns_only_projects_in_that_industry(): void
    {
        $saas = Industry::factory()->create(['slug' => 'saas', 'name' => 'SaaS']);
        $legal = Industry::factory()->create(['slug' => 'legal', 'name' => 'Legal']);
        $a = Project::factory()->create(['name' => 'In SaaS']);
        $a->industries()->attach($saas->id);
        $b = Project::factory()->create(['name' => 'In Legal']);
        $b->industries()->attach($legal->id);

        $this->getJson('/api/v1/drill-down?industry=saas')
            ->assertOk()
            ->assertJsonPath('data.scope.type', 'industry')
            ->assertJsonCount(1, 'data.projects')
            ->assertJsonCount(0, 'data.packages')
            ->assertJsonFragment(['name' => 'In SaaS']);
    }

    public function test_architecture_scope_returns_matching_projects(): void
    {
        $mono = Architecture::factory()->create(['slug' => 'monolith', 'name' => 'Monolith']);
        $p = Project::factory()->create(['name' => 'Monolith project']);
        $p->architectures()->attach($mono->id);

        $this->getJson('/api/v1/drill-down?architecture=monolith')
            ->assertOk()
            ->assertJsonPath('data.scope.type', 'architecture')
            ->assertJsonCount(1, 'data.projects')
            ->assertJsonFragment(['name' => 'Monolith project']);
    }

    public function test_category_scope_includes_projects_and_packages_via_alias_rollup(): void
    {
        $canonical = Capability::factory()->create(['category' => 'Automation']);
        $alias = Capability::factory()->create(['category' => 'Marketing', 'canonical_id' => $canonical->id]);
        $project = Project::factory()->create(['name' => 'Automation via alias']);
        $project->capabilities()->attach($alias->id);
        $pkg = Package::factory()->create(['name' => 'automation-pkg']);
        $pkg->capabilities()->attach($canonical->id);

        $this->getJson('/api/v1/drill-down?category=Automation')
            ->assertOk()
            ->assertJsonPath('data.scope.type', 'category')
            ->assertJsonCount(1, 'data.projects')
            ->assertJsonCount(1, 'data.packages');
    }

    public function test_cell_scope_combines_category_and_industry(): void
    {
        // Two projects in different industries, both tagged with an
        // Automation capability. The cell drill on (Automation, saas)
        // should return only the SaaS one.
        $cap = Capability::factory()->create(['category' => 'Automation']);
        $saas = Industry::factory()->create(['slug' => 'saas']);
        $legal = Industry::factory()->create(['slug' => 'legal']);
        $a = Project::factory()->create(['name' => 'SaaS automation']);
        $a->capabilities()->attach($cap->id);
        $a->industries()->attach($saas->id);
        $b = Project::factory()->create(['name' => 'Legal automation']);
        $b->capabilities()->attach($cap->id);
        $b->industries()->attach($legal->id);

        $this->getJson('/api/v1/drill-down?category=Automation&industry=saas')
            ->assertOk()
            ->assertJsonPath('data.scope.type', 'cell')
            ->assertJsonCount(1, 'data.projects')
            ->assertJsonFragment(['name' => 'SaaS automation']);
    }

    public function test_unknown_query_param_returns_422(): void
    {
        // strict_query_keys discipline — DrillDownRequest's allow-list.
        $this->getJson('/api/v1/drill-down?wrong-key=auth')
            ->assertStatus(422);
    }
}
