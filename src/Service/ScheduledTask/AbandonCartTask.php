<?php
namespace AbandonCart\Plugin\Service\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * Class AbandonCartTask
 */
class AbandonCartTask extends ScheduledTask
{

    /**
     * @return string
     */
    public static function getTaskName(): string
    {
        return 'abandoncart';
    }

    /**
     * @return int
     */
    public static function getDefaultInterval(): int
    {
        return 60;
    }
}
