<?php

namespace App\Scheduler;

use App\Message\ApplyTaskPenaltiesMessage;
use App\Message\PurgeTrashMessage;
use App\Message\RunWeeklyInsightsJobMessage;
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
            ))
            ->add(RecurringMessage::trigger(
                new CronExpressionTrigger(
                    CronExpression::factory('10 0 * * *')
                ),
                new ApplyTaskPenaltiesMessage()
            ))
            ->add(RecurringMessage::trigger(
                new CronExpressionTrigger(
                    CronExpression::factory('0 22 * * 0')
                ),
                new RunWeeklyInsightsJobMessage()
            ));
    }
}
