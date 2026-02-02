<?php

declare(strict_types=1);

namespace Laravel\Boost\Composer;

class ScriptRemover
{
    /**
     * Pattern to match boost:update command with any parameters.
     * Matches variations like:
     * - @php artisan boost:update
     * - @php artisan boost:update --ansi
     * - php artisan boost:update --ansi
     * - ./artisan boost:update
     */
    private const BOOST_UPDATE_PATTERN = '/^(@)?php\s+(\.|\.\/)?artisan\s+boost:update(\s+.*)?$/i';

    public function __construct(private string $composerJsonPath)
    {
    }

    /**
     * Remove boost:update script from composer.json.
     *
     * @return bool True if script was found and removed, false if not found
     *
     * @throws \RuntimeException If composer.json cannot be read or written
     */
    public function removeBoostUpdateScript(): bool
    {
        $composerData = $this->readComposerJson();
        $originalData = $composerData;

        $composerData = $this->removeScriptFromSection($composerData);

        // Check if anything was actually removed
        if ($composerData === $originalData) {
            return false;
        }

        $this->writeComposerJson($composerData);

        return true;
    }

    /**
     * Read and decode composer.json file.
     *
     * @throws \RuntimeException If file cannot be read or JSON is invalid
     */
    protected function readComposerJson(): array
    {
        if (! is_readable($this->composerJsonPath)) {
            throw new \RuntimeException(sprintf(
                'composer.json file is not readable: %s',
                $this->composerJsonPath
            ));
        }

        $content = file_get_contents($this->composerJsonPath);

        if ($content === false) {
            throw new \RuntimeException('Failed to read composer.json file');
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(sprintf(
                'Invalid JSON in composer.json: %s',
                json_last_error_msg()
            ));
        }

        return $data;
    }

    /**
     * Remove boost:update script from post-update-cmd section.
     */
    protected function removeScriptFromSection(array $composerData): array
    {
        if (! isset($composerData['scripts']['post-update-cmd'])) {
            return $composerData;
        }

        $scripts = $composerData['scripts']['post-update-cmd'];

        // Handle both string and array formats
        if (is_string($scripts)) {
            if ($this->isBoostUpdateScript($scripts)) {
                unset($composerData['scripts']['post-update-cmd']);
            }
        } elseif (is_array($scripts)) {
            $filtered = array_values(array_filter(
                $scripts,
                fn ($script) => ! $this->isBoostUpdateScript($script)
            ));

            if (count($filtered) === 0) {
                // If no scripts remain, remove the entire post-update-cmd section
                unset($composerData['scripts']['post-update-cmd']);
            } else {
                $composerData['scripts']['post-update-cmd'] = $filtered;
            }
        }

        // Clean up empty scripts section
        if (isset($composerData['scripts']) && empty($composerData['scripts'])) {
            unset($composerData['scripts']);
        }

        return $composerData;
    }

    /**
     * Check if a script string is a boost:update command.
     */
    protected function isBoostUpdateScript(mixed $script): bool
    {
        if (! is_string($script)) {
            return false;
        }

        return preg_match(self::BOOST_UPDATE_PATTERN, trim($script)) === 1;
    }

    /**
     * Write composer.json file with proper formatting.
     *
     * @throws \RuntimeException If file cannot be written
     */
    protected function writeComposerJson(array $composerData): void
    {
        if (! is_writable($this->composerJsonPath)) {
            throw new \RuntimeException(sprintf(
                'composer.json file is not writable: %s',
                $this->composerJsonPath
            ));
        }

        $json = json_encode(
            $composerData,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ($json === false) {
            throw new \RuntimeException(sprintf(
                'Failed to encode composer.json: %s',
                json_last_error_msg()
            ));
        }

        // Ensure proper line ending
        $json .= "\n";

        if (file_put_contents($this->composerJsonPath, $json) === false) {
            throw new \RuntimeException('Failed to write composer.json file');
        }
    }
}
