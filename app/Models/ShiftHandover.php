<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiftHandover extends Model
{
    protected $fillable = [
        'date',
        'shift_type',
        'outgoing_employee_id',
        'incoming_employee_id',
        'handover_at',
        'received_at',
        'cash_theoretical',
        'cash_actual',
        'cash_diff',
        'cash_note',
        'total_orders',
        'revenue_cash',
        'revenue_transfer',
        'total_revenue',
        'nvl_snapshot',
        'equipment_checklist',
        'handover_note',
        'has_alert',
        'status',
        'dispute_note',
    ];

    protected $casts = [
        'handover_at' => 'datetime',
        'received_at' => 'datetime',
        'cash_theoretical' => 'integer',
        'cash_actual' => 'integer',
        'cash_diff' => 'integer',
        'total_orders' => 'integer',
        'revenue_cash' => 'integer',
        'revenue_transfer' => 'integer',
        'total_revenue' => 'integer',
        'nvl_snapshot' => 'array',
        'equipment_checklist' => 'array',
        'has_alert' => 'boolean',
    ];

    public function outgoingEmployee()
    {
        return $this->belongsTo(User::class, 'outgoing_employee_id');
    }

    public function incomingEmployee()
    {
        return $this->belongsTo(User::class, 'incoming_employee_id');
    }
}
