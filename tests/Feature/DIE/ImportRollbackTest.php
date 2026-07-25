<?php

declare(strict_types=1);

use App\Platform\DataExchange\Actions\CommitImportBatchAction;
use App\Support\Exceptions\BusinessRuleException;
use App\Support\Uuid\PublicId;
use Illuminate\Support\Facades\DB;

/**
 * FR-DIE-002/005, prompt §18 — import rollback on validation failure; no partial
 * commit.
 */
beforeEach(fn () => $this->action = app(CommitImportBatchAction::class));

function seedBatch(array $rows): string
{
    $tmpl = DB::table('import_templates')->insertGetId(['public_id' => PublicId::generate(), 'key' => 'k-'.random_int(1, 99999), 'target' => 'demo', 'created_at' => now(), 'updated_at' => now()]);
    $public = PublicId::generate();
    $batch = DB::table('import_batches')->insertGetId(['public_id' => $public, 'import_template_id' => $tmpl, 'status' => 'validated', 'total_rows' => count($rows), 'created_at' => now(), 'updated_at' => now()]);
    foreach ($rows as $i => $r) {
        DB::table('import_rows')->insert(['import_batch_id' => $batch, 'row_number' => $i + 1, 'data' => json_encode($r['data']), 'status' => $r['status'], 'created_at' => now(), 'updated_at' => now()]);
    }

    return $public;
}

it('rolls back the whole batch when any row is invalid (no partial commit)', function () {
    $applied = [];
    $public = seedBatch([
        ['data' => ['name' => 'A'], 'status' => 'valid'],
        ['data' => ['name' => 'B'], 'status' => 'invalid'], // one bad row
    ]);

    expect(fn () => $this->action->execute($public, 1, function ($row) use (&$applied) {
        $applied[] = $row;
    }))
        ->toThrow(BusinessRuleException::class);

    // No rows applied; batch marked failed.
    expect($applied)->toBeEmpty();
    expect(DB::table('import_batches')->where('public_id', $public)->value('status'))->toBe('failed');
});

it('commits all valid rows atomically on success', function () {
    $applied = 0;
    $public = seedBatch([
        ['data' => ['name' => 'A'], 'status' => 'valid'],
        ['data' => ['name' => 'B'], 'status' => 'valid'],
    ]);

    $this->action->execute($public, 1, function ($row) use (&$applied) {
        $applied++;
    });

    expect($applied)->toBe(2);
    expect(DB::table('import_batches')->where('public_id', $public)->value('status'))->toBe('committed');
    expect(DB::table('import_rows')->where('status', 'committed')->count())->toBe(2);
});

it('rolls back when a row throws during apply (no partial import)', function () {
    $public = seedBatch([
        ['data' => ['name' => 'A'], 'status' => 'valid'],
        ['data' => ['name' => 'B'], 'status' => 'valid'],
    ]);

    expect(fn () => $this->action->execute($public, 1, function ($row) {
        if (json_decode($row->data)->name === 'B') {
            throw new RuntimeException('apply failed');
        }
    }))->toThrow(BusinessRuleException::class);

    // Transaction rolled back — no committed rows.
    expect(DB::table('import_rows')->where('status', 'committed')->count())->toBe(0);
    expect(DB::table('import_batches')->where('public_id', $public)->value('status'))->toBe('rolled_back');
});
