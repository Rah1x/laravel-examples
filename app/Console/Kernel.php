<?php
/**
 * I Use this to (1) run schedules; (2)initiate queue jobs at a schedule via laravel queues (managed by supervisord on the server)
*/

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

/**
 * V. Important: Make sure no command has `exit` or `die` otherwise next command will not execute, instead they should `return`
*/
class Kernel extends ConsoleKernel
{
    protected $commands = [];

    protected function schedule(Schedule $schedule)
    {
        #/ Called directly
        $schedule->call('\App\Http\Controllers\lorem1\ipsum1\dolor::sitamet')->dailyAt('01:00');
        $schedule->call('\App\Http\Controllers\lorem3\ipsum3\dolor::sitamet')->weeklyOn(1, '16:00'); //monday 4pm

        # Jobs Called via enclosure
        $schedule->call(function() {
            \App\Jobs\lorem_job1::dispatch()->onQueue('ipsum_queue_1');
        })->monthlyOn(1, '01:00'); //1st of every month at 1am
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}