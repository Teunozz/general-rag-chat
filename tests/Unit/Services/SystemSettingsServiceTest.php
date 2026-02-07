<?php

namespace Tests\Unit\Services;

use App\Models\SystemSetting;
use App\Services\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    private SystemSettingsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SystemSettingsService();
    }

    public function test_get_returns_default_when_setting_does_not_exist(): void
    {
        $this->assertSame('default', $this->service->get('group', 'key', 'default'));
    }

    public function test_get_returns_null_when_no_default(): void
    {
        $this->assertNull($this->service->get('group', 'nonexistent'));
    }

    public function test_set_and_get_string_value(): void
    {
        $this->service->set('branding', 'app_name', 'My App');

        $this->assertSame('My App', $this->service->get('branding', 'app_name'));
    }

    public function test_set_and_get_integer_value(): void
    {
        $this->service->set('chat', 'max_chunks', 15);

        $this->assertSame(15, $this->service->get('chat', 'max_chunks'));
    }

    public function test_set_and_get_boolean_value(): void
    {
        $this->service->set('email', 'system_enabled', true);

        $this->assertTrue($this->service->get('email', 'system_enabled'));
    }

    public function test_set_overwrites_existing_value(): void
    {
        $this->service->set('branding', 'app_name', 'Old Name');
        $this->service->set('branding', 'app_name', 'New Name');

        $this->assertSame('New Name', $this->service->get('branding', 'app_name'));
    }

    public function test_group_returns_all_settings_in_group(): void
    {
        $this->service->set('branding', 'app_name', 'My App');
        $this->service->set('branding', 'app_description', 'A description');
        $this->service->set('other', 'key', 'value');

        $group = $this->service->group('branding');

        $this->assertCount(2, $group);
        $this->assertSame('My App', $group['app_name']);
        $this->assertSame('A description', $group['app_description']);
    }

    public function test_group_returns_empty_array_for_nonexistent_group(): void
    {
        $this->assertSame([], $this->service->group('nonexistent'));
    }
}
