// ========== CONSTANTS & GLOBALS ==========
const canvasElement = document.getElementById("graph-canvas");
const graph = new LGraph();
const canvas = new LGraphCanvas("#graph-canvas", graph);
let selectedNode = null; // Currently selected node
let editingNode = null; // Node being edited in modal
const customNodeTitles = {}; // Maps node types to display titles
let activeLinks = []; // Currently animated links
// Load all registered node modules
loadNodeModules(window.NODE_MODULES);
// ========== UI ELEMENTS ==========
const nodeTypeSelect = document.getElementById("nodeType");
const toolbar = document.getElementById("toolbar");
/**
 * Refresh the node type dropdown with available custom nodes
 */
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
/**
 * Adjust canvas size to fit window
 */
function resizeCanvas() {
    const toolbarHeight = toolbar?.offsetHeight || 54;
    const sidebarWidth = window.innerWidth > 650 ? 240 : 0;
    canvasElement.width = window.innerWidth - sidebarWidth;
    canvasElement.height = window.innerHeight - toolbarHeight;
}
// Set up window resize handler
window.addEventListener("resize", resizeCanvas);
resizeCanvas();
// ========== ZOOM CONTROLS ==========
/**
 * Adjust canvas zoom level
 * @param {number} factor - Zoom multiplier
 */
const zoom = (factor) => {
    const current = canvas.ds.scale || 1;
    canvas.ds.scale = Math.max(0.2, Math.min(current * factor, 3));
    canvas.draw(true, true);
};
const zoomIn = () => zoom(1.2);
const zoomOut = () => zoom(1 / 1.2);
/**
 * Reset zoom to default and center on selected node
 */
const resetZoom = () => {
    canvas.ds.scale = 1;
    let node = selectedNode;
    if (!node) {
        // Find default node if none selected
        node = graph._nodes.find(n => n.type === "custom/user_message");
    }
    if (node) {
        const [nodeX, nodeY] = node.pos;
        const [nodeW, nodeH] = node.size ?? [100, LiteGraph.NODE_TITLE_HEIGHT || 50];
        canvas.ds.offset = [
            canvas.canvas.width / 2 / canvas.ds.scale - nodeX - nodeW / 2,
            canvas.canvas.height / 2 / canvas.ds.scale - nodeY - nodeH / 2
        ];
    } else {
        canvas.ds.offset = [0, 0];
    }
    canvas.draw(true, true);
};
// ========== NODE MANAGEMENT ==========
/**
 * Check if node definition is valid
 * @param {object} def - Node definition
 * @returns {boolean}
 */
function isValidNodeDef(def) {
    return def && typeof def.type === "string" && (def.inputs || def.outputs);
}
/**
 * Register custom node types with LiteGraph
 * @param {Array|object} nodeDefs - Node definitions
 */
