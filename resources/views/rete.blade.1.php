<!DOCTYPE html>
<html lang="en">
<head>
    <title>LiteGraph Flow Visualizer</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/jagenjo/litegraph.js/build/litegraph.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"/>
    <style>
        :root {
            --bg-main: #23262e;
            --bg-sidebar: #1e2027;
            --bg-toolbar: #282a36;
            --accent: #00ffc3;
            --accent-dim: #40d6b3;
            --border-main: #353945;
            --text-main: #e9ecef;
            --text-secondary: #b7b8c2;
            --button-bg: #00ffc3;
            --button-hover: #40d6b3;
            --radius: 8px;
            --sidebar-width: 240px;
            --transition: 0.18s cubic-bezier(.4, 0, .2, 1);
            font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
        }
        html, body {
            height: 100%; margin: 0; padding: 0;
            background: var(--bg-main); color: var(--text-main);
            min-width: 100vw; min-height: 100vh; overflow: hidden;
            font-family: inherit;
        }
        body { height: 100vh; width: 100vw; display: flex; flex-direction: column; }
        #app { flex: 1; min-height: 0; display: flex; flex-direction: row; width: 100vw; height: 100vh; }
        #toolbar {
            width: 100vw; height: 54px; background: var(--bg-toolbar);
            display: flex; align-items: center; gap: 18px; padding: 0 18px;
            border-bottom: 1px solid var(--border-main); box-sizing: border-box;
            position: relative; z-index: 3; user-select: none;
        }
        #toolbar h1 { font-size: 1.12rem; font-weight: 600; color: var(--accent); margin: 0 14px 0 0; letter-spacing: 1px; flex-shrink: 0; line-height: 1; }
        #toolbar .toolbar-spacer { flex: 1 1 auto; }
        #toolbar .toolbar-btn {
            background: var(--button-bg); border: none; color: #16181e;
            border-radius: var(--radius); font-size: 1rem; padding: 8px 16px; margin-right: 7px;
            cursor: pointer; font-weight: 600; letter-spacing: .03em;
            transition: background var(--transition), box-shadow var(--transition);
            box-shadow: 0 1px 6px rgba(0, 255, 195, 0.07); outline: none;
            display: flex; align-items: center; gap: 7px;
        }
        #toolbar .toolbar-btn:hover { background: var(--button-hover); }
        #sidebar {
            width: var(--sidebar-width); background: var(--bg-sidebar); padding: 22px 16px;
            box-sizing: border-box; display: flex; flex-direction: column; gap: 20px;
            border-right: 1.5px solid var(--border-main); z-index: 2;
        }
        #sidebar label { color: var(--accent); font-size: 1.04rem; font-weight: 500; margin-bottom: 10px; }
        #sidebar select {
            width: 100%; padding: 12px; background: #23262e; color: var(--text-main);
            border: 1px solid var(--border-main); border-radius: var(--radius); font-size: 15px;
            outline: none; margin-bottom: 7px; transition: border var(--transition), background var(--transition);
        }
        #sidebar button {
            width: 100%; background: var(--button-bg); color: #13151c;
            padding: 13px 0; border: none; border-radius: var(--radius); font-size: 1rem; font-weight: 600;
            cursor: pointer; margin-bottom: 6px; transition: background var(--transition);
            box-shadow: 0 2px 10px rgba(0, 255, 195, 0.10); display: flex; align-items: center; justify-content: center; gap: 7px;
        }
        #sidebar button:hover { background: var(--button-hover); }
        #main { flex: 1 1 0%; min-width: 0; position: relative; background: var(--bg-main); height: 100%; display: flex; align-items: stretch; z-index: 1; }
        #graph-canvas { width: 100% !important; height: 100% !important; display: block; background: transparent; }
        #overlay {
            position: fixed; inset: 0; background: rgba(28, 30, 36, 0.58); display: none; z-index: 12;
        }
        #node-modal {
            position: fixed; left: 50%; top: 48%; transform: translate(-50%, -50%);
            background: #23262e; color: var(--text-main); padding: 28px 22px 22px 22px; border-radius: 14px;
            min-width: 280px; max-width: 340px; width: 90vw;
            box-shadow: 0 6px 38px rgba(0, 0, 0, 0.25), 0 1.5px 0 #00ffc342;
            display: none; z-index: 20; transition: opacity .18s cubic-bezier(.4, 0, .2, 1); font-family: inherit; text-align: left;
        }
        #node-modal h3 { margin-top: 0; margin-bottom: 16px; font-size: 1.15rem; font-weight: 700; color: var(--accent); }
        #node-modal input {
            width: 100%; padding: 13px; background: #23262e; border: 1.5px solid var(--border-main);
            color: var(--text-main); font-size: 1rem; border-radius: var(--radius);
            box-sizing: border-box; outline: none; margin-bottom: 12px; margin-top: 2px; font-family: inherit;
        }
        #node-modal button {
            width: 100%; background: var(--button-bg); border: none; padding: 12px; font-size: 1rem;
            font-weight: bold; border-radius: var(--radius); cursor: pointer; transition: background var(--transition);
            margin-top: 3px; color: #181d1f;
        }
        #node-modal button:hover { background: var(--button-hover); }
        .highlighted-node {
            box-shadow: 0 0 16px 2px var(--accent-dim), 0 1px 0 #00ffc355 !important;
            border-radius: 16px !important;
            border: 2.5px solid var(--accent);
            background: #25362e !important;
            transition: box-shadow .12s, border .12s, background .18s;
        }
        ::-webkit-scrollbar { width: 7px; }
        ::-webkit-scrollbar-thumb { background: #32333a; border-radius: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::selection { background: var(--accent-dim); color: #16181e; }
        @media (max-width: 650px) {
            #sidebar { display: none; }
            #main { margin-left: 0; }
            #toolbar { padding: 0 8px; }
            #node-modal { padding: 20px 10px; }
        }
        #zoom-controls {
            position: fixed; right: 24px; bottom: 24px; background: #1e2027cc;
            border-radius: 14px; box-shadow: 0 3px 14px #0006;
            z-index: 99; display: flex; flex-direction: column; gap: 10px; padding: 14px 12px;
        }
        #zoom-controls button {
            width: 44px; height: 44px; margin: 0; padding: 0; background: var(--button-bg); color: #16181e;
            font-size: 1.4rem; border: none; border-radius: 50%; box-shadow: 0 2px 10px #00ffc314; cursor: pointer;
            font-weight: bold; transition: background .15s; display: flex; align-items: center; justify-content: center;
        }
        #zoom-controls button:hover { background: var(--button-hover); }
        @media (max-width: 650px) {
            #zoom-controls { right: 7px; bottom: 10px; padding: 7px 6px; }
            #zoom-controls button { width: 32px; height: 32px; font-size: 1rem; }
        }
    </style>
