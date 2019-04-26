<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;

abstract class BaseCommand extends Command
{

    protected $httpClient;

    protected $timeout = 12;

    public function getHttpClient()
    {
        if (!$this->httpClient) {
            $this->httpClient = $this->makeHttpClient([
                'verify' => false,
                'timeout' => $this->timeout,
                'defaults' => [
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:67.0) Gecko/20100101 Firefox/67.0',
                        'Accept' => 'application/json'
                    ]
                ]
            ]);
        }
        return $this->httpClient;
    }

    public function request($method, $url, array $params)
    {
        return $this->getHttpClient()->request($method, $url, $params);
    }

    public function multiRequest(array $promises)
    {
        $client = $this->getHttpClient();

        $responses = Promise\settle($promises)->wait();

        return $responses;
    }

    public function multiRequestJson(array $promises, $maxAttempts = 1, $attemptsCount = 0)
    {
        try {
            $responses = $this->multiRequest($promises);
            return array_map(function ($response) {
                return $this->getJsonFromResponse($response);
            }, $responses);
        } catch (\Exception $e) {
            if ($attemptsCount == $maxAttempts) {
                throw $e;
            }

            return $this->multiRequestJson($promises, $maxAttempts, $attemptsCount + 1);
        }
    }

    public function makeHttpClient($options = []): Client
    {
        return new Client($options);
    }

    public function getJsonFromResponse($response)
    {
        if (!isset($response['value'])) {
            throw $response['reason'];
        }
        $body = $response['value']->getBody();
        return json_decode($body->getContents(), true);
    }

    public function asyncRequest($method, $url, array $params = [])
    {
        return $this->getHttpClient()->requestAsync($method, $url, $params);
    }

    public function loadData($file)
    {
        $file = base_path('data/'.$file);
        $content = file_get_contents($file);

        return json_decode($content, true);
    }

}
