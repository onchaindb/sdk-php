# OnChainDB PHP SDK

A fluent PHP client for interacting with OnChainDB - a decentralized database built on Celestia.

## Installation

```bash
composer require onchaindb/sdk
```

## Requirements

- PHP 8.1 or higher
- Guzzle HTTP client (installed automatically)

## Quick Start

```php
<?php

use OnChainDB\OnChainDBClient;

$client = new OnChainDBClient(
    endpoint: 'https://your-onchaindb-endpoint.com',
    appId: 'your-app-id',
    appKey: 'your-app-key',
    userKey: 'optional-user-key' // Optional: enables Auto-Pay for reads/writes
);

// Find a single user
$user = $client->findUnique('users', ['email' => 'john@example.com']);

// Find multiple users
$activeUsers = $client->findMany('users', ['status' => 'active'], limit: 10);

// Count documents
$count = $client->countDocuments('users', ['status' => 'active']);
```

## QueryBuilder API

The QueryBuilder provides a fluent interface for constructing complex queries.

### Basic Queries

```php
$results = $client->queryBuilder()
    ->collection('users')
    ->whereField('status')->equals('active')
    ->whereField('age')->greaterThan(18)
    ->limit(10)
    ->offset(0)
    ->orderBy('createdAt', 'desc')
    ->execute();
```

### Field Selection

```php
// Select specific fields
$results = $client->queryBuilder()
    ->collection('users')
    ->selectFields(['id', 'name', 'email'])
    ->execute();

// Select with builder
$results = $client->queryBuilder()
    ->collection('users')
    ->select(fn($s) => $s
        ->fields(['id', 'name'])
        ->nested('profile', fn($n) => $n->fields(['avatar', 'bio']))
    )
    ->execute();
```

### Execute Unique

Returns the most recent record by metadata (updatedAt/createdAt):

```php
$latestRecord = $client->queryBuilder()
    ->collection('users')
    ->whereField('email')->equals('john@example.com')
    ->executeUnique();
```

## Condition Operators

### Comparison Operators

```php
->whereField('age')->equals(25)
->whereField('age')->notEquals(25)
->whereField('age')->greaterThan(18)
->whereField('age')->greaterThanOrEqual(18)
->whereField('age')->lessThan(65)
->whereField('age')->lessThanOrEqual(65)
->whereField('price')->between(10, 100)
```

### String Operators

```php
->whereField('name')->contains('john')
->whereField('name')->startsWith('J')
->whereField('name')->endsWith('son')
->whereField('email')->regExpMatches('/^[a-z]+@/')
->whereField('name')->includesCaseInsensitive('JOHN')
->whereField('name')->startsWithCaseInsensitive('J')
->whereField('name')->endsWithCaseInsensitive('SON')
```

### Array Operators

```php
->whereField('status')->in(['active', 'pending'])
->whereField('status')->notIn(['deleted', 'banned'])
```

### Existence & Null Operators

```php
->whereField('email')->exists()
->whereField('deletedAt')->notExists()
->whereField('middleName')->isNull()
->whereField('email')->isNotNull()
```

### Boolean Operators

```php
->whereField('verified')->isTrue()
->whereField('banned')->isFalse()
```

### IP Address Operators

```php
->whereField('ip')->isLocalIp()
->whereField('ip')->isExternalIp()
->whereField('ip')->inCountry('US')
->whereField('ip')->cidr('192.168.1.0/24')
```

### Special Operators

```php
->whereField('signature')->b64('base64encodedvalue')
->whereField('userId')->inDataset('premium_users')
```

## Complex Conditions

Use the `find()` method with `ConditionBuilder` for complex logical operations:

```php
use OnChainDB\Query\LogicalOperator;

$results = $client->queryBuilder()
    ->collection('users')
    ->find(fn($c) => $c->andGroup(fn() => [
        LogicalOperator::Condition($c->field('status')->equals('active')),
        $c->orGroup(fn() => [
            LogicalOperator::Condition($c->field('role')->equals('admin')),
            LogicalOperator::Condition($c->field('role')->equals('moderator')),
        ]),
    ]))
    ->execute();
```

### AND/OR/NOT Groups

