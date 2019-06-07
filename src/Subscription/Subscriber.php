<?php

namespace LeNats\Subscription;

use Exception;
use Google\Protobuf\Internal\Message;
use LeNats\Contracts\EventDispatcherAwareInterface;
use LeNats\Events\CloudEvent;
use LeNats\Exceptions\StreamException;
use LeNats\Exceptions\SubscriptionException;
use LeNats\Exceptions\SubscriptionNotFoundException;
use LeNats\Listeners\Responses\SubscriptionResponseListener;
use LeNats\Listeners\SubscriptionListener;
use LeNats\Services\Configuration;
use LeNats\Support\Dispatcherable;
use LeNats\Support\Inbox;
use NatsStreamingProtocol\Ack;
use NatsStreamingProtocol\StartPosition;
use NatsStreamingProtocol\SubscriptionRequest;
use NatsStreamingProtocol\UnsubscribeRequest;
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

                $this->getConnection()->stopTimer($subscription->getSid());
            });

        $this->getConnection()->runTimer($subscription->getSid(), $this->config->getWriteTimeout());

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

        if ($subscription->getTimeout()) {
            $this->getConnection()->runTimer($sid, $subscription->getTimeout());
        } else {
            $this->getConnection()->run();
        }

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
            $this->close($sid);
        }
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

    /**
     * @param Subscription $subscription
     * @return Message
     * @throws SubscriptionException
     */
    protected function getRequest(Subscription $subscription): Message
    {
        $request = new SubscriptionRequest();

        $request->setSubject($subscription->getSubject());

        $request->setQGroup($subscription->getGroup() ?? $this->config->getClientId());
        $request->setClientID($this->config->getClientId());
        $request->setAckWaitInSecs($subscription->getAcknowledgeWait());
        $request->setMaxInFlight($subscription->getMaxInFlight());
        $request->setDurableName($this->config->getClientId());
        $request->setInbox($subscription->getInbox());
        $request->setStartPosition($subscription->getStartPosition());

        if ($subscription->getStartPosition() === StartPosition::SequenceStart) {
            if ($subscription->getStartSequence() === null) {
                throw new SubscriptionException('Start sequence number must be defined with start position by sequence');
            }

            $request->setStartSequence($subscription->getStartSequence());
        } elseif ($subscription->getStartPosition() === StartPosition::TimeDeltaStart){
            if ($subscription->getTimeDeltaStart() === null) {
                throw new SubscriptionException('Time delta start must be defined with start position by time');
            }

            $request->setStartTimeDelta($subscription->getTimeDeltaStart());
        }

        return $request;
    }

    public function close(string $sid)
    {
        if (!array_key_exists($sid, self::$subscriptions)) {
            return;
        }

        $subscription = self::$subscriptions[$sid];
        $this->remove($sid);

        $requestInbox = Inbox::newInbox();

        $unsubSid = $this->generator->generateString(16);
        $this->storeSubscription($subscription, $unsubSid);

        $promises[] = $this->send($requestInbox, $unsubSid)->then(function () use ($unsubSid): void {
            $this->getConnection()->getLoop()->futureTick(function () use ($unsubSid) {
                $this->unsubscribe($unsubSid, 1);
            });
        });

        $this->registerListener($unsubSid, function () use ($unsubSid): void {
            $this->getConnection()->stopTimer($unsubSid);
            $this->remove($unsubSid);
        });

        $request = new UnsubscribeRequest();

        $request->setClientID($this->config->getClientId());
        $request->setSubject($subscription->getSubject());
        $request->setInbox($subscription->getInbox());
        $request->setDurableName($this->config->getClientId());

        $this->getConnection()->publish(
            $this->config->getUnsubRequests(),
            $request,
            $requestInbox
        );

        $this->getConnection()->runTimer($unsubSid, $this->config->getWriteTimeout());
    }
}
