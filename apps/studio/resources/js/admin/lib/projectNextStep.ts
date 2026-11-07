type Status = { value: string; label: string };
type Shoot = { id: string; label: string | null; kind: string; forwardStatuses: Status[]; dueStatus: Status | null };

export function nextStep(shoots: Shoot[]): { shootId: string; status: Status } | null {
    const due = shoots.find((shoot) => shoot.dueStatus);

    return due?.dueStatus ? { shootId: due.id, status: due.dueStatus } : null;
}

export function stepRows(shoots: Shoot[]): { shootId: string; label: string; status: string }[] {
    return shoots.flatMap((shoot) =>
        shoot.forwardStatuses
            .filter((status) => status.value !== "archived")
            .map((status) => ({ shootId: shoot.id, label: `${shoot.label ?? shoot.kind} · ${status.label}`, status: status.value })),
    );
}

export function paymentCall(
    plan: { installments: { isLate: boolean }[] } | null,
    people: { role: string; phone: string | null }[],
): string | null {
    const phone = people.find((person) => person.role === "primary")?.phone?.trim();

    return plan?.installments.some((installment) => installment.isLate) && phone ? `tel:${phone.replace(/[^\d+]/g, "")}` : null;
}
