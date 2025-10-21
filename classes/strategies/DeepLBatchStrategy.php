<?php namespace Pensoft\AutoTranslation\Classes\Strategies;

use Pensoft\AutoTranslation\Classes\Contracts\BatchStrategyInterface;
use Pensoft\AutoTranslation\Classes\Contracts\TranslationProviderInterface;

/**
 * DeepL Batch Strategy
 *
 * Implements batching strategy for DeepL API.
 * DeepL supports up to 50 texts per API call.
 */
class DeepLBatchStrategy implements BatchStrategyInterface
{
    /**
     * @var TranslationProviderInterface
     */
    protected $provider;

    /**
     * @var int Maximum batch size for DeepL API
     */
    protected $maxBatchSize = 50;

    /**
     * @var int Maximum number of retry attempts
     */
    protected $maxRetries = 3;

    /**
     * Constructor
     *
     * @param TranslationProviderInterface $provider Translation provider
     * @param int|null $maxBatchSize Optional custom batch size (max 50)
     * @param int|null $maxRetries Optional custom max retries (default: 3)
     */
    public function __construct(TranslationProviderInterface $provider, $maxBatchSize = null, $maxRetries = null)
    {
        $this->provider = $provider;

        if ($maxBatchSize !== null) {
            $this->maxBatchSize = min($maxBatchSize, 50);
        }

        if ($maxRetries !== null) {
            $this->maxRetries = max(1, $maxRetries);
        }
    }

    /**
     * Get maximum batch size supported by DeepL
     *
     * @return int Maximum number of texts per batch
     */
    public function getMaxBatchSize()
    {
        return $this->maxBatchSize;
    }

    /**
     * Set custom batch size
     *
     * @param int $size Batch size (will be capped at 50)
     * @return void
     */
    public function setMaxBatchSize($size)
    {
        $this->maxBatchSize = min($size, 50);
    }

    /**
     * Split items into batches based on DeepL's limits
     *
     * @param array $items Items to batch
     * @return array Array of batches
     */
    public function createBatches(array $items)
    {
        if (empty($items)) {
            return [];
        }

        return array_chunk($items, $this->maxBatchSize);
    }

    /**
     * Process a single batch through DeepL API with retry logic
     *
     * @param array $batch Batch of texts to translate
     * @param string $sourceLang Source language code
     * @param string $targetLang Target language code
     * @param array $options Translation options
     * @return array Translated results
     * @throws \Exception If batch translation fails after all retries
     */
    public function processBatch(array $batch, $sourceLang, $targetLang, array $options = [])
    {
        if (empty($batch)) {
            return [];
        }

        if (!$this->isValidBatchSize(count($batch))) {
            throw new \Exception("Batch size exceeds maximum of {$this->maxBatchSize}");
        }

        return $this->processBatchWithRetry($batch, $sourceLang, $targetLang, $options);
    }

    /**
     * Validate that batch size is within DeepL's limits
     *
     * @param int $size Batch size to validate
     * @return bool True if valid
     */
    public function isValidBatchSize($size)
    {
        return $size > 0 && $size <= $this->maxBatchSize;
    }

    /**
     * Process multiple batches sequentially
     *
     * @param array $batches Array of batches to process
     * @param string $sourceLang Source language code
     * @param string $targetLang Target language code
     * @param array $options Translation options
     * @return array All translated results merged
     */
    public function processMultipleBatches(array $batches, $sourceLang, $targetLang, array $options = [])
    {
        $allResults = [];

        foreach ($batches as $batch) {
            $results = $this->processBatch($batch, $sourceLang, $targetLang, $options);
            $allResults = array_merge($allResults, $results);
        }

        return $allResults;
    }

    /**
     * Estimate number of API calls needed for given item count
     *
     * @param int $itemCount Number of items to translate
     * @return int Number of API calls required
     */
    public function estimateApiCalls($itemCount)
    {
        return (int) ceil($itemCount / $this->maxBatchSize);
    }

    /**
     * Process batch with retry logic and exponential backoff
     *
     * @param array $batch Batch of texts to translate
     * @param string $sourceLang Source language code
     * @param string $targetLang Target language code
     * @param array $options Translation options
     * @return array Translated results
     * @throws \Exception If all retry attempts fail
     */
    protected function processBatchWithRetry(array $batch, $sourceLang, $targetLang, array $options = [])
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxRetries) {
            try {
                $attempt++;

                if ($attempt > 1) {
                    \Log::info("DeepL batch translation retry attempt {$attempt}/{$this->maxRetries}");
                }

                $results = $this->provider->translateBatch($batch, $sourceLang, $targetLang, $options);

                // Success - log if this was a retry
                if ($attempt > 1) {
                    \Log::info("DeepL batch translation succeeded on retry attempt {$attempt}");
                }

                return $results;

            } catch (\DeepL\DeepLException $e) {
                $lastException = $e;

                // Don't retry on certain errors (invalid input, auth errors, etc.)
                if ($this->shouldNotRetry($e)) {
                    \Log::error("DeepL batch translation failed with non-retryable error: " . $e->getMessage());
                    throw $e;
                }

                // If we haven't exhausted retries, wait with exponential backoff
                if ($attempt < $this->maxRetries) {
                    $waitTime = $this->calculateBackoffTime($attempt);
                    \Log::warning("DeepL batch translation failed (attempt {$attempt}/{$this->maxRetries}): {$e->getMessage()}. Retrying in {$waitTime}s...");
                    sleep($waitTime);
                } else {
                    \Log::error("DeepL batch translation failed after {$this->maxRetries} attempts: " . $e->getMessage());
                }

            } catch (\Exception $e) {
                // Non-DeepL exceptions - log and rethrow immediately
                \Log::error("Unexpected error during batch translation: " . $e->getMessage());
                throw $e;
            }
        }

        // All retries exhausted
        throw $lastException;
    }

    /**
     * Check if exception should not be retried
     * Don't retry on authentication, authorization, or invalid input errors
     *
     * @param \DeepL\DeepLException $e
     * @return bool True if should NOT retry
     */
    protected function shouldNotRetry(\DeepL\DeepLException $e)
    {
        $message = strtolower($e->getMessage());

        // Don't retry authentication/authorization errors
        if (strpos($message, 'unauthorized') !== false ||
            strpos($message, 'forbidden') !== false ||
            strpos($message, 'invalid') !== false ||
            strpos($message, 'bad request') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Calculate exponential backoff time
     * Returns: 2s, 4s, 8s for attempts 1, 2, 3
     *
     * @param int $attempt Current attempt number
     * @return int Wait time in seconds
     */
    protected function calculateBackoffTime($attempt)
    {
        return (int) pow(2, $attempt);
    }

    /**
     * Get maximum retry attempts
     *
     * @return int
     */
    public function getMaxRetries()
    {
        return $this->maxRetries;
    }

    /**
     * Set maximum retry attempts
     *
     * @param int $maxRetries
     * @return void
     */
    public function setMaxRetries($maxRetries)
    {
        $this->maxRetries = max(1, $maxRetries);
    }

    /**
     * Get the underlying translation provider
     *
     * @return TranslationProviderInterface
     */
    public function getProvider()
    {
        return $this->provider;
    }
}
