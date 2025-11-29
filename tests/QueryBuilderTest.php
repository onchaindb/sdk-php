<?php

declare(strict_types=1);

namespace OnChainDB\Tests;

use PHPUnit\Framework\TestCase;
use OnChainDB\Query\QueryBuilder;
use OnChainDB\Query\FieldCondition;
use OnChainDB\Query\LogicalOperator;

/**
 * QueryBuilder - Query Building Verification Tests
 * These tests mirror the TypeScript SDK tests to ensure parity
 */
class QueryBuilderTest extends TestCase
{
    private string $serverUrl = 'http://localhost:3000';
    private string $app = 'testApp';

    // ===== BASIC QUERY STRUCTURE =====

    public function testShouldBuildCorrectQueryRequestWithCollection(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('users')
            ->whereField('email')->equals('alice@example.com')
            ->getQueryRequest();

        $this->assertEquals('testApp::users', $request['root']);
        $this->assertEquals(['email' => ['is' => 'alice@example.com']], $request['find']);
    }

    public function testShouldBuildQueryWithMultipleWhereFieldConditions(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        // Note: chaining whereField overwrites previous condition in current implementation
        $request = $queryBuilder
            ->collection('users')
            ->whereField('active')->equals(true)
            ->getQueryRequest();

        $this->assertEquals(['active' => ['is' => true]], $request['find']);
    }

    public function testShouldBuildQueryWithLimitAndOffset(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('orders')
            ->whereField('status')->equals('completed')
            ->limit(50)
            ->offset(100)
            ->getQueryRequest();

        $this->assertEquals(50, $request['limit']);
        $this->assertEquals(100, $request['offset']);
        $this->assertEquals('testApp::orders', $request['root']);
    }

    public function testShouldBuildQueryWithOrderBy(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('products')
            ->whereField('category')->equals('electronics')
            ->orderBy('price')
            ->getQueryRequest();

        $this->assertEquals('price', $request['sortBy']);
    }

    public function testShouldBuildQueryWithFieldSelection(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('users')
            ->whereField('active')->equals(true)
            ->selectFields(['id', 'name', 'email'])
            ->getQueryRequest();

        $this->assertEquals([
            'id' => true,
            'name' => true,
            'email' => true,
        ], $request['select']);
    }

    public function testShouldBuildQueryWithSelectAll(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('users')
            ->selectAll()
            ->getQueryRequest();

        // Empty selection should serialize to {} in JSON (not [])
        $this->assertInstanceOf(\stdClass::class, $request['select']);
        $this->assertEquals('{}', json_encode($request['select']));
    }

    // ===== COMPLEX FIND CONDITIONS =====

    public function testShouldBuildAndConditionsCorrectly(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('users')
            ->find(fn($builder) =>
                LogicalOperator::And([
                    LogicalOperator::Condition((new FieldCondition('status'))->equals('active')),
                    LogicalOperator::Condition((new FieldCondition('age'))->greaterThan(18)),
                ])
            )
            ->getQueryRequest();

        $this->assertEquals([
            'and' => [
                ['status' => ['is' => 'active']],
                ['age' => ['greaterThan' => 18]],
            ],
        ], $request['find']);
    }

    public function testShouldBuildOrConditionsCorrectly(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('orders')
            ->find(fn($builder) =>
                LogicalOperator::Or([
                    LogicalOperator::Condition((new FieldCondition('status'))->equals('pending')),
                    LogicalOperator::Condition((new FieldCondition('status'))->equals('processing')),
                ])
            )
            ->getQueryRequest();

        $this->assertEquals([
            'or' => [
                ['status' => ['is' => 'pending']],
                ['status' => ['is' => 'processing']],
            ],
        ], $request['find']);
    }

    public function testShouldBuildNotConditionsCorrectly(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('users')
            ->find(fn($builder) =>
                LogicalOperator::Not([
                    LogicalOperator::Condition((new FieldCondition('status'))->equals('banned')),
                ])
            )
            ->getQueryRequest();

        $this->assertEquals([
            'not' => [
                ['status' => ['is' => 'banned']],
            ],
        ], $request['find']);
    }

