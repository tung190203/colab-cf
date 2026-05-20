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
        $occupiedTableIds = $this->activeOccupiedTableIds($now);

        $occupied = Table::query()
            ->where('is_active', true)
            ->whereIn('id', $occupiedTableIds)
            ->where('status', '!=', 'occupied')
            ->update(['status' => 'occupied']);

        $freeQuery = Table::query()
            ->where('is_active', true)
            ->where('status', 'occupied');

        if ($occupiedTableIds->isNotEmpty()) {
            $freeQuery->whereNotIn('id', $occupiedTableIds);
        }

        $released = $freeQuery->update(['status' => 'free']);

        $this->info("Occupied {$occupied} table(s), released {$released} table(s).");
        return 0;
    }

    protected function activeOccupiedTableIds(Carbon $now)
    {
        $activeBookings = Booking::where('status', 'confirmed')
            ->whereNotNull('table_id')
            ->where('start_time', '<=', $now)
            ->where('end_time', '>', $now)
            ->get(['table_id', 'mode_booking']);

        $tableIds = $activeBookings->pluck('table_id');

        $c1Id = Table::where('code', 'C1')->value('id');
        $hasActiveC1RoomBooking = $c1Id
            && $activeBookings->contains(fn($booking) => (int) $booking->table_id === (int) $c1Id && $booking->mode_booking === 'room');

        if ($hasActiveC1RoomBooking) {
            $tableIds = $tableIds->merge(Table::whereIn('code', ['C2', 'C3'])->pluck('id'));
        }

        return $tableIds->unique()->values();
    }
}
