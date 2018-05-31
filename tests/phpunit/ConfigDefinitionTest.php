<?php

declare(strict_types=1);

namespace Keboola\App\OrchestratorTrigger\Tests;

use PHPUnit\Framework\TestCase;
use Keboola\App\OrchestratorTrigger\ConfigDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class ConfigDefinitionTest extends TestCase
{
    /**
     * @dataProvider provideValidConfigs
     */
    public function testValidConfigDefinition(array $inputConfig, array $expectedConfig): void
    {
        $definition = new ConfigDefinition();
        $processor = new Processor();
        $processedConfig = $processor->processConfiguration($definition, [$inputConfig]);
        $this->assertSame($expectedConfig, $processedConfig);
    }

    /**
     * @return mixed[][]
     */
    public function provideValidConfigs(): array
    {
        return [
            'config' => [
                [
                    'parameters' => [
                        '#kbcToken' => 'some-token',
                        'orchestrationId' => 1234567890,
                    ],
                ],
                [
                    'parameters' => [
                        '#kbcToken' => 'some-token',
                        'orchestrationId' => 1234567890,
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider provideInvalidConfigs
     */
    public function testInvalidConfigDefinition(
        array $inputConfig,
        string $expectedExceptionClass,
        string $expectedExceptionMessage
    ): void {
        $definition = new ConfigDefinition();
        $processor = new Processor();
        $this->expectException($expectedExceptionClass);
        $this->expectExceptionMessage($expectedExceptionMessage);
        $processor->processConfiguration($definition, [$inputConfig]);
    }

    /**
     * @return mixed[][]
     */
    public function provideInvalidConfigs(): array
    {
        return [
            'empty parameters' => [
                [
                    'parameters' => [],
                ],
                InvalidConfigurationException::class,
                'The child node "#kbcToken" at path "root.parameters" must be configured.',
            ],
            'missing orchestration ID' => [
                [
                    'parameters' => [
                        '#kbcToken' => 'some-token',
                    ],
                ],
                InvalidConfigurationException::class,
                'The child node "orchestrationId" at path "root.parameters" must be configured.',
            ],
            'extra params' => [
                [
                    'parameters' => [
                        'other' => 'something',
                    ],
                ],
                InvalidConfigurationException::class,
                'Unrecognized option "other" under "root.parameters"',
            ],
        ];
    }
}
