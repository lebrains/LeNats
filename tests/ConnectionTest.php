<?php

namespace LeNats\Tests;

use LeNats\Events\CloudEvent;
use LeNats\Subscription\Publisher;
use LeNats\Subscription\Subscriber;
use LeNats\Subscription\Subscription;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ConnectionTest extends KernelTestCase
{
    /** @test */
    public function it_creates_stream()
    {
        static::bootKernel();
        $container = self::$container;

        $subscriber = $container->get(Subscriber::class);
        $subscription = new Subscription(getenv('TEST_QUEUE_NAME'));

        $subscription->setTimeout(10);
        $subscriber->subscribe($subscription);
    }

    /** @test */
    public function it_publish_data_to_queue()
    {
        static::bootKernel();
        $container = self::$container;

        $publisher = $container->get(Publisher::class);

        $event = new CloudEvent();
        $event->setType('skeleton.shtrikul.event.created');
        $event->setId('asd');
        $data = [
            'id' => 'sdfsdfsf sdf sdf',
            'key' => 'Test 1'
        ];
        $event->setData($data);

        $publisher->publish($event);
    }
}
