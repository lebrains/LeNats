<?php

namespace LeNats\Commands;

use LeNats\Subscription\Subscriber;
use LeNats\Subscription\Subscription;
use Rakit\Validation\Validator;
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

    /**
     * @var Validator
     */
    private $validator;

    public function __construct(Subscriber $subscriber)
    {
        parent::__construct();

        $this->validator = new Validator();

        $this->subscriber = $subscriber;
    }

    protected function configure(): void
    {
        parent::configure();

        $this->addArgument('queue', InputArgument::REQUIRED, 'Nats queue name')
            ->addOption('timeout', 't', InputOption::VALUE_OPTIONAL, 'Subscription time out (default: 60)', 60)
            ->addOption(
                'start-position',
                'p',
                InputOption::VALUE_OPTIONAL,
                'Start position (0 - new only, 1 - last received, 2 - time delta start, 3 - from specific sequence position, 4 - from first)',
                0
            )
            ->addOption(
                'start-sequence',
                'i',
                InputOption::VALUE_OPTIONAL,
                'Start sequence number (required if start-position = 3)'
            )
            ->addOption(
                'start-time',
                's',
                InputOption::VALUE_OPTIONAL,
                'Time delta start (required if start-position = 2)'
            )
            ->addOption(
                'max-in-flight',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Maximum inflight messages without an ack allowed',
                1024
            )
            ->addOption(
                'group',
                'g',
                InputOption::VALUE_OPTIONAL,
                'Group name (if needs to processing one queue in several process with on clientID)'
            )
            ->addOption(
                'ack-wait',
                'a',
                InputOption::VALUE_OPTIONAL,
                'Timeout for receiving an ack from the client',
                30
            )
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limit received messages')
            ->addOption('unsubscribe', 'u', InputOption::VALUE_OPTIONAL, 'Prefer unsubscribe nor close command', 0)
            ->setDescription('Subscribes to queue and dispatches events to your application')
            ->setHelp('bin/console nats:subscribe your.queue.name [-t timeout]');
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $queue = $input->getArgument('queue');

        if ($queue === null || !is_string($queue)) {
            $output->write('Queue name must be defined');

            return 1;
        }

        $subscription = new Subscription($queue);

        if (!$this->configureSubscription($subscription, $input, $output)) {
            return 1;
        }

        try {
            $this->subscriber->subscribe($subscription);

            $output->write('Received:' . $subscription->getReceived(), true);
            $output->write('Processed:' . $subscription->getProcessed(), true);
        } catch (\Throwable $e) {
            $output->write('Finished with exception:' . $e->getMessage());

            return 1;
        }

        return null;
    }

    private function configureSubscription(
        Subscription $subscription,
        InputInterface $input,
        OutputInterface $output
    ): bool {
        $validation = $this->validator->validate($input->getOptions(), [
            'timeout'        => 'integer|min:0',
            'start-position' => 'integer|in:0,1,2,3,4',
            'start-sequence' => 'required_if:start-position,3|integer|min:0',
            'start-time'     => 'required_if:start-position,2|integer|min:0',
            'max-in-flight'  => 'integer|min:0',
            'group'          => 'alpha_dash|max:128',
            'ack-wait'       => 'integer|min:0',
            'limit'          => 'integer|min:0',
            'unsubscribe'    => 'integer|in:0,1',
        ]);

        if ($validation->fails()) {
            foreach ($validation->errors()->all() as $message) {
                $output->writeln(sprintf('Error: %s', $message));
            }

            return false;
        }

        $subscription->setTimeout($input->getOption('timeout'));

        if ($input->hasOption('start-position')) {
            $subscription->setStartPosition((int)$input->getOption('start-position'));
        }

        if ($input->hasOption('start-sequence')) {
            $subscription->setStartSequence((int)$input->getOption('start-sequence'));
        }

        if ($input->hasOption('start-time')) {
            $subscription->setTimeDeltaStart((int)$input->getOption('start-time'));
        }

        if ($input->hasOption('max-in-flight')) {
            $subscription->setMaxInFlight((int)$input->getOption('max-in-flight'));
        }

        if ($input->hasOption('group')) {
            $subscription->setGroup($input->getOption('group'));
        }

        if ($input->hasOption('ack-wait')) {
            $subscription->setAcknowledgeWait((int)$input->getOption('ack-wait'));
        }

        if ($input->hasOption('limit')) {
            $subscription->setMessageLimit((int)$input->getOption('limit'));
        }

        if ($input->hasOption('unsubscribe')) {
            $subscription->setUnsubscribe((bool)$input->getOption('unsubscribe'));
        }

        return true;
    }
}
