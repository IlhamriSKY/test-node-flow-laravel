export default {
  type: "custom/deepseek_message",
  title: "DeepSeek Message",
  color: "#70a597",
  size: [200, 80],
  bgImageUrl: "/logo/chatgpt-6.svg",
  bgImageSize: { width: "64", height: "64" },
  outputs: [{ name: "Message", type: "string" }],
  properties: { message: "Hello from DeepSeek!" },
  widgets: [
    { type: "text", name: "Message", property: "message", hidden: true }
  ],
  onExecute() {
    this.setOutputData(0, this.properties.message);
  }
};
