<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\VoterImportList;
use Carbon\Carbon;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class MonitorChunksAndInsert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $chunksDir;
    protected $importId;
    public $timeout = 86400; // 24 hours
    public $tries = 1;

    public function __construct($chunksDir, $importId)
    {
        $this->chunksDir = $chunksDir;
        $this->importId = $importId;
    }

    public function handle()
    {
        try {
            // Wait for chunks to be fully created
            $this->waitForChunksCompletion();

            // Start postgres insertion
            $this->startPostgresInsertion();

        } catch (\Exception $e) {
            Log::error('Line ' . __LINE__ . ' - Exception in MonitorChunksAndInsert: ' . $e->getMessage());
            VoterImportList::where('id', $this->importId)->update([
                'status' => 'failed',
                'error_message' => 'Error on line ' . __LINE__ . ': ' . $e->getMessage(),
                'completed_at' => Carbon::now()
            ]);
            throw $e; 
        }
    } 

    protected function waitForChunksCompletion()
    {
        $maxWaitTime = 7200; // Increased to 2 hours maximum wait time
        $startTime = time();
        $lastFileCount = 0;
        $stableCount = 0;
        $lastProgressTime = time();
 
        while (true) {
            $currentFileCount = count(glob($this->chunksDir . '/chunk_*.xlsx'));
            $currentTime = time();
            
            // Log progress with more details
            Log::info("Monitoring chunks creation: {$currentFileCount} chunks found. Stable count: {$stableCount}. Elapsed time: " . ($currentTime - $startTime) . " seconds");
            
            // If no new files in 10 minutes, consider it complete
            if ($currentTime - $lastProgressTime > 600 && $currentFileCount > 0) {
                Log::info("No new chunks created in last 10 minutes. Considering process complete with {$currentFileCount} chunks");
                break;
            }
             
            // If file count hasn't changed in last 2 checks
            if ($currentFileCount === $lastFileCount) {
                $stableCount++;
                if ($stableCount >= 2) { // Reduced to 2 checks (40 seconds) for faster progression
                    Log::info("Chunk creation appears complete with {$currentFileCount} chunks");
                    break;
                }
            } else {
                $stableCount = 0;
                $lastProgressTime = $currentTime; // Reset the progress timer when new files are detected
            }

            // Check for timeout
            if ($currentTime - $startTime > $maxWaitTime) {
                throw new \Exception("Timeout waiting for chunks to be created after " . ($currentTime - $startTime) . " seconds");
            }

            $lastFileCount = $currentFileCount;
            sleep(20); // Keep 20 seconds between checks
        }

        // Verify we actually have chunks before proceeding
        if ($currentFileCount === 0) {
            throw new \Exception("No chunks were created in the specified directory");
        }

        // Add a small delay to ensure all file writes are complete
        sleep(30);
        Log::info("Proceeding to database insertion with {$currentFileCount} chunks");
    }

    protected function startPostgresInsertion()
    {
        VoterImportList::where('id', $this->importId)->update([
            'status' => 'inserting',
            'error_message' => 'Starting postgres insertion'
        ]);

        $process = new Process([
            '/var/www/python-scripts/run_insert_to_postgres.sh',
            $this->chunksDir
        ]);
        
        $process->setTimeout(86400); // 24 hours
        
        $process->run(function ($type, $buffer) {
            if (Process::ERR === $type) {
                Log::error('Postgres Insertion Error: ' . $buffer);
            } else {
                Log::info('Postgres Insertion Output: ' . $buffer);
            }
        });

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        // Update status on completion
        VoterImportList::where('id', $this->importId)->update([
            'status' => 'completed',
            'error_message' => 'Process completed successfully',
            'completed_at' => Carbon::now()
        ]); 
    }
}