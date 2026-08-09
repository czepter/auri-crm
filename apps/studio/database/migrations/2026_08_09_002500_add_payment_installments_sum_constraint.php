<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The acceptance criterion the model layer alone cannot make true: a plan's
 * installments sum exactly to its total, enforced by the database itself
 * rather than only by whichever action last wrote the rows.
 *
 * quotes_net_check (see 2026_08_09_002000) is a plain CHECK constraint
 * because `net >= 0` is a single-row fact — Postgres evaluates a CHECK
 * against the row being written and nothing else, so it cannot see across
 * payment_installments to sum a whole plan's rows. That needs a trigger.
 *
 * Two triggers, one rule, checked from both sides of the relationship:
 *   - AFTER INSERT OR UPDATE OR DELETE on payment_installments, keyed off
 *     whichever plan the changed row belongs to;
 *   - AFTER INSERT OR UPDATE OF total on payment_plans, so a plan created (or
 *     retotalled) with no matching installments is caught too, not only a
 *     plan whose installments alone drift from an unchanged total.
 *
 * Both are CONSTRAINT TRIGGERs, DEFERRABLE INITIALLY DEFERRED. An ordinary
 * (non-deferred) trigger fires after every individual statement, which would
 * reject the intermediate states App\Payments\Actions\UpdatePaymentPlan
 * passes through on purpose — zero installments for the instant between
 * deleting the old set and inserting the new one, or a total that has not
 * yet been updated to match. Deferring to end-of-transaction is what makes
 * "sum exactly to the total" a fact about the committed state rather than
 * about every intermediate one, while still making a genuinely mismatched
 * COMMIT impossible — proven directly in
 * tests/Feature/Payments/PaymentPlanTest.php by inserting mismatched rows
 * through raw DB calls that bypass every app-level guard.
 */
return new class extends Migration
{
    public function up(): void
    {
        // OR REPLACE, not a plain CREATE: Schema::dropAllTables() (what
        // RefreshDatabase's migrate:fresh uses to reset the test database
        // between runs) drops tables, and this function's own triggers go
        // with them, but a standalone function is not a table and survives
        // that wipe — a plain CREATE FUNCTION would collide with itself on
        // the very next fresh migration.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION payment_plan_sum_matches_total() RETURNS trigger AS $$
            DECLARE
                target_plan_id bigint;
                plan_total integer;
                installments_total integer;
            BEGIN
                -- One function, shared by both triggers below. NEW/OLD are
                -- untyped records here, so a field that only exists on the
                -- other table (payment_installments.payment_plan_id versus
                -- payment_plans.id) is only ever referenced inside the branch
                -- for the table that actually has it.
                IF TG_TABLE_NAME = 'payment_plans' THEN
                    target_plan_id := COALESCE(NEW.id, OLD.id);
                ELSE
                    target_plan_id := COALESCE(NEW.payment_plan_id, OLD.payment_plan_id);
                END IF;

                SELECT total INTO plan_total FROM payment_plans WHERE id = target_plan_id;

                -- The plan itself is gone (cascade-deleted alongside its last
                -- installments, or this row's own DELETE is what removed it) —
                -- nothing left to reconcile.
                IF plan_total IS NULL THEN
                    RETURN NULL;
                END IF;

                SELECT COALESCE(SUM(amount), 0) INTO installments_total
                    FROM payment_installments WHERE payment_plan_id = target_plan_id;

                IF installments_total <> plan_total THEN
                    RAISE EXCEPTION
                        'payment plan % installments sum to % but its total is %',
                        target_plan_id, installments_total, plan_total;
                END IF;

                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE CONSTRAINT TRIGGER payment_installments_sum_matches_plan
                AFTER INSERT OR UPDATE OR DELETE ON payment_installments
                DEFERRABLE INITIALLY DEFERRED
                FOR EACH ROW EXECUTE FUNCTION payment_plan_sum_matches_total();
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE CONSTRAINT TRIGGER payment_plan_total_matches_installments
                AFTER INSERT OR UPDATE OF total ON payment_plans
                DEFERRABLE INITIALLY DEFERRED
                FOR EACH ROW EXECUTE FUNCTION payment_plan_sum_matches_total();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS payment_plan_total_matches_installments ON payment_plans');
        DB::unprepared('DROP TRIGGER IF EXISTS payment_installments_sum_matches_plan ON payment_installments');
        DB::unprepared('DROP FUNCTION IF EXISTS payment_plan_sum_matches_total()');
    }
};
