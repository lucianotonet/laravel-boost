<?php

declare(strict_types=1);

use Laravel\Boost\Composer\ScriptRemover;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'boost_test_'.uniqid();
    mkdir($this->tempDir);
    $this->composerJsonPath = $this->tempDir.DIRECTORY_SEPARATOR.'composer.json';
});

afterEach(function () {
    if (file_exists($this->composerJsonPath)) {
        unlink($this->composerJsonPath);
    }

    if (is_dir($this->tempDir)) {
        rmdir($this->tempDir);
    }
});

test('removes boost update script with ansi flag', function () {
    $composerData = [
        'name' => 'test/project',
        'scripts' => [
            'post-update-cmd' => [
                '@php artisan vendor:publish --tag=laravel-assets --ansi --force',
                '@php artisan boost:update --ansi',
            ],
        ],
    ];

    file_put_contents($this->composerJsonPath, json_encode($composerData));

    $remover = new ScriptRemover($this->composerJsonPath);
    $removed = $remover->removeBoostUpdateScript();

    expect($removed)->toBeTrue();

    $result = json_decode(file_get_contents($this->composerJsonPath), true);
    expect($result['scripts']['post-update-cmd'])
        ->toHaveCount(1)
        ->toContain('@php artisan vendor:publish --tag=laravel-assets --ansi --force');
});

test('removes boost update script without flags', function () {
    $composerData = [
        'name' => 'test/project',
        'scripts' => [
            'post-update-cmd' => [
                '@php artisan vendor:publish --tag=laravel-assets --ansi --force',
                '@php artisan boost:update',
            ],
        ],
    ];

    file_put_contents($this->composerJsonPath, json_encode($composerData));

    $remover = new ScriptRemover($this->composerJsonPath);
    $removed = $remover->removeBoostUpdateScript();

    expect($removed)->toBeTrue();

    $result = json_decode(file_get_contents($this->composerJsonPath), true);
    expect($result['scripts']['post-update-cmd'])
        ->toHaveCount(1)
        ->toContain('@php artisan vendor:publish --tag=laravel-assets --ansi --force');
});

test('removes boost update script with custom flags', function () {
    $composerData = [
        'name' => 'test/project',
        'scripts' => [
            'post-update-cmd' => [
                '@php artisan boost:update --ansi --force --verbose',
            ],
        ],
    ];

    file_put_contents($this->composerJsonPath, json_encode($composerData));

    $remover = new ScriptRemover($this->composerJsonPath);
    $removed = $remover->removeBoostUpdateScript();

    expect($removed)->toBeTrue();

    $result = json_decode(file_get_contents($this->composerJsonPath), true);
    expect($result['scripts'] ?? [])->toBeEmpty();
});

test('removes boost update script without @ prefix', function () {
    $composerData = [
        'name' => 'test/project',
        'scripts' => [
            'post-update-cmd' => [
                'php artisan boost:update --ansi',
            ],
        ],
    ];

    file_put_contents($this->composerJsonPath, json_encode($composerData));

    $remover = new ScriptRemover($this->composerJsonPath);
    $removed = $remover->removeBoostUpdateScript();

    expect($removed)->toBeTrue();

    $result = json_decode(file_get_contents($this->composerJsonPath), true);
    expect($result['scripts'] ?? [])->toBeEmpty();
});

test('removes boost update script with relative artisan path', function () {
    $composerData = [
        'name' => 'test/project',
        'scripts' => [
            'post-update-cmd' => [
                '@php ./artisan boost:update --ansi',
            ],
        ],
    ];

    file_put_contents($this->composerJsonPath, json_encode($composerData));

    $remover = new ScriptRemover($this->composerJsonPath);
    $removed = $remover->removeBoostUpdateScript();

    expect($removed)->toBeTrue();

    $result = json_decode(file_get_contents($this->composerJsonPath), true);
    expect($result['scripts'] ?? [])->toBeEmpty();
});

test('removes post-update-cmd section when it was the only script', function () {
    $composerData = [
        'name' => 'test/project',
        'scripts' => [
            'post-update-cmd' => '@php artisan boost:update --ansi',
        ],
    ];

    file_put_contents($this->composerJsonPath, json_encode($composerData));

    $remover = new ScriptRemover($this->composerJsonPath);
    $removed = $remover->removeBoostUpdateScript();

    expect($removed)->toBeTrue();

    $result = json_decode(file_get_contents($this->composerJsonPath), true);
    expect($result['scripts'] ?? [])->toBeEmpty();
});

test('preserves other scripts in post-update-cmd', function () {
    $composerData = [
        'name' => 'test/project',
        'scripts' => [
            'post-update-cmd' => [
                '@php artisan vendor:publish --tag=laravel-assets --ansi --force',
                '@php artisan boost:update --ansi',
                '@php artisan optimize',
            ],
        ],
    ];

    file_put_contents($this->composerJsonPath, json_encode($composerData));

    $remover = new ScriptRemover($this->composerJsonPath);
    $removed = $remover->removeBoostUpdateScript();

    expect($removed)->toBeTrue();

    $result = json_decode(file_get_contents($this->composerJsonPath), true);
    expect($result['scripts']['post-update-cmd'])
        ->toHaveCount(2)
        ->toContain('@php artisan vendor:publish --tag=laravel-assets --ansi --force')
        ->toContain('@php artisan optimize');
});

