<?php 

namespace App;

use App\Account;
use App\Utils;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Uri;

class BinanceApi
{
    private Client $httpClient;
    private const BASE_URI = 'https://fapi.binance.com';
    private const DEFAULT_ASSET = 'USDT';

    private Account $account;

    public function __construct($id, $name)
    {
       $this->account = new Account($id, $name, $_ENV['API_KEY'], $_ENV['SECRET_KEY']); 

       $this->httpClient = new Client([
        'base_uri' => self::BASE_URI,
        'timeout' => 5.0,
    ]);
   }

   public function testRequest()
   {
       $ma = new \BinanceRequest\Account(65, 'test', $_ENV['API_KEY'], $_ENV['SECRET_KEY']);

       $req = \BinanceRequest\Request::getBinanceRequest();

       $res = $req->getAccountInfo($ma)->wait();

       print_r($res['positions'][0]);

       foreach ($res['positions'] as $position) {
           if ($position['positionAmt'] > 0) {
               print_r($position);
           }
       }
   }


private function getSignature(Account $account, string $queryString): string {
    return hash_hmac("sha256", $queryString, $account->apiSecret);
}

private function getTimestamp(): int {
    return floor(microtime(true) * 1000);
}

private function getRequest(string $method, string $url, array $options): \GuzzleHttp\Psr7\Request {
    $params = $options['params'] ?? [];
    /** @var Account|null $account */
    $account = $options['account'] ?? null;

    $params = array_map(function ($param) {
        if (gettype($param) === "boolean") {
            return $param === true ? "true" : "false";
        }
        return $param;
    }, $params);

    $params['timestamp'] = $this->getTimestamp();

    if ($account) {
        $params['signature'] = $this->getSignature($account, http_build_query($params));
    }

    $uri = Uri::withQueryValues(new Uri(self::BASE_URI . $url), $params);

    $request = new \GuzzleHttp\Psr7\Request($method, $uri);

    if ($account) {
        $request = $request->withHeader('X-MBX-APIKEY', $account->apiKey);
    }

    return $request;
}

public function requestAsync(string $method, string $url, array $options = []): PromiseInterface 
{
    $request = $this->getRequest($method, $url, $options);

    return $this->httpClient->sendAsync($request)
    ->then(function(\GuzzleHttp\Psr7\Response $response) {
        return json_decode($response->getBody()->getContents(), true);
    });
}

private function getAccountBalance($asset): PromiseInterface 
{
    return $this->requestAsync("get", "/fapi/v2/balance", [
        'account' => $this->account,
    ])->then(function ($response) use ($asset) {
        return Utils::arrayFind(
            $response,
            fn($accountInfo) => $accountInfo['asset'] === $asset
        );
    });
}


public function getAvailableBalance(string $asset = self::DEFAULT_ASSET)
{
    return $this->getAccountBalance($asset)->then(function ($resp){
        return $resp['availableBalance'];
    })->wait();
}


public function getHistoricalTrades(string $symbol, int $limit = null, int $fromId = null)
{
    $params = [
        'symbol' => $symbol,
        'limit' => $limit,
        'fromId' => $fromId
    ];

    return $this->makeRequest("get", "/fapi/v1/historicalTrades", $params)->wait();
}

public function getExchangeInfo()
{
    return $this->requestAsync("get", "/fapi/v1/exchangeInfo")->then(function ($response) {
        return $response["symbols"];
    })->wait();
}


private function makeRequest($method, $address, $params)
{
    return $this->requestAsync($method, $address, [
        'params' => $params,
        'account' => $this->account,
    ]);
}

}

