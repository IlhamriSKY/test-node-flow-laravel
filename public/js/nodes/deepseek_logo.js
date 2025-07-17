export default {
  type: "custom/deepseek_message",
  title: "DeepSeek Message",
  color: "#ffffff",
  size: [180, 80],
  bgImageUrl: "/logo/Google-Gemini-White-Logo.png",
  bgImageSize: { width: "50%", height: "50%" },
  outputs: [{ name: "Message", type: "string" }],
  properties: { message: "Hello from DeepSeek!" },
  widgets: [
    { type: "text", name: "Message", property: "message", hidden: true }
  ],
  onExecute() {
    this.setOutputData(0, this.properties.message);
  }
};
