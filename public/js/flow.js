// ========== CONSTANTS & GLOBALS ==========
const canvasElement = document.getElementById("graph-canvas");
const graph = new LGraph();
const canvas = new LGraphCanvas("#graph-canvas", graph);
let selectedNode = null;
let editingNode = null;
const customNodeTitles = {};
let activeLinks = [];
// ===== NODE DEFINITIONS =====
loadNodeModules(window.NODE_MODULES);
// ========== UTILS ==========
const nodeTypeSelect = document.getElementById("nodeType");
const toolbar = document.getElementById("toolbar");

function refreshNodeTypeSelect() {
    if (!nodeTypeSelect) return;
    nodeTypeSelect.innerHTML = "";
    Object.entries(LiteGraph.registered_node_types)
        .filter(([type]) => type.startsWith("custom/"))
        .forEach(([type]) => {
            const opt = document.createElement("option");
            opt.value = type;
            opt.innerText = customNodeTitles[type] || type;
            nodeTypeSelect.appendChild(opt);
        });
}

function resizeCanvas() {
    const toolbarHeight = toolbar?.offsetHeight || 54;
    const sidebarWidth = window.innerWidth > 650 ? 240 : 0;
    canvasElement.width = window.innerWidth - sidebarWidth;
    canvasElement.height = window.innerHeight - toolbarHeight;
}
window.addEventListener("resize", resizeCanvas);
resizeCanvas();
// Zoom controls
const zoom = (factor) => {
    const current = canvas.ds.scale || 1;
    canvas.ds.scale = Math.max(0.2, Math.min(current * factor, 3));
    canvas.draw(true, true);
};
const zoomIn = () => zoom(1.2);
const zoomOut = () => zoom(1 / 1.2);
const resetZoom = () => {
    canvas.ds.scale = 1;
    canvas.draw(true, true);
};
// ========== DYNAMIC NODE LOADER ==========
function isValidNodeDef(def) {
    return def && typeof def.type === "string" && (def.inputs || def.outputs);
}

