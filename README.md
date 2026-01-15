# loteria-api/sdk

Official PHP SDK for [Loteria API](https://loteria-api.com).

## Requirements

- PHP 8.1 or higher
- Guzzle HTTP client

## Installation

```bash
composer require loteria-api/sdk
```

## Quick Start

```php
use LoteriaApi\LoteriaApi;

$api = new LoteriaApi('lat_xxx...'); // Get your API key at https://loteria-api.com

// Get latest Euromillones result
$result = $api->results->getLatest('euromillones');
print_r($result['data']['combination']); // [4, 15, 23, 38, 42]
print_r($result['data']['resultData']['estrellas']); // [3, 9]
```

## Usage Examples

### Get Latest Results

```php
// Latest result for a specific game
$euromillones = $api->results->getLatest('euromillones');
$primitiva = $api->results->getLatest('primitiva');
$bonoloto = $api->results->getLatest('bonoloto');

// Latest results for ALL games
$all = $api->results->getLatestAll();
print_r($all['data']['euromillones']['combination']);
print_r($all['data']['primitiva']['combination']);
```

### Check Your Numbers

```php
$result = $api->results->checkNumbers(
    gameType: 'euromillones',
    numbers: [4, 15, 23, 38, 42],
    extraNumbers: [3, 11] // Stars for Euromillones
);

if ($result['data']['isWinner']) {
    echo "You won! Prize: " . $result['data']['prize']['formattedPrize'] . "\n";
    echo "Category: " . $result['data']['prize']['categoryName'] . "\n";
} else {
    echo "No luck. You matched " . $result['data']['mainNumbersMatched'] . " numbers.\n";
}
```

### Query Historical Results

```php
// Results from a date range
$results = $api->results->getByDateRange(
    gameType: 'euromillones',
    from: '2024-01-01',
    to: '2024-12-31',
    page: 1,
    limit: 10
);

// Results from a specific date
$dateResults = $api->results->getByDate('primitiva', '2024-06-15');

// Filter by minimum jackpot
$bigJackpots = $api->results->listByGame('euromillones', [
    'minJackpot' => 100000000, // 100 million
    'year' => 2024
]);
```

### Get Upcoming Draws

```php
// All upcoming draws
$upcoming = $api->draws->listUpcoming();

// Next draw for each game
$next = $api->draws->getNextAll();
echo "Next Euromillones: " . $next['data']['euromillones']['drawDate'] . "\n";
echo "Jackpot: " . $next['data']['euromillones']['jackpotFormatted'] . "\n";

// Upcoming draws for a specific game
$euromillonesDraws = $api->draws->listUpcomingByGame('euromillones');
```

## Available Game Types

| Slug | Name |
|------|------|
| `bonoloto` | Bonoloto |
| `euromillones` | Euromillones |
| `primitiva` | La Primitiva |
| `gordo` | El Gordo |
| `nacional` | Loteria Nacional |
| `eurodreams` | EuroDreams |
| `quiniela` | La Quiniela |
| `quinigol` | Quinigol |
| `lototurf` | Lototurf |
| `quintuple` | Quintuple Plus |

You can use string literals or the `GameType` enum:

```php
use LoteriaApi\Enums\GameType;

$result = $api->results->getLatest(GameType::EUROMILLONES->value);
// or
$result = $api->results->getLatest('euromillones');
```

## Error Handling

```php
use LoteriaApi\LoteriaApi;
use LoteriaApi\Exceptions\LoteriaApiException;

try {
    $result = $api->results->getLatest('euromillones');
} catch (LoteriaApiException $e) {
    echo "API Error: " . $e->getErrorCode() . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Status: " . $e->getStatusCode() . "\n";

    if ($e->getErrorCode() === 'RATE_LIMIT_EXCEEDED') {
        // Wait and retry
    }
    if ($e->getErrorCode() === 'TOKEN_LIMIT_EXCEEDED') {
        // Upgrade plan or wait for reset
    }
}
```

## Configuration

```php
$api = new LoteriaApi(
    apiKey: 'lat_xxx...',                              // Required
    baseUrl: 'https://custom.url',                     // Optional (default: https://api.loteria-api.com/api/v1)
    timeout: 60                                        // Optional (default: 30 seconds)
);
```

## License

MIT
