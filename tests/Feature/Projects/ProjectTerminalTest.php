<?php

use App\Models\Project;
use App\Models\User;
use App\Services\ContainerTerminalService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->deployed()->create(['user_id' => $this->user->id]);
});

it('starts a terminal session for the project owner', function () {
    $terminal = Mockery::mock(ContainerTerminalService::class);
    $terminal->shouldReceive('start')
        ->once()
        ->with(Mockery::on(fn (Project $project): bool => $project->is($this->project)))
        ->andReturn([
            'token' => 'session-token',
            'output_url' => route('projects.terminal.output', [$this->project, 'session-token']),
        ]);
    $this->app->instance(ContainerTerminalService::class, $terminal);

    $response = $this->actingAs($this->user)
        ->postJson(route('projects.terminal.start', $this->project));

    $response->assertOk()
        ->assertJsonPath('token', 'session-token');
});

it('forbids a user from opening another users terminal', function () {
    $otherUser = User::factory()->create();

    $response = $this->actingAs($otherUser)
        ->postJson(route('projects.terminal.start', $this->project));

    $response->assertForbidden();
});

it('rejects terminal input larger than four thousand characters', function () {
    $terminal = Mockery::mock(ContainerTerminalService::class);
    $terminal->shouldNotReceive('write');
    $this->app->instance(ContainerTerminalService::class, $terminal);

    $response = $this->actingAs($this->user)
        ->postJson(route('projects.terminal.input', [$this->project, 'session-token']), [
            'input_base64' => base64_encode(str_repeat('x', 4097)),
        ]);

    $response->assertUnprocessable()
        ->assertSee('Invalid terminal input.');
});

it('writes terminal input for the project owner', function () {
    $terminal = Mockery::mock(ContainerTerminalService::class);
    $terminal->shouldReceive('write')
        ->once()
        ->with(
            Mockery::on(fn (Project $project): bool => $project->is($this->project)),
            'session-token',
            "ls\r"
        );
    $this->app->instance(ContainerTerminalService::class, $terminal);

    $response = $this->actingAs($this->user)
        ->postJson(route('projects.terminal.input', [$this->project, 'session-token']), [
            'input_base64' => base64_encode("ls\r"),
        ]);

    $response->assertOk()
        ->assertJson(['ok' => true]);
});