</head>
<body>
    <div id="toolbar">
        <h1><i class="fa-solid fa-diagram-project"></i> LiteGraph Flow</h1>
        <div class="toolbar-spacer"></div>
        <button class="toolbar-btn" onclick="runFlowFromInputs()"><i class="fa fa-play"></i>Start Flow</button>
        <button class="toolbar-btn" onclick="openModal()"><i class="fa fa-pen"></i>Edit Selected</button>
    </div>
    <div id="app">
        <div id="sidebar">
            <label for="nodeType">Add Node:</label>
            <select id="nodeType">
                <option value="custom/command_input">Command Input</option>
                <option value="custom/condition">Condition</option>
                <option value="custom/delay">Delay</option>
                <option value="custom/webhook">Webhook</option>
                <option value="custom/logger">Logger</option>
                <option value="custom/dog_image">Dog Image</option>
            </select>
            <button onclick="addNode()"><i class="fa fa-plus"></i>Add Node</button>
            <hr style="border:0;border-top:1px solid #353945;margin:16px 0 0 0;">
            <div style="color:var(--text-secondary);font-size:13px;line-height:1.5;margin-top:6px;">
                <b>Double-click</b> node untuk edit cepat.<br>
                <b>Start Flow</b> jalankan dari node input.<br>
                <b>Edit Selected</b> edit value node yang dipilih.
            </div>
        </div>
        <div id="main">
            <canvas id="graph-canvas"></canvas>
        </div>
    </div>
    <div id="overlay"></div>
    <div id="node-modal">
        <h3>Edit Node Value</h3>
        <input type="text" id="nodeValueInput" placeholder="Enter value">
        <button onclick="saveNodeValue()"><i class="fa fa-save"></i>Save</button>
    </div>
    <div id="zoom-controls">
        <button title="Zoom In" onclick="zoomIn()"><i class="fa fa-plus"></i></button>
        <button title="Zoom Out" onclick="zoomOut()"><i class="fa fa-minus"></i></button>
        <button title="Reset Zoom" onclick="resetZoom()"><i class="fa fa-rotate-left"></i></button>
    </div>
    <script src="https://cdn.jsdelivr.net/gh/jagenjo/litegraph.js/build/litegraph.js"></script>
