export default {
    type: "custom/condition",
    title: "Condition",
    color: "#f3b403",
    inputs: [
        { name: "Flow", type: "flow" }
    ],
    outputs: [
        { name: "True", type: "flow" },     // index 0
        { name: "False", type: "flow" },    // index 1
        { name: "Result", type: "string" }  // index 2: always output message
    ],
    properties: {
        operator: "==",
        compare_with: "!hello"
    },
    widgets: [
        {
            type: "combo",
            name: "Operator",
            property: "operator",
            options: ["==", "!=", ">", "<", ">=", "<=", "&&", "||"]
        },
        {
            type: "text",
            name: "Compare With",
            property: "compare_with"
        }
    ],
    onExecute() {
        const ctx = this.graph.context || {};
        const val = ctx.message ?? ""; // data yang dibandingkan
        const cmp = this.properties.compare_with ?? "";
        const op = this.properties.operator ?? "==";

        let result = false;

        try {
            switch (op) {
                case "==":
                    result = val == cmp;
                    break;
                case "!=":
                    result = val != cmp;
                    break;
                case ">":
                    result = val > cmp;
                    break;
                case "<":
                    result = val < cmp;
                    break;
                case ">=":
                    result = val >= cmp;
                    break;
                case "<=":
                    result = val <= cmp;
                    break;
                case "&&":
                    result = val && cmp;
                    break;
                case "||":
                    result = val || cmp;
                    break;
                default:
                    console.warn("Unknown operator:", op);
            }
        } catch (e) {
            console.warn("Condition evaluation error:", e);
        }

        // Outputkan nilai asli ke Result (string)
        this.setOutputData(2, String(val));

        // Optional simpan ke context
        this.graph.context.condition_result = result;
        this.graph.context.last_message_source = "condition";

        // Trigger ke True atau False
        const slot = result ? 0 : 1;
        this._lastTriggeredSlot = slot;
        if (typeof this.triggerSlot === "function") {
            this.triggerSlot(slot);
        }
    }
};