test('preserves other script sections', function () {
    $composerData = [
        'name' => 'test/project',
        'scripts' => [
            'post-install-cmd' => [
                '@php artisan optimize',
            ],
            'post-update-cmd' => [
                '@php artisan boost:update --ansi',
            ],
            'test' => 'pest',
        ],
    ];

    file_put_contents($this->composerJsonPath, json_encode($composerData));

    $remover = new ScriptRemover($this->composerJsonPath);
    $removed = $remover->removeBoostUpdateScript();

    expect($removed)->toBeTrue();

    $result = json_decode(file_get_contents($this->composerJsonPath), true);
    expect($result['scripts'])
        ->toHaveKey('post-install-cmd')
        ->toHaveKey('test')
        ->not->toHaveKey('post-update-cmd');
});

test('returns false when script is not found', function () {
    $composerData = [
        'name' => 'test/project',
        'scripts' => [
            'post-update-cmd' => [
                '@php artisan optimize',
            ],
        ],
    ];

    file_put_contents($this->composerJsonPath, json_encode($composerData));

    $remover = new ScriptRemover($this->composerJsonPath);
    $removed = $remover->removeBoostUpdateScript();

    expect($removed)->toBeFalse();

    $result = json_decode(file_get_contents($this->composerJsonPath), true);
    expect($result['scripts']['post-update-cmd'])->toHaveCount(1);
});

test('returns false when post-update-cmd does not exist', function () {
    $composerData = [
        'name' => 'test/project',
        'scripts' => [
            'test' => 'pest',
        ],
    ];

    file_put_contents($this->composerJsonPath, json_encode($composerData));

    $remover = new ScriptRemover($this->composerJsonPath);
    $removed = $remover->removeBoostUpdateScript();

    expect($removed)->toBeFalse();
});

test('returns false when scripts section does not exist', function () {
    $composerData = [
        'name' => 'test/project',
    ];

    file_put_contents($this->composerJsonPath, json_encode($composerData));

    $remover = new ScriptRemover($this->composerJsonPath);
    $removed = $remover->removeBoostUpdateScript();

    expect($removed)->toBeFalse();
});

test('throws exception when composer.json is not readable', function () {
    $remover = new ScriptRemover('/non/existent/path/composer.json');
    $remover->removeBoostUpdateScript();
})->throws(\RuntimeException::class, 'composer.json file is not readable');

test('throws exception when composer.json has invalid json', function () {
    file_put_contents($this->composerJsonPath, '{invalid json}');

    $remover = new ScriptRemover($this->composerJsonPath);
    $remover->removeBoostUpdateScript();
})->throws(\RuntimeException::class, 'Invalid JSON in composer.json');

test('throws exception when composer.json is not writable', function () {
    $composerData = [
        'name' => 'test/project',
        'scripts' => [
            'post-update-cmd' => '@php artisan boost:update --ansi',
        ],
    ];

    file_put_contents($this->composerJsonPath, json_encode($composerData));
    chmod($this->composerJsonPath, 0444); // Read-only

    $remover = new ScriptRemover($this->composerJsonPath);
    $remover->removeBoostUpdateScript();
})->throws(\RuntimeException::class, 'composer.json file is not writable')->skip(
    fn () => strtoupper(substr(PHP_OS, 0, 3)) === 'WIN',
    'chmod does not work reliably on Windows'
);

test('preserves json formatting', function () {
    $composerData = [
        'name' => 'test/project',
        'description' => 'A test project',
        'scripts' => [
            'post-update-cmd' => [
                '@php artisan vendor:publish --tag=laravel-assets --ansi --force',
                '@php artisan boost:update --ansi',
            ],
        ],
    ];

    file_put_contents($this->composerJsonPath, json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $remover = new ScriptRemover($this->composerJsonPath);
    $remover->removeBoostUpdateScript();

    $content = file_get_contents($this->composerJsonPath);
    expect($content)
        ->toContain('    "name"') // Indented with 4 spaces
        ->toEndWith("\n"); // Has trailing newline
});

test('handles case insensitive boost update command', function () {
    $composerData = [
        'name' => 'test/project',
        'scripts' => [
            'post-update-cmd' => [
                '@PHP artisan BOOST:UPDATE --ANSI',
            ],
        ],
    ];

    file_put_contents($this->composerJsonPath, json_encode($composerData));

    $remover = new ScriptRemover($this->composerJsonPath);
    $removed = $remover->removeBoostUpdateScript();

    expect($removed)->toBeTrue();

    $result = json_decode(file_get_contents($this->composerJsonPath), true);
    expect($result['scripts'] ?? [])->toBeEmpty();
});

test('does not remove similar but different commands', function () {
    $composerData = [
        'name' => 'test/project',
        'scripts' => [
            'post-update-cmd' => [
                '@php artisan boost:install --ansi',
                '@php artisan boost:start --ansi',
                '@php artisan cache:clear',
            ],
        ],
    ];

    file_put_contents($this->composerJsonPath, json_encode($composerData));

    $remover = new ScriptRemover($this->composerJsonPath);
    $removed = $remover->removeBoostUpdateScript();

    expect($removed)->toBeFalse();

    $result = json_decode(file_get_contents($this->composerJsonPath), true);
    expect($result['scripts']['post-update-cmd'])->toHaveCount(3);
});
