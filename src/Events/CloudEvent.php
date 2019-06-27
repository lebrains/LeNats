<?php

namespace LeNats\Events;

use DateTimeImmutable;
use DateTimeInterface;
use JMS\Serializer\Annotation as Serializer;
use LeNats\Subscription\Subscription;

/**
 * @Serializer\ExclusionPolicy("ALL")
 */
class CloudEvent extends Event
{
    /**
     * @var bool
     * @Serializer\Exclude()
     */
    protected $propagationStopped = false;
    /**
     * @var mixed
     * @Serializer\Expose()
     * @Serializer\Type("array")
     */
    protected $data;

    /**
     * @var string
     * @Serializer\Expose()
     * @Serializer\Type("string")
     * @Serializer\SerializedName("specversion")
     */
    private $specVersion = '0.3';

    /**
     * @var string
     * @Serializer\Expose()
     * @Serializer\Type("string")
     */
    private $type;

    /**
     * @var string|null
     * @Serializer\Expose()
     * @Serializer\Type("string")
     */
    private $source;

    /**
     * @var string
     * @Serializer\Expose()
     * @Serializer\Type("string")
     */
    private $id;

    /**
     * @var int|string
     */
    private $sequenceId;

    /**
     * @var Subscription
     */
    private $subscription;

    /**
     * @var DateTimeInterface
     * @Serializer\Expose()
     * @Serializer\Type("DateTimeImmutable")
     */
    private $time;

    /**
     * @var string
     * @Serializer\Expose()
     * @Serializer\Type("string")
     * @Serializer\SerializedName("datacontenttype")
     */
    private $dataContentType = 'application/json';

    public function __construct()
    {
        $this->time = new DateTimeImmutable();
    }

    /**
     * @return string
     */
    public function getSpecVersion(): string
    {
        return $this->specVersion;
    }

    /**
     * @param string $specVersion
     */
    public function setSpecVersion(string $specVersion): void
    {
        $this->specVersion = $specVersion;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getSource(): ?string
    {
        return $this->source;
    }

    /**
     * @param string $source
     */
    public function setSource(?string $source): void
    {
        $this->source = $source;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param string $id
     */
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    /**
     * @return Subscription
     */
    public function getSubscription(): Subscription
    {
        return $this->subscription;
    }

    /**
     * @param Subscription $subscription
     */
    public function setSubscription(Subscription $subscription): void
    {
        $this->subscription = $subscription;
    }

    /**
     * @return DateTimeInterface
     */
    public function getTime(): DateTimeInterface
    {
        return $this->time;
    }

    /**
     * @param DateTimeInterface $time
     */
    public function setTime(DateTimeInterface $time): void
    {
        $this->time = $time;
    }

    /**
     * @return string
     */
    public function getDataContentType(): string
    {
        return $this->dataContentType;
    }

    /**
     * @param string $dataContentType
     */
    public function setDataContentType(string $dataContentType): void
    {
        $this->dataContentType = $dataContentType;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param mixed $data
     */
    public function setData($data): void
    {
        $this->data = $data;
    }

    /**
     * @return int|string
     */
    public function getSequenceId()
    {
        return $this->sequenceId;
    }

    /**
     * @param int|string $sequenceId
     */
    public function setSequenceId($sequenceId): void
    {
        $this->sequenceId = $sequenceId;
    }

    /**
     * @Serializer\PreSerialize()
     */
    public function setIdFromData(): void
    {
        if (is_object($this->data) && method_exists($this->data, 'getId')) {
            $this->id = $this->data->getId();
        } elseif (is_array($this->data) && isset($this->data['id'])) {
            $this->id = $this->data['id'];
        }
    }
}
