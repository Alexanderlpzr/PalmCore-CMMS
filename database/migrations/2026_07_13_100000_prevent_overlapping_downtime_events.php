<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Two paros of the same asset cannot share an hour.
 *
 * The service used to check this only against *open* paros, so nothing stopped a
 * supervisor from typing up two closed paros of the prensa that overlapped — and
 * every hour they shared was billed twice to the plant's lost hours. The number
 * that reaches gerencia has to be defensible, so the rule lives in the database:
 * an overlapping paro is not «rejected by the app», it is impossible to write.
 *
 * An open paro (`ended_at IS NULL`) is treated as running to infinity: nothing can
 * be recorded for that asset until somebody closes it.
 *
 * Equipment paros and plant-wide paros are two separate ranges — a power cut can
 * legitimately happen while a pump is being repaired. They do not double count
 * because plant lost hours are the *union* of the intervals, not their sum.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Exclusion constraints need gist over a uuid equality operator.
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        $this->assertNoExistingOverlaps();

        DB::statement(
            "ALTER TABLE equipment_downtime_events
             ADD CONSTRAINT downtime_events_equipment_no_overlap
             EXCLUDE USING gist (
                 equipment_id WITH =,
                 tstzrange(started_at, COALESCE(ended_at, 'infinity'::timestamptz)) WITH &&
             ) WHERE (equipment_id IS NOT NULL)"
        );

        DB::statement(
            "ALTER TABLE equipment_downtime_events
             ADD CONSTRAINT downtime_events_plant_no_overlap
             EXCLUDE USING gist (
                 plant_id WITH =,
                 tstzrange(started_at, COALESCE(ended_at, 'infinity'::timestamptz)) WITH &&
             ) WHERE (equipment_id IS NULL)"
        );

        // A paro that ends before it starts is not a paro. tstzrange would have
        // thrown anyway; saying so explicitly gives the error a name.
        DB::statement(
            'ALTER TABLE equipment_downtime_events
             ADD CONSTRAINT downtime_events_chronology_check
             CHECK (ended_at IS NULL OR ended_at >= started_at)'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE equipment_downtime_events DROP CONSTRAINT IF EXISTS downtime_events_equipment_no_overlap');
        DB::statement('ALTER TABLE equipment_downtime_events DROP CONSTRAINT IF EXISTS downtime_events_plant_no_overlap');
        DB::statement('ALTER TABLE equipment_downtime_events DROP CONSTRAINT IF EXISTS downtime_events_chronology_check');
    }

    /**
     * Refuse to enforce integrity over data that already violates it.
     *
     * Deleting or silently truncating somebody's paro log is not this migration's
     * call to make: the events are historical facts and the overlap may be the
     * interesting part. If this fires, the offending pairs have to be reconciled by
     * hand first — the exception names them.
     */
    private function assertNoExistingOverlaps(): void
    {
        $overlaps = DB::select(
            "SELECT a.id AS a_id, b.id AS b_id, a.started_at AS a_start, b.started_at AS b_start
             FROM equipment_downtime_events a
             JOIN equipment_downtime_events b
               ON a.id < b.id
              AND a.equipment_id IS NOT DISTINCT FROM b.equipment_id
              AND (a.equipment_id IS NOT NULL OR a.plant_id IS NOT DISTINCT FROM b.plant_id)
              AND tstzrange(a.started_at, COALESCE(a.ended_at, 'infinity'::timestamptz))
                  && tstzrange(b.started_at, COALESCE(b.ended_at, 'infinity'::timestamptz))
             LIMIT 20"
        );

        if ($overlaps === []) {
            return;
        }

        $pairs = implode(', ', array_map(
            fn (object $row): string => "{$row->a_id}↔{$row->b_id}",
            $overlaps,
        ));

        throw new RuntimeException(
            'No se puede activar la restricción de paros solapados: ya existen paros que se cruzan '
            .'y sus horas se están contando dos veces. Reconcílielos antes de migrar. Pares: '.$pairs
        );
    }
};
