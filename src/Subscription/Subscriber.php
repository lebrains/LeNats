<?php

namespace LeNats\Subscription;

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
use function React\Promise\all;
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

    /**
     * @var Configuration
     */
    protected $config;

    /** @var Subscription[] */
    private static $subscriptions = [];

    /**
     * @param  Subscription    $subscription
     * @param  callable|null   $onSuccess
     * @throws StreamException
     * @throws Exception
     * @return static
     */
    public function subscribe(Subscription $subscription, ?callable $onSuccess = null): self
    {
        $subscription->setSid($this->generator->generateString(16));
        $this->storeSubscription($subscription);

        $promises = [];

        $this->registerListener($subscription->getSid(), static::MESSAGE_LISTENER, 100);
        $promises[] = $this->send($subscription->getInbox(), $subscription->getSid())
            ->then(function () use ($subscription): void {
                if ($subscription->getMessageLimit()) {
                    $this->unsubscribe($subscription->getSid(), $subscription->getMessageLimit());
                }
            });

        $requestInbox = Inbox::newInbox();

        $sid = $this->generator->generateString(16);
        $this->storeSubscription($subscription, $sid);

        $promises[] = $this->send($requestInbox, $sid)->then(function () use ($sid): void {
            $this->unsubscribe($sid, 1);
        });

        $this->registerListener($sid, static::RESPONSE_LISTENER);
        $this->registerListener($sid, function () use ($sid): void {
            $this->remove($sid);
        });

        $promises[] = $this->getConnection()->publish(
            $this->getPublishSubject($subscription),
            $this->getRequest($subscription),
            $requestInbox
        );

        if ($onSuccess) {
            $onSuccess($subscription);
        }

        all($promises);

        $this->run($subscription->getTimeout());

        return $this;
    }

    /**
     * @param  string          $sid
     * @param  string|null     $eventName
     * @throws StreamException
     */
    public function remove(string $sid, ?string $eventName = null): void
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

    /**
     * @param  CloudEvent      $event
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
     * @param  string                        $sid
     * @throws SubscriptionNotFoundException
     * @return Subscription
     */
    public function getSubscription(string $sid): Subscription
    {
        $subscription = self::$subscriptions[$sid] ?? null;

        if ($subscription === null) {
            throw new SubscriptionNotFoundException('Subscription not found');
        }

        return $subscription;
    }

    /**
     * @throws StreamException
     */
    public function unsubscribeAll(): void
    {
        $subscriptions = self::$subscriptions;

        foreach ($subscriptions as $sid => $subscription) {
            $this->unsubscribe($sid);
        }

        $this->getConnection()->run(5);
    }

    protected function storeSubscription(Subscription $subscription, ?string $sid = null): void
    {
        self::$subscriptions[$sid ?? $subscription->getSid()] = $subscription;
    }

    /**
     * @param string          $sid
     * @param string|callable $handler
     * @param int             $priority
     */
    protected function registerListener(string $sid, $handler, int $priority = 0): void
    {
        if (is_callable($handler)) {
            $listener = $handler;
        } else {
            $listener = [$this->container->get($handler), 'handle'];
        }

        $this->dispatcher->addListener($sid, $listener, $priority);
    }

    protected function getPublishSubject(Subscription $subscription): string
    {
        return $this->config->getSubRequests();
    }

    protected function getRequest(Subscription $subscription): Message
    {
        $request = new SubscriptionRequest();

        $request->setSubject($subscription->getSubject());
        if (!empty($subscription->getGroup())) {
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
}