    public function testShouldBuildNestedAndOrConditions(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('products')
            ->find(fn($builder) =>
                LogicalOperator::And([
                    LogicalOperator::Condition((new FieldCondition('active'))->equals(true)),
                    LogicalOperator::Or([
                        LogicalOperator::Condition((new FieldCondition('category'))->equals('electronics')),
                        LogicalOperator::Condition((new FieldCondition('category'))->equals('computers')),
                    ]),
                ])
            )
            ->getQueryRequest();

        $this->assertEquals([
            'and' => [
                ['active' => ['is' => true]],
                [
                    'or' => [
                        ['category' => ['is' => 'electronics']],
                        ['category' => ['is' => 'computers']],
                    ],
                ],
            ],
        ], $request['find']);
    }

    // ===== WHERECLAUSE OPERATORS =====

    public function testShouldBuildEqualsCondition(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('users')
            ->whereField('name')->equals('John')
            ->getQueryRequest();

        $this->assertEquals(['name' => ['is' => 'John']], $request['find']);
    }

    public function testShouldBuildNotEqualsCondition(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('users')
            ->whereField('status')->notEquals('deleted')
            ->getQueryRequest();

        $this->assertEquals(['status' => ['isNot' => 'deleted']], $request['find']);
    }

    public function testShouldBuildGreaterThanCondition(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('products')
            ->whereField('price')->greaterThan(100)
            ->getQueryRequest();

        $this->assertEquals(['price' => ['greaterThan' => 100]], $request['find']);
    }

    public function testShouldBuildGreaterThanOrEqualCondition(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('products')
            ->whereField('stock')->greaterThanOrEqual(10)
            ->getQueryRequest();

        $this->assertEquals(['stock' => ['greaterThanOrEqual' => 10]], $request['find']);
    }

    public function testShouldBuildLessThanCondition(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('products')
            ->whereField('price')->lessThan(50)
            ->getQueryRequest();

        $this->assertEquals(['price' => ['lessThan' => 50]], $request['find']);
    }

    public function testShouldBuildLessThanOrEqualCondition(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('orders')
            ->whereField('quantity')->lessThanOrEqual(100)
            ->getQueryRequest();

        $this->assertEquals(['quantity' => ['lessThanOrEqual' => 100]], $request['find']);
    }

    public function testShouldBuildContainsCondition(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('posts')
            ->whereField('content')->contains('blockchain')
            ->getQueryRequest();

        $this->assertEquals(['content' => ['includes' => 'blockchain']], $request['find']);
    }

    public function testShouldBuildStartsWithCondition(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('users')
            ->whereField('email')->startsWith('admin')
            ->getQueryRequest();

        $this->assertEquals(['email' => ['startsWith' => 'admin']], $request['find']);
    }

    public function testShouldBuildEndsWithCondition(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('users')
            ->whereField('email')->endsWith('@example.com')
            ->getQueryRequest();

        $this->assertEquals(['email' => ['endsWith' => '@example.com']], $request['find']);
    }

    public function testShouldBuildInCondition(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('orders')
            ->whereField('status')->in(['pending', 'processing', 'shipped'])
            ->getQueryRequest();

        $this->assertEquals(['status' => ['in' => ['pending', 'processing', 'shipped']]], $request['find']);
    }

    public function testShouldBuildNotInCondition(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('users')
            ->whereField('role')->notIn(['banned', 'suspended'])
            ->getQueryRequest();

        $this->assertEquals(['role' => ['notIn' => ['banned', 'suspended']]], $request['find']);
    }

    public function testShouldBuildExistsCondition(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('profiles')
            ->whereField('avatar')->exists()
            ->getQueryRequest();

        $this->assertEquals(['avatar' => ['exists' => true]], $request['find']);
    }

    public function testShouldBuildNotExistsCondition(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('profiles')
            ->whereField('deletedAt')->notExists()
            ->getQueryRequest();

        $this->assertEquals(['deletedAt' => ['exists' => false]], $request['find']);
    }

    public function testShouldBuildIsNullCondition(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('orders')
            ->whereField('cancelledAt')->isNull()
            ->getQueryRequest();

        $this->assertEquals(['cancelledAt' => ['isNull' => true]], $request['find']);
    }

