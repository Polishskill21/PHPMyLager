<?php

namespace Tests\Feature\Suppliers;

use App\Models\Suppliers\Supplier;
use App\Models\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\ForcesInMemorySqlite;
use Tests\TestCase;

class SupplierCreateTest extends TestCase
{
    use RefreshDatabase, ForcesInMemorySqlite;

    protected User $admin;
    protected User $writer;
    protected User $viewer;

    protected function setUp(): void
    {
        $this->guardAgainstUnsafeCachedConfig();
        $this->forceInMemorySqliteEnvironment();

        parent::setUp();

        $this->admin  = User::factory()->create(['role' => 'admin']);
        $this->writer = User::factory()->create(['role' => 'writer']);
        $this->viewer = User::factory()->create(['role' => 'viewer']);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            Supplier::COL_NAME    => 'New Supplier',
            Supplier::COL_STRASSE => 'Industriestrasse 1',
            Supplier::COL_PLZ     => '12345',
            Supplier::COL_ORT     => 'Hamburg',
            Supplier::COL_EMAIL   => 'new.supplier@example.com',
        ], $overrides);
    }

    public function test_unauthenticated_user_cannot_create_supplier(): void
    {
        $this->postJson('/api/suppliers', $this->validPayload())->assertStatus(401);
    }

    public function test_admin_and_writer_can_create_supplier(): void
    {
        $users = ['admin' => $this->admin, 'writer' => $this->writer];

        foreach ($users as $role => $user) {
            $payload = $this->validPayload([
                Supplier::COL_EMAIL => "{$role}.create@example.com",
                Supplier::COL_NAME  => ucfirst($role) . ' Supplier',
            ]);

            $response = $this->actingAs($user)->postJson('/api/suppliers', $payload);

            $response->assertStatus(201)
                     ->assertJsonPath('data.' . Supplier::COL_NAME, $payload[Supplier::COL_NAME])
                     ->assertJsonPath('data.' . Supplier::COL_EMAIL, $payload[Supplier::COL_EMAIL]);

            $this->assertDatabaseHas(Supplier::TABLE, [
                Supplier::COL_NAME  => $payload[Supplier::COL_NAME],
                Supplier::COL_EMAIL => $payload[Supplier::COL_EMAIL],
            ]);
        }
    }

    public function test_viewer_cannot_create_supplier(): void
    {
        $this->actingAs($this->viewer)
             ->postJson('/api/suppliers', $this->validPayload())
             ->assertStatus(403);
    }

    public function test_store_validates_required_fields_and_plz_format(): void
    {
        $response = $this->actingAs($this->writer)->postJson('/api/suppliers', [
            Supplier::COL_PLZ => '123',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors([Supplier::COL_NAME, Supplier::COL_PLZ]);
    }

    public function test_store_rejects_duplicate_email(): void
    {
        Supplier::create($this->validPayload([Supplier::COL_EMAIL => 'duplicate@example.com']));

        $response = $this->actingAs($this->writer)->postJson('/api/suppliers', $this->validPayload([
            Supplier::COL_EMAIL => 'duplicate@example.com'
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors([Supplier::COL_EMAIL]);
    }
}