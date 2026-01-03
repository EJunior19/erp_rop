    <?php

    use Illuminate\Foundation\Application;
    use Illuminate\Console\Scheduling\Schedule;
    use Illuminate\Foundation\Configuration\Middleware;

    use App\Jobs\ProcessCreditNotifications;
    use App\Jobs\UpcomingCreditReminders;

    return Application::configure(basePath: dirname(__DIR__))
        ->withRouting(
            web: __DIR__.'/../routes/web.php',
            api: __DIR__.'/../routes/api.php',
            commands: __DIR__.'/../routes/console.php',
            health: '/up',
        )
        ->withExceptions(function () {
            //
        })
        ->withMiddleware(function (Middleware $middleware) {
            // ⬇️ SOLO este alias. Nada de 'files' aquí.
            $middleware->alias([
                'finance.pin' => \App\Http\Middleware\FinancePinMiddleware::class,
                'force.password'  => \App\Http\Middleware\ForcePasswordChange::class,
            ]);

        })
        ->withSchedule(function (Schedule $schedule) {
            $schedule->job(new ProcessCreditNotifications())
                ->cron('0 */8 * * *')
                ->withoutOverlapping()
                ->onOneServer();

            $schedule->job(new UpcomingCreditReminders())
                ->dailyAt('08:10')
                ->withoutOverlapping()
                ->onOneServer();
        })
        ->create();
