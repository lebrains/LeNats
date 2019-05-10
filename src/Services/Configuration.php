<?php

namespace LeNats\Services;


use NatsStreamingProtocol\ConnectResponse;

class Configuration
{
    /** @var string */
    private $lang = 'php';

    /** @var string */
    private $version = PHP_VERSION;

    /** @var string */
    private $dsn;

    /** @var bool */
    private $verbose = false;

    /** @var bool */
    private $pedantic = false;

    /** @var int */
    private $protocol = 1;

    /** @var string|null */
    private $user;

    /** @var string|null */
    private $pass;

    /** @var string */
    private $clusterId;

    /** @var string */
    private $clientId;

    /** @var array */
    private $context = [];

    /**
     * @var string
     */
    private $pubPrefix;

    /**
     * @var string
     */
    private $subRequests;

    /**
     * @var string
     */
    private $unsubRequests;

    /**
     * @var string
     */
    private $subCloseRequests;

    /**
     * @var string
     */
    private $closeRequests;

    public function __construct(?array $config = null)
    {
        $config = $config ?? [];

        foreach ($config as $field => $value) {
            $method = 'set' . str_replace('_', '', ucwords($field, '_'));

            if (method_exists($this, $method)) {
                $this->{$method}($value);
            }
        }
    }

    public function configureConnection(ConnectResponse $response): void
    {
        $this->pubPrefix = $response->getPubPrefix();
        $this->subRequests = $response->getSubRequests();
        $this->unsubRequests = $response->getUnsubRequests();
        $this->subCloseRequests = $response->getSubCloseRequests();
        $this->closeRequests = $response->getCloseRequests();
    }

    /**
     * @return string
     */
    public function getLang(): string
    {
        return $this->lang;
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @return bool
     */
    public function isVerbose(): bool
    {
        return $this->verbose;
    }

    /**
     * @param bool $verbose
     */
    public function setVerbose(bool $verbose): void
    {
        $this->verbose = $verbose;
    }

    /**
     * @return bool
     */
    public function isPedantic(): bool
    {
        return $this->pedantic;
    }

    /**
     * @param bool $pedantic
     */
    public function setPedantic(bool $pedantic): void
    {
        $this->pedantic = $pedantic;
    }

    /**
     * @return int
     */
    public function getProtocol(): int
    {
        return $this->protocol;
    }

    /**
     * @param int $protocol
     */
    public function setProtocol(int $protocol): void
    {
        $this->protocol = $protocol;
    }

    /**
     * @return string|null
     */
    public function getUser(): ?string
    {
        return $this->user;
    }

    /**
     * @param string|null $user
     */
    public function setUser(?string $user): void
    {
        $this->user = $user;
    }

    /**
     * @return string|null
     */
    public function getPass(): ?string
    {
        return $this->pass;
    }

    /**
     * @param string|null $pass
     */
    public function setPass(?string $pass): void
    {
        $this->pass = $pass;
    }

    /**
     * @return string
     */
    public function getDsn(): string
    {
        return $this->dsn;
    }

    /**
     * @param string $dsn
     */
    public function setDsn(string $dsn): void
    {
        $this->dsn = $dsn;
    }

    /**
     * @return string
     */
    public function getClusterId(): string
    {
        return $this->clusterId;
    }

    /**
     * @param string $clusterId
     */
    public function setClusterId(string $clusterId): void
    {
        $this->clusterId = $clusterId;
    }

    /**
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * @param array $context
     */
    public function setContext(array $context): void
    {
        $this->context = $context;
    }

    /**
     * @return string
     */
    public function getClientId(): string
    {
        return $this->clientId;
    }

    /**
     * @param string $clientId
     */
    public function setClientId(string $clientId): void
    {
        $this->clientId = $clientId;
    }

    /**
     * @return string
     */
    public function getSubRequests(): string
    {
        return $this->subRequests;
    }

    /**
     * @return string
     */
    public function getPubPrefix(): string
    {
        return $this->pubPrefix;
    }
}
