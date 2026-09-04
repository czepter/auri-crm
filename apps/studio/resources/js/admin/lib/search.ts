/* Staff type "Muller" for "Müller" and the row still has to come up. */
export function fold(value: string) {
    return value
        .normalize("NFD")
        .replace(/\p{Diacritic}/gu, "")
        .toLowerCase()
}
