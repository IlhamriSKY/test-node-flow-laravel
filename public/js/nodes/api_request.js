export default {
    type: "custom/api_request",
    title: "API Request",
    color: "#36d399",
    size: [380, 150],
    inputs: [
        { name: "Flow", type: "flow" }
    ],
    outputs: [
        { name: "After", type: "flow" },
        { name: "Response", type: "string" }
    ],
    output_limit: 1,
    properties: {
        method: "POST",
        url: "https://jsonplaceholder.typicode.com/posts",
        headers: '{"Content-Type": "application/json"}',
        body: '{"title": "foo", "body": "bar", "userId": 1}',
    },
    widgets: [
        {
            type: "combo",
            name: "Method",
            property: "method",
            options: ["GET", "POST", "PUT", "DELETE"]
        },
        {
            type: "text",
            name: "URL",
            property: "url"
        },
        {
            type: "text",
            name: "Headers (JSON)",
            property: "headers"
        },
        {
            type: "text",
            name: "Body (JSON)",
            property: "body",
            multiline: true
        }
    ],
    onExecute() {
        if (this._executing) return;
        this._executing = true;

        const method = this.properties.method || "GET";
        const url = this.properties.url || "";
        const delay = parseInt(this.properties.delay) || 0;

        let headers = {};
        let body = null;

        try {
            headers = JSON.parse(this.properties.headers || "{}");
        } catch (e) {
            console.warn("Invalid JSON in headers:", e);
        }

        try {
            body = JSON.parse(this.properties.body || null);
        } catch (e) {
            console.warn("Invalid JSON in body:", e);
        }

        const fetchOptions = {
            method,
            headers
        };

        if (["POST", "PUT", "PATCH", "DELETE"].includes(method.toUpperCase())) {
            fetchOptions.body = JSON.stringify(body);
        }

        fetch(url, fetchOptions)
            .then((res) => res.json())
            .then((data) => {
                const output = typeof data === "object"
                    ? JSON.stringify(data, null, 2)
                    : String(data);

                this.setOutputData(1, output); // Output to "Response"

                this.graph.context = this.graph.context || {};
                this.graph.context.message = output;
                this.graph.context.__last_response = output;
                this.graph.context.last_message_source = "api_request";
                this._lastTriggeredSlot = 0; // Trigger "After" flow
            })
            .catch((err) => {
                const errorMsg = "ERROR: " + err.message;
                this.setOutputData(1, errorMsg);

                this.graph.context = this.graph.context || {};
                this.graph.context.message = errorMsg;
                this.graph.context.__last_response = errorMsg;
                this.graph.context.last_message_source = "api_request";
                this._lastTriggeredSlot = 0;
            })
            .finally(() => {
                this._executing = false;
                setTimeout(() => {
                    this.triggerSlot?.(0);
                }, delay);
            });
    }
};
