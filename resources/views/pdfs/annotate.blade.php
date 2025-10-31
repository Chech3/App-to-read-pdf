@extends('layouts.app')

@section('content')
<div class="container-fluid mt-4">
    <h2>Anotar PDF: {{ $pdf->name }}</h2>

    <div class="mb-3 d-flex gap-2">
        <a href="{{ route('pdfs.index') }}" class="btn btn-secondary">⬅️ Volver</a>
        <button id="toggleDraw" class="btn btn-primary">✏️ Modo Dibujo</button>
        <button id="saveAnnotations" class="btn btn-success">💾 Guardar Anotaciones</button>

        <input id="searchText" type="text" class="form-control w-25 ms-3" placeholder="Buscar texto...">
        <button id="btnSearch" class="btn btn-warning">🔍 Buscar</button>
    </div>

    <div id="pdfContainer"
         style="position: relative; border: 1px solid #ccc; height: 90vh; overflow-y: auto; background: #f8f9fa;">
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.14.305/pdf.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.14.305/pdf.worker.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js" defer></script>

<script>
const pdfUrl = "{{ asset('storage/' . $pdf->path) }}";
const saveUrl = "{{ route('pdfs.annotate.save', $pdf->id) }}";
const csrfToken = "{{ csrf_token() }}";

pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.14.305/pdf.worker.min.js';

const container = document.getElementById('pdfContainer');
let fabricCanvases = [];
let drawingEnabled = true;

// almacenar info por página
const pagesMeta = [];

function escapeRegExp(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

// Render PDF
pdfjsLib.getDocument(pdfUrl).promise.then(pdf => {
    for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
        (function(pageIndex) {
            pdf.getPage(pageIndex).then(async page => {

                const baseViewport = page.getViewport({ scale: 1 });
                const containerWidth = container.clientWidth - 40;
                const scale = containerWidth / baseViewport.width;
                const scaledViewport = page.getViewport({ scale });

                const pageWrapper = document.createElement('div');
                pageWrapper.style.position = 'relative';
                pageWrapper.style.margin = '0 auto';
                pageWrapper.style.marginBottom = '30px';
                pageWrapper.style.padding = '0';
                pageWrapper.style.background = '#fff';
                pageWrapper.style.boxShadow = '0 0 8px rgba(0,0,0,0.2)';
                pageWrapper.style.width = scaledViewport.width + 'px';
                container.appendChild(pageWrapper);

                // PDF canvas
                const pdfCanvas = document.createElement('canvas');
                const pdfCtx = pdfCanvas.getContext('2d');
                pdfCanvas.width = scaledViewport.width;
                pdfCanvas.height = scaledViewport.height;
                pdfCanvas.classList.add("pdf-canvas");
                pageWrapper.appendChild(pdfCanvas);

                // highlight canvas
                const highlightCanvas = document.createElement('canvas');
                highlightCanvas.width = scaledViewport.width;
                highlightCanvas.height = scaledViewport.height;
                highlightCanvas.classList.add("highlight-canvas");
                pageWrapper.appendChild(highlightCanvas);

                // draw canvas (fabric)
                const drawCanvas = document.createElement('canvas');
                drawCanvas.width = scaledViewport.width;
                drawCanvas.height = scaledViewport.height;
                drawCanvas.classList.add("overlay-canvas");
                pageWrapper.appendChild(drawCanvas);

                await page.render({ canvasContext: pdfCtx, viewport: scaledViewport }).promise;

                const fc = new fabric.Canvas(drawCanvas, {
                    isDrawingMode: drawingEnabled,
                    selection: false,
                });
                fc.freeDrawingBrush.width = 3;
                fc.freeDrawingBrush.color = "red";
                fabricCanvases.push(fc);

                const textContent = await page.getTextContent();
                const items = [];

                textContent.items.forEach(item => {
                    const tx = item.transform;
                    const x1 = tx[4];
                    const y1 = tx[5];
                    const x2 = x1 + (item.width || 0);
                    const estHeight = Math.abs(tx[3] || item.height || 10);
                    const y2 = y1 - estHeight;

                    const rect = scaledViewport.convertToViewportRectangle([x1, y1, x2, y2]);

                    const left = Math.min(rect[0], rect[2]);
                    const top = Math.min(rect[1], rect[3]);
                    const width = Math.abs(rect[2] - rect[0]);
                    const height = Math.abs(rect[3] - rect[1]);

                    items.push({
                        str: item.str,
                        left,
                        top,
                        width,
                        height
                    });
                });

                pagesMeta[pageIndex - 1] = {
                    highlightCanvas,
                    textItems: items,
                };
            });
        })(pageNum);
    }
});

// Toggle draw mode
document.getElementById('toggleDraw').addEventListener('click', () => {
    drawingEnabled = !drawingEnabled;
    fabricCanvases.forEach(fc => fc.isDrawingMode = drawingEnabled);
    alert('Modo dibujo: ' + (drawingEnabled ? 'Activado' : 'Desactivado'));
});

// Save annotations
document.getElementById('saveAnnotations').addEventListener('click', async () => {
    const overlays = fabricCanvases.map(fc => fc.toDataURL("image/png"));

    const response = await fetch(saveUrl, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": csrfToken
        },
        body: JSON.stringify({ overlays })
    });

    const result = await response.json();
    if (result.success) {
        alert("✅ PDF anotado guardado correctamente");
        window.location.href = "{{ route('pdfs.index') }}";
    } else {
        alert("❌ Error al guardar anotaciones.");
    }
});

// Search & highlight
document.getElementById("btnSearch").addEventListener("click", () => {
    const query = document.getElementById("searchText").value.trim();
    if (!query) return;

    const re = new RegExp("\\b" + escapeRegExp(query) + "\\b", "i");

    pagesMeta.forEach(pm => {
        if (!pm) return;
        const ctx = pm.highlightCanvas.getContext("2d");
        ctx.clearRect(0, 0, pm.highlightCanvas.width, pm.highlightCanvas.height);

        pm.textItems.forEach(item => {
            if (re.test(item.str)) {
                ctx.fillStyle = "rgba(255,255,0,0.45)";
                ctx.fillRect(item.left, item.top, item.width, item.height);
            }
        });
    });
});

document.getElementById("searchInput").addEventListener("keydown", function(e) {
    if (e.key === "Enter") {
        e.preventDefault(); // Evita que el formulario se envíe
        highlightSearchTerm(); // Llama la función de búsqueda
    }
});
</script>

<style>
#pdfContainer {
    text-align: center;
}
.pdf-canvas {
    position: absolute !important;
    top: 0;
    left: 0;
}
.overlay-canvas {
    position: absolute !important;
    top: 0;
    left: 0;
    z-index: 20;
}
.highlight-canvas {
    position: absolute !important;
    top: 0;
    left: 0;
    z-index: 10;
    pointer-events: none;
}
</style>
@endsection
