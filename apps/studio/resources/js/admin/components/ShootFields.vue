<script lang="ts">
/** The form's own row: empty strings, never null — the transform on save is
 * what turns an untouched field back into null. */
export type EditableShoot = {
    id: string;
    date: string;
    call_time: string;
    end_time: string;
    venue_name: string;
    venue_city: string;
};
</script>

<script setup lang="ts">
/*
 * The editable half of a shooting day, shared by the two screens that offer
 * it. The row object is the form's own, so the fields write straight into it.
 */
import { DatePicker } from "@/ui/date-picker";
import { Field } from "@/ui/field";
import { Input } from "@/ui/input";
import { TimePicker } from "@/ui/time-picker";

defineProps<{ shoot: EditableShoot }>();
</script>

<template>
    <div class="pt-3.5 pb-4 shadow-[inset_0_1px_0_var(--rule)]">
        <div class="flex gap-3 [&+&]:mt-3">
            <Field v-slot="{ id }" dense :label="$t('project.shootDate')">
                <DatePicker :id="id" v-model="shoot.date" class="w-[148px] [&_input]:pr-8" />
            </Field>
            <Field v-slot="{ id }" dense :label="$t('project.callTime')">
                <TimePicker :id="id" v-model="shoot.call_time" variant="filled" />
            </Field>
            <Field v-slot="{ id }" dense :label="$t('project.endTime')">
                <TimePicker :id="id" v-model="shoot.end_time" variant="filled" />
            </Field>
        </div>
        <div class="flex gap-3 [&+&]:mt-3">
            <Field v-slot="{ id }" dense class="min-w-[148px] flex-1" :label="$t('project.venueName')">
                <Input :id="id" v-model="shoot.venue_name" variant="filled" type="text" />
            </Field>
            <Field v-slot="{ id }" dense class="min-w-[148px] flex-1" :label="$t('project.venueCity')">
                <Input :id="id" v-model="shoot.venue_city" variant="filled" type="text" />
            </Field>
        </div>
    </div>
</template>
