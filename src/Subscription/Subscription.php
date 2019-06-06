<?php

namespace LeNats\Subscription;

use LeNats\Support\Inbox;

class Subscription
{
    /** @var int */
    private $received = 0;

    /** @var int */
    private $processed = 0;

    /** @var string */
    private $sid;

    /** @var string */
    private $subject;

    /** @var string */
    private $inbox;

    /** @var bool */
    private $startWaiting = false;

    /** @var int */
    private $timeout = 0;

    /** @var int */
    private $acknowledgeWait = 30;

    /** @var int */
    private $startAt = 0;

    /** @var int */
    private $startMicroTime = 0;

    /** @var string|null */
    private $group;

    /** @var int|null */
    private $messageLimit;

    /** @var string */
    private $acknowledgeInbox;

    public function __construct(string $subject, ?array $options = null)
    {
        $options = $options ?? [];

        $this->subject = $subject;
        $this->inbox = Inbox::newInbox();

        foreach ($options as $field => $value) {
            $method = 'set' . str_replace('_', '', ucwords($field, '_'));

            if (method_exists($this, $method)) {
                $this->{$method}($value);
            }
        }
    }

    /**
     * @return string
     */
    public function getSid(): string
    {
        return $this->sid;
    }

    /**
     * @param string $sid
     */
    public function setSid(string $sid): void
    {
        $this->sid = $sid;
    }

    /**
     * @return string
     */
    public function getSubject(): string
    {
        return $this->subject;
    }

    /**
     * @return string
     */
    public function getInbox(): string
    {
        return $this->inbox;
    }

    /**
     * @param string $inbox
     */
    public function setInbox(string $inbox): void
    {
        $this->inbox = $inbox;
    }

    /**
     * @return bool
     */
    public function isStartWaiting(): bool
    {
        return $this->startWaiting;
    }

    /**
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * @param int $timeout
     */
    public function setTimeout(int $timeout): void
    {
        $this->startWaiting = true;

        $this->timeout = $timeout;
    }

    /**
     * @return int
     */
    public function getAcknowledgeWait(): int
    {
        return $this->acknowledgeWait;
    }

    /**
     * @param int $acknowledgeWait
     */
    public function setAcknowledgeWait(int $acknowledgeWait): void
    {
        $this->acknowledgeWait = $acknowledgeWait;
    }

    /**
     * @return int
     */
    public function getStartAt(): int
    {
        return $this->startAt;
    }

    /**
     * @param int $startAt
     */
    public function setStartAt(int $startAt): void
    {
        $this->startAt = $startAt;
    }

    /**
     * @return int
     */
    public function getStartMicroTime(): int
    {
        return $this->startMicroTime;
    }

    /**
     * @param int $startMicroTime
     */
    public function setStartMicroTime(int $startMicroTime): void
    {
        $this->startMicroTime = $startMicroTime;
    }

    /**
     * @return string|null
     */
    public function getGroup(): ?string
    {
        return $this->group;
    }

    /**
     * @param string|null $group
     */
    public function setGroup(?string $group): void
    {
        $this->group = $group;
    }

    public function getMessageLimit(): ?int
    {
        return $this->messageLimit;
    }

    /**
     * @param int $messageLimit
     */
    public function setMessageLimit(int $messageLimit): void
    {
        $this->messageLimit = $messageLimit;
    }

    /**
     * @return string
     */
    public function getAcknowledgeInbox(): string
    {
        return $this->acknowledgeInbox;
    }

    /**
     * @param string $acknowledgeInbox
     */
    public function setAcknowledgeInbox(string $acknowledgeInbox): void
    {
        $this->acknowledgeInbox = $acknowledgeInbox;
    }

    public function incrementReceived(): int
    {
        return ++$this->received;
    }

    public function incrementProcessed(): int
    {
        return ++$this->processed;
    }

    /**
     * @return int
     */
    public function getReceived(): int
    {
        return $this->received;
    }

    /**
     * @return int
     */
    public function getProcessed(): int
    {
        return $this->processed;
    }
}
