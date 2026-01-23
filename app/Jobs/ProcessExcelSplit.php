<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ProcessExcelSplit implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
 
    protected $filePath;
    protected $outputDir;
 
    public function __construct($filePath, $outputDir)
    {
        $this->filePath = $filePath;
        $this->outputDir = $outputDir;
    } 
 
    public function handle()
    {
        $shellScript = "/var/www/python-scripts/run_split_excel.sh";

        $process = new Process([
            $shellScript,
            $this->filePath,
            $this->outputDir
        ]);
        $process->setTimeout(7200); // 2 hours timeout
         
        $process->start();
          
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    } 
}