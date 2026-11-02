<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * MySQL/MariaDB with explicit_defaults_for_timestamp=OFF silently give the
 * first NOT NULL TIMESTAMP column of a table "ON UPDATE CURRENT_TIMESTAMP",
 * so the database overwrote these columns (with its own clock) whenever a
 * row was updated:
 *
 *   lead_assignments.assigned_at, contact_consents.granted_at,
 *   lead_follow_ups.due_at, lead_appointments.starts_at
 *
 * This keeps every column and row, removes the automatic update (an
 * explicit DEFAULT stops the server adding it) and restores the original
 * values from records that were never affected: the row's created_at, and
 * the times written to lead_activities when follow-ups and appointments
 * were scheduled. SQLite (tests) never had the problem; nothing runs there.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'lead_assignments' => 'assigned_at',
        'contact_consents' => 'granted_at',
        'lead_follow_ups' => 'due_at',
        'lead_appointments' => 'starts_at',
    ];

    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach (self::COLUMNS as $table => $column) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
        }

        // From here on these columns only change when the application changes them.
        DB::transaction(function () {
            // Consents: granted when the row was created (rows are never re-granted).
            DB::table('contact_consents')->whereColumn('granted_at', '!=', 'created_at')->update(['granted_at' => DB::raw('created_at')]);

            // Assignments: the latest (re)assignment recorded in the timeline, else when the row was created.
            foreach (DB::table('lead_assignments')->get(['id', 'created_at']) as $row) {
                $assigned = DB::table('lead_activities')->where('lead_assignment_id', $row->id)->where('type', 'assigned')->max('created_at') ?? $row->created_at;
                $lastActivity = DB::table('lead_activities')->where('lead_assignment_id', $row->id)->whereNotNull('actor_id')->max('created_at');
                DB::table('lead_assignments')->where('id', $row->id)->update([
                    'assigned_at' => $assigned,
                    'last_activity_at' => $lastActivity && $lastActivity > $assigned ? $lastActivity : $assigned,
                ]);
            }

            // Follow-ups and appointments: the time recorded when they were scheduled (or last rescheduled).
            foreach (DB::table('lead_activities')->whereIn('type', ['follow_up_created', 'appointment_scheduled', 'appointment_updated'])->orderBy('id')->get(['type', 'properties']) as $activity) {
                $p = json_decode($activity->properties ?? '{}', true) ?: [];
                if ($activity->type === 'follow_up_created' && isset($p['follow_up_id'], $p['due_at'])) {
                    DB::table('lead_follow_ups')->where('id', $p['follow_up_id'])->update(['due_at' => Carbon::parse($p['due_at'])->utc()->toDateTimeString()]);
                }
                $time = $activity->type === 'appointment_scheduled' ? ($p['starts_at'] ?? null) : ($p['to'] ?? null);
                if (str_starts_with($activity->type, 'appointment_') && isset($p['appointment_id']) && $time) {
                    DB::table('lead_appointments')->where('id', $p['appointment_id'])->update(['starts_at' => Carbon::parse($time)->utc()->toDateTimeString()]);
                }
            }
        });
    }

    public function down(): void
    {
        // Restoring the automatic overwrite would only reintroduce the bug.
    }
};
