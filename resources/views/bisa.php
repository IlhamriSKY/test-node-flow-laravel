<!DOCTYPE html>
<html lang="en">

<head>
    <title>TEST Flow Visualizer</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" />
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

        html,
        body {
            height: 100%;
            margin: 0;
            padding: 0;
            background: var(--bg-main);
            color: var(--text-main);
            min-width: 100vw;
            min-height: 100vh;
            overflow: hidden;
            font-family: inherit;
        }

        body {
            height: 100vh;
            width: 100vw;
            display: flex;
            flex-direction: column;
        }

        #app {
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: row;
            width: 100vw;
            height: 100vh;
        }

        #toolbar {
            width: 100vw;
            height: 54px;
            background: var(--bg-toolbar);
            display: flex;
            align-items: center;
            gap: 18px;
            padding: 0 18px;
            border-bottom: 1px solid var(--border-main);
            box-sizing: border-box;
            position: relative;
            z-index: 3;
            user-select: none;
        }

        #toolbar h1 {
            font-size: 1.12rem;
            font-weight: 600;
            color: var(--accent);
            margin: 0 14px 0 0;
            letter-spacing: 1px;
            flex-shrink: 0;
            line-height: 1;
        }

        #toolbar .toolbar-spacer {
            flex: 1 1 auto;
        }

        #toolbar .toolbar-btn {
            background: var(--button-bg);
            border: none;
            color: #16181e;
            border-radius: var(--radius);
            font-size: 1rem;
            padding: 8px 16px;
            margin-right: 7px;
            cursor: pointer;
            font-weight: 600;
            letter-spacing: .03em;
            transition: background var(--transition), box-shadow var(--transition);
            box-shadow: 0 1px 6px rgba(0, 255, 195, 0.07);
            outline: none;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        #toolbar .toolbar-btn:hover {
            background: var(--button-hover);
        }

        #sidebar {
            width: var(--sidebar-width);
            background: var(--bg-sidebar);
            padding: 22px 16px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            gap: 20px;
            border-right: 1.5px solid var(--border-main);
            z-index: 2;
        }

        #sidebar label {
            color: var(--accent);
            font-size: 1.04rem;
            font-weight: 500;
            margin-bottom: 10px;
        }

        #sidebar select {
            width: 100%;
            padding: 12px;
            background: #23262e;
            color: var(--text-main);
            border: 1px solid var(--border-main);
            border-radius: var(--radius);
            font-size: 15px;
            outline: none;
            margin-bottom: 7px;
            transition: border var(--transition), background var(--transition);
        }

        #sidebar button {
            width: 100%;
            background: var(--button-bg);
            color: #13151c;
            padding: 13px 0;
            border: none;
            border-radius: var(--radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-bottom: 6px;
            transition: background var(--transition);
            box-shadow: 0 2px 10px rgba(0, 255, 195, 0.10);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
        }

        #sidebar button:hover {
            background: var(--button-hover);
        }

        #main {
            flex: 1 1 0%;
            min-width: 0;
            position: relative;
            background: var(--bg-main);
            height: 100%;
            display: flex;
            align-items: stretch;
            z-index: 1;
        }

        #graph-canvas {
            width: 100% !important;
            height: 100% !important;
            display: block;
            background: transparent;
        }

        #overlay {
            position: fixed;
            inset: 0;
            background: rgba(28, 30, 36, 0.58);
            display: none;
            z-index: 12;
        }

        #node-modal {
            position: fixed;
            left: 50%;
            top: 48%;
            transform: translate(-50%, -50%);
            background: #23262e;
            color: var(--text-main);
            padding: 28px 22px 22px 22px;
            border-radius: 14px;
            min-width: 280px;
            max-width: 340px;
            width: 90vw;
            box-shadow: 0 6px 38px rgba(0, 0, 0, 0.25), 0 1.5px 0 #00ffc342;
            display: none;
            z-index: 20;
            transition: opacity .18s cubic-bezier(.4, 0, .2, 1);
            font-family: inherit;
            text-align: left;
        }

        #node-modal h3 {
            margin-top: 0;
            margin-bottom: 16px;
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--accent);
        }

        #node-modal input {
            width: 100%;
            padding: 13px;
            background: #23262e;
            border: 1.5px solid var(--border-main);
            color: var(--text-main);
            font-size: 1rem;
            border-radius: var(--radius);
            box-sizing: border-box;
            outline: none;
            margin-bottom: 12px;
            margin-top: 2px;
            font-family: inherit;
        }

        #node-modal select {
            width: 100%;
            padding: 13px;
            background: #23262e;
            border: 1.5px solid var(--border-main);
            color: var(--text-main);
            font-size: 1rem;
            border-radius: var(--radius);
            box-sizing: border-box;
            outline: none;
            margin-bottom: 12px;
            margin-top: 2px;
            font-family: inherit;
        }

        #node-modal button {
            width: 100%;
            background: var(--button-bg);
            border: none;
            padding: 12px;
            font-size: 1rem;
            font-weight: bold;
            border-radius: var(--radius);
            cursor: pointer;
            transition: background var(--transition);
            margin-top: 3px;
            color: #181d1f;
        }

        #node-modal button:hover {
            background: var(--button-hover);
        }

        .highlighted-node {
            box-shadow: 0 0 16px 2px var(--accent-dim), 0 1px 0 #00ffc355 !important;
            border-radius: 16px !important;
            border: 2.5px solid var(--accent);
            background: #25362e !important;
            transition: box-shadow .12s, border .12s, background .18s;
        }

        ::-webkit-scrollbar {
            width: 7px;
        }

        ::-webkit-scrollbar-thumb {
            background: #32333a;
            border-radius: 8px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::selection {
            background: var(--accent-dim);
            color: #16181e;
        }

        @media (max-width: 650px) {
            #sidebar {
                display: none;
            }

            #main {
                margin-left: 0;
            }

            #toolbar {
                padding: 0 8px;
            }

            #node-modal {
                padding: 20px 10px;
            }
        }

        #zoom-controls {
            position: fixed;
            right: 24px;
            bottom: 24px;
            background: #1e2027cc;
            border-radius: 14px;
            box-shadow: 0 3px 14px #0006;
            z-index: 99;
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 14px 12px;
        }

        #zoom-controls button {
            width: 44px;
            height: 44px;
            margin: 0;
            padding: 0;
            background: var(--button-bg);
            color: #16181e;
            font-size: 1.4rem;
            border: none;
            border-radius: 50%;
            box-shadow: 0 2px 10px #00ffc314;
            cursor: pointer;
            font-weight: bold;
            transition: background .15s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #zoom-controls button:hover {
            background: var(--button-hover);
        }

        @media (max-width: 650px) {
            #zoom-controls {
                right: 7px;
                bottom: 10px;
                padding: 7px 6px;
            }

            #zoom-controls button {
                width: 32px;
                height: 32px;
                font-size: 1rem;
            }
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
            <select id="nodeType"></select>
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
        <div id="modal-fields"></div>
        <button onclick="saveNodeValue()"><i class="fa fa-save"></i>Save</button>
    </div>
    <div id="zoom-controls">
        <button title="Zoom In" onclick="zoomIn()"><i class="fa fa-plus"></i></button>
        <button title="Zoom Out" onclick="zoomOut()"><i class="fa fa-minus"></i></button>
        <button title="Reset Zoom" onclick="resetZoom()"><i class="fa fa-rotate-left"></i></button>
    </div>
    {{-- <script src="https://cdn.jsdelivr.net/gh/jagenjo/litegraph.js/build/litegraph.js"></script> --}}
    <script src="{{ asset('js/litegraph.js') }}"></script>
    {{-- <script src="{{ asset('js/nodes/node_command_input.js') }}"></script> --}}
    <script>
        // =======================
        // 1. DYNAMIC NODE LOADER
        // =======================
        // Daftar file node JSON yang ingin diload (array, bisa tambah banyak file!)
        const customNodeTitles = {};
        const NODE_JSON_LIST = [
            "https://rete-flow.dev/js/nodes/user_message.json",
            "https://rete-flow.dev/js/nodes/get_message.json",
            "https://rete-flow.dev/js/nodes/condition.json",
            "https://rete-flow.dev/js/nodes/delay.json",
            "https://rete-flow.dev/js/nodes/webhook.json",
            "https://rete-flow.dev/js/nodes/api_request.json",
            "https://rete-flow.dev/js/nodes/json_parser.json",
            "https://rete-flow.dev/js/nodes/logger.json"
        ];
        // Factory function: register node dari JSON definition
        function registerNodesFromJson(nodeDefs) {
            if (!Array.isArray(nodeDefs)) nodeDefs = [nodeDefs];
            nodeDefs.forEach(def => {
                function DynamicNode() {
                    this.properties = {
                        ...(def.properties || {})
                    };
                    // Tambah input
                    if (Array.isArray(def.inputs)) {
                        def.inputs.forEach(input => this.addInput(input.name, input.type));
                    }
                    // Tambah output
                    if (Array.isArray(def.outputs)) {
                        def.outputs.forEach(output => this.addOutput(output.name, output.type));
                    }
                    // Tambah widget & simpan widgetDef
                    this._widgetsDef = def.widgets || []; // <--- TAMBAHKAN INI
                    if (Array.isArray(def.widgets)) {
                        def.widgets.forEach(widget => {
                            if (widget.type === "text") {
                                this.addWidget("text", widget.name, this.properties[widget.property] || "",
                                    (v) => {
                                        this.properties[widget.property] = v;
                                        this.setDirtyCanvas(true, true);
                                    });
                            } else if (widget.type === "combo") {
                                this.addWidget("combo", widget.name, this.properties[widget.property] || "",
                                    (v) => {
                                        this.properties[widget.property] = v;
                                        this.setDirtyCanvas(true, true);
                                    }, {
                                        values: widget.options
                                    });
                            }
                        });
                    }
                    // Warna, judul, dll.
                    if (def.color) this.color = def.color;
                    this.title = def.title || def.type;
                    if (def.size && Array.isArray(def.size)) {
                        this.size = [...def.size];
                    }
                }
                // Eksekusi dinamis
                DynamicNode.prototype.onExecute = function() {
                    if (typeof def.onExecute === "string") {
                        try {
                            const fn = new Function(def.onExecute);
                            fn.call(this); // Jalankan dalam konteks node
                        } catch (e) {
                            console.warn(`[LiteGraph] Error in node '${def.type}' onExecute:`, e);
                        }
                    } else if (def.outputs?.length && def.widgets?.length) {
                        // Default fallback: kirim properti pertama ke output[0]
                        const prop = def.widgets[0].property;
                        this.setOutputData(0, this.properties[prop]);
                    }
                };
                // Sinkronisasi widget UI
                DynamicNode.prototype.syncWidget = function() {
                    if (Array.isArray(this.widgets) && Array.isArray(this._widgetsDef)) {
                        this.widgets.forEach((widget, i) => {
                            const def = this._widgetsDef[i];
                            if (!def) return;
                            if (def.property && this.properties.hasOwnProperty(def.property)) {
                                widget.value = this.properties[def.property];
                            }
                        });
                    }
                };
                // Simpan judul (untuk sidebar dropdown)
                customNodeTitles[def.type] = def.title || def.type;
                // Registrasi node ke LiteGraph
                LiteGraph.registerNodeType(def.type, DynamicNode);
            });
        }
        // Fetch semua file node JSON dan register ke LiteGraph
        Promise.all(NODE_JSON_LIST.map(url => fetch(url).then(r => r.json())))
            .then(allDefs => {
                allDefs.forEach(nodeDefs => registerNodesFromJson(nodeDefs));
                // Sidebar node select harus di-refresh setelah node sudah didaftarkan
                if (typeof refreshNodeTypeSelect === "function") refreshNodeTypeSelect();
            });
    </script>
    <!-- ======================== -->
    <!--         MAIN JS          -->
    <!-- ======================== -->
    <script>
        // ===== INIT & UTILS =====
        const canvasElement = document.getElementById('graph-canvas');
        const graph = new LGraph();
        const canvas = new LGraphCanvas("#graph-canvas", graph);
        let selectedNode = null,
            editingNode = null;
        // Sidebar: otomatis generate node type dari LiteGraph
        function refreshNodeTypeSelect() {
            const select = document.getElementById('nodeType');
            select.innerHTML = "";
            const types = Object.keys(LiteGraph.registered_node_types)
                .filter(type => type.startsWith("custom/"));
            types.forEach(type => {
                const opt = document.createElement("option");
                opt.value = type;
                // Pakai title dari customNodeTitles map
                opt.innerText = customNodeTitles[type] || type;
                select.appendChild(opt);
            });
        }
        // Panggil saat node type baru di-load
        setTimeout(refreshNodeTypeSelect, 150);
        // Responsive canvas
        function resizeCanvas() {
            const toolbarHeight = document.getElementById('toolbar').offsetHeight || 54;
            canvasElement.width = window.innerWidth - (window.innerWidth > 650 ? 240 : 0);
            canvasElement.height = window.innerHeight - toolbarHeight;
        }
        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();
        // ZOOM
        function zoomIn() {
            canvas.ds.scale = Math.min((canvas.ds.scale || 1) * 1.2, 3);
            canvas.draw(true, true);
        }

        function zoomOut() {
            canvas.ds.scale = Math.max((canvas.ds.scale || 1) / 1.2, 0.2);
            canvas.draw(true, true);
        }

        function resetZoom() {
            canvas.ds.scale = 1;
            canvas.draw(true, true);
        }
        // MODAL EDIT
        function openModal() {
            if (!selectedNode || !selectedNode.properties) return alert("No node selected or cannot edit!");
            const modal = document.getElementById("node-modal");
            const container = document.createElement("div");
            container.id = "modal-fields";
            container.innerHTML = ""; // reset
            for (const key in selectedNode.properties) {
                const val = selectedNode.properties[key];
                // Ambil widgetDef dari _widgetsDef (bukan widgets)
                let widgetDef = Array.isArray(selectedNode._widgetsDef) ?
                    selectedNode._widgetsDef.find(w => w.property === key) :
                    null;
                let field;
                if (widgetDef && widgetDef.type === "combo") {
                    field = document.createElement("select");
                    field.dataset.key = key;
                    let options = widgetDef.options || [];
                    if (Array.isArray(options)) {
                        options.forEach(option => {
                            const opt = document.createElement("option");
                            opt.value = option;
                            opt.textContent = option;
                            if (option == val) opt.selected = true;
                            field.appendChild(opt);
                        });
                    } else if (typeof options === "object") {
                        Object.entries(options).forEach(([label, value]) => {
                            const opt = document.createElement("option");
                            opt.value = value;
                            opt.textContent = label;
                            if (value == val) opt.selected = true;
                            field.appendChild(opt);
                        });
                    }
                } else {
                    field = document.createElement("input");
                    field.type = "text";
                    field.dataset.key = key;
                    field.placeholder = widgetDef && widgetDef.name ? widgetDef.name : key;
                    field.value = val;
                }
                container.appendChild(field);
            }
            const existing = modal.querySelector("#modal-fields");
            if (existing) existing.remove();
            modal.insertBefore(container, modal.querySelector("button"));
            editingNode = selectedNode;
            document.getElementById("overlay").style.display = "block";
            modal.style.display = "block";
        }

        function saveNodeValue() {
            if (!editingNode || !editingNode.properties) return closeModal();
            const fields = document.querySelectorAll("#modal-fields [data-key]");
            fields.forEach(input => {
                const key = input.dataset.key;
                editingNode.properties[key] = input.value;
            });
            if (typeof editingNode.syncWidget === "function") {
                editingNode.syncWidget();
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
        // NODE SELECTION
        canvas.onNodeSelected = function(node) {
            selectedNode = node;
        };
        canvas.onNodeDeselected = function() {
            selectedNode = null;
        };
        canvas.onNodeDblClicked = function(node) {
            selectedNode = node;
            if (isEditableNode(node)) openModal();
        };
        // Disable context menu
        LGraphCanvas.prototype.showMenu = function() {
            return false;
        };
        LiteGraph.ContextMenu = function() {
            return false;
        };
        LiteGraph.ContextMenu.prototype = {
            show: function() {
                return false;
            }
        };
        if (LiteGraph.ContextMenu.show) LiteGraph.ContextMenu.show = function() {
            return false;
        };
        canvas.onContextMenu = function(node, event) {
            if (event) event.preventDefault();
            return false;
        };
        // Utils: isEditableNode lebih general
        function isEditableNode(node) {
            if (!node || !node.properties) return false;
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
        // Animasi garis aktif
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
        (function animateLinks() {
            canvas.draw(true, true);
            requestAnimationFrame(animateLinks);
        })();
        // Highlight node
        function highlightNode(node) {
            if (!node || node._custom_highlight) return;
            node._custom_highlight = true;
            node._glowFrame = 0;
            node.boxcolor = "#00ff88"; // ✅ Ganti warna menjadi hijau terang
            node._glowInterval = setInterval(() => {
                node._glowFrame++;
                node.setDirtyCanvas(true, true);
                if (node._glowFrame > 14) {
                    clearInterval(node._glowInterval);
                    removeNodeHighlight(node);
                }
            }, 42);
            node._old_dirty_draw = node.onDrawBackground;
            node.onDrawBackground = function(ctx) {
                const f = node._glowFrame || 0,
                    pulse = 0.22 + 0.28 * Math.sin(f / 3);
                const titleHeight = LiteGraph.NODE_TITLE_HEIGHT || 24,
                    yOffset = -titleHeight - 7;
                const glowHeight = this.size[1] + titleHeight + 14;
                ctx.save();
                ctx.globalAlpha = 0.15 + 0.18 * pulse;
                ctx.fillStyle = "rgba(0,255,195,1)";
                ctx.beginPath();
                ctx.roundRect(-7, yOffset, this.size[0] + 14, glowHeight, 17);
                ctx.fill();
                const gradient = ctx.createLinearGradient(0, yOffset, this.size[0], yOffset + glowHeight);
                gradient.addColorStop(0, "#00ffc3");
                gradient.addColorStop(1, "#60ffe6");
                ctx.globalAlpha = 0.58 + 0.21 * pulse;
                ctx.shadowColor = "#60ffe6";
                ctx.shadowBlur = 13 + 10 * pulse;
                ctx.lineWidth = 3.1 + 1.7 * pulse;
                ctx.strokeStyle = gradient;
                ctx.beginPath();
                ctx.roundRect(-4, yOffset + 3, this.size[0] + 8, glowHeight - 6, 13);
                ctx.stroke();
                ctx.restore();
                ctx.save();
                ctx.globalAlpha = 0.06 + 0.05 * pulse;
                ctx.strokeStyle = "#fff";
                ctx.lineWidth = 1.1;
                ctx.beginPath();
                ctx.roundRect(2, yOffset + 8, this.size[0] - 4, glowHeight - 16, 8);
                ctx.stroke();
                ctx.restore();
                if (typeof node._old_dirty_draw === 'function') node._old_dirty_draw(ctx);
            };
        }

        function removeNodeHighlight(node) {
            if (!node || !node._custom_highlight) return;
            if (node._glowInterval) clearInterval(node._glowInterval);
            node.bgcolor = "#222";
            node.boxcolor = LiteGraph.NODE_DEFAULT_BOXCOLOR;
            node.onDrawBackground = node._old_dirty_draw || null;
            node._custom_highlight = false;
            node._glowFrame = 0;
            node.setDirtyCanvas(true, true);
        }
        // ========== FLOW LOGIC ==========
        function resetNodes() {
            graph._nodes.forEach(node => {
                removeNodeHighlight(node);
                if (node.title === "Logger") node.logs = [];
                if (typeof node.resetTimeouts === "function") node.resetTimeouts();
                if (node.type === "custom/delay") {
                    node._delaying = false;
                    node._delayValue = undefined;
                    node._hasDelayed = false;
                    // Tambahkan baris ini:
                    node.setOutputData && node.setOutputData(1, undefined); // <-- Reset output Value!
                }
            });
        }

        LiteGraph.LGraphNode.prototype.triggerSlot = function(slot, param) {
            if (!this.outputs || !this.outputs[slot]) return;
            const output = this.outputs[slot];
            if (!output.links) return;
            for (let i = 0; i < output.links.length; ++i) {
                const linkId = output.links[i];
                const link = this.graph.links[linkId];
                if (!link) continue;
                const nextNode = this.graph.getNodeById(link.target_id);
                if (nextNode) {
                    setTimeout(() => executeFlowFromNode(nextNode), 0);
                }
            }
        };

        function executeFlowFromNode(node, visited = new Set()) {
            if (!node || visited.has(node.id)) return;
            visited.add(node.id);
            highlightNode(node);
            if (!graph._flow_id) graph._flow_id = 1;
            else graph._flow_id++;
            // ============ NODE ASYNC (fetch) ============
            let isAsyncFetch =
                node.properties && node.properties.url &&
                node.outputs && node.outputs.some(o => o.name && o.name.toLowerCase().includes('response'));
            if (isAsyncFetch) {
                if (node._executing) return;
                node._executing = true;
                const props = node.properties;
                const url = props.url;
                const method = (props.method || 'GET').toUpperCase();
                let headers = {};
                let body = null;
                try {
                    headers = props.headers ? JSON.parse(props.headers) : {};
                } catch (e) {
                    headers = {};
                }
                try {
                    body = props.body ? JSON.parse(props.body) : null;
                } catch (e) {
                    body = null;
                }
                let fetchOpts = {
                    method,
                    headers
                };
                if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method) && body) fetchOpts.body = JSON.stringify(body);
                fetch(url, fetchOpts)
                    .then(res => res.json())
                    .then(data => {
                        let responseIdx = node.outputs.findIndex(o => o.name.toLowerCase().includes('response'));
                        if (responseIdx === -1) responseIdx = 0;
                        node.setOutputData(responseIdx, typeof data === "object" ? JSON.stringify(data, null, 2) :
                            String(data));
                        node._executing = false;
                        // Otomatis cari output "after" buat next flow
                        let flowIdx = node.outputs.findIndex(o => o.name.toLowerCase().includes('after') || o.type ===
                            "flow");
                        if (flowIdx === -1) flowIdx = 0;
                        if (node.outputs[flowIdx] && node.outputs[flowIdx].links) {
                            for (let linkId of node.outputs[flowIdx].links) {
                                const link = graph.links[linkId];
                                if (link) {
                                    activeLinks.push({
                                        id: linkId,
                                        start: Date.now()
                                    });
                                    const nextNode = graph.getNodeById(link.target_id);
                                    if (nextNode) setTimeout(() => executeFlowFromNode(nextNode, visited), 150);
                                }
                            }
                        }
                    })
                    .catch((e) => {
                        let responseIdx = node.outputs.findIndex(o => o.name.toLowerCase().includes('response'));
                        if (responseIdx === -1) responseIdx = 0;
                        node.setOutputData(responseIdx, "ERROR: " + e.message);
                        node._executing = false;
                    });
                return;
            }
            // =========== NODE SYNC BIASA ==========
            if (typeof node.onExecute === "function") {
                node._lastTriggeredSlot = null;
                node.onExecute();
            }
            // =========== FLOW (True/False) ==========
            if (node.outputs && node.outputs.length > 0) {
                const slotIndex = typeof node._lastTriggeredSlot === "number" ? node._lastTriggeredSlot : null;
                for (let i = 0; i < node.outputs.length; i++) {
                    const output = node.outputs[i];
                    if (slotIndex !== null && i !== slotIndex) continue;
                    if (!output || !output.links) continue;
                    for (let linkId of output.links) {
                        const link = graph.links[linkId];
                        activeLinks.push({
                            id: linkId,
                            start: Date.now()
                        });
                        if (link) {
                            const nextNode = graph.getNodeById(link.target_id);
                            if (nextNode) setTimeout(() => executeFlowFromNode(nextNode, visited), 150);
                        }
                    }
                }
            }
            // =========== PATCH: Animasi output "Value" hanya di branch aktif ==========
            if (node.outputs) {
                if (typeof node._lastTriggeredSlot === "number") {
                    // Cari output "Value"
                    const valueOutputIndex = node.outputs.findIndex(o => o && o.name && o.name.toLowerCase() === "value");
                    const valueOutput = node.outputs[valueOutputIndex];
                    const flowOutput = node.outputs[node._lastTriggeredSlot];
                    if (valueOutput && valueOutput.links && flowOutput && flowOutput.links && flowOutput.links.length) {
                        // Ambil semua node tujuan flow aktif (bisa lebih dari 1!)
                        let validTargetIds = [];
                        for (let linkId of flowOutput.links) {
                            const flowLink = graph.links[linkId];
                            if (flowLink) validTargetIds.push(flowLink.target_id);
                        }
                        // Untuk semua link "Value", cek: target-nya adalah salah satu node tujuan flow aktif
                        for (let linkId of valueOutput.links) {
                            const valueLink = graph.links[linkId];
                            if (valueLink && validTargetIds.includes(valueLink.target_id)) {
                                activeLinks.push({
                                    id: linkId,
                                    start: Date.now()
                                });
                            }
                        }
                    }
                }
            }
        }

        function runFlowFromInputs() {
            resetNodes();
            graph._nodes.forEach(node => {
                if (!node.inputs || node.inputs.length === 0) {
                    executeFlowFromNode(node);
                }
            });
            canvas.draw(true, true);
        }
        // Canvas settings
        canvas.prompt = function() {
            return;
        };
        canvas.allow_searchbox = false;
        LiteGraph.allow_searchbox = false;
        LiteGraph.always_show_properties = false;
        LiteGraph.debug = false;
        canvas.ds.scale = 1;
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
                // Default: biru tua
                let color = "#3aaaff";
                if (origin.type === "custom/condition") {
                    if (link.origin_slot === 0) color = "#00ffc3";
                    if (link.origin_slot === 1) color = "#ff0055";
                }
                this.renderLink(ctx, a, b, link, false, isActive, color);
            }
            ctx.restore();
        };
        LGraphCanvas.prototype.drawDraggedConnection = function(ctx) {};
        canvas.drawLink = function(ctx, a, b, link) {
            let color = "#3aaaff";
            if (canvas.connecting_node && canvas.connecting_node.type === "custom/condition") {
                let slot = canvas.connecting_slot || 0;
                if (slot === 0) color = "#00ffc3";
                if (slot === 1) color = "#ff0055";
            }
            ctx.save();
            ctx.beginPath();
            ctx.moveTo(a[0], a[1]);
            const dist = Math.abs(a[0] - b[0]) * 0.5 + 10;
            ctx.bezierCurveTo(a[0] + dist, a[1], b[0] - dist, b[1], b[0], b[1]);
            ctx.strokeStyle = color;
            ctx.lineWidth = 3;
            ctx.globalAlpha = 0.7;
            ctx.stroke();
            ctx.restore();
        };
        canvas.draw(true, true);
    </script>
</body>

</html>
