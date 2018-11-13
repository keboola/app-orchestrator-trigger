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
                        'kbcUrl' => 'https://connection.keboola.com',
                        'orchestrationId' => 1234567890,
                    ],
                ],
                [
                    'parameters' => [
                        '#kbcToken' => 'some-token',
                        'kbcUrl' => 'https://connection.keboola.com',
                        'orchestrationId' => 1234567890,
                        'waitUntilFinish' => false,
                        'notificationsEmails' => [],
                    ],
                ],
            ],
            'config with wait' => [
                [
                    'parameters' => [
                        '#kbcToken' => 'some-token',
                        'kbcUrl' => 'https://connection.keboola.com',
                        'orchestrationId' => 1234567890,
                        'waitUntilFinish' => true,
                    ],
                ],
                [
                    'parameters' => [
                        '#kbcToken' => 'some-token',
                        'kbcUrl' => 'https://connection.keboola.com',
                        'orchestrationId' => 1234567890,
                        'waitUntilFinish' => true,
                        'notificationsEmails' => [],
                    ],
                ],
            ],
            'config with email' => [
                [
                    'parameters' => [
                        '#kbcToken' => 'some-token',
                        'kbcUrl' => 'https://connection.keboola.com',
                        'orchestrationId' => 1234567890,
                        'waitUntilFinish' => true,
                        'notificationsEmails' => [
                            'spam@keboola.com',
                        ],
                    ],
                ],
                [
                    'parameters' => [
                        '#kbcToken' => 'some-token',
                        'kbcUrl' => 'https://connection.keboola.com',
                        'orchestrationId' => 1234567890,
                        'waitUntilFinish' => true,
                        'notificationsEmails' => [
                            'spam@keboola.com',
                        ],
                    ],
                ],
            ],
            'config with more mails' => [
                [
                    'parameters' => [
                        '#kbcToken' => 'some-token',
                        'kbcUrl' => 'https://connection.keboola.com',
                        'orchestrationId' => 1234567890,
                        'waitUntilFinish' => true,
                        'notificationsEmails' => [
                            'spam@keboola.com',
                            'spam+trigger@keboola.com',
                        ],
                    ],
                ],
                [
                    'parameters' => [
                        '#kbcToken' => 'some-token',
                        'kbcUrl' => 'https://connection.keboola.com',
                        'orchestrationId' => 1234567890,
                        'waitUntilFinish' => true,
                        'notificationsEmails' => [
                            'spam@keboola.com',
                            'spam+trigger@keboola.com',
                        ],
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
            'missing KBC endpoint' => [
                [
                    'parameters' => [
                        '#kbcToken' => 'some-token',
                    ],
                ],
                InvalidConfigurationException::class,
                'The child node "kbcUrl" at path "root.parameters" must be configured.',
            ],
            'missing orchestration ID' => [
                [
                    'parameters' => [
                        '#kbcToken' => 'some-token',
                        'kbcUrl' => 'https://connection.keboola.com',
                    ],
                ],
                InvalidConfigurationException::class,
                'The child node "orchestrationId" at path "root.parameters" must be configured.',
            ],
            'notifications emails as string' => [
                [
                    'parameters' => [
                        '#kbcToken' => 'some-token',
                        'kbcUrl' => 'https://connection.keboola.com',
                        'orchestrationId' => 1234567890,
                        'notificationsEmails' => 'spam@keboola.com',
                    ],
                ],
                InvalidConfigurationException::class,
                'Invalid type for path "root.parameters.notificationsEmails". Expected array, but got string',
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
