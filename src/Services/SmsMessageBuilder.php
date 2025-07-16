<?php

namespace Rayzenai\LaravelSms\Services;

use Illuminate\Support\Collection;
use Rayzenai\LaravelSms\Models\SentMessage;

class SmsMessageBuilder
{
    protected SmsService $service;
    protected string|array $recipients = '';
    protected string $message = '';
    protected ?string $sender = null;

    public function __construct(SmsService $service)
    {
        $this->service = $service;
    }

    /**
     * Set the recipient(s) for the SMS.
     *
     * @param string|array $recipients
     * @return $this
     */
    public function to(string|array $recipients): self
    {
        $this->recipients = $recipients;
        return $this;
    }

    /**
     * Set the message content.
     *
     * @param string $message
     * @return $this
     */
    public function message(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    /**
     * Set the sender name/number.
     *
     * @param string $sender
     * @return $this
     */
    public function from(string $sender): self
    {
        $this->sender = $sender;
        return $this;
    }

    /**
     * Send the SMS to a single recipient.
     *
     * @return SentMessage
     * @throws \Exception
     */
    public function send(): SentMessage
    {
        if (is_array($this->recipients)) {
            if (count($this->recipients) === 1) {
                $recipient = $this->recipients[0];
            } else {
                throw new \InvalidArgumentException('Use sendBulk() for multiple recipients');
            }
        } else {
            $recipient = $this->recipients;
        }

        if (empty($recipient)) {
            throw new \InvalidArgumentException('Recipient is required');
        }

        if (empty($this->message)) {
            throw new \InvalidArgumentException('Message content is required');
        }

        return $this->service->send($recipient, $this->message);
    }

    /**
     * Send the SMS to multiple recipients.
     *
     * @return Collection
     * @throws \Exception
     */
    public function sendBulk(): Collection
    {
        if (!is_array($this->recipients)) {
            $this->recipients = [$this->recipients];
        }

        if (empty($this->recipients)) {
            throw new \InvalidArgumentException('Recipients are required');
        }

        if (empty($this->message)) {
            throw new \InvalidArgumentException('Message content is required');
        }

        return $this->service->sendBulk($this->recipients, $this->message);
    }
}