function registerCustomNodes(nodeDefs) {
    if (nodeDefs?.default) nodeDefs = nodeDefs.default;
    if (!Array.isArray(nodeDefs)) nodeDefs = [nodeDefs];
    nodeDefs.forEach((def) => {
        if (!isValidNodeDef(def)) return;

        function DynamicNode() {
            this.properties = {
                ...(def.properties || {})
            };
            def.inputs?.forEach(input => this.addInput(input.name, input.type));
            def.outputs?.forEach(output => this.addOutput(output.name, output.type));
            this._widgetsDef = def.widgets || [];
            def.widgets?.forEach((widget) => {
                const prop = widget.property;
                const value = this.properties[prop] ?? "";
                if (widget.type === "text") {
                    this.addWidget("text", widget.name, value, (v) => {
                        this.properties[prop] = v;
                        this.setDirtyCanvas(true, true);
                    });
                } else if (widget.type === "combo") {
                    this.addWidget("combo", widget.name, value, (v) => {
                        this.properties[prop] = v;
                        this.setDirtyCanvas(true, true);
                    }, {
                        values: widget.options
                    });
                } else if (widget.type === "slider") {
                    const min = widget.min ?? 0;
                    const max = widget.max ?? 1;
                    const step = widget.step ?? 0.01;
                    this.addWidget("slider", widget.name, parseFloat(value), (v) => {
                        this.properties[prop] = parseFloat(v);
                        this.setDirtyCanvas(true, true);
                    }, {
                        min,
                        max,
                        step
                    });
                } else if (widget.type === "number") {
                    const min = widget.options?.min ?? 0;
                    const max = widget.options?.max ?? 60000; // ← FIXED: default max = 60000 ms (60 detik)
                    const step = widget.options?.step ?? 100;
                    const safeValue = isNaN(parseFloat(value)) ? min : parseFloat(value);
                    this.addWidget("number", widget.name, safeValue, (v) => {
                        this.properties[prop] = parseFloat(v);
                        this.setDirtyCanvas(true, true);
                    }, {
                        min,
                        max,
                        step
                    });
                }
            });
            this.title = def.title || def.type;
            if (def.color) this.color = def.color;
            if (Array.isArray(def.size)) this.size = [...def.size];
        }
        // Called when the node is executed
        DynamicNode.prototype.onExecute = function () {
            try {
                if (typeof def.onExecute === "function") {
                    def.onExecute.call(this);
                }
            } catch (e) {
                this.setOutputData?.(0, "ERROR: " + e.message);
            }
        };
        // Sync UI widget values from node properties
        DynamicNode.prototype.syncWidget = function () {
            if (!Array.isArray(this.widgets) || !Array.isArray(this._widgetsDef)) return;
            this.widgets.forEach((widget, i) => {
                const def = this._widgetsDef[i];
                if (def?.property && this.properties.hasOwnProperty(def.property)) {
                    widget.value = this.properties[def.property];
                }
            });
        };
        // Handle locked connections or output limits
        if (typeof def.output_limit === "number" || def.combo?.lock_connection) {
            DynamicNode.prototype.onConnectionsChange = function (type, slot, connected, link_info) {
                if (type === LiteGraph.INPUT && connected && this.inputs?.[slot]?.link != null) {
                    const link = this.graph.links[this.inputs[slot].link];
                    const originNode = this.graph.getNodeById(link.origin_id);
                    const isLocked =
                        link._locked ||
                        (this._combo_autoconnect_info?.source_id === originNode?.id &&
                            this._combo_autoconnect_info?.locked);
                    if (isLocked) {
                        setTimeout(() => {
                            originNode.connect(link.origin_slot, this, link.target_slot);
                            canvas.setDirty(true, true);
                        }, 0);
                        return;
                    }
                }
                if (type === LiteGraph.OUTPUT && connected) {
                    const targetNode = this.graph.getNodeById(link_info.target_id);
                    if (def.combo?.lock_connection && def.combo.pair &&
                        targetNode?.type === def.combo.pair &&
                        link_info.origin_slot === 0 && link_info.target_slot === 0) {
                        const link = this.graph.links?.[this.outputs?.[slot]?.links?.slice(-1)[0]];
                        if (link) {
                            link._locked = true;
                            targetNode._combo_autoconnect_info = {
                                source_id: this.id,
                                locked: true,
                            };
                        }
                    }
                    if (typeof def.output_limit === "number") {
                        const out = this.outputs?.[slot];
                        if (out?.links?.length > def.output_limit) {
                            setTimeout(() => {
                                this.disconnectOutput(slot, this.graph.getNodeById(link_info.target_id));
                            }, 0);
                        }
                    }
                }
            };
        }
        // Called when node is added to graph
        DynamicNode.prototype.onAdded = function () {
            const combo = def.combo;
            if (!combo?.pair) return;
            const pairNode = LiteGraph.createNode(combo.pair);
            const offset = combo.pair_offset || [220, 0];
            pairNode.pos = [this.pos[0] + offset[0], this.pos[1] + offset[1]];
            if (combo.pair_properties) {
                Object.assign(pairNode.properties, combo.pair_properties);
                pairNode.syncWidget?.();
            }
            this.graph.add(pairNode);
            this.connect(0, pairNode, 0);
            if (def.group) {
                const group = new LiteGraph.LGraphGroup();
                group.title = `${def.title} Group`;
                group.font_size = def.group.font_size ?? 12;
                const titleHeight = def.group.title_height ?? 10;
                const marginTop = def.group.margin_top ?? 50;
                const marginSide = def.group.margin_side ?? 20;
                const marginBottom = def.group.margin_bottom ?? 20;
                const minX = Math.min(this.pos[0], pairNode.pos[0]) - marginSide;
                const minY = Math.min(this.pos[1], pairNode.pos[1]) - marginTop;
                const maxX = Math.max(this.pos[0] + this.size[0], pairNode.pos[0] + pairNode.size[0]) + marginSide;
                const maxY = Math.max(this.pos[1] + this.size[1], pairNode.pos[1] + pairNode.size[1]) + marginBottom;
                group.pos = [minX, minY];
                group.size = [maxX - minX, maxY - minY + titleHeight];
                group.color = def.color || "#3acfd5";
                this.graph.add(group);
            }
            const links = this.graph.links;
            for (const id in links) {
                const link = links[id];
                if (
                    link.origin_id === this.id &&
                    link.origin_slot === 0 &&
                    link.target_id === pairNode.id &&
                    link.target_slot === 0
                ) {
                    if (combo.lock_connection) {
                        link._locked = true;
                        pairNode._combo_autoconnect_info = {
                            source_id: this.id,
                            locked: true,
                        };
                    }
                    break;
                }
            }
        };
        customNodeTitles[def.type] = def.title || def.type;
        LiteGraph.registerNodeType(def.type, DynamicNode);
    });
}
// Load all node modules dynamically
async function loadNodeModules(modules) {
    const results = await Promise.allSettled(
        modules.map(url => import(url).catch(() => null))
    );
    results.forEach(result => {
        if (result.status === "fulfilled" && result.value) {
            registerCustomNodes(result.value.default || result.value);
        }
    });
    refreshNodeTypeSelect();
}
// ========== CANVAS & NODE EVENTS ==========
function createInputField(type, key, value, options = [], locked = false, widgetDef = {}) {
    let input;
    const forceTextarea = widgetDef?.multiline === true;
    if (type === "combo") {
        input = document.createElement("select");
        (Array.isArray(options) ? options : Object.values(options)).forEach(option => {
            const opt = document.createElement("option");
            opt.value = opt.textContent = option;
            if (option === value) opt.selected = true;
            input.appendChild(opt);
        });
    } else if ((type === "text" || type === "string") && forceTextarea) {
        input = document.createElement("textarea");
        input.rows = widgetDef.rows || 6;
        input.value = value;
        input.placeholder = widgetDef.name || key;
        input.style.width = "100%";
        input.style.resize = "vertical";
    } else if (type === "number") {
        input = document.createElement("input");
        input.type = "number";
        input.value = value;
        input.placeholder = widgetDef.name || key;
        // optional: tambahkan batasan min/max/step jika disediakan
        if (widgetDef.options?.min !== undefined) input.min = widgetDef.options.min;
        if (widgetDef.options?.max !== undefined) input.max = widgetDef.options.max;
        if (widgetDef.options?.step !== undefined) input.step = widgetDef.options.step;
    } else if (type === "slider") {
        const wrapper = document.createElement("div");
        wrapper.className = "custom-slider-wrapper";
        wrapper.dataset.key = key;
        const track = document.createElement("div");
        track.className = "custom-slider-track";
        const fill = document.createElement("div");
        fill.className = "custom-slider-fill";
        const thumb = document.createElement("div");
        thumb.className = "custom-slider-thumb";
        const tooltip = document.createElement("div");
        tooltip.className = "custom-slider-tooltip";
        track.appendChild(fill);
        track.appendChild(thumb);
        track.appendChild(tooltip);
        wrapper.appendChild(track);
        let val = parseFloat(value) || 0;
        const min = widgetDef.min ?? 0;
        const max = widgetDef.max ?? 1;
        const step = widgetDef.step ?? 0.01;
        const updateSlider = (v) => {
            val = Math.max(min, Math.min(max, v));
            const percent = ((val - min) / (max - min)) * 100;
            fill.style.width = percent + "%";
            thumb.style.left = percent + "%";
            tooltip.style.left = percent + "%";
            tooltip.textContent = val.toFixed(2);
        };
        updateSlider(val);
        let dragging = false;
        const onMouseMove = (e) => {
            if (!dragging) return;
            const rect = track.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const percent = x / rect.width;
            const raw = min + percent * (max - min);
            const snapped = Math.round(raw / step) * step;
            updateSlider(snapped);
        };
        thumb.addEventListener("mousedown", () => {
            dragging = true;
        });
        document.addEventListener("mouseup", () => {
            dragging = false;
        });
        document.addEventListener("mousemove", onMouseMove);
        // For value saving later:
        wrapper.getValue = () => val;
        return wrapper;
    } else {
        input = document.createElement("input");
        input.type = "text";
        input.value = value;
        input.placeholder = widgetDef.name || key;
    }
    input.dataset.key = key;
    if (locked) {
        input.disabled = true;
        input.title = "This value is locked due to autoconnect Webhook";
    }
    return input;
}

