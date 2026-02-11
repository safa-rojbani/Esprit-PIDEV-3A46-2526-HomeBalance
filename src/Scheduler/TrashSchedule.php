<?php

namespace App\Scheduler;

use App\Message\PurgeTrashMessage;
use Cron\CronExpression;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Component\Scheduler\Trigger\CronExpressionTrigger;

#[AsSchedule('default')]
final class TrashSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(RecurringMessage::trigger(
                new CronExpressionTrigger(
                    CronExpression::factory('0 0 * * *')
                ),
                new PurgeTrashMessage()
            ));
    }
}
