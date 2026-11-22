<?php

namespace App\Finance\Actions;

use App\Finance\Enums\ExpenseStatus;
use App\Finance\Models\Expense;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Validation\ValidationException;

class DeleteExpense
{
    public function __invoke(Expense $expense, ?User $actor = null): void
    {
        if ($expense->status === ExpenseStatus::Booked) {
            throw ValidationException::withMessages(['status' => __('finance.notDeletable')]);
        }

        $expense->delete();

        AuditLogger::record('finance.expense.deleted', $actor, ['vendor' => $expense->vendor, 'gross' => $expense->gross], $expense->public_id);
    }
}
