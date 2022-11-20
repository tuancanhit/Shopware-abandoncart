<?php

namespace AbandonCart\Plugin\Service\ScheduledTask;

use AbandonCart\Plugin\Commands\AbandonCartRemindCommand;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

/**
 * Class AbandonCartHandler
 */
class AbandonCartHandler extends ScheduledTaskHandler
{
    protected AbandonCartRemindCommand $abandonCart;

    public function __construct(EntityRepositoryInterface $scheduledTaskRepository, AbandonCartRemindCommand $abandonCart)
    {
        $this->abandonCart = $abandonCart;
        parent::__construct($scheduledTaskRepository);
    }

    /**
     * @return iterable
     */
    public static function getHandledMessages(): iterable
    {
        return [\AbandonCart\Plugin\Service\ScheduledTask\AbandonCartTask::class];
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function run(): void
    {
        file_put_contents('/Users/william/Develop/Shopware/shopw/var/log/schedule.log',
            sprintf("%s: AbandonCartHandler running\r\n", date('Y-m-d H:i:s')));
        $this->abandonCart->process();
    }
}
