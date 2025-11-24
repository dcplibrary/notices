<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * NoticeImportController
 * 
 * Handles streaming of command output to the browser for real-time progress updates.
 */
class NoticeImportController extends Controller
{
    private static $currentProcess = null;

    /**
     * Stream command output to browser in real-time
     */
    public function stream(Request $request)
    {
        $command = $request->input('command');

        if (!is_array($command)) {
            return response()->json(['error' => 'Invalid command format'], 400);
        }

        // Set up streaming response
        return response()->stream(function () use ($command) {
            // Create process
            $process = new Process($command, base_path(), null, null, null);
            self::$currentProcess = $process;

            // Start process
            $process->start();

            // Stream output line by line
            foreach ($process as $type => $data) {
                if ($process::OUT === $type) {
                    // Standard output
                    $lines = explode("\n", $data);
                    foreach ($lines as $line) {
                        if (trim($line)) {
                            // Try to parse as JSON for structured progress
                            if ($this->isJson($line)) {
                                echo $line . "\n";
                            } else {
                                // Send plain text wrapped in JSON
                                echo json_encode([
                                    'progress' => $this->stripAnsiCodes($line)
                                ]) . "\n";
                            }
                            
                            ob_flush();
                            flush();
                        }
                    }
                } elseif ($process::ERR === $type) {
                    // Error output
                    echo json_encode([
                        'error' => $this->stripAnsiCodes($data)
                    ]) . "\n";
                    
                    ob_flush();
                    flush();
                }
            }

            // Wait for process to finish
            $process->wait();

            // Send completion message
            if ($process->isSuccessful()) {
                echo json_encode([
                    'completed' => true,
                    'success' => true,
                    'message' => 'Import completed successfully',
                    'exit_code' => $process->getExitCode()
                ]) . "\n";
            } else {
                echo json_encode([
                    'completed' => true,
                    'success' => false,
                    'message' => 'Import failed with exit code ' . $process->getExitCode(),
                    'exit_code' => $process->getExitCode()
                ]) . "\n";
            }

            ob_flush();
            flush();

            self::$currentProcess = null;

        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no', // Disable nginx buffering
        ]);
    }

    /**
     * Cancel the currently running import process
     */
    public function cancel(Request $request)
    {
        if (self::$currentProcess && self::$currentProcess->isRunning()) {
            self::$currentProcess->stop(3, SIGTERM);
            
            return response()->json([
                'success' => true,
                'message' => 'Import process cancelled'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No active import process'
        ], 400);
    }

    /**
     * Get current import status
     */
    public function status(Request $request)
    {
        return response()->json([
            'is_running' => self::$currentProcess && self::$currentProcess->isRunning(),
            'pid' => self::$currentProcess ? self::$currentProcess->getPid() : null,
        ]);
    }

    /**
     * Check if string is valid JSON
     */
    private function isJson($string): bool
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Strip ANSI color codes from string
     */
    private function stripAnsiCodes($string): string
    {
        return preg_replace('/\e\[[0-9;]*m/', '', $string);
    }
}
