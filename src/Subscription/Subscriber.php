<?php

namespace LeNats\Subscription;

use Closure;
use Exception;
use Google\Protobuf\Internal\Message;
use LeNats\Contracts\EventDispatcherAwareInterface;
use LeNats\Events\CloudEvent;
use LeNats\Exceptions\StreamException;
use LeNats\Exceptions\SubscriptionNotFoundException;
use LeNats\Listeners\Responses\SubscriptionResponseListener;
use LeNats\Listeners\SubscriptionListener;
use LeNats\Services\Configuration;
use LeNats\Support\Dispatcherable;
use LeNats\Support\Inbox;
use NatsStreamingProtocol\Ack;
use NatsStreamingProtocol\SubscriptionRequest;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class Subscriber extends MessageStreamer implements EventDispatcherAwareInterface, ContainerAwareInterface
{
    use Dispatcherable;
    use ContainerAwareTrait;
    /** @var string */
    protected const MESSAGE_LISTENER = SubscriptionListener::class;
    /** @var string */
    protected const RESPONSE_LISTENER = SubscriptionResponseListener::class;

    /** @var Subscription[] */
    private static $subscriptions = [];

    /**
     * @var Configuration
     */
    protected $config;

    /**
     * @param Subscription $subscription
     * @return Subscription|null
     * @throws StreamException
     * @throws Exception
     */
    public function subscribe(Subscription $subscription): ?Subscription
    {
        $subscription->setSid($this->generator->generateString(16));
        $this->storeSubscription($subscription);

        if (!$this->send($subscription->getInbox(), $subscription->getSid())) {
            return null;
        }

        $this->registerListener($subscription->getSid(), static::MESSAGE_LISTENER, 100);

        if ($subscription->getMessageLimit()) {
            $this->unsubscribe($subscription->getSid(), $subscription->getMessageLimit());
        }

        $requestInbox = Inbox::newInbox();

        $sid = $this->generator->generateString(16);
        $this->storeSubscription($subscription, $sid);

        if (!$this->send($requestInbox, $sid)) {
            return null;
        }

        $this->registerListener($sid, static::RESPONSE_LISTENER);
        $this->registerListener($sid, function () use ($sid) {
            $this->remove($sid);
        });

        $this->unsubscribe($sid, 1);

        $this->getConnection()->publish(
            $this->getPublishSubject($subscription),
            $this->getRequest($subscription),
            $requestInbox
        );

        if ($subscription->isStartWaiting()) {
            $this->getConnection()->wait($subscription->getTimeout());
        }

        return $subscription;
    }

    protected function storeSubscription(Subscription $subscription, string $sid = null)
    {
        self::$subscriptions[$sid ?? $subscription->getSid()] = $subscription;
    }

    /**
     * @param string $sid
     * @param string|callable $handler
     * @param int $priority
     */
    protected function registerListener(string $sid, $handler, int $priority = 0): void
    {
        if ($handler instanceof Closure) {
            $listener = $handler;
        } else {
            $listener = [$this->container->get($handler), 'handle'];
        }

        $this->dispatcher->addListener($sid, $listener, $priority);
    }

    public function remove(string $sid, ?string $eventName = null)
    {
        if (empty(self::$subscriptions[$sid])) {
            return;
        }

        unset(self::$subscriptions[$sid]);
        $this->unsubscribe($sid);

        $eventName = $eventName ?? $sid;

        foreach ($this->dispatcher->getListeners($eventName) as $listener) {
            $this->dispatcher->removeListener($eventName, $listener);
        }

        foreach ($this->dispatcher->getListeners($sid) as $listener) {
            $this->dispatcher->removeListener($sid, $listener);
        }
    }

    protected function getPublishSubject(Subscription $subscription): string
    {
        return $this->config->getSubRequests();
    }

    protected function getRequest(Subscription $subscription): Message
    {
        $request = new SubscriptionRequest();

        $request->setSubject($subscription->getSubject());
        if ($subscription->getGroup()) {
            $request->setQGroup($subscription->getGroup());
        }

        $request->setClientID($this->config->getClientId());
        $request->setAckWaitInSecs($subscription->getAcknowledgeWait());
        $request->setMaxInFlight(1024);
        $request->setDurableName($this->config->getClientId());
        $request->setInbox($subscription->getInbox());
        $request->setStartPosition($subscription->getStartAt());

        return $request;
    }

    /**
     * @param CloudEvent $event
     * @throws StreamException
     */
    public function acknowledge(CloudEvent $event): void
    {
        $subscription = $event->getSubscription();
        $subscription->incrementProcessed();

        $request = new Ack();
        $request->setSubject($subscription->getSubject());
        $request->setSequence($event->getSequenceId());

        $this->getConnection()->publish($subscription->getAcknowledgeInbox(), $request);
    }

    /**
     * @param $sid
     * @return Subscription
     * @throws SubscriptionNotFoundException
     */
    public function getSubscription($sid): Subscription
    {
        $subscription = self::$subscriptions[$sid] ?? null;

        if ($subscription === null) {
            throw new SubscriptionNotFoundException('Subscription not found');
        }

        return $subscription;
    }

    public function unsubscribeAll(): void
    {
        $subscriptions = self::$subscriptions;

        foreach ($subscriptions as $sid => $subscription) {
            $this->unsubscribe($sid);
        }

        $this->getConnection()->wait(5);
    }
}
