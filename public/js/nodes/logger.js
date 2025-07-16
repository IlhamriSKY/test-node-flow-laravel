export default {
    type: "custom/logger",
    title: "Logger",
    color: "#64748b",
    inputs: [
        { name: "Flow", type: "flow" },       // index 0: trigger
        { name: "Message", type: "string" }   // index 1: data
    ],
    outputs: [
        { name: "Out", type: "string" }
    ],
    properties: {},
    onExecute() {
        const msg = this.getInputData(1); // index 1 = message input
        if (!msg) return;

        this.logs = this.logs || [];
        this.logs.push(msg);

        console.log("[Logger]", msg);
        this.setOutputData(0, msg);
    }
};
