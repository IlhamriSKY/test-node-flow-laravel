<!DOCTYPE html>
<html lang="en">

<head>
    <title>Flow Visualizer</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" />
    <link rel="stylesheet" href="{{ asset('css/flow.css') }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
</head>

<body>
    <div id="toolbar">
        <h1><i class="fa-solid fa-diagram-project"></i> LiteGraph Flow</h1>
        <div class="toolbar-spacer"></div>
        <button class="toolbar-btn" id="btn-run-flow"><i class="fa fa-play"></i>Start Flow</button>
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
        <button id="btn-save-node"><i class="fa fa-save"></i>Save</button>
    </div>
    <div id="zoom-controls">
        <button title="Zoom In" id="btn-zoom-in"><i class="fa fa-plus"></i></button>
        <button title="Zoom Out" id="btn-zoom-out"><i class="fa fa-minus"></i></button>
        <button title="Reset View" id="btn-reset-view"><i class="fa fa-crosshairs"></i></button>
    </div>
    <!-- Ghost + Preview Card -->
    <div id="ghost-drag"></div>
    <div id="drag-preview"></div>
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
        // Toolbar buttons
        document.getElementById('btn-run-flow').onclick = runFlowFromInputs;
        document.getElementById('btn-zoom-in').onclick = zoomIn;
        document.getElementById('btn-zoom-out').onclick = zoomOut;
        document.getElementById('btn-reset-view').onclick = resetZoom;
        // ✅ FIXED: Button Save handler for modal
        window.addEventListener("load", () => {
            initMinimap();
            document.getElementById("btn-save-node").onclick = saveNodeValue;
        });
    </script>
    <script>
        const dragPreview = document.getElementById("drag-preview");
        let currentDraggingType = null;

        function convertEventToCanvasCoords(e, canvas) {
            const rect = canvas.canvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const scale = canvas.ds.scale;
            const offset = canvas.ds.offset;
            return [(x - offset[0]) / scale, (y - offset[1]) / scale];
        }

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
                        e.preventDefault();
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
            // Tampilkan tombol toggle di mobile
            if (window.innerWidth <= 650) {
                document.getElementById('mobile-toggle-sidebar').style.display = 'block';
            }
        }
        canvas.canvas.addEventListener("dragover", (e) => {
            e.preventDefault();
            if (!currentDraggingType) return;
            // Ambil posisi mouse relatif ke canvas
            const rect = canvas.canvas.getBoundingClientRect();
            const mouseX = e.clientX - rect.left;
            const mouseY = e.clientY - rect.top;
            // Pastikan mouse berada dalam area canvas (tidak negatif / keluar)
            if (
                mouseX >= 0 && mouseY >= 0 &&
                mouseX <= rect.width &&
                mouseY <= rect.height
            ) {
                // Baru aktifkan preview
                if (dragPreview.dataset.ready === "false") {
                    dragPreview.style.display = "block";
                    dragPreview.dataset.ready = "true";
                }
                dragPreview.style.left = `${e.clientX + 12}px`;
                dragPreview.style.top = `${e.clientY + 12}px`;
                dragPreview.style.transform = `scale(${canvas.ds.scale})`;
            }
        });

        function eventToCanvasPos(e) {
            const rect = canvas.canvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const scale = canvas.ds.scale;
            const offset = canvas.ds.offset;
            return [
                x / scale - offset[0],
                y / scale - offset[1]
            ];
        }
        canvas.canvas.addEventListener("drop", (e) => {
            e.preventDefault();
            dragPreview.style.display = "none";
            const nodeType = e.dataTransfer.getData("text/plain") || currentDraggingType;
            if (!nodeType) return;
            const [canvasX, canvasY] = eventToCanvasPos(e);
            const node = LiteGraph.createNode(nodeType);
            if (!node) return alert("Gagal membuat node: " + nodeType);
            node.pos = [canvasX, canvasY];
            graph.add(node);
            canvas.setDirty(true, true);
            currentDraggingType = null;
        });
        const originalRefresh = refreshNodeTypeSelect;
        refreshNodeTypeSelect = function() {
            originalRefresh?.();
            renderNodeGrid();
        };
        window.addEventListener("load", () => {
            initMinimap();
        });
        let touchStartTime = 0;
        let touchStartX = 0;
        let touchStartY = 0;
        canvas.canvas.addEventListener("touchstart", (e) => {
            touchStartTime = Date.now();
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
        });
        canvas.canvas.addEventListener("touchend", (e) => {
            const duration = Date.now() - touchStartTime;
            const distance = Math.sqrt(
                Math.pow(e.changedTouches[0].clientX - touchStartX, 2) +
                Math.pow(e.changedTouches[0].clientY - touchStartY, 2)
            );
            // Jika long press (500ms+) dan pergerakan kecil
            if (duration > 500 && distance < 10) {
                const touch = e.changedTouches[0];
                const pos = [touch.clientX, touch.clientY];
                const node = graph.getNodeOnPos(pos[0], pos[1]);
                if (node) {
                    canvas.selectNode(node);
                    openNodeContextMenu(node, pos);
                }
            }
        });
    </script>
    <script>
        // Di dalam script yang sudah ada, tambahkan:
        document.getElementById('mobile-toggle-sidebar').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 650) {
                document.getElementById('sidebar').classList.remove('active');
                document.getElementById('mobile-toggle-sidebar').style.display = 'none';
            } else {
                document.getElementById('mobile-toggle-sidebar').style.display = 'block';
            }
        });
    </script>
    <script>
        // ========== Mobile Touch Drag for Nodes ==========
        let touchDraggingNode = null;
        let touchOffset = [0, 0];
        canvas.convertEventToCanvasCoords = function(e) {
            const rect = canvas.canvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const scale = canvas.ds.scale;
            const offset = canvas.ds.offset;
            return [(x - offset[0]) / scale, (y - offset[1]) / scale];
        };
        canvas.canvas.addEventListener("touchstart", function(e) {
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
        canvas.canvas.addEventListener("touchend", function() {
            touchDraggingNode = null;
        }, {
            passive: false
        });
        // Mouse event spoofing to make LiteGraph react to touches
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
        // Aktifkan interaksi canvas
        canvas.ds.allow_interaction = true;
        // ========== Nonaktifkan Pinch Zoom Page ==========
        document.addEventListener("gesturestart", e => e.preventDefault());
        document.addEventListener("gesturechange", e => e.preventDefault());
        document.addEventListener("gestureend", e => e.preventDefault());
        // ========== Tap-to-Connect Mode ==========
        let pendingConnection = null;
        canvas.canvas.addEventListener("touchend", function(e) {
            const touch = e.changedTouches[0];
            const [cx, cy] = canvas.convertEventToCanvasCoords(touch);
            const node = graph.getNodeOnPos(cx, cy, 10, true);
            if (!node) return;
            const slot = canvas.getSlotOnPos(cx, cy);
            if (!slot) return;
            if (slot[0] === LiteGraph.OUTPUT) {
                // Simpan output
                pendingConnection = {
                    node,
                    slotIndex: slot[1]
                };
            } else if (slot[0] === LiteGraph.INPUT && pendingConnection) {
                // Sambungkan dari pending output ke input
                const inputNode = node;
                const inputSlot = slot[1];
                pendingConnection.node.connect(pendingConnection.slotIndex, inputNode, inputSlot);
                canvas.setDirty(true, true);
                pendingConnection = null;
            }
        }, {
            passive: false
        });
    </script>
    <script>
// ========== EASY CONNECT MODE FOR MOBILE ==========
let connectMode = {
    fromNode: null,
    fromSlot: null
};

// Saat user tap node pertama
canvas.canvas.addEventListener("touchstart", function (e) {
    if (e.touches.length !== 1) return;

    const touch = e.touches[0];
    const [cx, cy] = canvas.convertEventToCanvasCoords(touch);
    const node = graph.getNodeOnPos(cx, cy, 10, true);
    if (!node) return;

    // Jika belum ada koneksi, pilih output
    if (!connectMode.fromNode) {
        const outputSlots = node.outputs || [];
        if (outputSlots.length === 1) {
            connectMode.fromNode = node;
            connectMode.fromSlot = 0;
            canvas.selectNode(node);
            canvas.setDirty(true, true);
        } else if (outputSlots.length > 1) {
            // TODO: tampilkan UI pilih slot jika lebih dari satu
            alert("Node ini punya banyak output. Pilih slot dulu.");
        }
        e.preventDefault();
    } else {
        // Jika sudah pilih from, sekarang pilih tujuan
        const targetNode = node;
        const inputSlots = targetNode.inputs || [];
        if (inputSlots.length > 0) {
            connectMode.fromNode.connect(connectMode.fromSlot, targetNode, 0);
            connectMode = { fromNode: null, fromSlot: null };
            canvas.setDirty(true, true);
        }
        e.preventDefault();
    }
}, { passive: false });

// Reset mode jika user tap kosong
canvas.canvas.addEventListener("touchend", (e) => {
    if (!connectMode.fromNode) return;

    setTimeout(() => {
        connectMode = { fromNode: null, fromSlot: null };
    }, 3000); // auto-reset setelah 3 detik
}, { passive: false });
</script>

</body>

</html>
