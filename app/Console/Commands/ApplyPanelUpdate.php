<?php

namespace App\Console\Commands;

use App\Services\PanelUpdateService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('bitora:apply-panel-update {--image= : Versioned Bitora image to install}')]
#[Description('Safely replace the Bitora panel container and roll back if its health check fails')]
class ApplyPanelUpdate extends Command
{
    public function handle(PanelUpdateService $panelUpdateService): int
    {
        $image = $this->option('image');

        if (! is_string($image) || $image === '') {
            $this->error('A versioned image is required.');

            return self::FAILURE;
        }

        try {
            $panelUpdateService->applyUpdate($image);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            $panelUpdateService->writeStatus('failed', 'Panel update did not complete: '.$exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