function openModal() {
    if (!selectedNode?.properties) {
        alert("No node selected or cannot edit!");
        return;
    }
    const modal = document.getElementById("node-modal");
    const existing = modal.querySelector("#modal-fields");
    if (existing) existing.remove();
    const container = document.createElement("div");
    container.id = "modal-fields";
    for (const key in selectedNode.properties) {
        const val = selectedNode.properties[key];
        const widget = selectedNode._widgetsDef?.find(w => w.property === key);
        const isLocked = key === "duration" && selectedNode._combo_autoconnect_info?.locked;
        const label = document.createElement("label");
        label.innerText = widget?.name || key;
        label.htmlFor = key;
        label.className = "modal-label";
        container.appendChild(label);
        const input = createInputField(widget?.type, key, val, widget?.options, isLocked, widget);
        input.id = key;
        container.appendChild(input);
    }
    modal.insertBefore(container, modal.querySelector("button"));
    editingNode = selectedNode;
    document.getElementById("overlay").style.display = "block";
    modal.style.display = "block";
}

function saveNodeValue() {
    if (!editingNode?.properties) return closeModal();
    const fields = document.querySelectorAll("#modal-fields [data-key]");
    fields.forEach((input) => {
        const key = input.dataset.key;
        let actualInput = input;
        if (input.querySelector(".custom-slider-thumb")) {
            actualInput = input;
            value = input.getValue?.();
        }
        const isNumber = actualInput.type === "number" || actualInput.type === "range";
        editingNode.properties[key] = isNumber ? parseFloat(actualInput.value) : actualInput.value;
    });
    editingNode.syncWidget?.();
    editingNode.setDirtyCanvas(true, true);
    closeModal();
}

