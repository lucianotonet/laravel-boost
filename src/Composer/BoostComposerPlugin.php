<?php

declare(strict_types=1);

namespace Laravel\Boost\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class BoostComposerPlugin implements PluginInterface, EventSubscriberInterface
{
    private IOInterface $io;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // No deactivation logic needed
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // No uninstall cleanup needed
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'pre-package-uninstall' => 'onPrePackageUninstall',
        ];
    }

    public function onPrePackageUninstall(PackageEvent $event): void
    {
        $package = $event->getOperation()->getPackage();

        // Only proceed if the package being uninstalled is laravel/boost
        if ($package->getName() !== 'laravel/boost') {
            return;
        }

        $this->io->write('<info>Removing boost:update script from composer.json...</info>');

        $composerJsonPath = getcwd().DIRECTORY_SEPARATOR.'composer.json';

        if (! file_exists($composerJsonPath)) {
            $this->io->writeError('<warning>composer.json not found, skipping script removal</warning>');

            return;
        }

        $remover = new ScriptRemover($composerJsonPath);

        try {
            $removed = $remover->removeBoostUpdateScript();

            if ($removed) {
                $this->io->write('<info>Successfully removed boost:update script from composer.json</info>');
            } else {
                $this->io->write('<comment>boost:update script not found in composer.json</comment>');
            }
        } catch (\Exception $e) {
            $this->io->writeError(sprintf(
                '<error>Failed to remove boost:update script: %s</error>',
                $e->getMessage()
            ));
        }
    }
}
