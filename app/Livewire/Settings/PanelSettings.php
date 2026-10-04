<?php

namespace App\Livewire\Settings;

use App\Services\PanelUpdateService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Process;
use Livewire\Component;
use RuntimeException;

class PanelSettings extends Component
{
    public $panelName = '';

    public $defaultWebServer = 'nginx';

    public $sshPublicKey = '';

    public string $currentVersion = '';

    public ?string $latestVersion = null;

    public bool $updateAvailable = false;

    public ?string $updateError = null;

    public array $updateStatus = [];

    public function mount(PanelUpdateService $panelUpdateService): void
    {
        $this->panelName = config('app.name', 'Lumina Forge');
        $this->defaultWebServer = config('forge.default_web_server', 'nginx');
        $this->sshPublicKey = $this->getServerSshKey();
        $this->currentVersion = $panelUpdateService->currentVersion();
        $this->updateStatus = $panelUpdateService->status();
        $this->checkForUpdates($panelUpdateService);
    }

    public function save()
    {
        $this->validate([
            'panelName' => ['required', 'string', 'max:255'],
            'defaultWebServer' => ['required', 'in:nginx,apache'],
        ]);

        // In a real app, these would be saved to a settings table or config
        // For now, we'll just show a success message

        $this->dispatch('notify', message: 'Settings saved successfully!', type: 'success');
    }

    private function getServerSshKey(): string
    {
        $sshKeyPath = storage_path('ssh/id_rsa.pub');

        if (file_exists($sshKeyPath)) {
            return file_get_contents($sshKeyPath);
        }

        return '';
    }

    public function generateSshKey()
    {
        $sshDir = storage_path('ssh');

        if (! file_exists($sshDir)) {
            mkdir($sshDir, 0700, true);
        }

        $keyPath = $sshDir.'/id_rsa';

        if (! file_exists($keyPath)) {
            Process::run("ssh-keygen -t rsa -b 4096 -f {$keyPath} -N ''");
            $this->sshPublicKey = file_get_contents($keyPath.'.pub');
            $this->dispatch('notify', message: 'SSH key generated successfully!', type: 'success');
        } else {
            $this->dispatch('notify', message: 'SSH key already exists!', type: 'info');
        }
    }

    public function checkForUpdates(PanelUpdateService $panelUpdateService): void
    {
        $this->updateError = null;

        try {
            $latestRelease = $panelUpdateService->latestRelease();
            $this->latestVersion = $latestRelease['version'] ?? null;
            $this->updateAvailable = $panelUpdateService->updateAvailable($latestRelease);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            $this->updateError = 'Unable to check Docker Hub for updates: '.$exception->getMessage();
        }
    }

    public function updatePanel(PanelUpdateService $panelUpdateService): void
    {
        $this->updateError = null;

        try {
            $panelUpdateService->startUpdate();
            $this->updateStatus = $panelUpdateService->status();
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            $this->updateError = 'Panel update could not be started: '.$exception->getMessage();
        }
    }

    public function refreshUpdateStatus(PanelUpdateService $panelUpdateService): void
    {
        $this->updateStatus = $panelUpdateService->status();

        if (in_array($this->updateStatus['status'] ?? null, ['completed', 'failed'], true)) {
            $this->currentVersion = $panelUpdateService->currentVersion();
            $this->checkForUpdates($panelUpdateService);
        }
    }

    public function render()
    {
        return view('livewire.settings.panel-settings')->layout('layouts.app');
    }
}
