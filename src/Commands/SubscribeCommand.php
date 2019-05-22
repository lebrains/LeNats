<?php

namespace LeNats\Commands;

use LeNats\Subscription\Subscriber;
use LeNats\Subscription\Subscription;
use LeNats\Subscription\SubscriptionFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SubscribeCommand extends Command
{
    protected static $defaultName = 'nats:subscribe';

    /**
     * @var Subscriber
     */
    private $subscriber;

    public function __construct(string $name = null, Subscriber $subscriber)
    {
        parent::__construct($name);

        $this->subscriber = $subscriber;
    }

    protected function configure()
    {
        parent::configure();

        $this->addArgument('queue', InputArgument::REQUIRED, 'Nats queue name')
            ->addOption('timeout', 't', InputOption::VALUE_OPTIONAL, 'Subscription working time', 60)
            ->setDescription('Subscribes to queue and dispatches events to your application')
            ->setHelp('bin/console nats:subscribe your.queue.name [-t timeout]');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $subscription = new Subscription($input->getArgument('queue'));
        $subscription->setTimeout($input->getOption('timeout'));

        try {
            $this->subscriber->subscribe($subscription);

            $this->subscriber->run($subscription->getTimeout());

            $output->write('Received:' . $subscription->getReceived(), true);
            $output->write('Processed:' . $subscription->getProcessed(), true);
        } catch (\Exception $e) {
            $output->write('Finished with exception:' . $e->getMessage());
        }
    }
}
