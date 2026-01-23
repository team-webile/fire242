<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\VoterImportList;
use App\Jobs\ProcessVoterImport;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;
use App\Models\VoterCard;

class ImportVotersFromExcel extends Command
{
    /**
     * The name and signature of the console command.
     * 
     * @var string
     */
    protected $signature = 'app:import-voters-from-excel {--retry : Retry failed or incomplete imports}';

    /** 
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending voter imports from Excel files';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->processOCR();

    }

    /**
     * Process OCR for voter cards
     */
    private function processOCR()
    {
        $this->info('Starting OCR processing...');
        Log::info('Starting OCR processing...');
        
        // Get pending voter cards (those with file_path but no processed data)
        $pendingCards = VoterCard::whereNotNull('file_path')
            ->where('status', 0)
            ->get();
            
        $this->info("Found {$pendingCards->count()} pending voter cards to process");
        Log::info("Found {$pendingCards->count()} pending voter cards to process");
        
        foreach ($pendingCards as $voterCard) {
            $this->info("Processing voter card ID: {$voterCard->id}");
            Log::info("Processing voter card Id: {$voterCard->id}");
            Log::info("Processing voter card File Path: {$voterCard->file_path}");
              
            // Start time logging
            $startTime = microtime(true);
            
            try {
                // Create Python script command
                //$pythonScriptPath = '/var/www/python-scripts/script/detect_marks.py';
                $pythonScriptPath = '/var/www/python-scripts/script/circle_finder.py';
                $pythonExecutable = '/var/www/python-scripts/venv/bin/python';
                
                // Execute Python script
                $command = escapeshellcmd("$pythonExecutable $pythonScriptPath {$voterCard->file_path}");
                $output = shell_exec($command);
                
                // Parse JSON response
                $result = json_decode($output, true);
                
                if (!$result) {
                    $this->error("Failed to process voter card ID: {$voterCard->id}");
                    Log::error("Failed to process voter card ID: {$voterCard->id}");
                    continue;
                }
                
                // Calculate processing time
                $processingTime = microtime(true) - $startTime;
                
                // Save results to VoterCard model
                $voterCard->circled_exit_poll = $result['circled_exit_poll'] ?? null;
                $voterCard->registration_number = $result['registration_number'] ?? null;
                $voterCard->processing_time = $result['processing_time'] ?? $processingTime;
                $voterCard->bounding_boxes = json_encode($result['bounding_boxes'] ?? []);
                $voterCard->status = 1; // Update status to 1 when processing is completed
                $voterCard->save();
                
                $this->info("Successfully processed voter card ID: {$voterCard->id}");
                $this->info("Successfully processed voter File Path: {$voterCard->file_path}");
                Log::info("Successfully processed voter card ID: {$voterCard->id}, processing time: {$processingTime} seconds");
            } catch (\Exception $e) {
                $this->error("Error processing voter card ID: {$voterCard->id}: " . $e->getMessage());
                Log::error("OCR Processing Error for voter card ID: {$voterCard->id}: " . $e->getMessage());
            }
        } 
        
        $this->info('OCR processing completed');
        Log::info('OCR processing completed');
    }

    /**
     * Process voter imports from Excel files
     */
    private function processImports()
    {
        $this->info('ImportVotersFromExcel command is running...');

        $query = DB::table('voter_import_lists');

        // If retry flag is set, look for failed or incomplete imports
        if ($this->option('retry')) {
            $query->where(function($q) {
                $q->where('status', 'failed')
                  ->orWhere(function($q) {
                      $q->where('status', 'processing')
                        ->whereRaw('processed_records < total_records');
                  });
            });
        } else {
            $query->where('status', 'pending');
        }

        // Get one import with lock
        $import = $query->orderBy('created_at', 'asc')
                       ->lockForUpdate()
                       ->first();

        if (!$import) {
            $this->info('No imports found to process.');
            return;
        }

        try {
            // Update status to processing
            DB::table('voter_import_lists')
                ->where('id', $import->id)
                ->update(['status' => 'processing']);

            // Load Excel file
            $rows = Excel::toArray([], $import->file_path);
            $totalRecords = count($rows[0]) - 1; // Subtract header row

            $this->info("Total records found: {$totalRecords}");
            
            // Update total records count if not already set
            if (!$import->total_records) {
                DB::table('voter_import_lists')
                    ->where('id', $import->id)
                    ->update(['total_records' => $totalRecords]);
            }

            // Remove header row
            array_shift($rows[0]);
            
            // Calculate starting point for processing
            $startFrom = $import->processed_records ?? 0;
            
            // Get remaining records
            $remainingRows = array_slice($rows[0], $startFrom);
            
            // Process remaining records in chunks of 1000
            $chunks = array_chunk($remainingRows, 1000);
            $processedRecords = $startFrom;
            
            foreach ($chunks as $index => $chunk) {
                ProcessVoterImport::dispatch($import->id, $chunk);
                $processedRecords += count($chunk);
                
                // Update processed records count
                DB::table('voter_import_lists')
                    ->where('id', $import->id)
                    ->update([
                        'processed_records' => $processedRecords,
                        'pending_records' => $totalRecords - $processedRecords
                    ]);

                $this->info("Dispatched chunk " . ($index + 1) . " of " . count($chunks));
                $this->info("Processed: {$processedRecords}, Pending: " . ($totalRecords - $processedRecords));
            }

            $this->info("Processing complete for import ID: {$import->id}");
            $this->info("Total records: {$totalRecords}, Processed: {$processedRecords}");

        } catch (\Exception $e) {
            Log::error('Error processing voter import: ' . $e->getMessage());
            
            DB::table('voter_import_lists')
                ->where('id', $import->id)
                ->update(['status' => 'failed']);
                
            $this->error('Import failed: ' . $e->getMessage());
        }
    }
}
