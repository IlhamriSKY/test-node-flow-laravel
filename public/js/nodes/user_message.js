export default {
    type: "custom/user_message",
    title: "User Message",
    color: "#ff4b4b",
    outputs: [{
        name: "Message",
        type: "string"
    }],
    properties: {
        message: "!hello"
    },
    widgets: [{
        type: "text",
        name: "Message",
        property: "message"
    }],
    output_limit: 1,
    onExecute() {
        this.setOutputData(0, this.properties.message);
    }
};
