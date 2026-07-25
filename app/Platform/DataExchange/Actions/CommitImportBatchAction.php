<?php

declare(strict_types=1);

namespace App\Platform\DataExchange\Actions;

use App\Domain\Governance\Services\AuditRecorder;
use App\Support\Clock\Clock;
use App\Support\Exceptions\BusinessRuleException;
use Illuminate\Support\Facades\DB;

/**
 * Commits a validated import batch, or rolls the whole batch back on any
 * validation failure (FR-DIE-002/005, prompt §18 import-rollback test).
 *
 * The batch has already been staged, validated and previewed. Commit runs inside
 * one transaction: if any row is still invalid (or a row fails during apply), the
 * transaction rolls back so NO partial data is written, and the batch is marked
 * failed. On success every valid row is applied atomically and the batch is
 * committed. The source file, initiator, approval and result are recorded
 * (FR-DIE-006).
 */
final class CommitImportBatchAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * @param  callable(object): void  $applyRow  applies one valid import row
     */
    public function execute(string $batchPublicId, int $approverId, callable $applyRow): void
    {
        $batch = DB::table('import_batches')->where('public_id', $batchPublicId)->first();
        if ($batch === null) {
            throw new BusinessRuleException('import.commit_conflict', 'Import batch not found.');
        }
        if ($batch->status !== 'validated' && $batch->status !== 'previewed') {
            throw new BusinessRuleException('import.commit_conflict', 'Batch is not ready to commit.');
        }

        // Any invalid row blocks the whole commit (full rollback semantics).
        $invalidCount = DB::table('import_rows')->where('import_batch_id', $batch->id)->where('status', 'invalid')->count();
        if ($invalidCount > 0) {
            DB::table('import_batches')->where('id', $batch->id)->update(['status' => 'failed', 'error_rows' => $invalidCount, 'updated_at' => Clock::now()]);
            $this->audit->record(action: 'import.rejected', resourceType: 'import_batch', resourcePublicId: $batchPublicId, outcome: 'failed');

            throw new BusinessRuleException('import.validation_failed', 'One or more rows failed validation; the import was rolled back.');
        }

        try {
            DB::transaction(function () use ($batch, $applyRow, $approverId, $batchPublicId): void {
                $rows = DB::table('import_rows')->where('import_batch_id', $batch->id)->where('status', 'valid')->orderBy('row_number')->get();

                foreach ($rows as $row) {
                    // A throw here rolls back the whole transaction — no partial import.
                    $applyRow($row);
                    DB::table('import_rows')->where('id', $row->id)->update(['status' => 'committed', 'updated_at' => Clock::now()]);
                }

                DB::table('import_batches')->where('id', $batch->id)->update([
                    'status' => 'committed',
                    'approved_by' => $approverId,
                    'updated_at' => Clock::now(),
                ]);

                $this->audit->record(action: 'import.committed', resourceType: 'import_batch', resourcePublicId: $batchPublicId, after: ['rows' => $rows->count()]);
            });
        } catch (\Throwable $e) {
            DB::table('import_batches')->where('id', $batch->id)->update(['status' => 'rolled_back', 'updated_at' => Clock::now()]);
            $this->audit->record(action: 'import.rolled_back', resourceType: 'import_batch', resourcePublicId: $batchPublicId, outcome: 'failed');

            throw new BusinessRuleException('import.validation_failed', 'Import failed during commit and was rolled back.', [], $e instanceof \Exception ? $e : null);
        }
    }
}
