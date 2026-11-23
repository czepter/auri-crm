<?php

namespace App\Finance\Actions;

use App\Finance\Enums\ExpenseStatus;
use App\Finance\Models\Expense;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Validator;

class SetExpenseStatus
{
    public function __invoke(Expense $expense, ExpenseStatus $status, ?User $actor = null): Expense
    {
        if ($status === ExpenseStatus::Booked) {
            Validator::make($expense->only(['vendor', 'paid_at', 'category_id']), [
                'vendor' => 'required',
                'paid_at' => 'required',
                'category_id' => 'required',
            ])->validate();
        }

        $from = $expense->status;
        $expense->update(['status' => $status]);

        if ($from !== $status) {
            AuditLogger::record('finance.expense.status_changed', $actor, ['from' => $from->value, 'to' => $status->value], $expense->public_id);
        }

        return $expense;
    }
}
