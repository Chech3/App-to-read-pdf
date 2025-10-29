@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <h2>Subir nuevo PDF</h2>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('pdfs.store') }}" method="POST" enctype="multipart/form-data" class="card p-4 shadow-sm">
        @csrf
        <div class="mb-3">
            <label for="pdf" class="form-label">Selecciona un archivo PDF</label>
            <input type="file" name="pdf" id="pdf" class="form-control" accept="application/pdf" required>
        </div>

        <div id="preview" class="mb-3" style="display:none;">
            <h5>Vista previa:</h5>
            <iframe id="pdfFrame" width="100%" height="400"></iframe>
        </div>

        <div class="d-flex justify-content-between">
            <a href="{{ route('pdfs.index') }}" class="btn btn-secondary">Volver</a>
            <button type="submit" class="btn btn-success">Guardar PDF</button>
        </div>
    </form>
</div>

<script>
document.getElementById('pdf').addEventListener('change', function (e) {
    const file = e.target.files[0];
    if (file && file.type === 'application/pdf') {
        const url = URL.createObjectURL(file);
        document.getElementById('pdfFrame').src = url;
        document.getElementById('preview').style.display = 'block';
    }
});
</script>
@endsection
