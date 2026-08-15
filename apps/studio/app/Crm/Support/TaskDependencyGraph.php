<?php

namespace App\Crm\Support;

use App\Crm\Models\Task;
use App\Crm\Models\TaskTemplate;

/**
 * S-12's one rule with teeth: a dependency chain may not close on itself.
 *
 * Checked at **write** time, never at read time. A cycle discovered while
 * rendering is a list that cannot be drawn and a screen that cannot be
 * opened — including the screen someone would need to fix it. Refusing the
 * save keeps the broken state from existing at all.
 *
 * Walks the chain rather than recursing over children: a dependency has
 * exactly one predecessor, so the whole check is following `depends_on`
 * upwards until it ends or comes back to where it started. Bounded by the
 * number of rows in the chain, which for a studio's task list is a handful.
 */
class TaskDependencyGraph
{
    /** Would making $task depend on $predecessor close a loop? */
    public static function wouldCycle(Task $task, Task $predecessor): bool
    {
        if ($task->is($predecessor)) {
            return true;
        }

        $seen = [];
        $current = $predecessor;

        while ($current !== null) {
            if ($current->is($task) || isset($seen[$current->id])) {
                return true;
            }

            $seen[$current->id] = true;
            $current = $current->dependsOn;
        }

        return false;
    }

    /**
     * The same question for templates, asked before a template chain is ever
     * applied to a project — otherwise the first booking after a bad edit is
     * what discovers it, on a screen a client is waiting behind.
     */
    public static function templateWouldCycle(TaskTemplate $template, TaskTemplate $predecessor): bool
    {
        if ($template->is($predecessor)) {
            return true;
        }

        $seen = [];
        $current = $predecessor;

        while ($current !== null) {
            if ($current->is($template) || isset($seen[$current->id])) {
                return true;
            }

            $seen[$current->id] = true;
            $current = $current->depends_on_template_id === null
                ? null
                : TaskTemplate::find($current->depends_on_template_id);
        }

        return false;
    }
}
