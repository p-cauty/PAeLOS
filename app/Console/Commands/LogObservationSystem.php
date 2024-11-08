<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\MonitoringAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class LogObservationSystem extends Command
{
    const string LOCAL_LOG_CACHE = 'error.log';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'paelos:launch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'PHP Apache error Log Observation System';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $cached_modified = Storage::exists(self::LOCAL_LOG_CACHE) ?
            Storage::lastModified(self::LOCAL_LOG_CACHE) :
            0;
        $midnight = now()->startOfDay()->timestamp;

        $cached_logs = $cached_modified > $midnight ?
            (string) Storage::get(self::LOCAL_LOG_CACHE) :
            '';
        $current_logs = file_exists(config('app.log_file_path')) ?
            (string) file_get_contents(config('app.log_file_path')) :
            '';

        $cached_hash = md5($cached_logs);
        $current_hash = md5($current_logs);

        if ($cached_hash === $current_hash) {
            $this->warn('No new logs to process.');
            return;
        }

        $new_logs = str_replace($cached_logs, '', $current_logs);
        $logs = explode("\n", $new_logs);

        $to_notify = [];
        foreach ($logs as $log) {
            if (str_contains($log, ' PHP ')) {
                $to_notify[] = $log;
            }
        }

        if (empty($to_notify)) {
            $this->warn('No PHP errors to process.');
            return;
        }

        User::find(1)->notify(new MonitoringAlert($to_notify));

        $this->info('PHP errors have been processed and notified.');

        Storage::put('error.log', $current_logs);
        $this->info('Logs have been cached.');
    }
}