</body>
<script>
/* ==== INIT & UTILS ==== */
const canvasElement = document.getElementById('graph-canvas');
const graph = new LGraph();
const canvas = new LGraphCanvas("#graph-canvas", graph);
let selectedNode = null, editingNode = null;

function resizeCanvas() {
    const toolbarHeight = document.getElementById('toolbar').offsetHeight || 54;
    canvasElement.width = window.innerWidth - (window.innerWidth > 650 ? 240 : 0);
    canvasElement.height = window.innerHeight - toolbarHeight;
}
window.addEventListener('resize', resizeCanvas); resizeCanvas();

/* ==== ZOOM CONTROLS ==== */
function zoomIn() { canvas.ds.scale = Math.min((canvas.ds.scale || 1) * 1.2, 3); canvas.draw(true, true);}
function zoomOut() { canvas.ds.scale = Math.max((canvas.ds.scale || 1) / 1.2, 0.2); canvas.draw(true, true);}
function resetZoom() { canvas.ds.scale = 1; canvas.draw(true, true);}

/* ==== UI MODAL ==== */
function openModal() {
    if (!selectedNode || !isEditableNode(selectedNode)) return alert("No node selected or node tidak bisa diedit!");
    const firstKey = Object.keys(selectedNode.properties)[0];
    document.getElementById('nodeValueInput').value = selectedNode.properties[firstKey] || "";
    editingNode = selectedNode;
    document.getElementById('overlay').style.display = "block";
    document.getElementById('node-modal').style.display = "block";
}
function saveNodeValue() {
    if (!editingNode || !isEditableNode(editingNode)) return closeModal();
    const val = document.getElementById('nodeValueInput').value;
    const firstKey = Object.keys(editingNode.properties)[0];
    editingNode.properties[firstKey] = val;
    if (typeof editingNode.syncWidget === "function") editingNode.syncWidget();
    else if (Array.isArray(editingNode.widgets)) {
        for (let w of editingNode.widgets) {
            if (w && w.name && w.name.toLowerCase() === firstKey.toLowerCase()) {
                w.value = val;
                if (typeof w.callback === "function") w.callback(val);
                if (typeof w.draw === "function") w.draw();
            }
        }
    }
    editingNode.setDirtyCanvas(true, true);
    closeModal();
}
function closeModal() {
    editingNode = null;
    document.getElementById('overlay').style.display = "none";
    document.getElementById('node-modal').style.display = "none";
}
document.getElementById('overlay').onclick = closeModal;

/* ==== NODE SELECTION ==== */
canvas.onNodeSelected = function(node) { selectedNode = node; };
canvas.onNodeDeselected = function() { selectedNode = null; };
canvas.onNodeDblClicked = function(node) {
    selectedNode = node;
    if (isEditableNode(node)) openModal();
};

/* ==== DISABLE CONTEXT MENU ==== */
LGraphCanvas.prototype.showMenu = () => false;
LiteGraph.ContextMenu = () => false;
LiteGraph.ContextMenu.prototype = { show: () => false };
canvas.onContextMenu = (node, event) => { if (event) event.preventDefault(); return false; };
canvasElement.addEventListener("contextmenu", e => { e.preventDefault(); return false; });
document.addEventListener("contextmenu", e => { if (e.target === canvasElement) { e.preventDefault(); return false;}}, true);

/* ==== UTILS ==== */
function isEditableNode(node) {
    if (!node || !node.properties) return false;
    if (node.type === "custom/dog_image") return false;
    const keys = Object.keys(node.properties);
    if (keys.length === 0) return false;
    const val = node.properties[keys[0]];
    return typeof val === "string" || typeof val === "number";
}
function addNode() {
    const type = document.getElementById('nodeType').value;
    const node = LiteGraph.createNode(type);
    node.pos = [Math.random() * 400 + 100, Math.random() * 300 + 100];
    graph.add(node);
}

