import { expect, it } from "vitest";
import { nextStep, stepRows } from "./projectNextStep";

const shot = { value: "shot", label: "Fotografiert" };
const prep = { value: "prep", label: "Vorbereitung" };
const base = { kind: "wedding", label: null };

it("offers Fotografiert for the first shoot whose day has come", () => {
    const shoots = [
        { ...base, id: "a", label: "Polterabend", forwardStatuses: [prep], dueStatus: null },
        { ...base, id: "b", label: "Hochzeitstag", forwardStatuses: [prep, shot], dueStatus: shot },
    ];

    expect(nextStep(shoots)).toEqual({ shootId: "b", status: shot });
    expect(nextStep([shoots[0]])).toBeNull();
});

it("lists every shoot with its forward statuses, archiving apart", () => {
    const rows = stepRows([
        { ...base, id: "b", label: "Hochzeitstag", forwardStatuses: [shot, { value: "archived", label: "Archiviert" }], dueStatus: shot },
    ]);

    expect(rows).toEqual([{ shootId: "b", label: "Hochzeitstag · Fotografiert", status: "shot" }]);
});
