<?php

namespace Eppo\Tests;

use Eppo\APIRequestWrapper;
use Eppo\Exception\HttpRequestException;
use Http\Discovery\Psr17Factory;
use Http\Mock\Client;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use PsrMock\Psr7\Collections\Headers;
use PsrMock\Psr7\Entities\Header;
use PsrMock\Psr7\Response;
use PsrMock\Psr7\Stream;
use Teapot\StatusCode\RFC\RFC7231;
use Teapot\StatusCode\RFC\RFC7235;

class APIRequestWrapperTest extends TestCase
{
    public function testApiFollowsRedirects(): void
    {
        $http = $this->getRedirectingClientMock();
        $api = new APIRequestWrapper(
            'APIKEY', [], $http, new Psr17Factory()
        );
        $api->get();
    }

    /**
     * @throws ClientExceptionInterface
     */
    public function testUnauthorizedClient(): void
    {
        $http = $this->getHttpClientMock(RFC7235::UNAUTHORIZED, '');
        $api = new APIRequestWrapper(
            '', [], $http, new Psr17Factory()
        );

        $this->expectException(HttpRequestException::class);

        $result = $api->get();

        $this->assertTrue($api->isUnauthorized);
    }

    /**
     * @throws ClientExceptionInterface
     */
    public function testRecoverableHttpError(): void
    {
        $this->assertStatusRecoverable(true, RFC7231::CONFLICT);
        $this->assertStatusRecoverable(true, RFC7231::REQUEST_TIMEOUT);
        $this->assertStatusRecoverable(true, RFC7231::BAD_GATEWAY);
        $this->assertStatusRecoverable(true, RFC7231::INTERNAL_SERVER_ERROR);
    }

    /**
     * @throws ClientExceptionInterface
     */
    public function testUnrecoverableHttpError(): void
    {
        $this->assertStatusRecoverable(false, RFC7235::UNAUTHORIZED);
        $this->assertStatusRecoverable(false, RFC7231::NOT_FOUND);
    }

    /**
     * @throws ClientExceptionInterface
     */
    private function assertStatusRecoverable(bool $recoverable, int $status): void
    {
        $http = $this->getHttpClientMock($status, '');
        $api = new APIRequestWrapper(
            '', [], $http, new Psr17Factory()
        );

        try {
            $api->get();
            $this->fail('Exception not thrown');
        } catch (HttpRequestException $e) {
            $this->assertEquals($recoverable, $e->isRecoverable);
        }
    }


    private function getHttpClientMock(int $statusCode, string $body): ClientInterface
    {

        $httpClientMock = $this->getMockBuilder(ClientInterface::class)->setConstructorArgs([
        ])->getMock();

        $stream = new Stream($body);

        $mockResponse = new Response(
            statusCode: $statusCode,
            stream: $stream
        );

        $httpClientMock->expects($this->any())
            ->method('sendRequest')
            ->willReturn($mockResponse);

        return $httpClientMock;
    }

    private function getRedirectingClientMock(): ClientInterface
    {
        $httpClientMock = $this->getMockBuilder(ClientInterface::class)->setConstructorArgs([
        ])->getMock();

        $redirectLocation = 'https://geteppo.com/api/randomized_assignment/v3/config?apiKey=APIKEY';
        $redirectHeaders = new Headers();
        $redirectHeaders->setHeader(new Header('Location', $redirectLocation));

        $redirectResponse = new Response(statusCode: RFC7231::MOVED_PERMANENTLY, headers: $redirectHeaders);
        $resourceUri = 'https://fscdn.eppo.cloud/api/randomized_assignment/v3/config?apiKey=APIKEY';

        $httpClientMock->expects($this->exactly(2))
            ->method('sendRequest')
            ->with($this->callback(function ($request) use ($resourceUri, $redirectLocation) {
                $uri = $request->getUri()->__toString();

                $this->assertContains($uri,
                    [$resourceUri, $redirectLocation]);

                return true;
            }))
            ->willReturnCallback(function ($request) use ($resourceUri, $redirectResponse) {
                $mockResponse = new Response(
                    statusCode: RFC7231::OK,
                );
                $uri = $request->getUri()->__toString();
                return ($uri == $resourceUri ? $redirectResponse : $mockResponse);
            });

        return $httpClientMock;
    }

}
