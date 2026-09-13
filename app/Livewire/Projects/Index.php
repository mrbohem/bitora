<?php

namespace App\Livewire\Projects;

use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public $search = '';

    public $statusFilter = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function render()
    {
        $projects = auth()->user()->projects()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('domain', 'like', '%'.$this->search.'%');
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->latest()
            ->paginate(12);

        return view('livewire.projects.index', [
            'projects' => $projects,
        ])->layout('layouts.app');
    }
}
