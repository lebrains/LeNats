<?php

namespace LeNats\Subscribers;

use LeNats\Events\Nats\Connected;
use LeNats\Exceptions\StreamException;
use LeNats\Services\Configuration;
use LeNats\Subscription\Connector;
use LeNats\Subscription\Subscription;
use LeNats\Support\Inbox;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class Registration implements EventSubscriberInterface
{
    /**
     * @var Configuration
     */
    private $config;

    /**
     * @var Connector
     */
    private $connector;

    public function __construct(Connector $connector, Configuration $config)
    {
        $this->config = $config;
        $this->connector = $connector;
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * ['eventName' => 'methodName']
     *  * ['eventName' => ['methodName', $priority]]
     *  * ['eventName' => [['methodName1', $priority], ['methodName2']]]
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents(): array
    {
        return [
            Connected::class => 'handle',
        ];
    }

    /**
     * @throws StreamException
     */
    public function handle(): void
    {
        $subscription = new Subscription(Inbox::getDiscoverSubject($this->config->getClusterId()));
        $subscription->setTimeout($this->config->getWriteTimeout());

        $this->connector->subscribe($subscription);
    }
}
