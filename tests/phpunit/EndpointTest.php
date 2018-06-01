<?php

declare(strict_types=1);

namespace Keboola\App\OrchestratorTrigger\Tests;

use GuzzleHttp\Client;
use Keboola\App\OrchestratorTrigger\OrchestratorEndpoint;
use Keboola\Component\UserException;
use PHPUnit\Framework\TestCase;

class EndpointTest extends TestCase
{
    public function testDetection(): void
    {
        $url = OrchestratorEndpoint::detect(
            getenv('TEST_STORAGE_API_TOKEN'),
            getenv('TEST_STORAGE_API_URL')
        );

        $this->assertNotEmpty($url);

        $guzzle = new Client();
        $response = $guzzle->get($url);

        $body = json_decode($response->getBody()->getContents(), true);

        $this->assertArrayHasKey('api', $body);
        $this->assertEquals(OrchestratorEndpoint::ORCHESTRATOR_COMPONENT_ID, $body['api']);
    }

    public function testDetectionTokenError(): void
    {
        try {
            OrchestratorEndpoint::detect(
                getenv('TEST_STORAGE_API_TOKEN'),
                md5(getenv('TEST_STORAGE_API_URL'))
            );

            $this->fail('Invalid url should produce user error');
        } catch (UserException $e) {
            $this->assertEquals('Cannot load KBC Component index', $e->getMessage());
        }
    }
}
