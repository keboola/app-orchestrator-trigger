<?php

declare(strict_types=1);

namespace Keboola\App\OrchestratorTrigger;

use Keboola\Component\UserException;
use Keboola\StorageApi\Client as StorageApiClient;
use Keboola\StorageApi\ClientException;

class OrchestratorEndpoint
{
    public const ORCHESTRATOR_COMPONENT_ID = 'orchestrator';

    public static function detect(string $kbcToken, string $kbcUrl): string
    {
        $sapiClient = new StorageApiClient([
            'token' => $kbcToken,
            'url' => $kbcUrl,
            'backoffMaxTries' => 3,
        ]);

        try {
            $index = $sapiClient->indexAction();
            foreach ($index['components'] as $component) {
                if ($component['id'] !== self::ORCHESTRATOR_COMPONENT_ID) {
                    continue;
                }

                return $component['uri'];
            }
        } catch (ClientException $e) {
            throw new UserException('Cannot load KBC Component index', 0, $e);
        }

        $tokenData = $sapiClient->verifyToken();
        throw new UserException(sprintf('Orchestrator not found in %s region', $tokenData['owner']['region']));
    }
}