function closeModal() {
    editingNode = null;
    document.getElementById("overlay").style.display = "none";
    document.getElementById("node-modal").style.display = "none";
}
document.getElementById("overlay").onclick = closeModal;
// Select additional node if this one is auto-connected (e.g. delay)
canvas.onNodeSelected = function (node) {
    selectedNode = node;
    const pairId = node._combo_autoconnect_info?.source_id;
    if (pairId) {
        const pairNode = graph.getNodeById(pairId);
        if (pairNode) this.selectNode(pairNode, true);
    }
    graph._nodes.forEach((otherNode) => {
        if (otherNode._combo_autoconnect_info?.source_id === node.id) {
            this.selectNode(otherNode, true);
        }
    });
};
canvas.onNodeDeselected = () => {
    selectedNode = null;
};
canvas.onNodeDblClicked = function (node) {
    // hanya aktif di desktop
    if (window.innerWidth <= 650) return;
    selectedNode = node;
    if (isEditableNode(node)) openModal();
};

function isEditableNode(node) {
    if (!node?.properties) return false;
    const keys = Object.keys(node.properties);
    return keys.length > 0 && typeof node.properties[keys[0]] !== "object";
}
// ========= CONTEXT MENU DISABLE (ALL AREA) =========
LiteGraph.ContextMenu = function () {
    return false;
};
// ========= ADD NODE ==========
function addNode() {
    const type = document.getElementById("nodeType").value;
    const node = LiteGraph.createNode(type);
    graph.add(node);
}
// ========== FLOW EXECUTION ==========
// Trigger connected nodes from specific output slot
LiteGraph.LGraphNode.prototype.triggerSlot = function (slot, param) {
    this._lastTriggeredSlot = slot;
    const output = this.outputs?.[slot];
    if (!output?.links) return;
    output.links.forEach(linkId => {
        const link = this.graph.links[linkId];
        const nextNode = this.graph.getNodeById(link?.target_id);
        if (linkId) {
            activeLinks.push({
                id: linkId,
                start: Date.now()
            });
        }
        if (nextNode) {
            setTimeout(() => executeFlowFromNode(nextNode), 0);
        }
    });
};
// Auto reconnect locked links if user tries to disconnect them
LiteGraph.LGraphNode.prototype.onConnectionsChange = function (type, slot, connected, link_info) {
    if (connected || type !== LiteGraph.INPUT || !link_info) return;
    const originNode = this.graph.getNodeById(link_info.origin_id);
    const targetNode = this;
    const linkId = targetNode.inputs?.[slot]?.link ?? null;
    const link = linkId != null ? graph.links[linkId] : null;
    const isLockedLink = link?._locked;
    const isLockedCombo =
        targetNode._combo_autoconnect_info?.source_id === originNode?.id &&
        targetNode._combo_autoconnect_info?.locked;
    if (isLockedLink || isLockedCombo) {
        setTimeout(() => {
            originNode.connect(link_info.origin_slot, targetNode, link_info.target_slot);
            canvas.setDirty(true, true);
        }, 0);
    }
};
// Run the entire graph starting from input-less nodes
function runFlowFromInputs() {
    resetNodes();
    graph._nodes.forEach((node) => {
        if (!node.inputs?.length) {
            node.outputs?.forEach(output => {
                output?.links?.forEach(linkId => {
                    activeLinks.push({
                        id: linkId,
                        start: Date.now()
                    });
                });
            });
            executeFlowFromNode(node);
        }
    });
    canvas.draw(true, true);
}
// Reset all node states and internal context
function resetNodes() {
    graph.context = {
        message: null,
        last_message_source: null,
        _log_version: null,
        __last_response: null
    };
    graph._nodes.forEach((node) => {
        removeNodeHighlight(node);
        node._lastTriggeredSlot = null;
        if (node.type === "custom/delay") {
            if (node._delayTimer) clearTimeout(node._delayTimer);
            node._delayTimer = null;
            node._delaying = false;
        }
        if (node.type === "custom/logger") {
            node.logs = [];
        }
        node.resetTimeouts?.();
    });
}
// Execute a single node and propagate forward
function executeFlowFromNode(node, visited = new Set()) {
    if (!node || visited.has(node.id)) return;
    visited.add(node.id);
    highlightNode(node);
    // Handle delay node
    if (node.type === "custom/delay") {
        const ms = parseInt(node.properties.duration) || 0;
        if (node._delaying) return;
        node._delaying = true;
        node._delayTimer = setTimeout(() => {
            node._delaying = false;
            node._lastTriggeredSlot = 0;
            node.triggerSlot?.(0);
        }, ms);
        return;
    }
    // Handle async fetch node
    const isAsyncFetch = node.properties?.url &&
        node.outputs?.some(o => o.name?.toLowerCase().includes("response"));
    if (isAsyncFetch) {
        if (node._executing) return;
        node._executing = true;
        const {
            url,
            method = "GET",
            headers,
            body
        } = node.properties;
        let parsedHeaders = {},
            parsedBody = null;
        try {
            parsedHeaders = headers ? JSON.parse(headers) : {};
        } catch {}
        try {
            parsedBody = body ? JSON.parse(body) : null;
        } catch {}
        const opts = {
            method: method.toUpperCase(),
            headers: parsedHeaders
        };
        if (["POST", "PUT", "PATCH", "DELETE"].includes(opts.method) && parsedBody) {
            opts.body = JSON.stringify(parsedBody);
        }
        fetch(url, opts)
            .then(res => res.json())
            .then(data => {
                const idx = node.outputs.findIndex(o =>
                    o.name?.toLowerCase().includes("response")
                ) || 0;
                node.setOutputData(idx, JSON.stringify(data, null, 2));
            })
            .catch(err => {
                const idx = node.outputs.findIndex(o =>
                    o.name?.toLowerCase().includes("response")
                ) || 0;
                node.setOutputData(idx, "ERROR: " + err.message);
            })
            .finally(() => {
                node._executing = false;
                const nextIdx = node.outputs.findIndex(o =>
                    o.name?.toLowerCase().includes("after") || o.type === "flow"
                ) || 0;
                node.outputs[nextIdx]?.links?.forEach(linkId => {
                    const nextNode = graph.getNodeById(graph.links[linkId]?.target_id);
                    if (nextNode) {
                        activeLinks.push({
                            id: linkId,
                            start: Date.now()
                        });
                        setTimeout(() => executeFlowFromNode(nextNode, visited), 150);
                    }
                });
            });
        return;
    }
    // Regular node execution
    try {
        node._lastTriggeredSlot = null;
        node.onExecute?.();
    } catch (e) {
        node.setOutputData?.(0, "Execution Error: " + e.message);
    }
    const triggeredSlot = typeof node._lastTriggeredSlot === "number" ? node._lastTriggeredSlot : null;
    const outputTargets = (slotIndex) =>
        node.outputs?.[slotIndex]?.links?.map(linkId =>
            graph.getNodeById(graph.links[linkId]?.target_id)
        ).filter(Boolean) || [];
    if (triggeredSlot === null) {
        // Auto propagate all outputs if no slot was manually triggered
        node.outputs?.forEach((output, i) => {
            output?.links?.forEach(linkId => {
                const nextNode = graph.getNodeById(graph.links[linkId]?.target_id);
                if (nextNode) {
                    activeLinks.push({
                        id: linkId,
                        start: Date.now()
                    });
                    setTimeout(() => executeFlowFromNode(nextNode, visited), 150);
                }
            });
        });
    }
}
// ========== HIGHLIGHT ==========
// Draw animated glow effect around a node
function drawRoundedGlow(ctx, node, pulse) {
    const titleHeight = LiteGraph.NODE_TITLE_HEIGHT || 24;
    const yOffset = -titleHeight - 7;
    const glowHeight = node.size[1] + titleHeight + 14;
    ctx.save();
    // Inner soft glow
    ctx.globalAlpha = 0.15 + 0.18 * pulse;
    ctx.fillStyle = "rgba(0,255,195,1)";
    ctx.beginPath();
    ctx.roundRect(-7, yOffset, node.size[0] + 14, glowHeight, 17);
    ctx.fill();
    // Outer pulse glow with gradient
    const gradient = ctx.createLinearGradient(0, yOffset, node.size[0], yOffset + glowHeight);
    gradient.addColorStop(0, "#00ffc3");
    gradient.addColorStop(1, "#60ffe6");
    ctx.globalAlpha = 0.58 + 0.21 * pulse;
    ctx.shadowColor = "#60ffe6";
    ctx.shadowBlur = 13 + 10 * pulse;
    ctx.lineWidth = 3.1 + 1.7 * pulse;
    ctx.strokeStyle = gradient;
    ctx.beginPath();
    ctx.roundRect(-4, yOffset + 3, node.size[0] + 8, glowHeight - 6, 13);
    ctx.stroke();
    ctx.restore();
}
// Apply glow animation to a node
function highlightNode(node) {
    if (!node || node._custom_highlight) return;
    node._custom_highlight = true;
    node._glowFrame = 0;
    node.boxcolor = "#00ff88";
    node._old_dirty_draw = node.onDrawBackground;
    node.onDrawBackground = function (ctx) {
        const pulse = 0.22 + 0.28 * Math.sin((node._glowFrame || 0) / 3);
        drawRoundedGlow(ctx, node, pulse);
        node._old_dirty_draw?.(ctx);
    };
    node._glowInterval = setInterval(() => {
        node._glowFrame++;
        node.setDirtyCanvas(true, true);
        if (node._glowFrame > 14) {
            clearInterval(node._glowInterval);
            removeNodeHighlight(node);
        }
    }, 42);
}
// Remove highlight and restore original drawing functions
function removeNodeHighlight(node) {
    if (!node || !node._custom_highlight) return;
    clearInterval(node._glowInterval);
    node._custom_highlight = false;
    node._glowFrame = 0;
    node.boxcolor = LiteGraph.NODE_DEFAULT_BOXCOLOR;
    node.onDrawBackground = node._originalDrawBackground ?? null;
    delete node._originalDrawBackground;
    node.onDrawForeground = node._originalDrawForeground ?? null;
    delete node._originalDrawForeground;
    node.setDirtyCanvas(true, true);
}
// Draw all graph connections with optional animated glow
canvas.drawConnections = function (ctx) {
    if (!this.graph?.links) return;
    ctx.save();
    for (const id in this.graph.links) {
        const link = this.graph.links[id];
        const origin = this.graph.getNodeById(link.origin_id);
        const target = this.graph.getNodeById(link.target_id);
        if (!origin || !target) continue;
        const a = origin.getConnectionPos(false, link.origin_slot);
        const b = target.getConnectionPos(true, link.target_slot);
        const isActive = activeLinks.some(l => l.id === link.id && Date.now() - l.start < 650);
        // Dynamic link color
        let color = "#3aaaff";
        if (isActive) {
            color = "#00ffc3";
        } else if (origin.type === "custom/condition") {
            color = link.origin_slot === 0 ?
                "#00ffc3" :
                link.origin_slot === 1 ?
                "#ff0055" :
                link.origin_slot === 2 ?
                "#3aaaff" :
                "#000000";
        }
        this.renderLink(ctx, a, b, link, false, isActive, color);
        if (isActive) ctx.globalAlpha = 1;
    }
    ctx.restore();
};
// Auto-remove expired animated links (active glow)
setInterval(() => {
    const now = Date.now();
    activeLinks = activeLinks.filter(link => now - link.start < 650);
}, 120);
// Continuous re-rendering for active link animation
(function animateLinks() {
    canvas.draw(true, true);
    requestAnimationFrame(animateLinks);
})();
// Graph behavior settings
canvas.prompt = () => {}; // Disable built-in prompt
Object.assign(LiteGraph, {
    allow_searchbox: false,
    always_show_properties: false,
    debug: false,
});
canvas.allow_searchbox = false;
// Minimap
initMinimap();
