<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * #408. A cancelled installment is paperwork, not money owed, so it must not
 * count towards the plan's total: a Korrektur puts the same amount back as a
 * new scheduled row, and a Wegfall lowers the total instead. Both would trip
 * the trigger from 2026_08_09_002500 while the cancelled row still summed.
 *
 * CREATE OR REPLACE on the function alone — the two constraint triggers
 * already point at it and keep doing so. Replacing the body is also why
 * 2026_08_09_002500 wrote OR REPLACE in the first place.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->defineFunction('AND cancelled_at IS NULL');
    }

    public function down(): void
    {
        $this->defineFunction('');
    }

    private function defineFunction(string $cancelledClause): void
    {
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION payment_plan_sum_matches_total() RETURNS trigger AS \$\$
            DECLARE
                target_plan_id bigint;
                plan_total integer;
                installments_total integer;
            BEGIN
                IF TG_TABLE_NAME = 'payment_plans' THEN
                    target_plan_id := COALESCE(NEW.id, OLD.id);
                ELSE
                    target_plan_id := COALESCE(NEW.payment_plan_id, OLD.payment_plan_id);
                END IF;

                SELECT total INTO plan_total FROM payment_plans WHERE id = target_plan_id;

                IF plan_total IS NULL THEN
                    RETURN NULL;
                END IF;

                SELECT COALESCE(SUM(amount), 0) INTO installments_total
                    FROM payment_installments
                    WHERE payment_plan_id = target_plan_id {$cancelledClause};

                IF installments_total <> plan_total THEN
                    RAISE EXCEPTION
                        'payment plan % installments sum to % but its total is %',
                        target_plan_id, installments_total, plan_total;
                END IF;

                RETURN NULL;
            END;
            \$\$ LANGUAGE plpgsql;
        SQL);
    }
};
