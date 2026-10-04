<?php

use App\Livewire\Settings\PanelSettings;
use App\Models\User;
use App\Services\PanelUpdateService;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('redirects guests away from panel update settings', function () {
    $this->get(route('settings.panel'))
        ->assertRedirect(route('login'));
});

it('shows available versions and lets an authenticated user start a panel update', function () {
    $user = User::factory()->create();
    $service = Mockery::mock(PanelUpdateService::class);
    $service->shouldReceive('currentVersion')->once()->andReturn('0.2.0');
    $service->shouldReceive('status')->twice()->andReturn(
        ['status' => 'idle', 'message' => null],
        ['status' => 'starting', 'message' => 'Preparing update.'],
    );
    $service->shouldReceive('latestRelease')->once()->andReturn([
        'tag' => 'v0.3.0',
        'version' => '0.3.0',
    ]);
    $service->shouldReceive('updateAvailable')->once()->andReturnTrue();
    $service->shouldReceive('startUpdate')->once();
    $this->app->instance(PanelUpdateService::class, $service);

    Livewire::actingAs($user)
        ->test(PanelSettings::class)
        ->assertSee('Current version: 0.2.0')
        ->assertSee('Latest version: 0.3.0')
        ->assertSee('Version 0.3.0 is available.')
        ->call('updatePanel')
        ->assertSet('updateStatus.status', 'starting')
        ->assertSee('Preparing update.');
});

it('confirms when no newer panel version is available', function () {
    $url = config('bitora.dockerhub_tags_url');
    config(['bitora.version' => '0.3.0']);
    Http::preventStrayRequests();
    Http::fake([
        $url => Http::response(['results' => [['name' => 'v0.3.0']]]),
    ]);
    $this->actingAs(User::factory()->create());

    Livewire::test(PanelSettings::class)
        ->assertSee('Latest version: 0.3.0')
        ->assertSee('You are already using the latest version.')
        ->call('checkForUpdates')
        ->assertSet('updateAvailable', false)
        ->assertSee('You are already using the latest version.');
});

it('renders the configured version in panel settings', function () {
    $url = config('bitora.dockerhub_tags_url');
    config(['bitora.version' => '0.1.0']);
    Http::preventStrayRequests();
    Http::fake([
        $url => Http::response(['results' => [['name' => 'v0.1.0']]]),
    ]);
    $this->actingAs(User::factory()->create());

    Livewire::test(PanelSettings::class)
        ->assertSee('Current version: 0.1.0');
});

it('shows Docker Hub failures instead of silently hiding update availability', function () {
    $url = config('bitora.dockerhub_tags_url');
    Http::preventStrayRequests();
    Http::fake([$url => Http::failedConnection()]);
    $this->actingAs(User::factory()->create());

    Livewire::test(PanelSettings::class)
        ->assertSee('Unable to check Docker Hub for updates:');
});