```php
// AND group
->find(fn($c) => $c->andGroup(fn() => [
    LogicalOperator::Condition($c->field('age')->greaterThan(18)),
    LogicalOperator::Condition($c->field('verified')->isTrue()),
]))

// OR group
->find(fn($c) => $c->orGroup(fn() => [
    LogicalOperator::Condition($c->field('role')->equals('admin')),
    LogicalOperator::Condition($c->field('role')->equals('superadmin')),
]))

// NOT group
->find(fn($c) => $c->notGroup(fn() => [
    LogicalOperator::Condition($c->field('status')->equals('banned')),
]))
```

## Aggregations

The QueryBuilder supports aggregation operations:

```php
// Count all matching records
$count = $client->queryBuilder()
    ->collection('orders')
    ->whereField('status')->equals('completed')
    ->count();

// Sum a numeric field
$totalRevenue = $client->queryBuilder()
    ->collection('orders')
    ->whereField('status')->equals('completed')
    ->sumBy('amount');

// Calculate average
$avgOrderValue = $client->queryBuilder()
    ->collection('orders')
    ->avgBy('amount');

// Find maximum value
$highestOrder = $client->queryBuilder()
    ->collection('orders')
    ->maxBy('amount');

// Find minimum value
$lowestOrder = $client->queryBuilder()
    ->collection('orders')
    ->minBy('amount');

// Get distinct values
$uniqueCategories = $client->queryBuilder()
    ->collection('products')
    ->distinctBy('category');

// Count distinct values
$categoryCount = $client->queryBuilder()
    ->collection('products')
    ->countDistinct('category');
```

## GroupBy Aggregations

Perform aggregations grouped by a field:

```php
// Count by status
$countByStatus = $client->queryBuilder()
    ->collection('orders')
    ->groupBy('status')
    ->count();
// Returns: ['pending' => 10, 'completed' => 50, 'cancelled' => 5]

// Sum by category
$revenueByCategory = $client->queryBuilder()
    ->collection('orders')
    ->groupBy('category')
    ->sumBy('amount');

// Average by region
$avgSalaryByRegion = $client->queryBuilder()
    ->collection('employees')
    ->groupBy('region')
    ->avgBy('salary');

// Max by department
$maxSalaryByDept = $client->queryBuilder()
    ->collection('employees')
    ->groupBy('department')
    ->maxBy('salary');

// Min by department
$minSalaryByDept = $client->queryBuilder()
    ->collection('employees')
    ->groupBy('department')
    ->minBy('salary');

// Group by nested field
$ordersByCountry = $client->queryBuilder()
    ->collection('orders')
    ->groupBy('customer.country')
    ->count();
```

## Server-Side JOINs

Join related collections on the server:

### One-to-One JOIN

```php
$users = $client->queryBuilder()
    ->collection('users')
    ->joinOne('profile', 'profiles')
        ->onField('userId')->equals('$parent.id')
        ->selectFields(['avatar', 'bio'])
        ->build()
    ->execute();
```

### One-to-Many JOIN

```php
$users = $client->queryBuilder()
    ->collection('users')
    ->joinMany('orders', 'orders')
        ->onField('userId')->equals('$parent.id')
        ->selectAll()
        ->build()
    ->execute();
```

### Complex JOIN Conditions

```php
$users = $client->queryBuilder()
    ->collection('users')
    ->joinMany('recentOrders', 'orders')
        ->on(fn($c) => $c->andGroup(fn() => [
            LogicalOperator::Condition($c->field('userId')->equals('$parent.id')),
            LogicalOperator::Condition($c->field('status')->in(['pending', 'completed'])),
        ]))
        ->selectFields(['id', 'amount', 'status'])
        ->build()
    ->execute();
```

### Nested JOINs

```php
$users = $client->queryBuilder()
    ->collection('users')
    ->joinMany('orders', 'orders')
        ->onField('userId')->equals('$parent.id')
        ->joinOne('product', 'products')
            ->onField('id')->equals('$parent.productId')
            ->selectFields(['name', 'price'])
            ->build()
        ->selectAll()
        ->build()
    ->execute();
```

## Query Debugging

Get the raw query request for debugging:

```php
$query = $client->queryBuilder()
    ->collection('users')
    ->whereField('status')->equals('active')
    ->limit(10);

$rawQuery = $query->getQueryRequest();
print_r($rawQuery);
```

## Custom HTTP Client

Implement `HttpClientInterface` for custom HTTP handling:

