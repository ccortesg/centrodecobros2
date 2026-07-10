<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->call('App\Http\Controllers\TransaccionDomController@ejecutarCron')->dailyAt('07:00');
        $schedule->call('App\Http\Controllers\TransaccionController@revisarStatus')
            ->name('revisar-status-pagos-spei')
            ->everyFiveMinutes()
            ->withoutOverlapping(10);
        $schedule->command('transacciones:sincronizar-status')
            ->dailyAt('00:05')
            ->timezone('America/Hermosillo')
            ->withoutOverlapping(120);
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
