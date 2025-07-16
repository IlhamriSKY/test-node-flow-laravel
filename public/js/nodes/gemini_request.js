export default {
    type: "custom/gemini_request",
    title: "Gemini Request",
    color: "#4f46e5",
    size: [400, 200],
    inputs: [
        { name: "Flow", type: "flow" }
    ],
    outputs: [
        { name: "After", type: "flow" },
        { name: "Response", type: "string" },
        { name: "Raw JSON", type: "string" }
    ],
    output_limit: 1,
    properties: {
        model: "gemini-2.0-flash",
        api_key: "AIzaSyAwr_GahlTRmkCizOyEIOFGLVvCIFO6qPY",
        system_prompt: "You are a helpful assistant.",
        temperature: 0.7,
        maxOutputTokens: 1024
    },
    widgets: [
        {
            type: "combo",
            name: "Model",
            property: "model",
            options: [
                "gemini-2.5-pro",
                "gemini-2.5-flash",
                "gemini-2.5-flash-lite",
                "gemini-2.0-flash",
                "gemini-2.0-flash-lite"
            ]
        },
        {
            type: "text",
            name: "System Prompt",
            property: "system_prompt",
            multiline: true
        },
        {
            type: "text",
            name: "API Key",
            property: "api_key"
        },
        {
            type: "slider",
            name: "Temperature",
            property: "temperature",
            min: 0.0,
            max: 1.0,
            step: 0.01
        },
        {
            type: "combo",
            name: "Max Tokens",
            property: "maxOutputTokens",
            options: [1024, 2048, 4096, 8192]
        }
    ],
    onExecute() {
        if (this._executing) return;
        this._executing = true;

        const model = this.properties.model || "gemini-2.0-flash";
        const prompt = this.properties.system_prompt || "";
        const apiKey = this.properties.api_key || "";
        const temperature = parseFloat(this.properties.temperature) || 0.7;
        const maxTokens = parseInt(this.properties.maxOutputTokens) || 512;
        const userMessage = this.graph.context?.message || "";

        this.setOutputData(1, "");
        this.setOutputData(2, "");

        if (!userMessage || !apiKey) {
            const errMsg = "ERROR: Missing message or API key.";
            this.setOutputData(1, errMsg);
            this.graph.context.message = errMsg;
            this.graph.context.last_message_source = "gemini_request";
            this.graph.context.error = errMsg;
            this._executing = false;
            return;
        }

        const url = `https://generativelanguage.googleapis.com/v1beta/models/${model}:generateContent?key=${apiKey}`;

        const body = {
            contents: [
                {
                    role: "user",
                    parts: [{ text: userMessage }]
                }
            ],
            generationConfig: {
                temperature,
                maxOutputTokens: maxTokens
            }
        };

        if (prompt?.trim()) {
            body.system_instruction = {
                role: "system",
                parts: [{ text: prompt }]
            };
        }

        fetch(url, {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(body)
        })
        .then(res => res.json())
        .then(json => {
            const raw = JSON.stringify(json, null, 2);
            let responseText = "";

            if (json?.candidates?.[0]?.content?.parts?.[0]?.text) {
                responseText = json.candidates[0].content.parts[0].text;
            } else if (json?.promptFeedback?.blockReason) {
                responseText = `⚠️ Blocked: ${json.promptFeedback.blockReason}`;
            } else {
                responseText = "⚠️ No valid response from Gemini API.";
            }

            this.setOutputData(1, responseText);
            this.setOutputData(2, raw);

            this.graph.context = this.graph.context || {};
            this.graph.context.message = responseText;
            this.graph.context.__last_response = raw;
            this.graph.context.last_message_source = "gemini_request";
            this.graph.context.error = null;

            this._lastTriggeredSlot = 0;
        })
        .catch(err => {
            const errorMsg = "ERROR: " + err.message;
            this.setOutputData(1, errorMsg);
            this.setOutputData(2, "");
            this.graph.context.message = errorMsg;
            this.graph.context.error = errorMsg;
            this.graph.context.last_message_source = "gemini_request";
            this._lastTriggeredSlot = 0;
        })
        .finally(() => {
            this._executing = false;
            this.triggerSlot?.(0);
        });
    }
};
