<?php

namespace App\Actions\Shifts;

use App\Models\ActivityLog;
use App\Models\CashShift;

class CloseShift
{
    public function execute(CashShift $shift, int $closingCash, ?string $notes = null): CashShift
    {
        $difference = $closingCash - $shift->expected_cash;

        $shift->update([
            'status' => 'closed',
            'closing_cash' => max(0, $closingCash),
            'difference' => $difference,
            'notes' => $notes ?? $shift->notes,
            'closed_at' => now(),
        ]);

        ActivityLog::record(
            action: 'shift.closed',
            description: "Tutup {$shift->shift_code} · Selisih kas: Rp ".number_format($difference, 0, ',', '.'),
            branchId: $shift->branch_id,
            userId: $shift->user_id,
            subject: $shift,
            properties: [
                'expected_cash' => $shift->expected_cash,
                'closing_cash' => $closingCash,
                'difference' => $difference,
            ]
        );

        return $shift->fresh();
    }
}
