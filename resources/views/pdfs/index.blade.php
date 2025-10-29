@extends('layouts.app')

@section('content')
<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>PDFs</h2>
        <a href="{{ route('pdfs.create') }}" class="btn btn-primary">➕ Subir PDF</a>
    </div>

    {{-- Barra de búsqueda --}}
    <form method="GET" action="{{ route('pdfs.index') }}" class="mb-4">
        <div class="input-group">
            <input type="text" name="search" value="{{ $search ?? '' }}" class="form-control" placeholder="Buscar por nombre...">
            <button class="btn btn-outline-secondary" type="submit">Buscar</button>
        </div>
    </form>

    {{-- Mensajes --}}
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    {{-- Galería de PDFs --}}
    @if($pdfs->count())
        <div class="row g-4">
            @foreach($pdfs as $pdf)
            <div class="col-md-3">
                <div class="card shadow-sm h-100">
                    <iframe src="{{ asset('storage/' . $pdf->path) }}" width="100%" height="200"></iframe>
                    <div class="card-body text-center">
                        <h6 class="text-truncate" title="{{ $pdf->name }}">{{ $pdf->name }}</h6>
                        <div class="d-flex justify-content-center gap-2 mt-2">
                            <a href="{{ route('pdfs.show', $pdf->id) }}" target="_blank" class="btn btn-sm btn-info">Ver</a>
                            <a href="{{ route('pdfs.annotate', $pdf->id) }}" class="btn btn-warning btn-sm">Anotar</a>

                            <form action="{{ route('pdfs.destroy', $pdf->id) }}" method="POST" onsubmit="return confirm('¿Eliminar este PDF?')" style="display:inline;">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-danger">Eliminar</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Paginación --}}
        <div class="mt-4">
            {{ $pdfs->appends(['search' => $search])->links() }}
        </div>

    @else
        <div class="alert alert-info">No se encontraron archivos PDF.</div>
    @endif
</div>
@endsection
