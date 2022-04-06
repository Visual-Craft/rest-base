<?php

declare(strict_types=1);

namespace VisualCraft\RestBaseBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class DeserializerTest extends WebTestCase
{
    public function testDeserializer(): void
    {
        $client = static::createClient();
        $encodedData = json_encode(['field1' => 'field1', 'field2' => 'val2', 'field3' => 'val3']);
        $client->request(
            'POST',
            '/api/process-request',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $encodedData
        );
        $response = $client->getResponse();

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString($encodedData, $response->getContent());
    }
}
