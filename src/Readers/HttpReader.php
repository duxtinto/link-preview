<?php

namespace Duxtinto\LinkPreview\Readers;

use Duxtinto\LinkPreview\Contracts\LinkInterface;
use Duxtinto\LinkPreview\Contracts\ReaderInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\TransferStats;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7;
use Illuminate\Support\Arr;

/**
 * Class HttpReader
 */
class HttpReader implements ReaderInterface
{
    /**
     * @var Client $client
     */
    private $client;

    /**
     * @var array $config
     */
    private $config;

    /**
     * @var CookieJar $jar
     */
    private $jar;

    /**
     * HttpReader constructor.
     * @param array|null $config
     */
    public function __construct($config = null)
    {
        $this->jar = new CookieJar();

        $this->config = $config ?: [
            'allow_redirects' => ['max' => 10],
            'cookies' => $this->jar,
            'connect_timeout' => 5,
            'headers' => [
                'User-Agent' => 'duxtinto/link-preview v2'
            ]
        ];
    }

    /**
     * @param int $timeout
     */
    public function setTimeout($timeout)
    {
        $this->config(['connect_timeout' => $timeout]);
    }

    /**
     * @param array $parameters
     */
    public function config(array $parameters)
    {
        foreach ($parameters as $key => $value) {
            $this->config[$key] = $value;
        }
    }

    /**
     * @return Client
     */
    public function getClient()
    {
        if (!$this->client) {
            $this->client = new Client();
        }

        return $this->client;
    }

    /**
     * @param Client $client
     */
    public function setClient($client)
    {
        $this->client = $client;
    }

    /**
     * @inheritdoc
     */
    public function readLink(LinkInterface $link)
    {
        $client = $this->getClient();

        try {
            $response = $client->request('GET', $link->getUrl(), array_merge($this->config, [
                'on_stats' => function (TransferStats $stats) use (&$link) {
                    $link->setEffectiveUrl($stats->getEffectiveUri());
                }
            ]));

            $contentType = $response->getHeader('Content-Type')[0];
            $parsedHeader = Psr7\parse_header($contentType);
            $content = Arr::has($parsedHeader, 0) && Arr::has($parsedHeader[0], 'charset')
                ? mb_convert_encoding($response->getBody(), 'UTF-8', Arr::get($parsedHeader[0], 'charset'))
                : $response->getBody();

            $link->setContent($content)
                ->setContentType($contentType);
        } catch (ConnectException $e) {
            $link->setContent(false)->setContentType(false);
        }

        return $link;
    }
}
