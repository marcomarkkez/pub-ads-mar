<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Proof;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * AD-delguard-09 · §3/§12 · UC-37 — the account purge, run BY A HUMAN.
 *
 * The sibling of `spaces:purge` and deliberately the same command in every respect that
 * matters, because it is the same rule at a different scale (owner 2026-08-03 on cron:
 * "Manual por el momento en la fase mvp… Hazlo manual en código con un comando").
 *
 *   php artisan accounts:purge              # dry run — lists what is due, deletes nothing
 *   php artisan accounts:purge --confirm    # actually destroys owner + account
 *
 * EVERY blocker is re-asked HERE, disputes included, and none of them is trusted from the
 * moment the deletion was programmed. Weeks pass in between: a client can reject a proof
 * or Support can raise `payment_held` while the account sits in `unpublished`, and the
 * check that ran a month ago is a check about a different world. A blocked account is
 * SKIPPED and reported, never fatal — one account that acquired a dispute must not stop
 * the other twenty from being tidied.
 *
 * The owner's proofs are destroyed here and only here, and only if
 * `proof_loss_confirmed_at` is set: `confirm_proof_loss` was given at schedule time and
 * persisted precisely so this command can point at it instead of assuming it.
 */
class PurgeScheduledAccounts extends Command
{
    protected $signature = 'accounts:purge {--confirm : Actually delete. Without it this is a dry run.}';

    protected $description = 'Destroy accounts whose programmed deletion has come due (manual; there is no cron).';

    public function handle(): int
    {
        $due = Account::query()
            ->whereNotNull('delete_scheduled_at')
            ->where('delete_scheduled_at', '<=', now())
            ->with('owner')
            ->get();

        if ($due->isEmpty()) {
            $this->info('Nothing is due for purge.');

            return self::SUCCESS;
        }

        $confirmed = (bool) $this->option('confirm');
        $purged = 0;
        $skipped = 0;

        foreach ($due as $account) {
            $blockers = $account->deletionBlockers();

            $proofCount = $account->ownerProofCount();

            // An unacknowledged proof loss is itself a blocker. The owner's consent is
            // what makes destroying their own evidence legitimate; if the schedule was
            // programmed before there were any proofs, the answer is to ask again, not
            // to guess.
            if ($proofCount > 0 && $account->proof_loss_confirmed_at === null) {
                $blockers[] = [
                    'kind' => 'unconfirmed_proof_loss',
                    'count' => $proofCount,
                    'message' => $proofCount . ' proof(s) would be destroyed and the owner never confirmed that loss.',
                ];
            }

            if ($blockers !== []) {
                $skipped++;
                $this->warn(sprintf(
                    'SKIP  #%d %s — %s',
                    $account->id,
                    $account->name,
                    implode(' ', array_column($blockers, 'message')),
                ));

                continue;
            }

            if (! $confirmed) {
                $this->line(sprintf('WOULD DELETE  #%d %s (due %s)', $account->id, $account->name, $account->delete_scheduled_at));
                $purged++;

                continue;
            }

            DB::transaction(function () use ($account, $proofCount) {
                $owner = $account->owner;

                // Written BEFORE the delete: the rows are about to stop existing, so the
                // mirrored attributes are the only record of what was destroyed.
                AuditLog::record(
                    $owner,
                    'purge',
                    $account,
                    [
                        'account' => $account->getAttributes(),
                        'owner_user_id' => $owner?->id,
                        'owner_email' => $owner?->email,
                        'proofs' => $proofCount,
                    ],
                    null,
                    'programmed deletion executed by accounts:purge (§3 · §12 · UC-37)',
                );

                if ($owner !== null) {
                    if ($proofCount > 0) {
                        Proof::where('uploaded_by_user_id', $owner->id)->delete();
                    }

                    // Sanctum tokens carry no FK to users — nothing would clean them up.
                    $owner->tokens()->delete();

                    // The user first: users.account_id is RESTRICT, so the account row
                    // can only go once nothing points at it.
                    $owner->delete();
                }

                $account->delete();
            });

            $purged++;
            $this->line(sprintf('DELETED  #%d %s', $account->id, $account->name));
        }

        $this->newLine();
        $this->info(sprintf(
            '%s: %d account(s), %d skipped as still in use or disputed.',
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