/* ==== CANVAS DRAW ANIM LINK (FLOW EFFECT) ==== */
let activeLinks = [];
canvas.drawConnections = function(ctx) {
    if (!this.graph || !this.graph.links) return;
    ctx.save();
    for (let i in this.graph.links) {
        let link = this.graph.links[i];
        let origin = this.graph.getNodeById(link.origin_id);
        let target = this.graph.getNodeById(link.target_id);
        if (!origin || !target) continue;
        let a = origin.getConnectionPos(false, link.origin_slot);
        let b = target.getConnectionPos(true, link.target_slot);
        let isActive = activeLinks.some(l => l.id === link.id && Date.now() - l.start < 650);
        this.renderLink(ctx, a, b, link, false, isActive);
    }
    ctx.restore();
};
setInterval(() => {
    const now = Date.now();
    activeLinks = activeLinks.filter(l => now - l.start < 650);
}, 120);
(function animateLinks(){ canvas.draw(true, true); requestAnimationFrame(animateLinks); })();

/* ==== NODE HIGHLIGHT ==== */
function highlightNode(node) {
    if (!node || node._custom_highlight) return;
    node._custom_highlight = true;
    node._glowFrame = 0;
    node._glowInterval = setInterval(() => {
        node._glowFrame++; node.setDirtyCanvas(true, true);
        if (node._glowFrame > 14) { clearInterval(node._glowInterval); removeNodeHighlight(node);}
    }, 42);
    node._old_dirty_draw = node.onDrawBackground;
    node.onDrawBackground = function(ctx) {
        const f = node._glowFrame || 0, pulse = 0.22 + 0.28 * Math.abs(Math.sin(f/3));
        const titleHeight = LiteGraph.NODE_TITLE_HEIGHT || 24, yOffset = -titleHeight - 7;
        const glowHeight = this.size[1] + titleHeight + 14;
        ctx.save();
        ctx.globalAlpha = 0.15 + 0.18 * pulse; ctx.fillStyle = "rgba(0,255,195,1)";
        ctx.beginPath(); ctx.roundRect(-7, yOffset, this.size[0]+14, glowHeight, 17); ctx.fill();
        const gradient = ctx.createLinearGradient(0, yOffset, this.size[0], yOffset+glowHeight);
        gradient.addColorStop(0, "#00ffc3"); gradient.addColorStop(1, "#60ffe6");
        ctx.globalAlpha = 0.58 + 0.21 * pulse; ctx.shadowColor = "#60ffe6";
        ctx.shadowBlur = 13 + 10 * pulse; ctx.lineWidth = 3.1 + 1.7 * pulse; ctx.strokeStyle = gradient;
        ctx.beginPath(); ctx.roundRect(-4, yOffset+3, this.size[0]+8, glowHeight-6, 13); ctx.stroke(); ctx.restore();
        ctx.save(); ctx.globalAlpha = 0.06 + 0.05 * pulse; ctx.strokeStyle = "#fff"; ctx.lineWidth = 1.1;
        ctx.beginPath(); ctx.roundRect(2, yOffset+8, this.size[0]-4, glowHeight-16, 8); ctx.stroke(); ctx.restore();
        if (typeof node._old_dirty_draw === 'function') node._old_dirty_draw(ctx);
    };
}
function removeNodeHighlight(node) {
    if (!node || !node._custom_highlight) return;
    if (node._glowInterval) clearInterval(node._glowInterval);
    node.bgcolor = "#222"; node.boxcolor = LiteGraph.NODE_DEFAULT_BOXCOLOR;
    node.onDrawBackground = node._old_dirty_draw || null;
    node._custom_highlight = false; node._glowFrame = 0; node.setDirtyCanvas(true, true);
}
</script>
<script>
/* ==== NODE & FLOW LOGIC ==== */
function resetNodes() {
    graph._nodes.forEach(node => {
        removeNodeHighlight(node);
        if (node.title === "Logger") node.logs = [];
        if (typeof node.resetTimeouts === "function") node.resetTimeouts();
        if (node.title === "DogImage") node._executing = false;
    });
}

