<?php

namespace App\Actions\Shifts;

use App\Models\ActivityLog;
use App\Models\CashShift;
use Illuminate\Validation\ValidationException;

class OpenShift
{
    public function execute(int $branchId, int $userId, int $openingCash, ?string $notes = null): CashShift
    {
        $existing = CashShift::query()
            ->where('branch_id', $branchId)
            ->where('user_id', $userId)
            ->where('status', 'open')
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'shift' => 'Anda masih memiliki shift aktif yang belum ditutup.',
            ]);
        }

        $seq = CashShift::query()->where('branch_id', $branchId)->count() + 1;
        $shift = CashShift::create([
            'branch_id' => $branchId,
            'user_id' => $userId,
            'shift_code' => sprintf('Shift #%02d', $seq),
            'status' => 'open',
            'opening_cash' => max(0, $openingCash),
            'expected_cash' => max(0, $openingCash),
            'notes' => $notes,
            'opened_at' => now(),
        ]);

        ActivityLog::record(
            action: 'shift.opened',
            description: "Buka {$shift->shift_code} dengan modal awal Rp ".number_format($shift->opening_cash, 0, ',', '.'),
            branchId: $branchId,
            userId: $userId,
            subject: $shift
        );

        return $shift;
    }
}
