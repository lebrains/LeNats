<?php

namespace LeNats\Subscription;

use Exception;
use Google\Protobuf\Internal\Message;
use LeNats\Contracts\EventDispatcherAwareInterface;
use LeNats\Exceptions\StreamException;
use LeNats\Exceptions\SubscriptionNotFoundException;
use LeNats\Support\Dispatcherable;
use LeNats\Support\Inbox;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

abstract class SubscriptionMessageStreamer extends MessageStreamer implements EventDispatcherAwareInterface, ContainerAwareInterface
{
    use Dispatcherable;
    use ContainerAwareTrait;

    /** @var Subscription[] */
    private static $subscriptions = [];

    /**
     * @param  Subscription    $subscription
     * @throws StreamException
     * @throws Exception
     * @return static
     */
    public function subscribe(Subscription $subscription): self
    {
        $subscription->setSid($this->generator->generateString(16));
        $this->storeSubscription($subscription);

        if ($this->getMessageListenerClass() !== null) {
            $this->registerListener($subscription->getSid(), $this->getMessageListenerClass(), 100);
        }

        $this->createSubscriptionInbox($subscription->getInbox(), $subscription->getSid());
        if ($subscription->getMessageLimit()) {
            $this->unsubscribe($subscription->getSid(), $subscription->getMessageLimit());
        }

        $requestInbox = Inbox::newInbox();

        $sid = $this->generator->generateString(16);
        $this->storeSubscription($subscription, $sid);

        $this->registerListener($sid, $this->getResponseListenerClass());
        $this->registerListener($sid, function () use ($sid): void {
            $this->reset($sid);
        });

        $this->createSubscriptionInbox($requestInbox, $sid);
        $this->unsubscribe($sid, 1);

        $this->getStream()->publish(
            $this->getPublishSubject($subscription),
            $this->getRequest($subscription),
            $requestInbox
        );

        if ($subscription->getTimeout()) {
            $this->connection->runTimer($subscription->getSid(), $subscription->getTimeout());
        } else {
            $this->connection->run();
        }

        return $this;
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
     * @return Subscription[]
     */
    protected function getSubscriptions(): array
    {
        return self::$subscriptions;
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

    abstract protected function getPublishSubject(Subscription $subscription): string;

    abstract protected function getRequest(Subscription $subscription): Message;

    abstract protected function getMessageListenerClass(): ?string;

    abstract protected function getResponseListenerClass(): string;

    protected function storeSubscription(Subscription $subscription, ?string $sid = null): void
    {
        self::$subscriptions[$sid ?? $subscription->getSid()] = $subscription;
    }

    protected function reset(string $sid, ?string $eventName = null): void
    {
        unset(self::$subscriptions[$sid]);

        $this->connection->stopTimer($sid);

        $eventName = $eventName ?? $sid;

        foreach ($this->dispatcher->getListeners($eventName) as $listener) {
            $this->dispatcher->removeListener($eventName, $listener);
        }

        foreach ($this->dispatcher->getListeners($sid) as $listener) {
            $this->dispatcher->removeListener($sid, $listener);
        }
    }
}
