<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\VoterCard;

class OCRController extends Controller
{
    /**
     * Show the upload form
     */
    public function index()
    {
        //test by arslan again
        return view('ocr.upload');
    }

    /**
     * Handle the image upload and OCR processing
     */
    public function processOCRImage(Request $request)
    {
        // Start time logging
        $startTime = microtime(true);

        // Validate the uploaded file
        $validator = \Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg|max:5048',
        ]);
 
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Upload the image directly to the public/uploads directory
        $image = $request->file('image');
        $imageName = time() . '.' . $image->getClientOriginalExtension();
        $uploadedImagePath = '/var/www/html/public/uploads/' . $imageName;
        
        // Move the uploaded file directly to the public/uploads directory
        $image->move(public_path('uploads'), $imageName);
        

        $voterCard = new VoterCard();
        $voterCard->file_path = $uploadedImagePath;
        $voterCard->save();
        // Log processing time
        $processingTime = microtime(true) - $startTime;
        \Log::info('OCR processing time: ' . $processingTime . ' seconds');

        return response()->json(['success' => 'Voter card processed successfully!', 'data' => $voterCard, 'processing_time' => $processingTime], 200);
    }
} 