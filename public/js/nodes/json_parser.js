export default {
    type: "custom/json_parser",
    title: "JSON Parser",
    color: "#34d399",
    inputs: [
        { name: "Flow", type: "flow" },          // Trigger
        { name: "JSON Input", type: "string" }   // Data JSON masuk
    ],
    outputs: [
        { name: "After", type: "flow" },         // Flow trigger
        { name: "Parsed Value", type: "string" } // Data yang diambil
    ],
    properties: {
        field: "value"
    },
    widgets: [
        {
            type: "text",
            name: "Field (optional)",
            property: "field"
        }
    ],
    onExecute() {
        const input = this.getInputData(1); // index 1 = JSON Input
        if (!input) return;

        try {
            const raw = JSON.parse(input);
            const field = this.properties.field.trim();
            const result = field
                ? field.split(".").reduce((obj, key) => obj?.[key], raw)
                : raw;

            const output = typeof result === "object"
                ? JSON.stringify(result, null, 2)
                : String(result);

            this.setOutputData(1, output); // output "Parsed Value"

            // Optional: update context
            const ctx = this.graph.context;
            ctx.message_parsed = result;
            ctx.last_message_source = "json_parser";
            ctx._log_version = Date.now();
        } catch (e) {
            const errorMsg = "ERROR: " + e.message;
            this.setOutputData(1, errorMsg);

            this.graph.context.message_parsed = errorMsg;
            this.graph.context.last_message_source = "json_parser";
            this.graph.context._log_version = Date.now();
        }
    }
};
