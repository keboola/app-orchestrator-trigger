<?php

declare(strict_types=1);

namespace Keboola\App\OrchestratorTrigger;

use Guzzle\Http\Exception\ClientErrorResponseException;
use Keboola\Component\BaseComponent;
use Keboola\Component\UserException;
use Keboola\Orchestrator\Client as OrchestratorClient;

class Component extends BaseComponent
{
    /**
     * @var int Maximum delay between queries for job state
     */
    private $maxDelay = 20;

    private const STATUS_SUCCESS = 'success';
    private const STATUS_CANCELLED = 'cancelled';
    private const STATUS_ERROR = 'error';
    private const STATUS_WARNING = 'warning';
    private const STATUS_TERMINATED = 'terminated';


    /**
     * @var OrchestratorClient
     */
    private $client;

    public function run(): void
    {
        $this->initOrchestratorClient();
        $wait = $this->getConfig()->getValue(['parameters', 'waitUntilFinish']);

        $orchestrationId = $this->getConfig()->getValue(['parameters', 'orchestrationId']);
        $orchestrationName = $this->loadOrchestrationName($orchestrationId);

        $this->getLogger()->info(sprintf('Triggering orchestration "%s"', $orchestrationName));
        $job = $this->client->runOrchestration($orchestrationId);

        $this->getLogger()->info(sprintf(
            'Orchestration "%s" triggered, job "%s" created',
            $orchestrationName,
            $job['id']
        ));

        if ($wait) {
            $this->waitUntilFinish($job['id']);
        }
    }

    private function waitUntilFinish(int $jobId): void
    {
        $this->getLogger()->info(sprintf('Waiting for job "%s" finish', $jobId));

        $attempt = 0;
        $job = $this->client->getJob($jobId);

        while (!$this->isFinishedStatus($job['status'])) {
            $attempt++;
            sleep(min(pow(2, $attempt), $this->maxDelay));

            $this->getLogger()->info(sprintf('Checking job "%s" status', $jobId));
            $job = $this->client->getJob($jobId);
        }

        if ($job['status'] !== self::STATUS_SUCCESS) {
            if (isset($job['results']['message'])) {
                $this->getLogger()->error($job['results']['message']);
            }

            throw new UserException(sprintf('Job "%s" finished with error', $job['id']));
        }

        $this->getLogger()->info(sprintf('Job "%s" successfully finished', $job['id']));
    }

    private function loadOrchestrationName(int $orchestrationId): string
    {
        try {
            $this->getLogger()->info('Fetching orchestration details');
            $orchestration = $this->client->getOrchestration($orchestrationId);
            return $orchestration['name'];
        } catch (ClientErrorResponseException $e) {
            $json = $e->getResponse()->json();

            if (isset($json['message'])) {
                throw new UserException($json['message'], 0, $e);
            }

            throw $e;
        }
    }

    protected function getConfigClass(): string
    {
        return Config::class;
    }

    protected function getConfigDefinitionClass(): string
    {
        return ConfigDefinition::class;
    }

    private function initOrchestratorClient(): void
    {
        $this->getLogger()->info('Detecting orchestrator API url');

        $kbcToken = $this->getConfig()->getValue(['parameters', '#kbcToken']);
        $kbcUrl = $this->getConfig()->getValue(['parameters', 'kbcUrl']);

        $this->client = OrchestratorClient::factory([
            'token' => $kbcToken,
            'url' => OrchestratorEndpoint::detect($kbcToken, $kbcUrl),
        ]);
    }

    private function isFinishedStatus(string $value): bool
    {
        $map = array(
            self::STATUS_SUCCESS,
            self::STATUS_ERROR,
            self::STATUS_CANCELLED,
            self::STATUS_TERMINATED,
            self::STATUS_WARNING,
        );

        return in_array($value, $map);
    }
}
