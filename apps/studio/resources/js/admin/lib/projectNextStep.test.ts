import { expect, it } from "vitest";
import { nextStep, paymentCall, stepRows } from "./projectNextStep";

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

it("offers the primary contact's number once a payment is late", () => {
    const people = [{ role: "partner", phone: "0151" }, { role: "primary", phone: "+49 170 123-45" }];

    expect(paymentCall({ installments: [{ isLate: false }, { isLate: true }] }, people)).toBe("tel:+4917012345");
    expect(paymentCall({ installments: [{ isLate: false }] }, people)).toBeNull();
    expect(paymentCall({ installments: [{ isLate: true }] }, [{ role: "primary", phone: null }])).toBeNull();
});
