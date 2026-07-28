<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class OpsBackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_n8n_backup_endpoint_requires_secret(): void
    {
        config(['backup.n8n_secret' => 'test-secret']);

        $this->postJson('/api/ops/backup')->assertForbidden();

        $this->withHeader('X-N8N-Backup-Secret', 'test-secret')
            ->postJson('/api/ops/backup')
            ->assertOk()
            ->assertJsonStructure(['success', 'backup']);
    }

    public function test_health_endpoint_returns_ok(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJson(['status' => 'ok']);
    }
}
