<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Table;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ReleaseOccupiedTables extends Command
{
    protected $signature = 'booking:release-tables';
    protected $description = 'Giải phóng các bàn đã hết giờ booking và cập nhật lại trạng thái';

    public function handle(): int
    {
        $now = Carbon::now();
        $c1Id = Table::where('code', 'C1')->value('id');

        $tables = Table::where('status', 'occupied')->get();
        $released = 0;

        foreach ($tables as $table) {
            if ($this->hasActiveBooking($table, $now, $c1Id)) {
                continue;
            }

            $table->status = 'free';
            $table->save();
            $this->info("Released table {$table->code}");
            $released++;
        }

        $this->info("Released {$released} table(s).");
        return 0;
    }

    protected function hasActiveBooking(Table $table, Carbon $now, ?int $c1Id): bool
    {
        $activeBookingQuery = Booking::where('status', 'confirmed')
            ->where('start_time', '<=', $now)
            ->where('end_time', '>', $now);

        if (in_array($table->code, ['C2', 'C3'], true)) {
            $hasDirect = (clone $activeBookingQuery)
                ->where('table_id', $table->id)
                ->exists();

            if ($hasDirect) {
                return true;
            }

            if ($c1Id) {
                return (clone $activeBookingQuery)
                    ->where('table_id', $c1Id)
                    ->where('mode_booking', 'room')
                    ->exists();
            }

            return false;
        }

        return (clone $activeBookingQuery)
            ->where('table_id', $table->id)
            ->exists();
    }
}
