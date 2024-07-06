<?php

namespace Eppo\Tests;

use Eppo\APIRequestWrapper;
use Eppo\Exception\HttpRequestException;
use Eppo\Exception\InvalidApiKeyException;
use Exception;
use Http\Discovery\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
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
        // Note: this test also verifies that the correct endpoint is called via mock expectations.
        $http = $this->getRedirectingClientMock();
        $api = new APIRequestWrapper(
            'APIKEY',
            [],
            $http,
            new Psr17Factory()
        );
        $api->getUFC();
    }

    public function testUnauthorizedClient(): void
    {
        $http = $this->getHttpClientMock(RFC7235::UNAUTHORIZED, '');
        $api = new APIRequestWrapper(
            '',
            [],
            $http,
            new Psr17Factory()
        );

        $this->expectException(InvalidApiKeyException::class);

        $result = $api->getUFC();

        $this->assertTrue($api->isUnauthorized);
    }

    public function testThrowsHttpError(): void
    {
        $http = $this->getHttpClientMock(RFC7231::INTERNAL_SERVER_ERROR, '');
        $api = new APIRequestWrapper(
            '',
            [],
            $http,
            new Psr17Factory()
        );

        $this->expectException(HttpRequestException::class);
        $this->expectExceptionCode(RFC7231::INTERNAL_SERVER_ERROR);

        $api->getUFC();
    }

    public function testRecoverableHttpError(): void
    {
        $this->assertStatusRecoverable(true, RFC7231::CONFLICT);
        $this->assertStatusRecoverable(true, RFC7231::REQUEST_TIMEOUT);
        $this->assertStatusRecoverable(true, RFC7231::BAD_GATEWAY);
        $this->assertStatusRecoverable(true, RFC7231::INTERNAL_SERVER_ERROR);
    }

    public function testUnrecoverableHttpError(): void
    {
        $this->assertStatusRecoverable(false, RFC7235::UNAUTHORIZED);
        $this->assertStatusRecoverable(false, RFC7231::NOT_FOUND);
    }

    public function testResourceFetching(): void
    {
        $http = $this->getRespondingHttpClientMock(RFC7231::OK, '');
        $api = new APIRequestWrapper(
            '',
            [],
            $http,
            new Psr17Factory()
        );

        $response = $api->getUFC();
        $this->assertEquals('UFC', $response);
        $response = $api->getBandits();
        $this->assertEquals('BANDIT', $response);
    }

    private function assertStatusRecoverable(bool $recoverable, int $status): void
    {
        $http = $this->getHttpClientMock($status, '');
        $api = new APIRequestWrapper(
            '',
            [],
            $http,
            new Psr17Factory()
        );

        try {
            $api->getUFC();
            $this->fail('Exception not thrown');
        } catch (HttpRequestException $e) {
            $this->assertEquals($recoverable, $e->isRecoverable);
        } catch (InvalidApiKeyException $e) {
            $this->assertEquals('', $e->getMessage());
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

    private function getRespondingHttpClientMock(int $statusCode): ClientInterface
    {
        $httpClientMock = $this->getMockBuilder(ClientInterface::class)->setConstructorArgs([])->getMock();

        $httpClientMock->expects($this->exactly(2))
            ->method('sendRequest')
            ->willReturnCallback(fn($arg) => $this->httpCallback($statusCode, $arg));

        return $httpClientMock;
    }

    /**
     * @throws Exception
     */
    public function httpCallback(int $statusCode, RequestInterface $request): Response
    {
        if (str_ends_with($request->getUri()->getPath(), 'config')) {
            return $this->returnResponse($statusCode, 'UFC');
        } elseif (str_ends_with($request->getUri()->getPath(), 'bandits')) {
            return $this->returnResponse($statusCode, 'BANDIT');
        } else {
            throw new Exception('Unexpected http request');
        }
    }

    private function returnResponse(int $statusCode, string $body): Response
    {
        $stream = new Stream($body);

        return new Response(
            statusCode: $statusCode,
            stream: $stream
        );
    }

    private function getRedirectingClientMock(): ClientInterface
    {
        $httpClientMock = $this->getMockBuilder(ClientInterface::class)->setConstructorArgs([
        ])->getMock();

        $redirectLocation = 'https://geteppo.com/api/flag-config/v1/config?apiKey=APIKEY';
        $redirectHeaders = new Headers();
        $redirectHeaders->setHeader(new Header('Location', $redirectLocation));

        $redirectResponse = new Response(statusCode: RFC7231::MOVED_PERMANENTLY, headers: $redirectHeaders);
        $resourceUri = 'https://fscdn.eppo.cloud/api/flag-config/v1/config?apiKey=APIKEY';

        $httpClientMock->expects($this->exactly(2))
            ->method('sendRequest')
            ->with(
                $this->callback(function ($request) use ($resourceUri, $redirectLocation) {
                    $uri = $request->getUri()->__toString();

                    $this->assertContains(
                        $uri,
                        [$resourceUri, $redirectLocation]
                    );

                    return true;
                })
            )
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
