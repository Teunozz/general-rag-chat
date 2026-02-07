<?php

use App\Services\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new SystemSettingsService();
});

test('get returns default when setting does not exist', function () {
    expect($this->service->get('group', 'key', 'default'))->toBe('default');
});

test('get returns null when no default', function () {
    expect($this->service->get('group', 'nonexistent'))->toBeNull();
});

test('set and get string value', function () {
    $this->service->set('branding', 'app_name', 'My App');

    expect($this->service->get('branding', 'app_name'))->toBe('My App');
});

test('set and get integer value', function () {
    $this->service->set('chat', 'max_chunks', 15);

    expect($this->service->get('chat', 'max_chunks'))->toBe(15);
});

test('set and get boolean value', function () {
    $this->service->set('email', 'system_enabled', true);

    expect($this->service->get('email', 'system_enabled'))->toBeTrue();
});

test('set overwrites existing value', function () {
    $this->service->set('branding', 'app_name', 'Old Name');
    $this->service->set('branding', 'app_name', 'New Name');

    expect($this->service->get('branding', 'app_name'))->toBe('New Name');
});

test('group returns all settings in group', function () {
    $this->service->set('branding', 'app_name', 'My App');
    $this->service->set('branding', 'app_description', 'A description');
    $this->service->set('other', 'key', 'value');

    $group = $this->service->group('branding');

    expect($group)->toHaveCount(2)
        ->and($group['app_name'])->toBe('My App')
        ->and($group['app_description'])->toBe('A description');
});

test('group returns empty array for nonexistent group', function () {
    expect($this->service->group('nonexistent'))->toBe([]);
});