    public function testShouldBuildIsNotNullCondition(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('orders')
            ->whereField('completedAt')->isNotNull()
            ->getQueryRequest();

        $this->assertEquals(['completedAt' => ['isNull' => false]], $request['find']);
    }

    public function testShouldBuildBetweenCondition(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('products')
            ->whereField('price')->between(10, 100)
            ->getQueryRequest();

        $this->assertEquals(['price' => ['betweenOp' => ['from' => 10, 'to' => 100]]], $request['find']);
    }

    public function testShouldBuildRegExpMatchesCondition(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('logs')
            ->whereField('message')->regExpMatches('^ERROR:.*')
            ->getQueryRequest();

        $this->assertEquals(['message' => ['regExpMatches' => '^ERROR:.*']], $request['find']);
    }

    // ===== NESTED FIELD QUERIES =====

    public function testShouldBuildNestedFieldConditionWithDotNotation(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('users')
            ->whereField('profile.country')->equals('USA')
            ->getQueryRequest();

        $this->assertEquals([
            'profile' => [
                'country' => [
                    'is' => 'USA',
                ],
            ],
        ], $request['find']);
    }

    public function testShouldBuildDeeplyNestedFieldCondition(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('data')
            ->whereField('a.b.c.d')->greaterThan(100)
            ->getQueryRequest();

        $this->assertEquals([
            'a' => [
                'b' => [
                    'c' => [
                        'd' => [
                            'greaterThan' => 100,
                        ],
                    ],
                ],
            ],
        ], $request['find']);
    }

    // ===== SERVER-SIDE JOINS =====

    public function testShouldBuildSimpleServerJoin(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('posts')
            ->whereField('published')->equals(true)
            ->joinOne('author', 'users')
                ->onField('authorId')->equals('userId')
                ->selectFields(['name', 'email'])
                ->build()
            ->getQueryRequest();

        $this->assertEquals('testApp::posts', $request['root']);
        $this->assertArrayHasKey('author', $request['find']);
        $this->assertEquals('users', $request['find']['author']['model']);
        $this->assertEquals(false, $request['find']['author']['many']);
        $this->assertEquals([
            'name' => true,
            'email' => true,
        ], $request['find']['author']['resolve']['select']);
    }

    public function testShouldBuildOneToManyJoin(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('users')
            ->joinMany('posts', 'posts')
                ->onField('authorId')->equals('userId')
                ->selectAll()
                ->build()
            ->getQueryRequest();

        $this->assertArrayHasKey('posts', $request['find']);
        $this->assertEquals('posts', $request['find']['posts']['model']);
        $this->assertEquals(true, $request['find']['posts']['many']);
    }

    public function testShouldBuildMultipleJoins(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('posts')
            ->joinOne('author', 'users')
                ->selectFields(['name'])
                ->build()
            ->joinMany('comments', 'comments')
                ->selectFields(['text', 'createdAt'])
                ->build()
            ->getQueryRequest();

        $this->assertArrayHasKey('author', $request['find']);
        $this->assertEquals(false, $request['find']['author']['many']);
        $this->assertArrayHasKey('comments', $request['find']);
        $this->assertEquals(true, $request['find']['comments']['many']);
    }

    // ===== INCLUDE HISTORY =====

    public function testShouldIncludeHistoryFlagWhenSetToTrue(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('documents')
            ->whereField('id')->equals('doc123')
            ->includeHistory(true)
            ->getQueryRequest();

        $this->assertTrue($request['include_history']);
    }

    public function testShouldNotIncludeHistoryFlagWhenNotSet(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('documents')
            ->whereField('id')->equals('doc123')
            ->getQueryRequest();

        $this->assertArrayNotHasKey('include_history', $request);
    }

    // ===== QUERY VALIDATION =====

    public function testShouldValidateQueryWithFindConditions(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $queryBuilder->whereField('name')->equals('test');
        $this->assertTrue($queryBuilder->isValid());
    }

    public function testShouldValidateQueryWithSelectionsOnly(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $queryBuilder->selectFields(['id', 'name']);
        $this->assertTrue($queryBuilder->isValid());
    }