```php
use OnChainDB\Http\HttpClientInterface;

class CustomHttpClient implements HttpClientInterface
{
    private array $headers;

    public function __construct(array $headers = [])
    {
        $this->headers = $headers;
    }

    public function post(string $url, array $data, array $headers = []): array
    {
        // Your custom implementation
        // Make sure to include $this->headers merged with $headers
    }

    public function get(string $url, array $headers = []): array
    {
        // Your custom implementation
        // Make sure to include $this->headers merged with $headers
    }
}

$client = new OnChainDBClient(
    endpoint: 'https://your-endpoint.com',
    appId: 'your-app',
    appKey: 'your-app-key',
    userKey: null,
    httpClient: new CustomHttpClient(['X-App-Key' => 'your-app-key'])
);
```

## Storing Data

The `store()` method waits for blockchain confirmation by default:

```php
// Store with automatic wait for confirmation (default behavior)
$result = $client->store(
    'users',
    [['id' => 'user_1', 'name' => 'Alice', 'email' => 'alice@example.com']],
    ['payment_tx_hash' => '...', 'payment_network' => 'mocha-4']
);

// Store without waiting (returns ticket_id immediately)
$result = $client->store(
    'users',
    [['id' => 'user_1', 'name' => 'Alice']],
    $paymentProof,
    waitForConfirmation: false
);

// Custom polling settings
$result = $client->store(
    'users',
    $data,
    $paymentProof,
    waitForConfirmation: true,
    pollIntervalMs: 1000,    // Poll every 1 second
    maxWaitTimeMs: 300000    // Wait up to 5 minutes
);
```

## Task Tracking

For async operations, you can manually track task completion:

```php
// Get task status
$taskInfo = $client->getTaskStatus($ticketId);
echo "Status: " . $taskInfo['status'];

// Wait for task to complete
$result = $client->waitForTaskCompletion(
    $ticketId,
    pollIntervalMs: 2000,   // Poll every 2 seconds
    maxWaitTimeMs: 600000   // Wait up to 10 minutes
);

if ($result['status'] === 'Completed') {
    echo "Transaction confirmed at height: " . $result['result']['celestia_height'];
}
```

## Exception Handling

```php
use OnChainDB\Exception\OnChainDBException;

try {
    $results = $client->queryBuilder()
        ->collection('users')
        ->execute();
} catch (OnChainDBException $e) {
    echo "OnChainDB error: " . $e->getMessage();
} catch (\RuntimeException $e) {
    // Task timeout or failure
    echo "Task error: " . $e->getMessage();
}
```

## Cost Estimation

```php
$quote = $client->getPricingQuote(
    collection: 'tweets',
    operationType: 'write',
    sizeKb: 50,
    monthlyVolumeKb: 10000  // Optional: for volume discounts
);

echo "Total cost: " . $quote['total_cost'] . " TIA\n";
echo "In utia: " . $quote['total_cost_utia'] . "\n";
```

## Collection Schema Management

Define and manage collection indexes using a schema-first approach:

### Create Collection

```php
$schema = [
    'name' => 'users',
    'fields' => [
        'email' => ['type' => 'string', 'index' => true],
        'age' => ['type' => 'number', 'index' => true],
        'status' => ['type' => 'string', 'index' => true, 'indexType' => 'hash'],
        'bio' => ['type' => 'string', 'index' => true, 'indexType' => 'fulltext'],
        'address.city' => ['type' => 'string', 'index' => true], // Nested field
    ],
    'useBaseFields' => true, // Auto-index id, createdAt, updatedAt, deletedAt
];

$result = $client->createCollection($schema);
// ['collection' => 'users', 'indexes' => [...], 'success' => true, 'warnings' => []]
```

### Sync Collection

Update indexes to match schema (creates new, removes old):

```php
$updatedSchema = [
    'name' => 'users',
    'fields' => [
        'email' => ['type' => 'string', 'index' => true],
        'username' => ['type' => 'string', 'index' => true], // New index
        // 'age' index removed
    ],
];

$result = $client->syncCollection($updatedSchema);
// ['collection' => 'users', 'created' => [...], 'removed' => [...], 'unchanged' => [...], 'success' => true]
```

### Field Types & Index Types

| Field Type | Index Types |
|------------|-------------|
| `string` | `string` (default), `hash`, `fulltext` |
| `number` | `number` (default) |
| `boolean` | `boolean` (default) |
| `date` | `date` (default) |
| `object` | `string` |
| `array` | `string` |

### Read Pricing on Fields

