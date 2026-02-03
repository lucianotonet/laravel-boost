<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Laravel\Boost\Console\InstallCommand;
use Laravel\Boost\Install\Agents\Codex;
use Laravel\Boost\Install\AgentsDetector;
use Laravel\Boost\Install\Detection\DetectionStrategyFactory;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputStyle;

function setPrivateProperty(object $object, string $property, mixed $value): void
{
    $reflection = new ReflectionProperty($object, $property);
    $reflection->setAccessible(true);
    $reflection->setValue($object, $value);
}

function setCommandInput(InstallCommand $command, ArrayInput $input): void
{
    $reflection = new ReflectionProperty($command, 'input');
    $reflection->setAccessible(true);
    $reflection->setValue($command, $input);
}

it('reports per-skill cleanup failures during stale skill removal', function (): void {
    $agent = new Codex(app(DetectionStrategyFactory::class));

    $agentsDetector = Mockery::mock(AgentsDetector::class);
    $agentsDetector->shouldReceive('getAgents')
        ->once()
        ->andReturn(collect([$agent]));

    app()->instance(AgentsDetector::class, $agentsDetector);

    $command = app(InstallCommand::class);
    $input = new ArrayInput([]);
    $input->setInteractive(false);
    $outputBuffer = new BufferedOutput();
    $output = new OutputStyle($input, $outputBuffer);

    $command->setLaravel($this->app);
    $command->setOutput($output);
    setCommandInput($command, $input);

    setPrivateProperty($command, 'selectedBoostFeatures', collect(['skills']));
    setPrivateProperty($command, 'selectedThirdPartyPackages', collect());
    setPrivateProperty($command, 'selectedAgents', new Collection([$agent]));
    setPrivateProperty($command, 'installedSkillNames', ['valid-skill']);
    setPrivateProperty($command, 'previouslyTrackedSkills', ['valid-skill', 'invalid/skill']);
    setPrivateProperty($command, 'previouslyTrackedPackages', ['vendor/package']);
    setPrivateProperty($command, 'previouslyTrackedAgents', ['codex']);

    $cleanup = new ReflectionMethod($command, 'cleanupStaleSkills');
    $cleanup->setAccessible(true);
    $cleanup->invoke($command);

    $outputText = $outputBuffer->fetch();

    expect($outputText)
        ->toContain('Failed to remove stale skill invalid/skill for Codex.')
        ->toContain('Stale skill cleanup finished with 1 failure.');
});
