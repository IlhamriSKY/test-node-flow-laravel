export default {
    type: "custom/webhook",
    title: "Webhook",
    color: "#3acfd5",
    size: [300, 100],
    inputs: [
        { name: "Flow", type: "flow" }
    ],
    outputs: [
        { name: "After", type: "flow" },      // index 0: trigger
        { name: "Response", type: "string" }  // index 1: data
    ],
    properties: {
        url: "https://api.chucknorris.io/jokes/random",
        method: "GET"
    },
    widgets: [
        {
            type: "text",
            name: "URL",
            property: "url"
        },
        {
            type: "combo",
            name: "Method",
            property: "method",
            options: ["GET", "POST", "PUT", "DELETE"]
        }
    ],
    output_limit: 1,

    onExecute() {
        if (this._executing) return;
        this._executing = true;

        const url = this.properties.url;
        const method = (this.properties.method || "GET").toUpperCase();
        const delay = parseInt(this.properties.delay) || 0;

        fetch(url, { method })
            .then(res => res.json())
            .then(data => {
                const response = JSON.stringify(data, null, 2);
                this.setOutputData(1, response);        // Set Response slot (index 1)
                this._lastTriggeredSlot = 0;             // Mark flow output to be triggered
            })
            .catch(err => {
                const errorMsg = "ERROR: " + err.message;
                this.setOutputData(1, errorMsg);
                this._lastTriggeredSlot = 0;
            })
            .finally(() => {
                this._executing = false;
                setTimeout(() => {
                    if (typeof this.triggerSlot === "function") {
                        this.triggerSlot(0);            // Trigger flow slot AFTER data ready
                    }
                }, delay);
            });
    }
};
