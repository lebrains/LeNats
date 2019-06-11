<?php

namespace LeNats\Tests;

use LeNats\Events\React\Data;
use LeNats\Services\Connection;
use LeNats\Support\Stream;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Socket\Connection as ReactConnection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ConnectionTest extends TestCase
{
    /** @test */
    public function it_creates_connection(): void
    {
        $this->assertTrue($this->getStream()->isConnected());
    }

    /** @test */
    public function it_receives_data()
    {
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $received = false;

        $dispatcher->addListener(Data::class, function () use (&$received) {
            $received = true;
        });

        fwrite($this->resource, 'Test data');
        rewind($this->resource);

        $connection = $this->getContainer()->get(Connection::class);
        $connection->setLoop($this->loop);
        $connection->setStream($this->getStream(), [Data::class]);

        $connection->runTimer('test', 1);

        $this->assertTrue($received);
    }

    /** @test */
    public function it_handles_events()
    {
        $stream = $this->getStream();

        $onEnd = false;
        $stream->on('end', function () use (&$onEnd) {
            $onEnd = true;
        });

        $stream->emit('end');

        $onClose = false;
        $stream->on('close', function () use (&$onClose) {
            $onClose = true;
        });

        unset($stream);

        $this->assertTrue($onClose);
        $this->assertTrue($onEnd);
    }
}
