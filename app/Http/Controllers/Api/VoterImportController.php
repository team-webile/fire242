<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessVoterImport;
use App\Jobs\ProcessExcelSplit;
use App\Models\VoterImportList;
use Illuminate\Http\Request; 
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;


class VoterImportController extends Controller
{ 
    public function import_backup(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|mimes:xlsx,xls|max:2147483648', // max 2GB
                'chunk' => 'required|integer',
                'chunks' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $file = $request->file('file');
            $chunk = (int)$request->input('chunk');
            $chunks = (int)$request->input('chunks');

            // Generate a unique filename for the complete file
            $fileName = uniqid() . '_' . str_replace(' ', '_', $file->getClientOriginalName());
            $filePath = storage_path('app/public/uploads/tmp/' . $fileName);

            // Append chunk to temporary file 
            Storage::disk('public')->append('uploads/tmp/' . $fileName, $file->get());

            // If this is the last chunk
            if ($chunk === $chunks - 1) {
                // Move the completed file to final destination
                $finalPath = storage_path('app/public/uploads/' . $fileName);
                Storage::disk('public')->move('uploads/tmp/' . $fileName, 'uploads/' . $fileName);
  
                // Create import record
                $import = VoterImportList::create([
                    'filename' => $fileName, // Changed from file_name to filename to match DB column
                    'file_path' => $finalPath,
                    'status' => 'pending',
                    'total_rows' => $chunks,
                    'processed_rows' => 4244
                ]);
  
                // // Process the file in chunks
                // ProcessVoterImport::dispatch($import);

                return response()->json([
                    'success' => true,
                    'message' => 'File upload completed and queued for processing',
                    'file_path' => Storage::url('uploads/' . $fileName),
                    'data' => [
                        'import_id' => $import->id
                    ]
                ]); 
            }

            return response()->json([
                'success' => true,
                'message' => "Chunk $chunk of $chunks uploaded successfully"
            ]);
 
        } catch (\Exception $e) {
            Log::error('Voter Import Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while uploading the file',
                'error' => $e->getMessage(),
                'file' => $e->getFile(), 
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function import(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|mimes:xlsx,xls|max:2147483648', // max 2GB
                'chunk_size' => 'integer|min:100|max:10000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
 
            $file = $request->file('file');
            $chunkSize = $request->input('chunk_size', 500);
  
            // Generate a unique directory for this file
            $uniqueDir = uniqid();
            $outputDir = storage_path("app/public/uploads/$uniqueDir");

            // Clean the file name by removing spaces and special characters, and add timestamp
            $cleanFileName = preg_replace('/[^A-Za-z0-9]/', '', pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            $timestamp = time();
            $fileNameWithTimestamp = $cleanFileName . '_' . $timestamp . '.' . $file->getClientOriginalExtension();
            $filePath = "$outputDir/$fileNameWithTimestamp";

            // Create directory and move file
            if (!file_exists($outputDir)) {
                mkdir($outputDir, 0755, true);
            }
            $file->move($outputDir, $fileNameWithTimestamp);

            // Dispatch the job to the queue
           $voterImport = VoterImportList::create([
               'filename' => $fileNameWithTimestamp,
               'file_path' => $filePath,
               'status' => 'queued',
               'processed_rows' => 0,
               'total_rows' => 0,
               'progress' => 0,
               'started_at' => now()
           ]); 
           
           //ProcessExcelSplit::dispatch($filePath, $outputDir, $chunkSize);
  
            return response()->json([
                'success' => true,
                'message' => 'File uploaded and queued for processing',
                'output_directory' => Storage::url("uploads/$uniqueDir"),
                'job_details' => [
                    'file_path' => $filePath,
                    'chunk_size' => $chunkSize
                ]
            ]);
  
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing the file',
                'error' => $e->getMessage()
            ], 500);
        }
    }
  
 
   

} 