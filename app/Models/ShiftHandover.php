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
        'opening_cash',
        'cash_theoretical',
        'cash_actual',
        'cash_diff',
        'receive_cash_actual',
        'receive_cash_reason',
        'cash_note',
        'total_orders',
        'revenue_cash',
        'revenue_transfer',
        'total_revenue',
        'report_snapshot',
        'sold_products',
        'nvl_snapshot',
        'damaged_materials',
        'receive_material_discrepancies',
        'equipment_checklist',
        'handover_note',
        'has_alert',
        'status',
        'dispute_note',
        'incoming_spot_check_ok',
    ];

    protected $casts = [
        'handover_at' => 'datetime',
        'received_at' => 'datetime',
        'opening_cash' => 'integer',
        'cash_theoretical' => 'integer',
        'cash_actual' => 'integer',
        'cash_diff' => 'integer',
        'receive_cash_actual' => 'integer',
        'total_orders' => 'integer',
        'revenue_cash' => 'integer',
        'revenue_transfer' => 'integer',
        'total_revenue' => 'integer',
        'report_snapshot' => 'array',
        'sold_products' => 'array',
        'nvl_snapshot' => 'array',
        'damaged_materials' => 'array',
        'receive_material_discrepancies' => 'array',
        'equipment_checklist' => 'array',
        'has_alert' => 'boolean',
        'incoming_spot_check_ok' => 'boolean',
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
