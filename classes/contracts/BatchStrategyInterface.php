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
     *
     * @return int Maximum number of items per batch
     */
    public function getMaxBatchSize();

    /**
     * Split items into batches based on provider limits
     *
     * @param array $items Items to batch
     * @return array Array of batches
     */
    public function createBatches(array $items);

    /**
     * Process a single batch through the translation provider
     *
     * @param array $batch Batch of items to translate
     * @param string $sourceLang Source language code
     * @param string $targetLang Target language code
     * @param array $options Translation options
     * @return array Translated results
     * @throws \Exception If batch translation fails
     */
    public function processBatch(array $batch, $sourceLang, $targetLang, array $options = []);

    /**
     * Validate that batch size is within limits
     *
     * @param int $size Batch size to validate
     * @return bool True if valid
     */
    public function isValidBatchSize($size);
}
