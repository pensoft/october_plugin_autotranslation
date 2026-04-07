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
     */
    public function __construct(TranslationProviderInterface $provider, ?int $maxBatchSize = null, ?int $maxRetries = null)
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
     */
    public function getMaxBatchSize(): int
    {
        return $this->maxBatchSize;
    }

    /**
     * Set custom batch size
     */
    public function setMaxBatchSize(int $size): void
    {
        $this->maxBatchSize = min($size, 50);
    }

    /**
     * Split items into batches based on DeepL's limits
     */
    public function createBatches(array $items): array
    {
        if (empty($items)) {
            return [];
        }

        return array_chunk($items, $this->maxBatchSize);
    }

    /**
     * Process a single batch through DeepL API with retry logic
     */
    public function processBatch(array $batch, string $sourceLang, string $targetLang, array $options = []): array
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
     */
    public function isValidBatchSize(int $size): bool
    {
        return $size > 0 && $size <= $this->maxBatchSize;
    }

    /**
     * Process multiple batches sequentially
     */
    public function processMultipleBatches(array $batches, string $sourceLang, string $targetLang, array $options = []): array
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
     */
    public function estimateApiCalls(int $itemCount): int
    {
        return (int) ceil($itemCount / $this->maxBatchSize);
    }

    /**
     * Process batch with retry logic and exponential backoff
     */
    protected function processBatchWithRetry(array $batch, string $sourceLang, string $targetLang, array $options = []): array
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
     */
    protected function shouldNotRetry(\DeepL\DeepLException $e): bool
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
     */
    protected function calculateBackoffTime(int $attempt): int
    {
        return (int) pow(2, $attempt);
    }

    /**
     * Get maximum retry attempts
     */
    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    /**
     * Set maximum retry attempts
     */
    public function setMaxRetries(int $maxRetries): void
    {
        $this->maxRetries = max(1, $maxRetries);
    }

    /**
     * Get the underlying translation provider
     */
    public function getProvider(): TranslationProviderInterface
    {
        return $this->provider;
    }
}