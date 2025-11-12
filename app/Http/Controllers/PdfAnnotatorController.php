<?php

namespace App\Http\Controllers;

use App\Models\PdfFile;
use App\Models\WordTag;
use Illuminate\Http\Request;
use setasign\Fpdi\Fpdi;
use Imagick;

class PdfAnnotatorController extends Controller
{
  public function show(PdfFile $pdf)
{
    $pdfPath = storage_path('app/public/' . $pdf->path);

    $fpdi = new Fpdi();
    $fpdi->setSourceFile($pdfPath);
    $tpl = $fpdi->importPage(1);
    $size = $fpdi->getTemplateSize($tpl);

    $pdfWidth = $size['width'];   
    $pdfHeight = $size['height']; 

    $imagick = new \Imagick();
    $imagick->setResolution(150, 150);
    $imagick->readImage($pdfPath);

    $images = [];
    foreach ($imagick as $i => $page) {
        $page->setImageFormat("png");
        
        $page->scaleImage($pdfWidth * 2, $pdfHeight * 2); 

        $file = 'tmp/pdf_page_' . $pdf->id . '_' . $i . '.png';
        $fullPath = storage_path('app/public/' . $file);
        $page->writeImage($fullPath);
        $images[] = asset('storage/' . $file);
    }

    return view('pdfs.annotate', [
        'pdf' => $pdf,
        'pages' => $images,
        'pdfWidth' => $pdfWidth * 2,
        'pdfHeight' => $pdfHeight * 2
    ]);
}



   public function store(Request $request, PdfFile $pdf)
{
    $request->validate([
        'overlays' => 'required|array',
    ]);

    $originalPath = storage_path('app/public/' . $pdf->path);
    $fpdi = new Fpdi();
    $pageCount = $fpdi->setSourceFile($originalPath);
    $overlayImages = $request->input('overlays');

    for ($i = 0; $i < $pageCount; $i++) {
        $tplId = $fpdi->importPage($i + 1);
        $size = $fpdi->getTemplateSize($tplId);

        $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
        $fpdi->useTemplate($tplId, 0, 0, $size['width'], $size['height']);

        if (!empty($overlayImages[$i])) {
            $overlayBase64 = preg_replace('#^data:image/\w+;base64,#i', '', $overlayImages[$i]);
            $overlayImage = base64_decode($overlayBase64);

            $tempImage = tempnam(sys_get_temp_dir(), 'overlay_') . '.png';
            file_put_contents($tempImage, $overlayImage);

            $fpdi->Image($tempImage, 0, 0, $size['width'], $size['height']);

            unlink($tempImage);
        }
    }

    $newFileName = 'annotated_' . time() . '_' . basename($pdf->path);
    $newPath = 'pdfs/' . $newFileName;

    $fpdi->Output(storage_path('app/public/' . $newPath), 'F');

    $annotated = PdfFile::create([
        'name' => 'Anotado - ' . $pdf->name,
        'path' => $newPath,
    ]);

    return response()->json(['success' => true, 'file' => $annotated]);
}



    public function review(PdfFile $pdf)
    {
        $tags = WordTag::where('pdf_id', $pdf->id)->get();

        return view('pdfs.review', [
            'pdf' => $pdf,
            'tags' => $tags
        ]);
    }
}
