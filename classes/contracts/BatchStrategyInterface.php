<?php namespace Pensoft\AutoTranslation\Classes\Contracts;

/**
 * Batch Strategy Interface
 *
 * Contract for implementing batch translation strategies.
 * Different providers may have different batch limits and requirements.
 */
interface BatchStrategyInterface
{
    /**
     * Get maximum batch size supported by the provider
     */
    public function getMaxBatchSize(): int;

    /**
     * Split items into batches based on provider limits
     */
    public function createBatches(array $items): array;

    /**
     * Process a single batch through the translation provider
     */
    public function processBatch(array $batch, string $sourceLang, string $targetLang, array $options = []): array;

    /**
     * Validate that batch size is within limits
     */
    public function isValidBatchSize(int $size): bool;
}