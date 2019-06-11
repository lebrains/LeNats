<?php

namespace LeNats\Subscription;

use Google\Protobuf\Internal\Message;
use LeNats\Events\CloudEvent;
use LeNats\Exceptions\ConnectionException;
use LeNats\Exceptions\StreamException;
use LeNats\Exceptions\SubscriptionException;
use LeNats\Exceptions\SubscriptionNotFoundException;
use LeNats\Listeners\Responses\SubscriptionResponseListener;
use LeNats\Listeners\SubscriptionListener;
use NatsStreamingProtocol\Ack;
use NatsStreamingProtocol\StartPosition;
use NatsStreamingProtocol\SubscriptionRequest;
use NatsStreamingProtocol\UnsubscribeRequest;

class Subscriber extends SubscriptionMessageStreamer
{
    /** @var string[] */
    private static $unsubscribed = [];

    /**
     * @param  CloudEvent          $event
     * @throws StreamException
     * @throws ConnectionException
     */
    public function acknowledge(CloudEvent $event): void
    {
        $subscription = $event->getSubscription();
        $subscription->incrementProcessed();

        $request = new Ack();
        $request->setSubject($subscription->getSubject());
        $request->setSequence($event->getSequenceId());

        $this->getStream()->publish($subscription->getAcknowledgeInbox(), $request);
    }

    /**
     * @throws StreamException
     * @throws SubscriptionNotFoundException
     * @throws ConnectionException
     */
    public function unsubscribeAll(): void
    {
        foreach ($this->getSubscriptions() as $sid => $subscription) {
            $this->unsubscribe($sid);
        }
    }

    /**
     * @param  string                        $sid
     * @param  int|null                      $quantity
     * @throws StreamException
     * @throws SubscriptionNotFoundException
     * @throws ConnectionException
     */
    public function unsubscribe(string $sid, ?int $quantity = null): void
    {
        if (!in_array($sid, self::$unsubscribed, true)) {
            parent::unsubscribe($sid, $quantity);
        }

        if ($quantity !== null) {
            self::$unsubscribed[] = $sid;

            return;
        }

        if (!array_key_exists($sid, $this->getSubscriptions())) {
            return;
        }

        $subscription = $this->getSubscription($sid);

        if (!$subscription->hasAcknowledgeInbox()) {
            return;
        }

        $config = $this->connection->getConfig();

        $request = new UnsubscribeRequest();
        $request->setSubject($subscription->getSubject());
        $request->setInbox($subscription->getAcknowledgeInbox());
        if ($subscription->getStartPosition() === StartPosition::NewOnly) {
            $request->setDurableName($config->getClientId());
        }

        $request->setClientID($config->getClientId());

        $this->getStream()->publish(
            $subscription->isUnsubscribe() ? $config->getUnsubRequests() : $config->getCloseRequests(),
            $request
        );

        $this->reset($subscription->getSid());
    }

    protected function getPublishSubject(Subscription $subscription): string
    {
        return $this->connection->getConfig()->getSubRequests();
    }

    /**
     * @param  Subscription          $subscription
     * @throws SubscriptionException
     * @return Message
     */
    protected function getRequest(Subscription $subscription): Message
    {
        $request = new SubscriptionRequest();

        $request->setSubject($subscription->getSubject());

        $request->setQGroup($subscription->getGroup() ?? '');
        $request->setClientID($this->connection->getConfig()->getClientId());
        $request->setAckWaitInSecs($subscription->getAcknowledgeWait());
        $request->setMaxInFlight($subscription->getMaxInFlight());
        $request->setDurableName($this->connection->getConfig()->getClientId());
        $request->setInbox($subscription->getInbox());
        $request->setStartPosition($subscription->getStartPosition());

        if ($subscription->getStartPosition() === StartPosition::SequenceStart) {
            if ($subscription->getStartSequence() === null) {
                throw new SubscriptionException(
                    'Start sequence number must be defined with start position by sequence'
                );
            }

            $request->setStartSequence($subscription->getStartSequence());
        } elseif ($subscription->getStartPosition() === StartPosition::TimeDeltaStart) {
            if ($subscription->getTimeDeltaStart() === null) {
                throw new SubscriptionException('Time delta start must be defined with start position by time');
            }

            $request->setStartTimeDelta($subscription->getTimeDeltaStart());
        }

        return $request;
    }

    protected function getMessageListenerClass(): string
    {
        return SubscriptionListener::class;
    }

    protected function getResponseListenerClass(): string
    {
        return SubscriptionResponseListener::class;
    }
}
