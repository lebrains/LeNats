<?php

namespace LeNats\Subscription;

use Google\Protobuf\Internal\Message;
use LeNats\Contracts\EventDispatcherAwareInterface;
use LeNats\Events\CloudEvent;
use LeNats\Exceptions\SubscriptionException;
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
    /** @var string  */
    protected const MESSAGE_LISTENER = SubscriptionListener::class;
    /** @var string  */
    protected const RESPONSE_LISTENER = SubscriptionResponseListener::class;

    /** @var Subscription[] */
    private static $subscriptions = [];

    /**
     * @var Configuration
     */
    protected $config;

    public function subscribe(Subscription $subscription): ?Subscription
    {
        if (!($sid = $this->send($subscription->getInbox()))) {
            return null;
        }

        $subscription->setSid($sid);
        $this->registerListener($sid, static::MESSAGE_LISTENER, $subscription);

        if ($subscription->getMessageLimit()) {
            $this->unsubscribe($sid, $subscription->getMessageLimit());
        }

        $requestInbox = Inbox::newInbox();

        if (!($sid = $this->send($requestInbox))) {
            return null;
        }

        $subscription->setSid($sid);
        $this->registerListener($sid, static::RESPONSE_LISTENER, $subscription);

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

    /**
     * @param string $sid
     * @param string $handlerClass
     * @param Subscription $subscription
     */
    protected function registerListener(string $sid, string $handlerClass, Subscription $subscription): void
    {
        self::$subscriptions[$sid] = $subscription;

        $handler = $this->container->get($handlerClass);

        $this->dispatcher->addListener($sid, [$handler, 'handle']);
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
     * @throws SubscriptionException
     */
    public function getSubscription($sid): Subscription
    {
        $subscription = self::$subscriptions[$sid] ?? null;

        if ($subscription === null) {
            throw new SubscriptionException('Subscription not found');
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
