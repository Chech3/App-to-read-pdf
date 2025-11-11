@extends('layouts.app')

@section('content')
    <div class="container-fluid mt-4">
        <h2>Anotar PDF: {{ $pdf->name }}</h2>

        <div class="mb-3 d-flex gap-2 align-items-center">
            <a href="{{ route('pdfs.index') }}" class="btn btn-secondary">⬅️ Volver</a>
            <button id="toggleDraw" class="btn btn-primary">✏️ Modo Dibujo</button>
            <button id="saveAnnotations" class="btn btn-success">💾 Guardar Anotaciones</button>

            <button id="btnComplete" class="btn btn-success">✅ COMPLETE</button>
            <button id="btnIncomplete" class="btn btn-danger">❌ INCOMPLETE</button>
            <input id="searchText" type="text" class="form-control w-25 ms-3" placeholder="Buscar texto...">



            <button id="btnSearch" class="btn btn-warning">🔍 Buscar</button>

            {{-- <span id="matchCounter" class="ms-2 fw-bold text-primary"></span> --}}
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
        let drawingEnabled = false;
        let addMode = null; // "complete" | "incomplete"

        // ---- Buscador ----
        let matches = [];
        let currentMatchIndex = -1;
        const pagesMeta = [];

        function escapeRegExp(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

        // ---- Render PDF ----
        pdfjsLib.getDocument(pdfUrl).promise.then(pdf => {
            for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
                ((pageIndex) => {
                    pdf.getPage(pageIndex).then(async page => {


                        const baseViewport = page.getViewport({
                            scale: 1
                        });
                        const containerWidth = container.clientWidth - 40;
                        const scale = containerWidth / baseViewport.width;
                        const scaledViewport = page.getViewport({
                            scale
                        });
                        page.scaleFactor = scale;


                        const pageWrapper = document.createElement('div');
                        pageWrapper.id = `page_${pageIndex}`;
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

                        // draw canvas
                        const drawCanvas = document.createElement('canvas');
                        drawCanvas.width = scaledViewport.width;
                        drawCanvas.height = scaledViewport.height;
                        drawCanvas.classList.add("overlay-canvas");
                        pageWrapper.appendChild(drawCanvas);

                        await page.render({
                            canvasContext: pdfCtx,
                            viewport: scaledViewport
                        }).promise;

                        const fc = new fabric.Canvas(drawCanvas, {
                            isDrawingMode: drawingEnabled,
                            selection: false,
                        });

                        fc.on("mouse:down", (opt) => {
                            if (!addMode) return;

                            const pointer = fc.getPointer(opt.e);

                            if (addMode === "complete") {
                                addStamp(fc, pointer.x, pointer.y, "COMPLETE", "green");
                            }

                            if (addMode === "incomplete") {
                                addStamp(fc, pointer.x, pointer.y, "INCOMPLETE", "red");
                            }

                            addMode = null;
                        });

                        fc.freeDrawingBrush.width = 3;
                        fc.freeDrawingBrush.color = "red";
                        fabricCanvases[pageIndex - 1] = fc;

                        const textContent = await page.getTextContent();
                        const items = [];

                        textContent.items.forEach(item => {
                            const tx = item.transform;
                            const x1 = tx[4];
                            const y1 = tx[5];
                            const x2 = x1 + (item.width || 0);
                            const estH = Math.abs(tx[3] || item.height || 10);
                            const y2 = y1 - estH;

                            const rect = scaledViewport.convertToViewportRectangle([x1, y1,
                                x2, y2
                            ]);
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
                            viewport: scaledViewport,
                            scaleFactor: scale,
                        };
                    });
                })(pageNum);
            }
        });

        async function addStamp(fc, x, y, text, color) {

            let noteText = "";

            // Si es INCOMPLETE, pedir texto al usuario
            if (text === "INCOMPLETE") {
                noteText = prompt("Describe qué falta o cuál es el error:");
                if (!noteText) noteText = "(sin detalles)";
            }

            const rect = new fabric.Rect({
                width: 140,
                height: noteText ? 60 : 40,
                fill: color,
                rx: 6,
                ry: 6,
                stroke: "black",
                strokeWidth: 1,
                originX: "center",
                originY: "center"
            });

            const label = new fabric.Text(text, {
                fontSize: 20,
                fill: "white",
                fontWeight: "bold",
                originX: "center",
                originY: "center",
                top: noteText ? -10 : 0,
                left: 0
            });

            let groupObjects = [rect, label];

            if (noteText) {
                const note = new fabric.Text(noteText, {
                    fontSize: 18,
                    fill: "white",
                    originX: "center",
                    originY: "center",
                    top: 15
                });
                groupObjects.push(note);
            }

            const group = new fabric.Group(groupObjects, {
                left: x,
                top: y,
                selectable: true,
                originX: "center",
                originY: "center"
            });

            fc.add(group);
            fc.renderAll();
        }

        // ---- Modo dibujo ----
        document.getElementById('toggleDraw').addEventListener('click', () => {
            drawingEnabled = !drawingEnabled;
            fabricCanvases.forEach(fc => fc.isDrawingMode = drawingEnabled);
            alert('Modo dibujo: ' + (drawingEnabled ? 'Activado' : 'Desactivado'));
        });

        document.getElementById("btnComplete").addEventListener("click", () => {
            addMode = "complete";
            drawingEnabled = false;
            fabricCanvases.forEach(fc => fc.isDrawingMode = false);
            alert("Modo: colocar etiqueta COMPLETE");
        });

        document.getElementById("btnIncomplete").addEventListener("click", () => {
            addMode = "incomplete";
            drawingEnabled = false;
            fabricCanvases.forEach(fc => fc.isDrawingMode = false);
            alert("Modo: colocar etiqueta INCOMPLETE. Haz clic dónde marcar y te pedirá detalles.");
        });


        document.getElementById('saveAnnotations').addEventListener('click', async () => {
            const overlays = fabricCanvases.map((fc, i) => {
                if (!fc || !pagesMeta[i]) return null;

                const scale = pagesMeta[i].scaleFactor;
                const tempCanvas = new fabric.Canvas(null, {
                    width: fc.width / scale,
                    height: fc.height / scale,
                });

                fc.getObjects().forEach(obj => {
                    let clone;

                    if (obj.type === "group") {
                        // Clonar cada objeto dentro del grupo
                        const clonedObjects = obj._objects.map(innerObj => fabric.util.object
                            .clone(innerObj));
                        clone = new fabric.Group(clonedObjects, {
                            left: obj.left / scale,
                            top: obj.top / scale,
                        });

                        // Ajustar tamaño y escala
                        clone.scaleX = obj.scaleX / scale;
                        clone.scaleY = obj.scaleY / scale;
                    } else {
                        // Clonar objetos normales (dibujo, líneas, etc)
                        clone = fabric.util.object.clone(obj);
                        clone.left /= scale;
                        clone.top /= scale;
                        clone.scaleX /= scale;
                        clone.scaleY /= scale;
                    }

                    clone.setCoords();
                    tempCanvas.add(clone);
                });

                tempCanvas.renderAll();
                // --- EXPORTAR EN ALTA RESOLUCIÓN ---
                const exportScale = 2; // sube a 3 si quieres más calidad

                // Guardar tamaño original
                const origWidth = tempCanvas.width;
                const origHeight = tempCanvas.height;

                // Ajustar para exportar HD
                tempCanvas.setWidth(origWidth * exportScale);
                tempCanvas.setHeight(origHeight * exportScale);
                tempCanvas.setZoom(exportScale);

                // Exportar imagen HD
                const img = tempCanvas.toDataURL({
                    format: "png",
                    multiplier: exportScale
                });

                // Restaurar tamaño original
                tempCanvas.setWidth(origWidth);
                tempCanvas.setHeight(origHeight);
                tempCanvas.setZoom(1);

                return img;

            });

            const response = await fetch(saveUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": csrfToken
                },
                body: JSON.stringify({
                    overlays
                })
            });

            const result = await response.json();

            if (result.success) {
                alert("✅ PDF anotado guardado correctamente");
                window.location.href = "{{ route('pdfs.index') }}";
            } else {
                alert("❌ Error al guardar");
            }
        });


        function runSearch() {
            const query = document.getElementById("searchText").value.trim();
            if (!query) return;

            matches = [];
            currentMatchIndex = 0;

            const re = new RegExp("\\b" + escapeRegExp(query) + "\\b", "gi");

            pagesMeta.forEach((pm, pageIndex) => {
                if (!pm) return;

                const ctx = pm.highlightCanvas.getContext("2d");
                ctx.clearRect(0, 0, pm.highlightCanvas.width, pm.highlightCanvas.height);

                pm.textItems.forEach(item => {
                    let m;
                    while ((m = re.exec(item.str)) !== null) {
                        const cw = item.width / item.str.length;
                        const x = item.left + m.index * cw;
                        const w = cw * query.length;

                        ctx.fillStyle = "rgba(255,255,0,0.45)";
                        ctx.fillRect(x, item.top, w, item.height);

                        matches.push({
                            pageIndex,
                            x,
                            y: item.top,
                            w,
                            h: item.height
                        });
                    }
                });
            });

            updateCounter();

            if (matches.length) {
                scrollToMatch(0);
            }
        }

        function scrollToMatch(i) {
            const m = matches[i];
            const pageDiv = document.getElementById(`page_${m.pageIndex + 1}`);

            // Calcula posición absoluta dentro del contenedor
            const topPos = pageDiv.offsetTop + m.y - 30; // ajusta -80 para bajar un poco más

            container.scrollTo({
                top: topPos,
                behavior: "smooth"
            });

        }

        function updateCounter() {
            const el = document.getElementById("matchCounter");
            el.textContent = matches.length ? `${matches.length} resultado(s)` : "0 resultados";
        }

        // eventos búsqueda
        document.getElementById("btnSearch").addEventListener("click", runSearch);
        document.getElementById("searchText").addEventListener("input", runSearch);
        document.getElementById("searchText").addEventListener("keydown", e => {
            if (e.key === "Enter") {
                e.preventDefault();
                runSearch();
            }
        });

        // botones quedan pero no hacen nada
        document.getElementById("btnPrev").addEventListener("click", prevMatch);
        document.getElementById("btnNext").addEventListener("click", nextMatch);

        function scrollToMatch(i) {
            const m = matches[i];
            const div = document.getElementById(`page_${m.pageIndex+1}`);
            div.scrollIntoView({
                behavior: "smooth",
                block: "center"
            });

        }

        function nextMatch() {
            if (!matches.length) return;
            currentMatchIndex = (currentMatchIndex + 1) % matches.length;
            runSearch();
            scrollToMatch(currentMatchIndex);
            updateCounter();
        }

        function prevMatch() {
            if (!matches.length) return;
            currentMatchIndex = (currentMatchIndex - 1 + matches.length) % matches.length;
            runSearch();
            scrollToMatch(currentMatchIndex);
            updateCounter();
        }

        function updateCounter() {
            const el = document.getElementById("matchCounter");
            if (!matches.length) el.textContent = "0 resultados";
            else el.textContent = `${currentMatchIndex+1} / ${matches.length}`;
        }

        // eventos búsqueda
        document.getElementById("btnSearch").addEventListener("click", runSearch);
        document.getElementById("searchText").addEventListener("input", runSearch);
        document.getElementById("searchText").addEventListener("keydown", e => {
            if (e.key === "Enter") {
                e.preventDefault();
                runSearch();
            }
        });
    </script>

    <style>
        #pdfContainer {
            text-align: center;
        }

        .pdf-canvas {
            position: absolute;
            top: 0;
            left: 0;
        }

        .overlay-canvas {
            position: absolute;
            top: 0;
            left: 0;
            z-index: 20;
        }

        .highlight-canvas {
            position: absolute;
            top: 0;
            left: 0;
            z-index: 10;
            pointer-events: none;
        }
    </style>
@endsection
