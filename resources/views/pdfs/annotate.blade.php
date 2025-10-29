@extends('layouts.app')

@section('content')
    <div class="container-fluid mt-4">
        <h2>Anotar PDF: {{ $pdf->name }}</h2>

        <div class="mb-3 d-flex gap-2">
            <a href="{{ route('pdfs.index') }}" class="btn btn-secondary">⬅️ Volver</a>
            <button id="toggleDraw" class="btn btn-primary">✏️ Modo Dibujo</button>
            <button id="saveAnnotations" class="btn btn-success">💾 Guardar Anotaciones</button>
        </div>

        <div id="pdfContainer"
            style="position: relative; border: 1px solid #ccc; height: 90vh; overflow-y: auto; background: #f8f9fa;">
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.14.305/pdf.min.js"></script>
    {{-- <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/4.6.0/fabric.min.js"></script> --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js" defer></script>

    <script>
        const pdfUrl = "{{ asset('storage/' . $pdf->path) }}";
        const saveUrl = "{{ route('pdfs.annotate.save', $pdf->id) }}";
        const csrfToken = "{{ csrf_token() }}";

        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.14.305/pdf.worker.min.js';

        const container = document.getElementById('pdfContainer');
        let fabricCanvases = [];
        let drawingEnabled = true;

        pdfjsLib.getDocument(pdfUrl).promise.then(pdf => {
            for (let i = 1; i <= pdf.numPages; i++) {
                pdf.getPage(i).then(page => {

                    const viewport = page.getViewport({
                        scale: 1
                    });
                    const containerWidth = container.clientWidth - 40;
                    const scale = containerWidth / viewport.width;
                    const scaledViewport = page.getViewport({
                        scale
                    });

                    // Wrapper
                    const pageWrapper = document.createElement('div');

                    pageWrapper.style.position = 'relative';
                    pageWrapper.style.margin = '0 auto';
                    pageWrapper.style.marginBottom = '30px'; // ok para separación
                    pageWrapper.style.padding = '0';


                    pageWrapper.style.background = '#fff';
                    pageWrapper.style.boxShadow = '0 0 8px rgba(0,0,0,0.2)';
                    pageWrapper.style.width = scaledViewport.width + 'px';
                    container.appendChild(pageWrapper);

                    // PDF Canvas
                    const pdfCanvas = document.createElement('canvas');
                    const pdfCtx = pdfCanvas.getContext('2d');
                    pdfCanvas.width = scaledViewport.width;
                    pdfCanvas.height = scaledViewport.height;
                    pdfCanvas.style.position = "relative";
                    pdfCanvas.style.display = "block";
                    pageWrapper.appendChild(pdfCanvas);

                    pdfCanvas.classList.add("pdf-canvas");


                    // Overlay Canvas
                    const drawCanvas = document.createElement('canvas');
                    drawCanvas.width = scaledViewport.width;
                    drawCanvas.height = scaledViewport.height;
                    drawCanvas.classList.add("overlay-canvas");
                    pageWrapper.appendChild(drawCanvas);

                    page.render({
                        canvasContext: pdfCtx,
                        viewport: scaledViewport
                    }).promise.then(() => {

                        const fc = new fabric.Canvas(drawCanvas, {
                            isDrawingMode: drawingEnabled,
                            selection: false
                        });

                        fc.setWidth(scaledViewport.width);
                        fc.setHeight(scaledViewport.height);
                        fc.freeDrawingBrush.width = 3;
                        fc.freeDrawingBrush.color = "red";

                        fabricCanvases.push(fc);
                    });
                });
            }
        });

        document.getElementById('toggleDraw').addEventListener('click', () => {
            drawingEnabled = !drawingEnabled;
            fabricCanvases.forEach(fc => fc.isDrawingMode = drawingEnabled);
            alert('Modo dibujo: ' + (drawingEnabled ? 'Activado' : 'Desactivado'));
        });

        document.getElementById('saveAnnotations').addEventListener('click', async () => {
            const overlays = fabricCanvases.map(fc => fc.toDataURL("image/png"));

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
                alert("❌ Error al guardar anotaciones.");
            }
        });
    </script>

    <style>
        #pdfContainer {
            text-align: center;
        }

        .overlay-canvas {
            position: absolute !important;
            top: 0;
            left: 0;
            z-index: 10;
        }

        .pdf-canvas {
            position: absolute !important;
            top: 0;
            left: 0;
        }
    </style>
@endsection
