<?php

declare(strict_types=1);

namespace Glpzzz\OtelLog;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Throwable;
use Yiisoft\Log\Logger;

/**
 * Records one line per form submission through a PSR-3 logger whose target is
 * formatted by {@see OtelFormatter}. The outcome *is* the level:
 *
 * - {@see sent()}     — INFO  — the email went out
 * - {@see rejected()} — WARN  — the submission was turned away
 *   ({@see Reason} `Method` / `Malformed` / `Honeypot` / `Csrf` / `Validation`)
 * - {@see failed()}   — ERROR — a runtime fault while handling it
 *   ({@see Reason} `Mail` / `Exception`)
 *
 * Every site calls the same three verbs, so the level mapping, the `message`
 * text and the `context` shape cannot drift between them.
 */
final class SubmissionLog
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    /**
     * @param array<string, mixed> $fields the submitted form fields
     */
    public function sent(string $form, array $fields): void
    {
        $this->write(LogLevel::INFO, 'sent', $form, null, $fields, null, null);
    }

    /**
     * @param array<string, mixed>              $fields the submitted form fields
     * @param array<string, list<string>>|null  $errors validation messages by property, when
     *                                                  `$reason` is {@see Reason::Validation}
     */
    public function rejected(string $form, Reason $reason, array $fields, ?array $errors = null): void
    {
        $this->write(LogLevel::WARNING, 'rejected', $form, $reason, $fields, $errors, null);
    }

    /**
     * @param array<string, mixed> $fields the submitted form fields
     */
    public function failed(string $form, Reason $reason, array $fields, Throwable $error): void
    {
        $this->write(LogLevel::ERROR, 'failed', $form, $reason, $fields, null, $error);
    }

    /**
     * @param array<string, mixed>             $fields
     * @param array<string, list<string>>|null $errors
     */
    private function write(
        string $level,
        string $outcome,
        string $form,
        ?Reason $reason,
        array $fields,
        ?array $errors,
        ?Throwable $error,
    ): void {
        $this->logger->log($level, "{$form} submission {$outcome}", [
            'form' => $form,
            'reason' => $reason === null ? '' : $reason->value,
            'fields' => $fields,
            'errors' => $errors,
            'errorMessage' => $error?->getMessage(),
            'errorStackTrace' => $error === null ? null : (string) $error,
        ]);

        // One line per request — write it now rather than waiting for shutdown,
        // which a re-thrown exception or an early exit() could skip.
        if ($this->logger instanceof Logger) {
            $this->logger->flush(true);
        }
    }
}
