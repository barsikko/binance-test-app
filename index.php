<?php 

error_reporting(E_ALL);
ini_set('display_errors', 'On'); 

require_once './vendor/autoload.php';

Dotenv\Dotenv::createImmutable(__DIR__)->load();

use App\BinanceApi;

$api = new BinanceApi(2,'test');

/* Метод на получение баланса */

$balance = $api->getAvailableBalance();

print_r($balance);

/* Метод на получение инфы обмена */

$exchangeInfo = $api->getExchangeInfo();

print_r($exchangeInfo);

/* Метод на получение истории операций */

$histTrades = $api->getHistoricalTrades('BTCUSDT', 3);

print_r($histTrades);
