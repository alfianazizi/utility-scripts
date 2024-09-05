<?php

// This script calculates optimal PHP-FPM settings based on system resources

// Function to get the number of CPU cores
function getCpuCores() {
    if (PHP_OS_FAMILY == 'Windows') {
        $cores = shell_exec('echo %NUMBER_OF_PROCESSORS%');
    } else {
        $cores = shell_exec('nproc');
    }

    return (int) $cores;
}

// Function to get the amount of free memory in MB
function getFreeMemory() {
    if (PHP_OS_FAMILY == 'Windows' && preg_match('~(\d+)~', shell_exec('wmic OS get FreePhysicalMemory'), $matches)) {
        $freeMemory = round((int) $matches[1] / 1024);
    } else {
        if (preg_match('~MemFree:\s+(\d+)\s+~', shell_exec('cat /proc/meminfo'), $matches)) {
            $freeMemory = $matches[1] / 1024;
        }
    }
    return (int) $freeMemory;
}

// Function to estimate the memory usage of a PHP-FPM worker in MB
function getWorkerMemory() {
    if (PHP_OS_FAMILY !== 'Windows' && preg_match_all('~(\d+).*php-fpm: pool~', shell_exec('ps -eo size,command'), $matches, PREG_PATTERN_ORDER)) {
        $processMemory = round(array_sum($matches[1]) / count($matches[1]) / 1024);
    }

    if (!isset($processMemory)) {
       $processMemory = round(ini_parse_quantity(ini_get('memory_limit')) / 1048576);
    }

    return (int) $processMemory;
}

// Function to get the amount of available memory in MB
function getAvailableMemory() {
    $meminfo = file_get_contents('/proc/meminfo');
    if (preg_match('/MemAvailable:\s+(\d+)\s+kB/', $meminfo, $matches)) {
        $availableMemory = $matches[1] / 1024; // Convert to MB
    } else {
        // Fallback for older systems
        preg_match('/MemFree:\s+(\d+)\s+kB/', $meminfo, $matches);
        $memFree = $matches[1];
        preg_match('/Cached:\s+(\d+)\s+kB/', $meminfo, $matches);
        $cached = $matches[1];
        preg_match('/Buffers:\s+(\d+)\s+kB/', $meminfo, $matches);
        $buffers = $matches[1];
        $availableMemory = ($memFree + $cached + $buffers) / 1024; // Convert to MB
    }
    return (int) $availableMemory;
}

// Get system information
$cpuCores = getCpuCores();
$freeMemory = getFreeMemory();
$workerMemory = getWorkerMemory();
$availableMemory = getAvailableMemory();

// Reserve 10% of available memory for system use
$memoryReserve = round(0.1 * $availableMemory);

// Calculate the maximum number of child processes based on available memory
$maxChildren = floor(($availableMemory - $memoryReserve) / $workerMemory);

// Output system information
echo "cpu cores: " . getCpuCores() . "\n";
echo "free memory: " . getFreeMemory() . "\n";
echo "available memory: " . getAvailableMemory() . "\n";
echo "worker memory: " . getWorkerMemory() . "\n";

// Output recommended PHP-FPM settings
echo "pm.max_children = " . $maxChildren . "\n";
echo "pm.start_servers = " . min(round(0.25 * $maxChildren), $cpuCores * 4) . "\n";
echo "pm.min_spare_servers = " . min(round(0.25 * $maxChildren), $cpuCores * 2) . "\n";
echo "pm.max_spare_servers = " . min(round(0.75 * $maxChildren), $cpuCores * 4) . "\n";