```php
$schema = [
    'name' => 'premium_content',
    'fields' => [
        'title' => ['type' => 'string', 'index' => true],
        'content' => [
            'type' => 'string',
            'index' => true,
            'readPricing' => [
                'pricePerAccess' => 0.001, // 0.001 TIA per read
            ],
        ],
    ],
];
```

## Materialized Views

Create pre-computed views for complex queries:

### Create View

```php
$view = $client->createView(
    'active_users_summary',
    ['users', 'orders'], // Source collections
    [
        'filter' => ['status' => 'active'],
        'groupBy' => 'region',
        'aggregate' => ['totalOrders' => ['$count' => 'orders']],
    ]
);
```

### List Views

```php
$views = $client->listViews();
// [['name' => 'active_users_summary', 'source_collections' => [...], 'created_at' => '...']]
```

### Get View Details

```php
$view = $client->getView('active_users_summary');
// ['name' => '...', 'source_collections' => [...], 'query' => [...], 'created_at' => '...']
```

### Refresh View

```php
$client->refreshView('active_users_summary');
```

### Delete View

```php
$client->deleteView('active_users_summary');
```

## API Reference

### OnChainDBClient

**Constructor:**

```php
new OnChainDBClient(
    string $endpoint,     // OnChainDB server endpoint URL
    string $appId,        // Your application ID
    string $appKey,       // Your application key (for authentication)
    ?string $userKey,     // Optional user key (enables Auto-Pay)
    ?HttpClientInterface $httpClient  // Optional custom HTTP client
)
```

**Methods:**

| Method | Description |
|--------|-------------|
| `queryBuilder()` | Create a new QueryBuilder instance |
| `findUnique(collection, where)` | Find single record (latest by metadata) |
| `findMany(collection, where, limit, offset)` | Find multiple records |
| `countDocuments(collection, where)` | Count matching records |
| `store(collection, data, paymentProof, waitForConfirmation, pollIntervalMs, maxWaitTimeMs)` | Store documents (waits for blockchain confirmation by default) |
| `getTaskStatus(ticketId)` | Get task status by ticket ID |
| `waitForTaskCompletion(ticketId, pollIntervalMs, maxWaitTimeMs)` | Poll until task completes |
| `getPricingQuote(collection, operationType, sizeKb, monthlyVolumeKb)` | Get pricing quote |
| `createCollection(schema)` | Create collection with indexes |
| `syncCollection(schema)` | Sync indexes to match schema |
| `createView(name, sourceCollections, query)` | Create materialized view |
| `listViews()` | List all views |
| `getView(name)` | Get view details |
| `deleteView(name)` | Delete a view |
| `refreshView(name)` | Refresh view data |
| `getEndpoint()` | Get the configured endpoint URL |
| `getAppId()` | Get the configured app ID |
| `getAppKey()` | Get the configured app key |
| `getUserKey()` | Get the configured user key (or null) |

### QueryBuilder

| Method | Description |
|--------|-------------|
| `collection(name)` | Set target collection |
| `whereField(field)` | Add field condition |
| `find(builderFn)` | Add complex conditions |
| `selectFields(fields)` | Select specific fields |
| `selectAll()` | Select all fields |
| `select(builderFn)` | Configure selection |
| `limit(n)` | Limit results |
| `offset(n)` | Skip results |
| `orderBy(field, direction)` | Sort results |
| `includeHistory(bool)` | Include historical records |
| `joinOne(alias, model)` | One-to-one JOIN |
| `joinMany(alias, model)` | One-to-many JOIN |
| `execute()` | Execute query |
| `executeUnique()` | Execute and return latest record |
| `count()` | Count matching records |
| `sumBy(field)` | Sum numeric field |
| `avgBy(field)` | Average of field |
| `maxBy(field)` | Maximum value |
| `minBy(field)` | Minimum value |
| `distinctBy(field)` | Distinct values |
| `countDistinct(field)` | Count distinct values |
| `groupBy(field)` | Start grouped aggregation |
| `getQueryRequest()` | Get raw query object |
| `clone()` | Clone the builder |

### GroupByQueryBuilder

| Method | Description |
|--------|-------------|
| `count()` | Count per group |
| `sumBy(field)` | Sum per group |
| `avgBy(field)` | Average per group |
| `maxBy(field)` | Maximum per group |
| `minBy(field)` | Minimum per group |

## License

MIT
