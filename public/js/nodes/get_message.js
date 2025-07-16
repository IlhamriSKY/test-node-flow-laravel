export default {
    type: "custom/get_message",
    title: "Get Message",
    color: "#fbbf24",
    inputs: [{
        name: "Input",
        type: "string"
    }],
    outputs: [{
        name: "Flow",
        type: "flow"
    }],
    onExecute() {
        this.graph.context = this.graph.context || {};
        const input = this.getInputData(0);
        if (input) {
            this.graph.context.message = input;
            this.graph.context.last_message_source = "user";
            this.graph.context._log_version = Date.now();
        }
        if (typeof this.triggerSlot === "function") {
            this.triggerSlot(0);
        }
    }
};
