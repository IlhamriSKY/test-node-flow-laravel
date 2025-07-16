let MINIMAP_SCALE = 0.07;
const MIN_SCALE = 0.03;
const MAX_SCALE = 0.3;
let minimapCanvas = null;
let minimapCtx = null;
/**
 * Initialize the minimap system
 */
function initMinimap(canvasId = "minimap") {
    minimapCanvas = document.getElementById(canvasId);
    if (!minimapCanvas) {
        console.warn("Minimap canvas not found:", canvasId);
        return;
    }
    minimapCtx = minimapCanvas.getContext("2d");
    minimapCanvas.addEventListener("click", onMinimapClick);
    minimapCanvas.addEventListener("wheel", onMinimapScroll);
    // Auto refresh minimap every 200ms
    setInterval(drawMinimap, 200);
    // Center canvas at start
    setTimeout(() => {
        centerCanvasView();
    }, 100);
}
/**
 * Handle scroll on minimap to zoom the main canvas
 */
function onMinimapScroll(e) {
    e.preventDefault();
    const delta = e.deltaY < 0 ? 1.1 : 0.9;
    const newScale = canvas.ds.scale * delta;
    // Clamp zoom scale
    canvas.ds.scale = Math.min(Math.max(newScale, 0.2), 3);
    canvas.draw(true, true);
    drawMinimap();
}
/**
 * Draw the entire minimap view including nodes, links, and viewport
 */
function drawMinimap() {
    if (!minimapCanvas || !graph || !canvas) return;
    const ctx = minimapCtx;
    ctx.clearRect(0, 0, minimapCanvas.width, minimapCanvas.height);
    ctx.save();
    ctx.scale(MINIMAP_SCALE, MINIMAP_SCALE);
    // Draw all node connections (links)
    Object.values(graph.links).forEach(link => {
        const origin = graph.getNodeById(link.origin_id);
        const target = graph.getNodeById(link.target_id);
        if (!origin || !target) return;
        const a = origin.getConnectionPos(false, link.origin_slot);
        const b = target.getConnectionPos(true, link.target_slot);
        let color = origin.type === "custom/condition"
        ? link.origin_slot === 0
            ? "#00ffc3"
            : link.origin_slot === 1
            ? "#ff0055"
            : link.origin_slot === 2
            ? "#3aaaff"
            : "#000000"
        : "#3aaaff";
        const dist = Math.abs(a[0] - b[0]) * 0.5 + 10;
        ctx.beginPath();
        ctx.moveTo(a[0], a[1]);
        ctx.bezierCurveTo(a[0] + dist, a[1], b[0] - dist, b[1], b[0], b[1]);
        ctx.strokeStyle = color;
        ctx.lineWidth = 1.5 / MINIMAP_SCALE;
        ctx.globalAlpha = 0.5;
        ctx.stroke();
    });
    ctx.globalAlpha = 1;
    // Draw all nodes
    graph._nodes.forEach(node => {
        ctx.fillStyle = node.color || "#888";
        ctx.fillRect(node.pos[0], node.pos[1], node.size[0], node.size[1]);
    });
    // Draw visible viewport box
    const viewX = -canvas.ds.offset[0];
    const viewY = -canvas.ds.offset[1];
    const viewW = canvas.canvas.width / canvas.ds.scale;
    const viewH = canvas.canvas.height / canvas.ds.scale;
    ctx.save();
    ctx.beginPath();
    ctx.rect(0, 0, minimapCanvas.width / MINIMAP_SCALE, minimapCanvas.height / MINIMAP_SCALE);
    ctx.clip();
    ctx.strokeStyle = "rgba(255,255,255,0.7)";
    ctx.lineWidth = 3 / MINIMAP_SCALE;
    ctx.strokeRect(viewX, viewY, viewW, viewH);
    ctx.restore();
    ctx.restore();
}
/**
 * Handle click on minimap to move main canvas view
 */
function onMinimapClick(e) {
    if (!canvas || !canvas.ds) return;
    const rect = minimapCanvas.getBoundingClientRect();
    const x = (e.clientX - rect.left) / MINIMAP_SCALE;
    const y = (e.clientY - rect.top) / MINIMAP_SCALE;
    const centerX = canvas.canvas.width / 2;
    const centerY = canvas.canvas.height / 2;
    canvas.ds.offset[0] = -(x - centerX / canvas.ds.scale);
    canvas.ds.offset[1] = -(y - centerY / canvas.ds.scale);
    canvas.draw(true, true);
}
/**
 * Public function to reset canvas position and zoom
 */
function resetView() {
    if (!canvas || !canvas.ds) return;
    canvas.ds.offset = [0, 0];
    canvas.ds.scale = 1;
    canvas.draw(true, true);
    drawMinimap();
}
/**
 * Center the canvas view on the middle of the graph nodes and reset zoom
 */
function centerCanvasView() {
    if (!graph || !canvas || !canvas.ds) return;
    if (!graph._nodes?.length) return;
    let minX = Infinity,
        minY = Infinity,
        maxX = -Infinity,
        maxY = -Infinity;
    graph._nodes.forEach(node => {
        const [x, y] = node.pos;
        minX = Math.min(minX, x);
        minY = Math.min(minY, y);
        maxX = Math.max(maxX, x + node.size[0]);
        maxY = Math.max(maxY, y + node.size[1]);
    });
    const graphCenterX = (minX + maxX) / 2;
    const graphCenterY = (minY + maxY) / 2;
    const canvasCenterX = canvas.canvas.width / 2;
    const canvasCenterY = canvas.canvas.height / 2;
    // ✅ Reset zoom
    canvas.ds.scale = 1;
    // ✅ Set offset to center graph
    canvas.ds.offset[0] = -(graphCenterX - canvasCenterX);
    canvas.ds.offset[1] = -(graphCenterY - canvasCenterY);
    canvas.draw(true, true);
    drawMinimap();
}
// Expose to global
window.initMinimap = initMinimap;
window.resetView = resetView;
window.centerCanvasView = centerCanvasView;
