<?php

namespace App\Http\Controllers;

use App\Models\PdfFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PdfFileController extends Controller
{
    public function index(Request $request)
    {
        $query = PdfFile::query();

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $pdfs = $query->latest()->paginate(8); // 8 por página

        return view('pdfs.index', compact('pdfs', 'search'));
    }


    public function create()
    {
        return view('pdfs.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'pdf' => 'required|mimes:pdf|max:10240', // 10MB
        ]);

        $file = $request->file('pdf');
        $name = $file->getClientOriginalName();
        $path = $file->store('pdfs', 'public');

        PdfFile::create([
            'name' => $name,
            'path' => $path,
        ]);

        return redirect()->route('pdfs.index')->with('success', 'PDF guardado correctamente.');
    }

    public function show(PdfFile $pdf)
    {
        return response()->file(storage_path('app/public/' . $pdf->path));
    }

    public function destroy(PdfFile $pdf)
    {
        Storage::disk('public')->delete($pdf->path);
        $pdf->delete();

        return redirect()->route('pdfs.index')->with('success', 'PDF eliminado.');
    }
}
