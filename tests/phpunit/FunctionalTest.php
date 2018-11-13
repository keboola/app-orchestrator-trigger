<?php

declare(strict_types=1);

namespace Keboola\App\OrchestratorTrigger\Tests;

use Keboola\App\OrchestratorTrigger\OrchestratorEndpoint;
use Keboola\Orchestrator\Client as OrchestratorClient;
use Keboola\Orchestrator\OrchestrationTask;
use Keboola\StorageApi\Client as StorageApi;
use Keboola\Temp\Temp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class FunctionalTest extends TestCase
{
    /**
     * @var Temp
     */
    protected $temp;

    /**
     * @var OrchestratorClient
     */
    protected $client;

    /**
     * @var StorageApi
     */
    protected $sapiClient;

    /**
     * @var OrchestratorClient
     */
    protected $destinationClient;

    /**
     * @var string
     */
    private $testRunId;

    public function setUp(): void
    {
        parent::setUp();

        $this->sapiClient = new StorageApi([
            'url' => getenv('TEST_STORAGE_API_URL'),
            'token' => getenv('TEST_STORAGE_API_TOKEN'),
        ]);

        $this->client = OrchestratorClient::factory([
            'token' => getenv('TEST_STORAGE_API_TOKEN'),
            'url' => OrchestratorEndpoint::detect(
                getenv('TEST_STORAGE_API_TOKEN'),
                getenv('TEST_STORAGE_API_URL')
            ),
        ]);

        $this->temp = new Temp('app-orchestrator-trigger');
        $this->temp->initRunFolder();

        $this->cleanupKbcProject();

        $this->testRunId = $this->sapiClient->generateRunId();
    }

    public function testRun(): void
    {
        $orchestrationId = $this->createOrchestration(getenv('TEST_COMPONENT_CONFIG_ID'));

        $fileSystem = new Filesystem();
        $fileSystem->dumpFile(
            $this->temp->getTmpFolder() . '/config.json',
            \json_encode([
                'parameters' => [
                    '#kbcToken' => getenv('TEST_STORAGE_API_TOKEN'),
                    'kbcUrl' => getenv('TEST_STORAGE_API_URL'),
                    'orchestrationId' => $orchestrationId,
                ],
            ])
        );

        $runProcess = $this->createTestProcess();
        $runProcess->mustRun();

        $output = $runProcess->getOutput();
        $errorOutput = $runProcess->getErrorOutput();

        sleep(5);
        $jobs = $this->client->getOrchestrationJobs($orchestrationId);
        $this->assertCount(1, $jobs);

        $this->assertEmpty($errorOutput);

        $this->assertContains('Triggering orchestration', $output);
        $this->assertContains('triggered', $output);
        $this->assertNotContains('Waiting for job', $output);

        $message = sprintf('job "%s" created', $jobs[0]['id']);
        $this->assertContains($message, $output);
    }

    public function testRunError(): void
    {
        $fileSystem = new Filesystem();
        $fileSystem->dumpFile(
            $this->temp->getTmpFolder() . '/config.json',
            \json_encode([
                'parameters' => [
                    '#kbcToken' => getenv('TEST_STORAGE_API_TOKEN'),
                    'kbcUrl' => getenv('TEST_STORAGE_API_URL'),
                    'orchestrationId' => 1,
                ],
            ])
        );

        $runProcess = $this->createTestProcess();
        $runProcess->run();

        $this->assertEquals(1, $runProcess->getExitCode());

        $errorOutput = $runProcess->getErrorOutput();
        $this->assertContains('Orchestration 1 not found', $errorOutput);
    }

    public function testWaitRun(): void
    {
        $orchestrationId = $this->createOrchestration(getenv('TEST_COMPONENT_CONFIG_ID'));

        $fileSystem = new Filesystem();
        $fileSystem->dumpFile(
            $this->temp->getTmpFolder() . '/config.json',
            \json_encode([
                'parameters' => [
                    '#kbcToken' => getenv('TEST_STORAGE_API_TOKEN'),
                    'kbcUrl' => getenv('TEST_STORAGE_API_URL'),
                    'orchestrationId' => $orchestrationId,
                    'waitUntilFinish' => true,
                ],
            ])
        );

        $runProcess = $this->createTestProcess();
        $runProcess->mustRun();

        $output = $runProcess->getOutput();
        $errorOutput = $runProcess->getErrorOutput();

        $this->assertEmpty($errorOutput);

        $jobs = $this->client->getOrchestrationJobs($orchestrationId);
        $this->assertCount(1, $jobs);

        $job = reset($jobs);

        $this->assertContains('Triggering orchestration', $output);
        $this->assertContains('triggered', $output);
        $this->assertContains('Waiting for job', $output);
        $this->assertContains('successfully finished', $output);

        $this->assertArrayHasKey('status', $job);
        $this->assertArrayHasKey('id', $job);

        $this->assertContains(sprintf('job "%s" created', $job['id']), $output);
        $this->assertEquals('success', $job['status']);
    }

    public function testWaitRunError(): void
    {
        $orchestrationId = $this->createOrchestration(null);

        $fileSystem = new Filesystem();
        $fileSystem->dumpFile(
            $this->temp->getTmpFolder() . '/config.json',
            \json_encode([
                'parameters' => [
                    '#kbcToken' => getenv('TEST_STORAGE_API_TOKEN'),
                    'kbcUrl' => getenv('TEST_STORAGE_API_URL'),
                    'orchestrationId' => $orchestrationId,
                    'waitUntilFinish' => true,
                ],
            ])
        );

        $runProcess = $this->createTestProcess();
        $runProcess->run();

        $output = $runProcess->getOutput();
        $errorOutput = $runProcess->getErrorOutput();

        $this->assertEquals(1, $runProcess->getExitCode());

        $jobs = $this->client->getOrchestrationJobs($orchestrationId);
        $this->assertCount(1, $jobs);

        $job = reset($jobs);

        $this->assertContains('Triggering orchestration', $output);
        $this->assertContains('triggered', $output);
        $this->assertContains('Waiting for job', $output);
        $this->assertContains('finished with error', $errorOutput);

        $this->assertArrayHasKey('status', $job);
        $this->assertArrayHasKey('id', $job);

        $this->assertContains(sprintf('job "%s" created', $job['id']), $output);
        $this->assertNotEquals('success', $job['status']);
    }

    private function cleanupKbcProject(): void
    {
        $orchestrations = $this->client->getOrchestrations();
        foreach ($orchestrations as $orchestration) {
            $this->client->deleteOrchestration($orchestration['id']);
        }
    }

    private function createTestProcess(): Process
    {
        $runCommand = "php /code/src/run.php";
        return new  Process($runCommand, null, [
            'KBC_DATADIR' => $this->temp->getTmpFolder(),
            'KBC_RUNID' => $this->testRunId,
        ]);
    }

    private function createOrchestration(?string $taskConfigId = null): int
    {
        $orchestration = $this->client->createOrchestration('Master orchestration');

        $orchestrationId = $orchestration['id'];

        // create orchestration tasks
        $task = (new OrchestrationTask())->setComponent(getenv('TEST_COMPONENT_ID'))
            ->setAction('run')
            ->setContinueOnFailure(false)
            ->setPhase(1)
            ->setActive(true)
            ->setTimeoutMinutes(3)
            ->setActionParameters(['config' => $taskConfigId]);

        $this->client->updateTasks($orchestrationId, [$task]);

        return $orchestrationId;
    }

    public function testRunIdNotPropagate(): void
    {
        $events = $this->sapiClient->listEvents(['runId' => $this->testRunId]);
        self::assertCount(0, $events);

        $orchestrationId = $this->createOrchestration(getenv('TEST_COMPONENT_CONFIG_ID'));

        $fileSystem = new Filesystem();
        $fileSystem->dumpFile(
            $this->temp->getTmpFolder() . '/config.json',
            \json_encode([
                'parameters' => [
                    '#kbcToken' => getenv('TEST_STORAGE_API_TOKEN'),
                    'kbcUrl' => getenv('TEST_STORAGE_API_URL'),
                    'orchestrationId' => $orchestrationId,
                    'waitUntilFinish' => true,
                ],
            ])
        );

        $runProcess = $this->createTestProcess();
        $runProcess->mustRun();

        $output = $runProcess->getOutput();
        $errorOutput = $runProcess->getErrorOutput();

        $this->assertEmpty($errorOutput);

        $jobs = $this->client->getOrchestrationJobs($orchestrationId);
        $this->assertCount(1, $jobs);

        $job = reset($jobs);

        $this->assertContains('Triggering orchestration', $output);
        $this->assertContains('triggered', $output);
        $this->assertContains('Waiting for job', $output);
        $this->assertContains('successfully finished', $output);

        $this->assertArrayHasKey('status', $job);
        $this->assertArrayHasKey('id', $job);

        $this->assertContains(sprintf('job "%s" created', $job['id']), $output);
        $this->assertEquals('success', $job['status']);

        $events = $this->sapiClient->listEvents(['runId' => $this->testRunId]);
        self::assertCount(0, $events);

        $events = $this->sapiClient->listEvents();
        $this->assertCount(1, array_filter(
            $events,
            function (array $event) use ($job) {
                return $event['message'] === sprintf('Orchestration job %s end', $job['id']);
            }
        ));
    }

    public function testNotificationsEmails(): void
    {
        $notificationEmail = 'spam@keboola.com';
        $orchestrationId = $this->createOrchestration(getenv('TEST_COMPONENT_CONFIG_ID'));

        $fileSystem = new Filesystem();
        $fileSystem->dumpFile(
            $this->temp->getTmpFolder() . '/config.json',
            \json_encode([
                'parameters' => [
                    '#kbcToken' => getenv('TEST_STORAGE_API_TOKEN'),
                    'kbcUrl' => getenv('TEST_STORAGE_API_URL'),
                    'orchestrationId' => $orchestrationId,
                    'waitUntilFinish' => true,
                    'notificationsEmails' => [$notificationEmail],
                ],
            ])
        );

        $runProcess = $this->createTestProcess();
        $runProcess->mustRun();

        $output = $runProcess->getOutput();
        $errorOutput = $runProcess->getErrorOutput();

        $this->assertEmpty($errorOutput);

        $jobs = $this->client->getOrchestrationJobs($orchestrationId);
        $this->assertCount(1, $jobs);

        $job = reset($jobs);

        $this->assertArrayHasKey('notificationsEmails', $job);
        $this->assertEquals([$notificationEmail], $job['notificationsEmails']);
    }
}
