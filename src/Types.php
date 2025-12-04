<?php

declare(strict_types=1);

namespace OnChainDB;

/**
 * Type definitions for OnChainDB SDK.
 *
 * This file contains PHPDoc type definitions that provide IDE autocomplete
 * and static analysis support (PHPStan/Psalm).
 *
 * =============================================================================
 * X402 Payment Types
 * =============================================================================
 *
 * @phpstan-type X402PaymentRequirement array{
 *     kind: string,
 *     chain: string,
 *     network: string,
 *     asset: string,
 *     payTo: string,
 *     maxAmountRequired: string,
 *     resource: string,
 *     scheme: string,
 *     quoteId: string,
 *     expiresAt: string,
 *     extra?: array<string, mixed>
 * }
 *
 * @phpstan-type X402Quote array{
 *     paymentRequirements: list<X402PaymentRequirement>,
 *     error?: string
 * }
 *
 * =============================================================================
 * Pricing Types
 * =============================================================================
 *
 * @phpstan-type PricingQuoteRequest array{
 *     app_id: string,
 *     operation_type: string,
 *     size_kb: int,
 *     collection: string,
 *     monthly_volume_kb?: int,
 *     data?: mixed
 * }
 *
 * @phpstan-type CreatorPremium array{
 *     premium_total: float,
 *     premium_total_utia: int,
 *     premium_type: string,
 *     premium_amount: float,
 *     creator_revenue: float,
 *     creator_revenue_utia: int,
 *     platform_revenue: float,
 *     platform_revenue_utia: int,
 *     revenue_split: string
 * }
 *
 * @phpstan-type PricingQuoteResponse array{
 *     type: string,
 *     base_celestia_cost: float,
 *     base_celestia_cost_utia: int,
 *     broker_fee: float,
 *     broker_fee_utia: int,
 *     indexing_costs: array<string, float>,
 *     indexing_costs_utia: array<string, int>,
 *     base_total_cost: float,
 *     base_total_cost_utia: int,
 *     total_cost: float,
 *     total_cost_utia: int,
 *     indexed_fields_count: int,
 *     request: PricingQuoteRequest,
 *     monthly_volume_kb: int,
 *     currency: string,
 *     creator_premium?: CreatorPremium,
 *     price?: mixed
 * }
 *
 * =============================================================================
 * Collection Schema Types
 * =============================================================================
 *
 * @phpstan-type ReadPricing array{
 *     pricePerAccess?: float,
 *     pricePerKb?: float
 * }
 *
 * @phpstan-type SimpleFieldDefinition array{
 *     type: 'string'|'number'|'boolean'|'date'|'object'|'array',
 *     index?: bool,
 *     indexType?: 'btree'|'hash'|'fulltext'|'price',
 *     readPricing?: ReadPricing
 * }
 *
 * @phpstan-type SimpleCollectionSchema array{
 *     name: string,
 *     fields: array<string, SimpleFieldDefinition>,
 *     useBaseFields?: bool
 * }
 *
 * @phpstan-type IndexResult array{
 *     field: string,
 *     type: string,
 *     status: 'created'|'updated'|'failed',
 *     error?: string
 * }
 *
 * @phpstan-type CreateCollectionResult array{
 *     collection: string,
 *     indexes: list<IndexResult>,
 *     success: bool,
 *     warnings: list<string>
 * }
 *
 * @phpstan-type SyncIndexResult array{
 *     field: string,
 *     type: string
 * }
 *
 * @phpstan-type SyncCollectionResult array{
 *     collection: string,
 *     created: list<SyncIndexResult>,
 *     removed: list<SyncIndexResult>,
 *     unchanged: list<SyncIndexResult>,
 *     success: bool,
 *     errors: list<string>
 * }
 *
 * =============================================================================
 * Materialized Views Types
 * =============================================================================
 *
 * @phpstan-type MaterializedView array{
 *     name: string,
 *     source_collections: list<string>,
 *     query: array<string, mixed>,
 *     created_at?: string
 * }
 *
 * @phpstan-type ViewInfo array{
 *     name: string,
 *     source_collections: list<string>,
 *     created_at: string
 * }
 *
 * =============================================================================
 * Store/Query Types
 * =============================================================================
 *
 * @phpstan-type StoreItemResult array{
 *     celestia_height: int,
 *     blob_id: string,
 *     tx_hash: string
 * }
 *
 * @phpstan-type StoreResult array{
 *     results: list<StoreItemResult>,
 *     ticket_id?: string,
 *     status?: string
 * }
 *
 * @phpstan-type TaskInfo array{
 *     ticket_id: string,
 *     status: string|array{Failed: array{error: string}},
 *     created_at: string,
 *     updated_at: string,
 *     operation_type: string,
 *     user_address?: string,
 *     transaction_hash?: string,
 *     block_height?: int,
 *     result?: mixed,
 *     progress_log: list<string>
 * }
 *
 * @phpstan-type QueryResult array{
 *     records: list<array<string, mixed>>,
 *     total: int,
 *     page: int,
 *     limit: int
 * }
 *
 * @phpstan-type PaymentProof array{
 *     payment_tx_hash: string,
 *     amount_utia: int,
 *     user_address?: string,
 *     broker_address?: string
 * }
 */
class Types
{
    // This class exists only for the PHPDoc type definitions above.
    // The types can be referenced in other files using:
    // @param SimpleCollectionSchema $schema
    // @return CreateCollectionResult
}
