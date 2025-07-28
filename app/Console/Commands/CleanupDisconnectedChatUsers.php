<?php

namespace App\Console\Commands;

use App\Models\ChatRoomUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupDisconnectedChatUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:cleanup-disconnected {--minutes=5 : Minutes of inactivity before marking user as offline}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup disconnected chat users who have been inactive for specified minutes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $inactiveMinutes = (int) $this->option('minutes');

        $this->info("Starting cleanup of users inactive for {$inactiveMinutes} minutes...");

        try {
            DB::beginTransaction();

            // Find users who haven't been seen for the specified time
            $inactiveUsers = ChatRoomUser::online()
                ->inactiveSince($inactiveMinutes)
                ->with(['user:id,name', 'room:id,name'])
                ->get();

            $cleanedCount = 0;
            foreach ($inactiveUsers as $roomUser) {
                $this->line("Marking {$roomUser->user->name} as offline in room '{$roomUser->room->name}'");
                $roomUser->markAsOffline();
                $cleanedCount++;
            }

            DB::commit();

            $this->info("Cleanup completed successfully. Marked {$cleanedCount} users as offline.");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Failed to cleanup disconnected users: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}