function registerCustomNodes(nodeDefs) {
    if (nodeDefs?.default) nodeDefs = nodeDefs.default;
    if (!Array.isArray(nodeDefs)) nodeDefs = [nodeDefs];
    nodeDefs.forEach((def) => {
        if (!isValidNodeDef(def)) return;
        // Dynamic node constructor
        function DynamicNode() {
            this.properties = {
                ...(def.properties || {})
            };
            // Add inputs/outputs
            def.inputs?.forEach(input => this.addInput(input.name, input.type));
            def.outputs?.forEach(output => this.addOutput(output.name, output.type));
            // Store widget definitions for later sync
            this._widgetsDef = def.widgets || [];
            // Add widgets based on definition
            def.widgets?.forEach((widget) => {
                const prop = widget.property;
                const value = this.properties[prop] ?? "";
                if (widget.hidden) return;
                switch (widget.type) {
                    case "text":
                        this.addWidget("text", widget.name, value, (v) => {
                            this.properties[prop] = v;
                            this.setDirtyCanvas(true, true);
                        });
                        break;
                    case "combo":
                        this.addWidget("combo", widget.name, value, (v) => {
                            this.properties[prop] = v;
                            this.setDirtyCanvas(true, true);
                        }, {
                            values: widget.options
                        });
                        break;
                    case "slider":
                        this.addWidget("slider", widget.name, parseFloat(value), (v) => {
                            this.properties[prop] = parseFloat(v);
                            this.setDirtyCanvas(true, true);
                        }, {
                            min: widget.min ?? 0,
                            max: widget.max ?? 1,
                            step: widget.step ?? 0.01
                        });
                        break;
                    case "number":
                        const min = widget.options?.min ?? 0;
                        const max = widget.options?.max ?? 60000;
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
                        break;
                }
            });
            this.title = def.title || def.type;
            if (def.color) this.color = def.color;
            if (Array.isArray(def.size)) this.size = [...def.size];
            if (def.bgImageUrl) {
                this.bgImage = new Image();
                this.bgImage.src = def.bgImageUrl;
                const drawLogo = function (ctx) {
                    if (this.bgImage.complete && this.bgImage.width > 0) {
                        let width = 32,
                            height = 32;
                        const parseSize = (val, base) => {
                            if (typeof val === "string" && val.endsWith("%")) {
                                const percent = parseFloat(val) / 100;
                                return base * percent;
                            }
                            return parseFloat(val) || base;
                        };
                        const baseW = this.size[0];
                        const baseH = this.size[1];
                        if (typeof def.bgImageSize === "number" || typeof def.bgImageSize === "string") {
                            width = height = parseSize(def.bgImageSize, Math.min(baseW, baseH));
                        } else if (typeof def.bgImageSize === "object") {
                            width = parseSize(def.bgImageSize.width, baseW);
                            height = parseSize(def.bgImageSize.height, baseH);
                        }
                        const x = (baseW - width) / 2;
                        const y = (baseH - height) / 2;
                        ctx.save();
                        ctx.drawImage(this.bgImage, x, y, width, height);
                        ctx.restore();
                    }
                };
                this._originalDrawForeground = drawLogo;
                this.onDrawForeground = drawLogo;
            }
        }
        // Node execution handler
        DynamicNode.prototype.onExecute = function () {
            try {
                if (typeof def.onExecute === "function") {
                    def.onExecute.call(this);
                }
            } catch (e) {
                this.setOutputData?.(0, "ERROR: " + e.message);
            }
        };
        // Sync widget values from properties
        DynamicNode.prototype.syncWidget = function () {
            if (!Array.isArray(this.widgets) || !Array.isArray(this._widgetsDef)) return;
            this.widgets.forEach((widget, i) => {
                const def = this._widgetsDef[i];
                if (def?.property && this.properties.hasOwnProperty(def.property)) {
                    widget.value = this.properties[def.property];
                }
            });
        };
        // Handle connection restrictions
        if (typeof def.output_limit === "number" || def.combo?.lock_connection) {
            DynamicNode.prototype.onConnectionsChange = function (type, slot, connected, link_info) {
                // Prevent disconnecting locked inputs
                if (type === LiteGraph.INPUT && connected && this.inputs?.[slot]?.link != null) {
                    const link = this.graph.links[this.inputs[slot].link];
                    const originNode = this.graph.getNodeById(link.origin_id);
                    const isLocked = link._locked ||
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
                // Handle output restrictions
                if (type === LiteGraph.OUTPUT && connected) {
                    const targetNode = this.graph.getNodeById(link_info.target_id);
                    // Handle locked combo connections
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
                    // Enforce output limits
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
        // Handle node pairing and grouping when added to graph
        if (def.combo?.pair) {
            DynamicNode.prototype.onAdded = function () {
                const combo = def.combo;
                const pairNode = LiteGraph.createNode(combo.pair);
                const offset = combo.pair_offset || [220, 0];
                pairNode.pos = [this.pos[0] + offset[0], this.pos[1] + offset[1]];
                // Apply paired node properties if defined
                if (combo.pair_properties) {
                    Object.assign(pairNode.properties, combo.pair_properties);
                    pairNode.syncWidget?.();
                }
                this.graph.add(pairNode);
                this.connect(0, pairNode, 0);
                // Create group container if specified
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
                // Lock the connection if specified
                const links = this.graph.links;
                for (const id in links) {
                    const link = links[id];
                    if (link.origin_id === this.id &&
                        link.origin_slot === 0 &&
                        link.target_id === pairNode.id &&
                        link.target_slot === 0) {
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
        }
        // Register the node type
        customNodeTitles[def.type] = def.title || def.type;
        LiteGraph.registerNodeType(def.type, DynamicNode);
    });
}
/**
 * Load all node modules dynamically
 * @param {Array} modules - Array of module URLs
 */
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
// ========== NODE EDITING ==========
/**
 * Create input field for node property editing
 * @param {string} type - Input type
 * @param {string} key - Property key
 * @param {*} value - Initial value
 * @param {Array} options - Options for combo boxes
 * @param {boolean} locked - Whether input is read-only
 * @param {object} widgetDef - Widget definition
 * @returns {HTMLElement} Created input element
 */
function createInputField(type, key, value, options = [], locked = false, widgetDef = {}) {
    let input;
    const forceTextarea = widgetDef?.multiline === true;
    switch (type) {
        case "combo":
            input = document.createElement("select");
            (Array.isArray(options) ? options : Object.values(options)).forEach(option => {
                const opt = document.createElement("option");
                opt.value = opt.textContent = option;
                if (option === value) opt.selected = true;
                input.appendChild(opt);
            });
            break;
        case "text":
        case "string":
            if (forceTextarea) {
                input = document.createElement("textarea");
                input.rows = widgetDef.rows || 6;
                input.value = value;
                input.placeholder = widgetDef.name || key;
                input.style.width = "100%";
                input.style.resize = "vertical";
            } else {
                input = document.createElement("input");
                input.type = "text";
                input.value = value;
                input.placeholder = widgetDef.name || key;
            }
            break;
        case "number":
            input = document.createElement("input");
            input.type = "number";
            input.value = value;
            input.placeholder = widgetDef.name || key;
            // Apply min/max/step constraints if defined
            if (widgetDef.options?.min !== undefined) input.min = widgetDef.options.min;
            if (widgetDef.options?.max !== undefined) input.max = widgetDef.options.max;
            if (widgetDef.options?.step !== undefined) input.step = widgetDef.options.step;
            break;
        case "slider":
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
        default:
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
/**
 * Open modal to edit selected node properties
 */
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
    // Create form fields for each property
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
/**
 * Save edited node properties from modal
 */
function saveNodeValue() {
    if (!editingNode?.properties) return closeModal();
    const fields = document.querySelectorAll("#modal-fields [data-key]");
    fields.forEach((input) => {
        const key = input.dataset.key;
        let value;
        if (input.querySelector(".custom-slider-thumb")) {
            value = input.getValue?.();
        } else {
            const isNumber = input.type === "number" || input.type === "range";
            value = isNumber ? parseFloat(input.value) : input.value;
        }
        editingNode.properties[key] = value;
    });
    editingNode.syncWidget?.();
    editingNode.setDirtyCanvas(true, true);
    closeModal();
}
/**
 * Close the node editing modal
 */
function closeModal() {
    editingNode = null;
    document.getElementById("overlay").style.display = "none";
    document.getElementById("node-modal").style.display = "none";
}
// Set up modal event handlers
document.getElementById("btn-save-node").addEventListener("click", saveNodeValue);
document.getElementById("btn-close-node").addEventListener("click", closeModal);
document.getElementById("overlay").onclick = closeModal;
// ========== CANVAS INTERACTION ==========
// Handle node selection and pairing
canvas.onNodeSelected = function (node) {
    selectedNode = node;
    const pairId = node._combo_autoconnect_info?.source_id;
    if (pairId) {
        const pairNode = graph.getNodeById(pairId);
        if (pairNode) this.selectNode(pairNode, true);
    }
    // Select any nodes that are paired with this one
    graph._nodes.forEach((otherNode) => {
        if (otherNode._combo_autoconnect_info?.source_id === node.id) {
            this.selectNode(otherNode, true);
        }
    });
};
canvas.onNodeDeselected = () => {
    selectedNode = null;
};
// Open modal on double-click (desktop only)
canvas.onNodeDblClicked = function (node) {
    if (window.innerWidth <= 650) return;
    selectedNode = node;
    if (isEditableNode(node)) openModal();
};
/**
 * Check if node has editable properties
 * @param {object} node - Node to check
 * @returns {boolean}
 */
function isEditableNode(node) {
    if (!node?.properties) return false;
    const keys = Object.keys(node.properties);
    return keys.length > 0 && typeof node.properties[keys[0]] !== "object";
}
// Disable context menu
LiteGraph.ContextMenu = function () {
    return false;
};
// ========== NODE OPERATIONS ==========
/**
 * Add new node of selected type to graph
 */
function addNode() {
    const type = document.getElementById("nodeType").value;
    const node = LiteGraph.createNode(type);
    graph.add(node);
}
// ========== FLOW EXECUTION ==========
/**
 * Trigger connected nodes from specific output slot
 * @param {number} slot - Output slot index
 * @param {*} param - Optional parameter to pass
 */
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
/**
 * Handle connection changes with special logic for locked connections
 */
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
    // Reconnect if this was a locked connection
    if (isLockedLink || isLockedCombo) {
        setTimeout(() => {
            originNode.connect(link_info.origin_slot, targetNode, link_info.target_slot);
            canvas.setDirty(true, true);
        }, 0);
    }
};
/**
 * Run the entire graph starting from input-less nodes
 */
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
/**
 * Reset all node states and execution context
 */
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
        // Reset delay nodes
        if (node.type === "custom/delay") {
            if (node._delayTimer) clearTimeout(node._delayTimer);
            node._delayTimer = null;
            node._delaying = false;
        }
        // Clear logger nodes
        if (node.type === "custom/logger") {
            node.logs = [];
        }
        node.resetTimeouts?.();
    });
}
/**
 * Execute a single node and propagate execution forward
 * @param {object} node - Node to execute
 * @param {Set} visited - Set of visited node IDs to prevent cycles
 */
function executeFlowFromNode(node, visited = new Set()) {
    if (!node || visited.has(node.id)) return;
    visited.add(node.id);
    highlightNode(node);
    // Handle delay nodes
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
    // Handle async fetch nodes
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
    // Propagate execution to connected nodes
    const triggeredSlot = typeof node._lastTriggeredSlot === "number" ? node._lastTriggeredSlot : null;
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
// ========== VISUAL EFFECTS ==========
/**
 * Draw animated glow effect around a node
 * @param {CanvasRenderingContext2D} ctx - Canvas context
 * @param {object} node - Node to highlight
 * @param {number} pulse - Pulsation intensity (0-1)
 */
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
/**
 * Apply glow animation to a node
 * @param {object} node - Node to highlight
 */
function highlightNode(node) {
    if (!node || node._custom_highlight) return;
    node._custom_highlight = true;
    node._glowFrame = 0;
    node.boxcolor = "#00ff88";
    // Store original draw method
    node._old_dirty_draw = node.onDrawBackground;
    // Override draw method to add glow effect
    node.onDrawBackground = function (ctx) {
        const pulse = 0.22 + 0.28 * Math.sin((node._glowFrame || 0) / 3);
        drawRoundedGlow(ctx, node, pulse);
        node._old_dirty_draw?.(ctx);
    };
    // Animate the glow effect
    node._glowInterval = setInterval(() => {
        node._glowFrame++;
        node.setDirtyCanvas(true, true);
        // Remove highlight after animation completes
        if (node._glowFrame > 14) {
            clearInterval(node._glowInterval);
            removeNodeHighlight(node);
        }
    }, 42);
}
/**
 * Remove highlight from node
 * @param {object} node - Node to restore
 */
function removeNodeHighlight(node) {
    if (!node || !node._custom_highlight) return;
    clearInterval(node._glowInterval);
    node._custom_highlight = false;
    node._glowFrame = 0;
    node.boxcolor = LiteGraph.NODE_DEFAULT_BOXCOLOR;
    node.onDrawBackground = node._originalDrawBackground ?? null;
    if (node._originalDrawForeground) {
        node.onDrawForeground = node._originalDrawForeground;
    }
    node.setDirtyCanvas(true, true);
}
// Custom connection drawing with active link highlighting
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
        // Determine connection color
        let color = "#3aaaff";
        if (isActive) {
            color = "#00ffc3";
        } else if (origin.type === "custom/condition") {
            color = link.origin_slot === 0 ? "#00ffc3" :
                link.origin_slot === 1 ? "#ff0055" :
                link.origin_slot === 2 ? "#3aaaff" : "#000000";
        }
        this.renderLink(ctx, a, b, link, false, isActive, color);
        if (isActive) ctx.globalAlpha = 1;
    }
    ctx.restore();
};
// Clean up expired active links periodically
setInterval(() => {
    const now = Date.now();
    activeLinks = activeLinks.filter(link => now - link.start < 650);
}, 120);
// Continuous rendering for smooth animations
(function animateLinks() {
    canvas.draw(true, true);
    requestAnimationFrame(animateLinks);
})();
// Configure LiteGraph behavior
canvas.prompt = () => {}; // Disable built-in prompt
Object.assign(LiteGraph, {
    allow_searchbox: false,
    always_show_properties: false,
    debug: false,
});
canvas.allow_searchbox = false;
// Initialize minimap
initMinimap();
