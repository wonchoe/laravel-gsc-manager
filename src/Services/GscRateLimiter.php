<?php

namespace Wonchoe\GscManager\Services;

use Google\Service\Exception as GoogleServiceException;
use Throwable;

class GscRateLimiter
{
    public function __construct(private readonly GscErrorFormatter $errors)
    {
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function retry(callable $callback)
    {
        $maxRetries = (int) config('gsc-manager.rate_limits.max_retries', 3);
        $retrySleep = (int) config('gsc-manager.rate_limits.retry_sleep_seconds', 5);
        $quotaSleep = (int) config('gsc-manager.rate_limits.sleep_on_quota_seconds', 15);
        $attempt = 0;

        beginning:
        try {
            return $callback();
        } catch (Throwable $exception) {
            if ($attempt >= $maxRetries || ! $this->isRetryable($exception)) {
                throw $exception;
            }

            $attempt++;
            sleep($this->isQuotaError($exception) ? $quotaSleep : $retrySleep);
            goto beginning;
        }
    }

    private function isRetryable(Throwable $exception): bool
    {
        $code = (int) $exception->getCode();

        return in_array($code, [429, 500, 502, 503, 504], true) || $this->isQuotaError($exception);
    }

    private function isQuotaError(Throwable $exception): bool
    {
        $message = strtolower($this->errors->redactString($exception->getMessage()));

        if (str_contains($message, 'quotaexceeded') || str_contains($message, 'ratelimitexceeded')) {
            return true;
        }

        if ($exception instanceof GoogleServiceException) {
            foreach ($exception->getErrors() as $error) {
                $reason = strtolower((string) ($error['reason'] ?? ''));
                if (in_array($reason, ['quotaexceeded', 'ratelimitexceeded', 'userratelimitexceeded'], true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
