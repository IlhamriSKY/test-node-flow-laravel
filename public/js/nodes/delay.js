export default {
    type: "custom/delay",
    title: "Delay",
    color: "#a78bfa",
    size: [200, 60],
    inputs: [{
        name: "Flow",
        type: "flow"
    }],
    outputs: [{
        name: "After",
        type: "flow"
    }],
    properties: {
        duration: 5000
    },
    widgets: [{
        type: "number",
        name: "Duration (ms)",
        property: "duration",
        options: {
            min: 0,
            max: 60000,
            step: 100
        }
    }],
    output_limit: 1,
    onExecute() {
        const ms = parseInt(this.properties.duration);
        if (isNaN(ms) || ms < 0) {
            this.setOutputData?.(0, "Invalid duration");
            return;
        }
        if (this._delaying) return;
        this._delaying = true;
        this._delayTimer = setTimeout(() => {
            this._delaying = false;
            this._lastTriggeredSlot = 0;
            this.triggerSlot?.(0);
        }, ms);
    }
};