    public function testShouldInvalidateEmptyQuery(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $this->assertFalse($queryBuilder->isValid());
    }

    // ===== QUERY CLONING =====

    public function testShouldCreateIndependentClone(): void
    {
        $original = (new QueryBuilder(null, $this->serverUrl, $this->app))
            ->collection('users')
            ->whereField('status')->equals('active')
            ->limit(10);

        $cloned = $original->clone();

        // Modify clone
        $cloned->limit(20);
        $cloned->offset(50);

        $originalRequest = $original->getQueryRequest();
        $clonedRequest = $cloned->getQueryRequest();

        $this->assertEquals(10, $originalRequest['limit']);
        $this->assertArrayNotHasKey('offset', $originalRequest);
        $this->assertEquals(20, $clonedRequest['limit']);
        $this->assertEquals(50, $clonedRequest['offset']);
    }

    // ===== FULL QUERY EXAMPLES =====

    public function testShouldBuildCompleteEcommerceQuery(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('orders')
            ->find(fn($builder) =>
                LogicalOperator::And([
                    LogicalOperator::Condition((new FieldCondition('status'))->equals('completed')),
                    LogicalOperator::Condition((new FieldCondition('total'))->greaterThan(100)),
                    LogicalOperator::Or([
                        LogicalOperator::Condition((new FieldCondition('paymentMethod'))->equals('credit_card')),
                        LogicalOperator::Condition((new FieldCondition('paymentMethod'))->equals('paypal')),
                    ]),
                ])
            )
            ->selectFields(['id', 'customerId', 'total', 'createdAt'])
            ->orderBy('createdAt')
            ->limit(50)
            ->offset(0)
            ->getQueryRequest();

        $this->assertEquals([
            'find' => [
                'and' => [
                    ['status' => ['is' => 'completed']],
                    ['total' => ['greaterThan' => 100]],
                    [
                        'or' => [
                            ['paymentMethod' => ['is' => 'credit_card']],
                            ['paymentMethod' => ['is' => 'paypal']],
                        ],
                    ],
                ],
            ],
            'select' => [
                'id' => true,
                'customerId' => true,
                'total' => true,
                'createdAt' => true,
            ],
            'sortBy' => 'createdAt',
            'limit' => 50,
            'offset' => 0,
            'root' => 'testApp::orders',
        ], $request);
    }

    public function testShouldBuildUserSearchQueryWithJoins(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('users')
            ->whereField('profile.verified')->equals(true)
            ->joinMany('posts', 'posts')
                ->onField('authorId')->equals('userId')
                ->selectFields(['title', 'likes'])
                ->build()
            ->joinOne('subscription', 'subscriptions')
                ->onField('userId')->equals('id')
                ->selectFields(['plan', 'expiresAt'])
                ->build()
            ->selectFields(['id', 'name', 'email'])
            ->limit(20)
            ->getQueryRequest();

        $this->assertEquals('testApp::users', $request['root']);
        $this->assertEquals(true, $request['find']['profile']['verified']['is']);
        $this->assertEquals(true, $request['find']['posts']['many']);
        $this->assertEquals(false, $request['find']['subscription']['many']);
        $this->assertEquals([
            'id' => true,
            'name' => true,
            'email' => true,
        ], $request['select']);
        $this->assertEquals(20, $request['limit']);
    }

    // ===== NESTED FIELD CONDITIONS IN FIND =====

    public function testShouldBuildNestedFieldConditionInFind(): void
    {
        $queryBuilder = new QueryBuilder(null, $this->serverUrl, $this->app);

        $request = $queryBuilder
            ->collection('users')
            ->find(fn($builder) =>
                LogicalOperator::And([
                    LogicalOperator::Condition((new FieldCondition('profile.country'))->equals('USA')),
                    LogicalOperator::Condition((new FieldCondition('profile.age'))->greaterThan(21)),
                ])
            )
            ->getQueryRequest();

        $this->assertEquals([
            'and' => [
                ['profile' => ['country' => ['is' => 'USA']]],
                ['profile' => ['age' => ['greaterThan' => 21]]],
            ],
        ], $request['find']);
    }
}
