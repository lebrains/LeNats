<?php

namespace LeNats\Commands;

use LeNats\Subscription\Subscriber;
use LeNats\Subscription\Subscription;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SubscribeCommand extends Command
{
    /** @var string */
    protected static $defaultName = 'nats:subscribe';

    /**
     * @var Subscriber
     */
    private $subscriber;

    public function __construct(Subscriber $subscriber)
    {
        parent::__construct();

        $this->subscriber = $subscriber;
    }

    protected function configure(): void
    {
        parent::configure();

        $this->addArgument('queue', InputArgument::REQUIRED, 'Nats queue name')
            ->addOption('timeout', 't', InputOption::VALUE_OPTIONAL, 'Subscription working time', 60)
            ->setDescription('Subscribes to queue and dispatches events to your application')
            ->setHelp('bin/console nats:subscribe your.queue.name [-t timeout]');
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $queue = $input->getArgument('queue');
        $timeout = $input->getOption('timeout');

        if ($queue === null || is_array($queue)) {
            $output->write('Argument `queue` must be type of string');

            return 1;
        }

        if ($timeout === null || is_array($timeout)) {
            $output->write('Argument `timeout` must be type of int');

            return 1;
        }

        $subscription = new Subscription((string)$queue);
        $subscription->setTimeout((int)$timeout);

        try {
            $this->subscriber->subscribe($subscription)->run();

            $output->write('Received:' . $subscription->getReceived(), true);
            $output->write('Processed:' . $subscription->getProcessed(), true);
        } catch (\Throwable $e) {
            $output->write('Finished with exception:' . $e->getMessage());

            return 1;
        }

        return null;
    }
}
