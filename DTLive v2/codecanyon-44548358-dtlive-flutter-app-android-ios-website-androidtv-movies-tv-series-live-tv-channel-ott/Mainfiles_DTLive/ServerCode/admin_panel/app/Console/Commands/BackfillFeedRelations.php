<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillFeedRelations extends Command
{
    protected $signature = 'feed:backfill-relations {--chunk=500 : Number of content rows per batch}';
    protected $description = 'Backfill indexed category, language, and cast relations for feed cursors';

    public function handle(): int
    {
        $chunk = max(50, (int) $this->option('chunk'));
        $this->backfill('tbl_video', 'video', $chunk);
        $this->backfill('tbl_tv_show', 'tv_show', $chunk);
        $this->info('Feed relation backfill completed.');
        return self::SUCCESS;
    }

    private function backfill(string $sourceTable, string $prefix, int $chunk): void
    {
        if (!DB::getSchemaBuilder()->hasTable($sourceTable)) {
            return;
        }

        $relations = [];
        foreach (['category_id' => 'categories', 'language_id' => 'languages', 'cast_id' => 'cast'] as $column => $suffix) {
            $table = $prefix . '_' . $suffix;
            if (DB::getSchemaBuilder()->hasTable($table)) {
                $relations[$column] = $table;
            }
        }
        if ($relations === []) {
            return;
        }

        DB::table($sourceTable)->select(['id', 'category_id', 'language_id', 'cast_id'])
            ->orderBy('id')->chunkById($chunk, function ($rows) use ($prefix, $relations): void {
                $pending = array_fill_keys(array_values($relations), []);
                foreach ($rows as $row) {
                    $id = (int) $row->id;
                    foreach ($relations as $column => $table) {
                        $taxonomyIds = array_filter(array_map('intval', explode(',', (string) ($row->{$column} ?? ''))));
                        foreach ($taxonomyIds as $taxonomyId) {
                            $pending[$table][] = [
                                $prefix === 'video' ? 'video_id' : 'tv_show_id' => $id,
                                $column => $taxonomyId,
                            ];
                        }
                    }
                }
                foreach ($pending as $table => $rowsToInsert) {
                    if ($rowsToInsert !== []) {
                        DB::table($table)->insertOrIgnore($rowsToInsert);
                    }
                }
                $this->output->write('.');
            });
        $this->newLine();
    }
}
