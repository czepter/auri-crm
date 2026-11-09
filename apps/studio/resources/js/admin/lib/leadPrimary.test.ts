import { expect, it } from "vitest";
import { sendQuoteHref } from "./leadPrimary";

it("points at the send screen while the quote is a draft", () => {
    expect(sendQuoteHref({ id: "q1", status: "draft" }, true)).toBe("/angebote/q1/senden");
    expect(sendQuoteHref({ id: "q1", status: "sent" }, true)).toBeNull();
    expect(sendQuoteHref(null, true)).toBeNull();
    expect(sendQuoteHref({ id: "q1", status: "draft" }, false)).toBeNull();
});
