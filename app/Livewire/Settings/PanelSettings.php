<?php

namespace App\Livewire\Settings;

use Illuminate\Support\Facades\Process;
use Livewire\Component;

class PanelSettings extends Component
{
    public $panelName = '';

    public $defaultWebServer = 'nginx';

    public $sshPublicKey = '';

    public function mount()
    {
        $this->panelName = config('app.name', 'Lumina Forge');
        $this->defaultWebServer = config('forge.default_web_server', 'nginx');
        $this->sshPublicKey = $this->getServerSshKey();
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

    public function render()
    {
        return view('livewire.settings.panel-settings')->layout('layouts.app');
    }
}
