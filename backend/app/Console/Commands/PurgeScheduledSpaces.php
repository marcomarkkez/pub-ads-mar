<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Space;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * AD-delguard-08 · §12 · UC-37 — the purge, run BY A HUMAN.
 *
 * Owner 2026-08-03, asked whether this should be a cron: "Manual por el momento en la
 * fase mvp, anótalo para un futuro… Hazlo manual en código con un comando al docker o
 * python." So there is no scheduler here, and none is registered in the kernel. A
 * scheduled deletion sits in `unpublished` until a person runs this and confirms.
 *
 *   php artisan spaces:purge                # dry run — lists what is due, deletes nothing
 *   php artisan spaces:purge --confirm      # actually destroys the rows
 *
 * The blockers are re-checked HERE, at purge time, and not trusted from the moment the
 * deletion was programmed. Days pass between the two: a listing can be booked again
 * while it waits, and the check that ran a month ago is a check about a different world.
 * That is also why a blocked row is SKIPPED and reported rather than failing the run —
 * one listing that got booked must not stop the other twenty from being tidied.
 */
class PurgeScheduledSpaces extends Command
{
    protected $signature = 'spaces:purge {--confirm : Actually delete. Without it this is a dry run.}';

    protected $description = 'Destroy listings whose programmed deletion has come due (manual; there is no cron).';

    public function handle(): int
    {
        $due = Space::whereNotNull('delete_scheduled_at')
            ->where('delete_scheduled_at', '<=', now())
            ->with('user')
            ->get();

        if ($due->isEmpty()) {
            $this->info('Nothing is due for purge.');

            return self::SUCCESS;
        }

        $confirmed = (bool) $this->option('confirm');
        $purged = 0;
        $skipped = 0;

        foreach ($due as $space) {
            $blockers = $space->deletionBlockers();

            if ($blockers !== []) {
                $skipped++;
                $this->warn(sprintf(
                    'SKIP  #%d %s — %s',
                    $space->id,
                    $space->name,
                    implode(' ', array_column($blockers, 'message')),
                ));

                continue;
            }

            if (! $confirmed) {
                $this->line(sprintf('WOULD DELETE  #%d %s (due %s)', $space->id, $space->name, $space->delete_scheduled_at));
                $purged++;

                continue;
            }

            DB::transaction(function () use ($space) {
                // Written BEFORE the delete: the row is about to stop existing, so the
                // mirrored attributes are the only record of what was destroyed.
                AuditLog::record(
                    $space->user,
                    'purge',
                    $space,
                    $space->getAttributes(),
                    null,
                    'programmed deletion executed by spaces:purge (§12 · UC-37)',
                );

                $space->delete();
            });

            $purged++;
            $this->line(sprintf('DELETED  #%d %s', $space->id, $space->name));
        }

        $this->newLine();
        $this->info(sprintf(
            '%s: %d listing(s), %d skipped as still in use.',
            $confirmed ? 'Purged' : 'Dry run (nothing was deleted) — would purge',
            $purged,
            $skipped,
        ));

        if (! $confirmed) {
            $this->comment('Re-run with --confirm to actually delete.');
        }

        return self::SUCCESS;
    }
}