function executeFlowFromNode(node, visited = new Set()) {
    if (!node || visited.has(node.id)) return;
    visited.add(node.id); highlightNode(node);
    if (typeof node.onExecute === "function") node.onExecute();
    if (node.outputs) {
        for (let i = 0; i < node.outputs.length; i++) {
            const output = node.outputs[i];
            if (!output || !output._data) continue;
            const links = output.links || [];
            for (let linkId of links) {
                const link = graph.links[linkId];
                if (link) {
                    activeLinks.push({id: linkId, start: Date.now()});
                    const nextNode = graph.getNodeById(link.target_id);
                    if (nextNode) setTimeout(() => executeFlowFromNode(nextNode, visited), 150);
                }
            }
        }
    }
}

function runFlowFromInputs() {
    resetNodes();
    if (!graph._flow_id) graph._flow_id = 1;
    else graph._flow_id++;
    graph._nodes.forEach(node => {
        if (!node.inputs || node.inputs.length === 0) {
            executeFlowFromNode(node);
        }
    });
    canvas.draw(true, true);
}


/* ==== NODE DEFINITIONS ==== */
function CommandInputNode() {
    this.properties = { cmd: "!hello" };
    const widget = this.addWidget("text", "Command", this.properties.cmd, v => {
        this.properties.cmd = v; this.setDirtyCanvas(true, true);
    });
    this._cmdWidget = widget; this.addOutput("cmd", "string");
    this.title = "Command Input";
    this.color = "#00ffc3";
}
CommandInputNode.prototype.onExecute = function() { this.setOutputData(0, this.properties.cmd); };
CommandInputNode.prototype.syncWidget = function() {
    if (this._cmdWidget) {
        this._cmdWidget.value = this.properties.cmd;
        if (typeof this._cmdWidget.callback === "function") this._cmdWidget.callback(this.properties.cmd);
    }
};
LiteGraph.registerNodeType("custom/command_input", CommandInputNode);

function ConditionNode() {
    this.addInput("input", "string");
    this.addOutput("true", "string"); this.addOutput("false", "string");
    this.properties = { match: "!hello" };
    this.addWidget("text", "Match", this.properties.match, v => { this.properties.match = v; });
    this.title = "Condition";
}
ConditionNode.prototype.onExecute = function() {
    for (let i = 0; i < this.outputs.length; i++) {
        this.setOutputData(i, null); if (this.outputs[i]) this.outputs[i]._data = null;
    }
    const input = this.getInputData(0), match = this.properties.match;
    const isMatch = input === match;
    if (isMatch) { this.setOutputData(0, input); if (this.outputs[0]) this.outputs[0]._data = input; }
    else { this.setOutputData(1, input); if (this.outputs[1]) this.outputs[1]._data = input; }
};
LiteGraph.registerNodeType("custom/condition", ConditionNode);

function WebhookNode() {
    this.addInput("trigger", "string"); this.addOutput("response", "string");
    this.properties = { url: "https://api.example.com" };
    this.addWidget("text", "URL", this.properties.url, v => { this.properties.url = v; });
    this.title = "Webhook";
}
WebhookNode.prototype.onExecute = function() {
    const input = this.getInputData(0);
    if (input) {
        const response = `Response from ${this.properties.url}`;
        this.setOutputData(0, response); if (this.outputs[0]) this.outputs[0]._data = response;
        this.setDirtyCanvas(true);
        const outputLinks = this.outputs[0]?.links || [];
        outputLinks.forEach(linkId => {
            const link = this.graph.links[linkId];
            if (link) {
                const nextNode = this.graph.getNodeById(link.target_id);
                if (nextNode) setTimeout(() => executeFlowFromNode(nextNode), 10);
            }
        });
    }
};
LiteGraph.registerNodeType("custom/webhook", WebhookNode);

