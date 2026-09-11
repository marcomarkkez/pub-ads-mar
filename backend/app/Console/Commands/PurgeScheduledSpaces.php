<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Space;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

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
 *
 * Since the owner's 2026-09-05 correction this check is the ONLY one there is. The
 * endpoint that programs the deletion no longer refuses a listing with live bookings or
 * unsettled payments — it schedules it, unpublishes it, and reports the blockers as the
 * reason a date exists at all. So a due listing arriving here with clients still on it is
 * now the NORMAL case, not a rare race: it is expected to be skipped, run after run, until
 * the last booking finishes and the last payout settles. Weaken this and the guardrail
 * stops existing anywhere.
 *
 * Four outcomes per row, and they are counted apart on purpose:
 *
 *   DELETED   nothing holds it; the row is gone and an audit entry mirrors what it was.
 *   SKIP      a reason that time will clear — a live booking, a payout still moving.
 *   KEPT      a PERMANENT reason (Space::deletionBlockers()'s `permanent` flag): past
 *             bookings reference this listing and their invoices, payments and proofs are
 *             argued from them, so it stays unpublished and is never destroyed. Not a
 *             retry; the answer will be the same every run, forever, and saying "skipped"
 *             would have a person waiting for a date that does not exist.
 *   FAILED    something threw. Caught PER ROW — see the loop.
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
        $kept = 0;
        $failed = 0;

        foreach ($due as $space) {
            // Every listing is its own attempt. A run is a QUEUE, not a transaction: the
            // twenty listings behind a broken one did nothing wrong and their provider is
            // waiting on them. Before this catch existed one row was enough to end the
            // whole run — a listing with completed bookings threw SQLSTATE[23503] out of
            // $space->delete() (bookings.space_id is RESTRICT for every status), the
            // exception escaped the loop, and every listing after it was left untouched
            // with no line of output saying so. Silence that looks exactly like "nothing
            // was due" is the worst shape this failure could have taken.
            try {
                $blockers = $space->deletionBlockers();

                if ($blockers !== []) {
                    // A permanent blocker is not a "come back next month". Reported on its
                    // own line and counted apart, because a skip the provider is waiting out
                    // and a skip that will never end need different answers from them.
                    $permanent = array_filter($blockers, fn (array $b) => $b['permanent'] ?? false);

                    if ($permanent !== []) {
                        $kept++;
                        $this->warn(sprintf(
                            'KEPT  #%d %s — %s',
                            $space->id,
                            $space->name,
                            implode(' ', array_column($blockers, 'message')),
                        ));

                        continue;
                    }

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
            } catch (Throwable $e) {
                // Reported loudly and by id: a row that neither died nor gave a reason is
                // something a person has to go and look at, and they can only look if the
                // run tells them which one it was. The audit write and the delete share a
                // transaction, so a failure here leaves no half-purged listing behind.
                $failed++;
                $this->error(sprintf(
                    'FAILED  #%d %s — %s',
                    $space->id,
                    $space->name,
                    Str::limit(preg_replace('/\s+/', ' ', $e->getMessage()), 300),
                ));
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%s: %d listing(s), %d skipped as still in use, %d kept forever (cannot be destroyed), %d failed.',
            $confirmed ? 'Purged' : 'Dry run (nothing was deleted) — would purge',
            $purged,
            $skipped,
            $kept,
            $failed,
        ));

        if (! $confirmed) {
            $this->comment('Re-run with --confirm to actually delete.');
        }

        // A non-zero exit only for the unexplained ones. A skip and a keep are the command
        // working as designed; a FAILED line is the one thing here that needs a human, and
        // whoever wires this into a script later must be able to see that without reading
        // the scrollback.
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
