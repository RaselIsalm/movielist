<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['tbl_video', 'tbl_tv_show', 'tbl_tv_show_video'] as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                    if (!Schema::hasColumn($table, 'thumbnail_webp')) {
                        $blueprint->string('thumbnail_webp')->nullable()->after('thumbnail');
                    }
                    if (!Schema::hasColumn($table, 'landscape_webp')) {
                        $blueprint->string('landscape_webp')->nullable()->after('landscape');
                    }
                });
            }
        }

        $this->createRelationTable('video_categories', 'video_id', 'category_id');
        $this->createRelationTable('video_languages', 'video_id', 'language_id');
        $this->createRelationTable('video_cast', 'video_id', 'cast_id');
        $this->createRelationTable('tv_show_categories', 'tv_show_id', 'category_id');
        $this->createRelationTable('tv_show_languages', 'tv_show_id', 'language_id');
        $this->createRelationTable('tv_show_cast', 'tv_show_id', 'cast_id');

        if (Schema::hasTable('tbl_video')) {
            Schema::table('tbl_video', function (Blueprint $table): void {
                $table->index(['status', 'video_type', 'id'], 'video_status_type_id_idx');
                $table->index(['status', 'video_type', 'created_at', 'id'], 'video_feed_created_idx');
                $table->index(['status', 'video_type', 'total_view', 'id'], 'video_feed_view_idx');
            });
        }
        if (Schema::hasTable('tbl_tv_show')) {
            Schema::table('tbl_tv_show', function (Blueprint $table): void {
                $table->index(['status', 'video_type', 'id'], 'show_status_type_id_idx');
                $table->index(['status', 'video_type', 'created_at', 'id'], 'show_feed_created_idx');
                $table->index(['status', 'video_type', 'total_view', 'id'], 'show_feed_view_idx');
            });
        }
        if (Schema::hasTable('tbl_home_section')) {
            Schema::table('tbl_home_section', function (Blueprint $table): void {
                $table->index(['is_home_screen', 'status', 'sort_order', 'id'], 'home_section_feed_idx');
                $table->index(['is_home_screen', 'type_id', 'status', 'sort_order', 'id'], 'home_section_type_feed_idx');
            });
        }
        if (Schema::hasTable('tbl_video_watch')) {
            Schema::table('tbl_video_watch', function (Blueprint $table): void {
                $table->index(['user_id', 'is_kids_profile', 'status', 'id'], 'video_watch_feed_idx');
            });
        }
        if (Schema::hasTable('tbl_bookmark')) {
            Schema::table('tbl_bookmark', function (Blueprint $table): void {
                $table->index(
                    ['user_id', 'is_kids_profile', 'status', 'video_type', 'sub_video_type', 'video_id'],
                    'bookmark_feed_lookup_idx'
                );
            });
        }
    }

    private function createRelationTable(string $tableName, string $contentColumn, string $taxonomyColumn): void
    {
        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table) use ($contentColumn, $taxonomyColumn): void {
            $table->unsignedInteger($contentColumn);
            $table->unsignedInteger($taxonomyColumn);
            $table->primary([$taxonomyColumn, $contentColumn]);
            $table->index($contentColumn);
        });
    }

    public function down(): void
    {
        foreach (['video_categories', 'video_languages', 'video_cast', 'tv_show_categories', 'tv_show_languages', 'tv_show_cast'] as $table) {
            Schema::dropIfExists($table);
        }

        foreach (['tbl_video', 'tbl_tv_show', 'tbl_tv_show_video'] as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    foreach (['thumbnail_webp', 'landscape_webp'] as $column) {
                        if (Schema::hasColumn($blueprint->getTable(), $column)) {
                            $blueprint->dropColumn($column);
                        }
                    }
                });
            }
        }

        foreach ([
            'tbl_video' => ['video_status_type_id_idx', 'video_feed_created_idx', 'video_feed_view_idx'],
            'tbl_tv_show' => ['show_status_type_id_idx', 'show_feed_created_idx', 'show_feed_view_idx'],
            'tbl_home_section' => ['home_section_feed_idx', 'home_section_type_feed_idx'],
            'tbl_video_watch' => ['video_watch_feed_idx'],
            'tbl_bookmark' => ['bookmark_feed_lookup_idx'],
        ] as $table => $indexes) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $blueprint) use ($indexes): void {
                    foreach ($indexes as $index) {
                        $blueprint->dropIndex($index);
                    }
                });
            }
        }
    }
};