function DelayNode() {
    this.addInput("input", "string"); this.addOutput("delayed", "string");
    this.properties = { ms: 1000 };
    const widget = this.addWidget("number", "Delay (ms)", this.properties.ms, v => {
        this.properties.ms = v; this.setDirtyCanvas(true, true);
    });
    this._msWidget = widget; this._executions = []; this._timeoutHandles = [];
    this.title = "Delay";
}
DelayNode.prototype.onExecute = function() {
    const input = this.getInputData(0);
    if (!input) return;
    const key = Date.now() + Math.random();
    this._executions.push(key);
    const timeoutId = setTimeout(() => {
        const index = this._executions.indexOf(key);
        if (index !== -1) {
            this._executions.splice(index, 1);
            this.setOutputData(0, input); if (this.outputs[0]) this.outputs[0]._data = input;
            this.setDirtyCanvas(true); highlightNode(this);
            const outputLinks = this.outputs[0]?.links || [];
            outputLinks.forEach(linkId => {
                const link = this.graph.links[linkId];
                if (link) {
                    const nextNode = this.graph.getNodeById(link.target_id);
                    if (nextNode) setTimeout(() => executeFlowFromNode(nextNode), 10);
                }
            });
        }
        const idIndex = this._timeoutHandles.indexOf(timeoutId);
        if (idIndex !== -1) this._timeoutHandles.splice(idIndex, 1);
    }, this.properties.ms);
    this._timeoutHandles.push(timeoutId);
};
DelayNode.prototype.resetTimeouts = function() {
    if (Array.isArray(this._timeoutHandles)) {
        this._timeoutHandles.forEach(id => clearTimeout(id)); this._timeoutHandles = [];
    }
    this._executions = [];
    if (this.outputs) {
        for (let i = 0; i < this.outputs.length; i++) {
            this.setOutputData(i, null); if (this.outputs[i]) this.outputs[i]._data = null;
        }
    }
};
DelayNode.prototype.syncWidget = function() {
    if (this._msWidget) {
        this._msWidget.value = Number(this.properties.ms) || 0;
        if (typeof this._msWidget.callback === "function") this._msWidget.callback(this._msWidget.value);
    }
};
LiteGraph.registerNodeType("custom/delay", DelayNode);

function LoggerNode() {
    this.addInput("Flow", LiteGraph.EVENT);
    this.addInput("Text", "string");
    this.title = "Logger";
    this.color = "#6366f1";
}
LoggerNode.prototype.onAction = function(action, param) {
    // ONLY trigger log via Flow event
    const msg = this.getInputData(1);
    if (msg !== undefined && msg !== null) {
        console.log("[Logger]", msg);
    }
};
LiteGraph.registerNodeType("custom/logger", LoggerNode);

LoggerNode.prototype.onExecute = function() {
    const msg = this.getInputData(0);
    if (!msg) return;
    const inputLink = this.inputs?.[0]?.link;
    const inputConnection = inputLink !== null ? this.graph.links[inputLink] : null;
    if (inputConnection) {
        const originNode = this.graph.getNodeById(inputConnection.origin_id);
        const outputName = originNode?.outputs?.[inputConnection.origin_slot]?.name || "unknown";
        const label = `${originNode?.title || "unknown"} -> ${outputName}`;
        const fullMessage = `[LoggerNode] From ${label}: ${msg}`;
        console.log(fullMessage);
    } else { console.log(`[LoggerNode] ${msg}`);}
};
LiteGraph.registerNodeType("custom/logger", LoggerNode);

function DogImageNode() {
    this.addInput("trigger", "string");
    this.addOutput("image_url", "string");
    this.properties = { image_url: "" }; this._executing = false;
    this.title = "DogImage";
}
DogImageNode.prototype.onExecute = function() {
    const input = this.getInputData(0);
    if (!input || this._executing) return;
    this._executing = true;
    fetch("https://dog.ceo/api/breeds/image/random")
        .then(res => res.json())
        .then(data => {
            if (data.status === "success") {
                this.properties.image_url = data.message;
                this.setOutputData(0, data.message);
                if (this.outputs[0]) this.outputs[0]._data = data.message;
                this.setDirtyCanvas(true);
                highlightNode(this);
                const outputLinks = this.outputs[0]?.links || [];
                outputLinks.forEach(linkId => {
                    const link = this.graph.links[linkId];
                    if (link) {
                        const nextNode = this.graph.getNodeById(link.target_id);
                        if (nextNode) setTimeout(() => executeFlowFromNode(nextNode), 10);
                    }
                });
            }
            this._executing = false;
        })
        .catch(err => { console.error("[DogImageNode] Error:", err); this._executing = false; });
};
LiteGraph.registerNodeType("custom/dog_image", DogImageNode);

/* ==== CANVAS SETTINGS ==== */
canvas.prompt = function() {
    return;
};
canvas.allow_searchbox = false;
LiteGraph.allow_searchbox = false;
LiteGraph.always_show_properties = false;
LiteGraph.debug = false;
canvas.ds.scale = 1;
canvas.draw(true, true);
</script>
