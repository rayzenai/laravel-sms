<?php

namespace Rayzenai\LaravelSms\Services;

use Exception;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Rayzenai\LaravelSms\Contracts\HasSmsNumber;
use Rayzenai\LaravelSms\Models\SentMessage;

class SmsMessageBuilder
{
    protected SmsService $service;

    /**
     * Raw recipients as supplied — resolved to phone strings at send time.
     *
     * @var mixed
     */
    protected mixed $recipients = null;

    protected string $message = '';

    protected ?string $sender = null;

    public function __construct(SmsService $service)
    {
        $this->service = $service;
    }

    /**
     * Set the recipient(s): a phone string, a model implementing HasSmsNumber, or
     * an array/collection mixing the two.
     */
    public function to(mixed $recipients): self
    {
        $this->recipients = $recipients;

        return $this;
    }

    /**
     * Set the message content.
     */
    public function message(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    /**
     * Set the sender name/number.
     */
    public function from(string $sender): self
    {
        $this->sender = $sender;

        return $this;
    }

    /**
     * Send the SMS to a single recipient.
     *
     * @throws Exception
     */
    public function send(): SentMessage
    {
        $recipients = $this->resolveRecipients();

        if (count($recipients) === 0) {
            throw new InvalidArgumentException('A recipient with a resolvable phone number is required.');
        }

        if (count($recipients) > 1) {
            throw new InvalidArgumentException('Use sendBulk() for multiple recipients');
        }

        $this->assertMessage();

        return $this->service->send($recipients[0], $this->message);
    }

    /**
     * Send the SMS to multiple recipients (recipients without a number are skipped).
     *
     * @return Collection<int, SentMessage>
     *
     * @throws Exception
     */
    public function sendBulk(): Collection
    {
        $recipients = $this->resolveRecipients();

        if (empty($recipients)) {
            throw new InvalidArgumentException('At least one recipient with a resolvable phone number is required.');
        }

        $this->assertMessage();

        return $this->service->sendBulk($recipients, $this->message);
    }

    protected function assertMessage(): void
    {
        if ($this->message === '') {
            throw new InvalidArgumentException('Message content is required');
        }
    }

    /**
     * Flatten the supplied recipients into a list of phone strings, dropping any
     * that resolve to null/empty.
     *
     * @return array<int, string>
     */
    protected function resolveRecipients(): array
    {
        $items = $this->recipients instanceof Collection
            ? $this->recipients->all()
            : (is_array($this->recipients) ? $this->recipients : [$this->recipients]);

        return collect($items)
            ->map(fn ($recipient) => $this->resolvePhone($recipient))
            ->filter(fn ($phone) => filled($phone))
            ->values()
            ->all();
    }

    protected function resolvePhone(mixed $recipient): ?string
    {
        if (is_string($recipient)) {
            return $recipient;
        }

        if ($recipient instanceof HasSmsNumber || (is_object($recipient) && method_exists($recipient, 'smsPhoneNumber'))) {
            return $recipient->smsPhoneNumber();
        }

        if ($recipient === null) {
            return null;
        }

        if (is_object($recipient)) {
            throw new InvalidArgumentException(sprintf(
                '%s cannot be used as an SMS recipient. Implement %s.',
                $recipient::class,
                HasSmsNumber::class
            ));
        }

        throw new InvalidArgumentException('Invalid SMS recipient.');
    }
}
