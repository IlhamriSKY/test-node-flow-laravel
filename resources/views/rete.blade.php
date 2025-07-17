<!DOCTYPE html>
<html lang="en">

<head>
    <title>Flow Visualizer</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" />
    <link rel="stylesheet" href="{{ asset('css/flow.css') }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
</head>

<body>
    <div id="toolbar">
        <h1><i class="fa-solid fa-diagram-project"></i> LiteGraph Flow</h1>
        <div class="toolbar-spacer"></div>
    </div>
    <div id="app">
        <div id="sidebar">
            <label>Drag or Tap a Node:</label>
            <div id="node-list-container">
                <div id="node-grid" class="node-grid"></div>
            </div>
            <hr style="border:0;border-top:1px solid var(--border-main);margin:16px 0 0 0;">
            <div class="sidebar-hint">
                <b>Drag</b> ke canvas (desktop).<br>
                <b>Tap</b> untuk tambahkan (mobile).<br>
                <b>Start Flow</b> jalankan dari node input.
            </div>
        </div>
        <div id="main">
            <canvas id="graph-canvas"></canvas>
            <canvas id="minimap" width="200" height="150"
                style="
                position: absolute;
                top: 10px;
                right: 10px;
                border: 1px solid #444;
                background: rgba(0, 0, 0, 0.2);
                z-index: 20;
                cursor: pointer;
            "></canvas>
        </div>
    </div>
    <div id="overlay"></div>
    <div id="node-modal">
        <h3>Edit Node Value</h3>
        <div id="modal-fields"></div>
        <button id="btn-save-node">Save</button>
        <button id="btn-close-node" class="btn-close">Close</button>
    </div>
    <div id="connect-modal" style="display:none;">
        <h3>Connect Node</h3>
        <label for="output-select">From Output</label>
        <select id="output-select"></select>
        <label for="target-node-select">To Node</label>
        <select id="target-node-select"></select>
        <label for="input-select">To Input</label>
        <select id="input-select"></select>
        <button id="connect-confirm-btn">Connect</button>
        <button onclick="document.getElementById('connect-modal').style.display='none'" class="btn-close">Close</button>
    </div>
    <div id="zoom-controls">
        <button title="Start Flow" id="btn-start-flow"><i class="fa fa-play"></i></button>
        <button id="btn-open-connect-modal" title="Manual Connect"><i class="fa fa-link"></i></button>
        <button title="Delete Node" id="btn-delete-node"><i class="fa fa-trash"></i></button>
        <button title="Zoom In" id="btn-zoom-in"><i class="fa fa-plus"></i></button>
        <button title="Zoom Out" id="btn-zoom-out"><i class="fa fa-minus"></i></button>
        <button title="Reset View" id="btn-reset-view"><i class="fa fa-crosshairs"></i></button>
    </div>
    <!-- Ghost + Preview Card -->
    <div id="ghost-drag"></div>
    <div id="drag-preview"></div>
    {{-- Toast --}}
    <div id="toast-container"></div>
    {{-- Mobile sidebar --}}
    <button id="mobile-toggle-sidebar" style="display: none;">
        <i class="fas fa-bars"></i>
    </button>
    {{-- JS dependencies --}}
    <script src="{{ asset('js/litegraph.js') }}"></script>
    <script src="{{ asset('js/nodelist.js') }}"></script>
    <script src="{{ asset('js/minimap.js') }}"></script>
    <script src="{{ asset('js/flow.js') }}"></script>
    <script>
        // ========== Global Variables ==========
        let currentDraggingType = null;
        let touchDraggingNode = null;
        let touchOffset = [0, 0];
        let connectMode = {
            fromNode: null,
            fromSlot: null
        };
        let lastTap = {
            time: 0,
            nodeId: null,
            position: {
                x: 0,
                y: 0
            }
        };
        const DOUBLE_TAP_DELAY = 300; // ms
        const MAX_TAP_DISTANCE = 30; // px
        // ========== DOM Elements ==========
        const dragPreview = document.getElementById("drag-preview");
        const deleteBtn = document.getElementById("btn-delete-node");
        const connectBtn = document.getElementById("btn-open-connect-modal");
        // ========== Helper Functions ==========
        function isEditableNode(node) {
            if (!node?.properties) return false;
            const keys = Object.keys(node.properties);
            return keys.length > 0 && typeof node.properties[keys[0]] !== "object";
        }

        function convertEventToCanvasCoords(e, canvas) {
            const rect = canvas.canvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const scale = canvas.ds.scale;
            const offset = canvas.ds.offset;
            return [(x - offset[0]) / scale, (y - offset[1]) / scale];
        }

        function convertCanvasToGraphCoords(x, y) {
            const scale = canvas.ds.scale;
            const offset = canvas.ds.offset;
            return [x / scale - offset[0], y / scale - offset[1]];
        }

        function eventToCanvasPos(e) {
            const rect = canvas.canvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const scale = canvas.ds.scale;
            const offset = canvas.ds.offset;
            return [x / scale - offset[0], y / scale - offset[1]];
        }
        // ========== Node Grid Management ==========
        function renderNodeGrid() {
            const container = document.getElementById("node-grid");
            container.innerHTML = "";
            Object.entries(LiteGraph.registered_node_types)
                .filter(([type]) => type.startsWith("custom/"))
                .forEach(([type]) => {
                    const div = document.createElement("div");
                    div.className = "node-item";
                    div.innerText = customNodeTitles[type] || type;
                    div.draggable = true;
                    div.dataset.type = type;
                    // Desktop drag
                    div.addEventListener("dragstart", (e) => {
                        currentDraggingType = type;
                        dragPreview.textContent = customNodeTitles[type] || type;
                        const nodeInstance = LiteGraph.createNode(type);
                        const nodeColor = nodeInstance?.color || "#444";
                        dragPreview.style.backgroundColor = nodeColor;
                        dragPreview.style.borderColor = "#333";
                        dragPreview.style.left = `${e.clientX + 12}px`;
                        dragPreview.style.top = `${e.clientY + 12}px`;
                        dragPreview.style.transform = `scale(${canvas.ds.scale})`;
                        dragPreview.style.display = "block";
                        dragPreview.dataset.ready = "true";
                        e.dataTransfer.setDragImage(document.getElementById("ghost-drag"), 0, 0);
                    });
                    // Mobile touch
                    div.addEventListener("touchend", (e) => {
                        if (e.cancelable) e.preventDefault();
                        const touch = e.changedTouches[0];
                        const element = document.elementFromPoint(touch.clientX, touch.clientY);
                        if (element === div) {
                            const [cx, cy] = convertEventToCanvasCoords({
                                clientX: window.innerWidth / 2,
                                clientY: window.innerHeight / 2
                            }, canvas);
                            const node = LiteGraph.createNode(type);
                            node.pos = [cx, cy];
                            graph.add(node);
                            canvas.setDirty(true, true);
                        }
                    });
                    container.appendChild(div);
                });
            // Show/hide mobile toggle button based on screen size
            document.getElementById('mobile-toggle-sidebar').style.display =
                window.innerWidth <= 650 ? 'block' : 'none';
        }

        function refreshNodeTypeSelect() {
            renderNodeGrid();
        }
        // ========== Button State Management ==========
        function updateDeleteButtonState() {
            if (canvas.selected_node) {
                deleteBtn.disabled = false;
                deleteBtn.classList.remove("disabled");
            } else {
                deleteBtn.disabled = true;
                deleteBtn.classList.add("disabled");
            }
        }

        function updateConnectButtonState() {
            if (canvas.selected_node) {
                connectBtn.disabled = false;
                connectBtn.classList.remove("disabled");
            } else {
                connectBtn.disabled = true;
                connectBtn.classList.add("disabled");
            }
        }
        // ========== Node Selection Handlers ==========
        canvas.onNodeSelected = function(node) {
            canvas.selected_node = node;
            selectedNode = node;
            updateDeleteButtonState();
            updateConnectButtonState();
        };
        canvas.onNodeDeselected = function() {
            canvas.selected_node = null;
            updateDeleteButtonState();
            updateConnectButtonState();
        };
        // ========== Drag and Drop Handling ==========
        canvas.canvas.addEventListener("dragover", (e) => {
            e.preventDefault();
            if (!currentDraggingType) return;
            const rect = canvas.canvas.getBoundingClientRect();
            const mouseX = e.clientX - rect.left;
            const mouseY = e.clientY - rect.top;
            if (mouseX >= 0 && mouseY >= 0 && mouseX <= rect.width && mouseY <= rect.height) {
                if (dragPreview.dataset.ready === "false") {
                    dragPreview.style.display = "block";
                    dragPreview.dataset.ready = "true";
                }
                dragPreview.style.left = `${e.clientX + 12}px`;
                dragPreview.style.top = `${e.clientY + 12}px`;
                dragPreview.style.transform = `scale(${canvas.ds.scale})`;
            }
        });
        canvas.canvas.addEventListener("drop", (e) => {
            e.preventDefault();
            dragPreview.style.display = "none";
            const nodeType = e.dataTransfer.getData("text/plain") || currentDraggingType;
            if (!nodeType) return;
            const [canvasX, canvasY] = eventToCanvasPos(e);
            const node = LiteGraph.createNode(nodeType);
            if (!node) return showToast(`Gagal membuat node: ${nodeType}`, 'error');
            node.pos = [canvasX, canvasY];
            graph.add(node);
            canvas.setDirty(true, true);
            currentDraggingType = null;
        });
        // ========== Touch Handling ==========
        canvas.canvas.addEventListener("touchstart", function(e) {
            if (e.touches.length !== 1) return;
            const touch = e.touches[0];
            const [cx, cy] = canvas.convertEventToCanvasCoords(touch);
            const node = graph.getNodeOnPos(cx, cy, 10, true);
            if (node) {
                touchDraggingNode = node;
                touchOffset = [cx - node.pos[0], cy - node.pos[1]];
                canvas.selectNode(node);
                e.preventDefault();
            }
        }, {
            passive: false
        });
        canvas.canvas.addEventListener("touchmove", function(e) {
            if (!touchDraggingNode) return;
            const touch = e.touches[0];
            const [cx, cy] = canvas.convertEventToCanvasCoords(touch);
            touchDraggingNode.pos = [cx - touchOffset[0], cy - touchOffset[1]];
            canvas.setDirty(true, true);
            e.preventDefault();
        }, {
            passive: false
        });
        canvas.canvas.addEventListener("touchend", function(e) {
            if (e.cancelable) e.preventDefault();
            const touch = e.changedTouches[0];
            const currentNode = canvas.selected_node;
            if (currentNode) {
                const currentTime = Date.now();
                const isDoubleTap =
                    lastTap.nodeId === currentNode.id &&
                    (currentTime - lastTap.time) < DOUBLE_TAP_DELAY &&
                    Math.abs(touch.clientX - lastTap.position.x) < MAX_TAP_DISTANCE &&
                    Math.abs(touch.clientY - lastTap.position.y) < MAX_TAP_DISTANCE;
                lastTap = {
                    time: currentTime,
                    nodeId: currentNode.id,
                    position: {
                        x: touch.clientX,
                        y: touch.clientY
                    }
                };
                if (isDoubleTap && isEditableNode(currentNode)) {
                    openModal();
                } else {
                    selectedNode = currentNode;
                }
            }
            touchDraggingNode = null;
        }, {
            passive: false
        });
        // ========== Button Event Handlers ==========
        deleteBtn.addEventListener("click", () => {
            const node = canvas.selected_node;
            if (node && graph) {
                graph.remove(node);
                canvas.selected_node = null;
                updateDeleteButtonState();
                canvas.setDirty(true, true);
            }
        });
        document.getElementById("btn-open-connect-modal").onclick = () => {
            const node = selectedNode;
            if (!node) return showToast("No node selected", 'warning');
            const outputSelect = document.getElementById("output-select");
            outputSelect.innerHTML = "";
            node.outputs?.forEach((o, i) => {
                const opt = document.createElement("option");
                opt.value = i;
                opt.textContent = `${i}: ${o.name} [${o.type}]`;
                outputSelect.appendChild(opt);
            });
            const targetNodeSelect = document.getElementById("target-node-select");
            targetNodeSelect.innerHTML = "";
            graph._nodes.forEach(n => {
                if (n.id !== node.id) {
                    const opt = document.createElement("option");
                    opt.value = n.id;
                    opt.textContent = n.title || n.type;
                    targetNodeSelect.appendChild(opt);
                }
            });
            const inputSelect = document.getElementById("input-select");
            const filterInputs = () => {
                const targetId = +targetNodeSelect.value;
                const target = graph.getNodeById(targetId);
                const outIndex = +outputSelect.value;
                const outType = node.outputs?.[outIndex]?.type;
                inputSelect.innerHTML = "";
                target?.inputs?.forEach((inp, i) => {
                    if (!outType || !inp.type || inp.type === outType) {
                        const opt = document.createElement("option");
                        opt.value = i;
                        opt.textContent = `${i}: ${inp.name} [${inp.type}]`;
                        inputSelect.appendChild(opt);
                    }
                });
            };
            outputSelect.onchange = filterInputs;
            targetNodeSelect.onchange = filterInputs;
            outputSelect.onchange();
            targetNodeSelect.onchange();
            document.getElementById("connect-modal").style.display = "block";
        };
        document.getElementById("connect-confirm-btn").onclick = () => {
            const source = selectedNode;
            const outIndex = +document.getElementById("output-select").value;
            const targetId = +document.getElementById("target-node-select").value;
            const inIndex = +document.getElementById("input-select").value;
            const target = graph.getNodeById(targetId);
            const outType = source.outputs?.[outIndex]?.type;
            const inType = target.inputs?.[inIndex]?.type;
            if (!source || !target) return showToast("Invalid nodes", 'error');
            if (outType !== inType) return showToast(`Incompatible types: ${outType} → ${inType}`, 'error');
            source.connect(outIndex, target, inIndex);
            canvas.setDirty(true, true);
            document.getElementById("connect-modal").style.display = "none";
        };
        // ========== Mobile Sidebar Handling ==========
        document.getElementById('mobile-toggle-sidebar').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
        window.addEventListener('resize', function() {
            if (window.innerWidth > 650) {
                document.getElementById('sidebar').classList.remove('active');
            }
            document.getElementById('mobile-toggle-sidebar').style.display =
                window.innerWidth <= 650 ? 'block' : 'none';
        });
        // ========== Other Event Handlers ==========
        document.addEventListener("gesturestart", e => e.preventDefault());
        document.addEventListener("gesturechange", e => e.preventDefault());
        document.addEventListener("gestureend", e => e.preventDefault());
        ["touchstart", "touchmove", "touchend"].forEach(type => {
            canvas.canvas.addEventListener(type, e => {
                const touch = e.changedTouches[0];
                const eventType = {
                    "touchstart": "mousedown",
                    "touchmove": "mousemove",
                    "touchend": "mouseup"
                } [type];
                const simulated = new MouseEvent(eventType, {
                    bubbles: true,
                    cancelable: true,
                    clientX: touch.clientX,
                    clientY: touch.clientY
                });
                canvas.canvas.dispatchEvent(simulated);
            }, {
                passive: false
            });
        });
        // ========== Toolbar Button Handlers ==========
        document.getElementById('btn-start-flow').onclick = runFlowFromInputs;
        document.getElementById('btn-zoom-in').onclick = zoomIn;
        document.getElementById('btn-zoom-out').onclick = zoomOut;
        document.getElementById('btn-reset-view').onclick = resetZoom;
        // ========== Initialization ==========
        window.addEventListener("load", () => {
            initMinimap();
            renderNodeGrid();
            updateDeleteButtonState();
            updateConnectButtonState();
            // Initialize canvas coordinate conversion
            canvas.ds.allow_interaction = true;
            canvas.convertEventToCanvasCoords = function(e) {
                const rect = canvas.canvas.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                const scale = canvas.ds.scale;
                const offset = canvas.ds.offset;
                return [(x - offset[0]) / scale, (y - offset[1]) / scale];
            };
        });
        // ========== Toast Notification System ==========
        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            container.appendChild(toast);
            // Auto-remove after animation
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }
    </script>
</body>

</html>
