<?php

function getAvailableMemory()
{
    $memInfo = file_get_contents('/proc/meminfo');
    if (preg_match('/MemAvailable:\s+(\d+)\s+kB/', $memInfo, $matches)) {
        return $matches[1] / 1024; // Convert to MB
    }
    return 0; // Return 0 if unable to determine available memory
}

function getPhpCodebaseSize($baseDirectory)
{
    $totalSize = 0;
    $laravelDirs = ['app', 'config', 'routes', 'database', 'resources/views'];

    foreach ($laravelDirs as $dir) {
        $fullPath = $baseDirectory . '/' . $dir;
        if (is_dir($fullPath)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if ($file->getExtension() == 'php') {
                    $totalSize += $file->getSize();
                }
            }
        }
    }

    // Optionally include vendor directory
    $vendorPath = $baseDirectory . '/vendor';
    if (is_dir($vendorPath)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($vendorPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->getExtension() == 'php') {
                $totalSize += $file->getSize();
            }
        }
    }

    return $totalSize / 1024 / 1024; // Convert to MB
}

function tuneOpCache($availableMemory, $codebaseSize)
{
    // Allocate 20% of available memory for OpCache, but not more than 512MB
    $opCacheMemory = min($availableMemory * 0.2, 512);

    // Estimate number of files based on codebase size (assuming average file size of 50KB)
    $estimatedFileCount = ceil($codebaseSize * 1024 / 50);

    $settings = [
        'opcache.enable' => 1,
        'opcache.memory_consumption' => max(64, min(round($opCacheMemory), 512)),
        'opcache.interned_strings_buffer' => 16,
        'opcache.max_accelerated_files' => max(4000, min($estimatedFileCount * 1.1, 100000)),
        'opcache.revalidate_freq' => 0,
        'opcache.fast_shutdown' => 1,
        'opcache.enable_file_override' => 1,
        'opcache.validate_timestamps' => 0,
        'opcache.save_comments' => 1,
    ];

    return $settings;
}

// Get available memory
$availableMemory = getAvailableMemory();

// Check if a command-line argument is provided
if ($argc > 1) {
    $codebasePath = $argv[1];
} else {
    // Use current directory as default path if no argument is provided
    $codebasePath = getcwd();
}

// Get PHP codebase size
$codebaseSize = getPhpCodebaseSize($codebasePath);

// Tune OpCache
$recommendedSettings = tuneOpCache($availableMemory, $codebaseSize);

// Output results
echo "Available Memory: " . round($availableMemory) . " MB\n";
echo "PHP Codebase Size: " . round($codebaseSize, 2) . " MB\n\n";
echo "Recommended OpCache Settings:\n";
foreach ($recommendedSettings as $key => $value) {
    echo "$key = $value\n";
}