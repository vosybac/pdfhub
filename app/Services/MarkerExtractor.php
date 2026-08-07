<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

class MarkerExtractor
{
    public function extract(string $pdfPath): string
    {
        if (! config('marker.enabled')) {
            throw new RuntimeException('Marker extraction is disabled.');
        }

        if (! is_file($pdfPath)) {
            throw new RuntimeException("PDF file not found: {$pdfPath}");
        }

        $python = config('marker.python');
        $script = config('marker.script');

        foreach ([$python, $script] as $path) {
            if (! is_file($path)) {
                throw new RuntimeException("Marker dependency not found: {$path}");
            }
        }

        $outJson = tempnam(sys_get_temp_dir(), 'marker_') . '.json';

        $mode = (string) config('marker.mode', 'fast');
        $disableOcr = (bool) config('marker.disable_ocr', true) ? '1' : '0';

        $process = new Process([
            $python,
            $script,
            $pdfPath,
            $outJson,
            $mode,
            $disableOcr,
        ]);
        $process->setTimeout(config('marker.timeout_sec', 600));

        try {
            $process->mustRun();
        } catch (\Symfony\Component\Process\Exception\ProcessFailedException $e) {
            @unlink($outJson);
            throw new RuntimeException('Marker extraction failed: ' . $e->getMessage(), 0, $e);
        }

        if (! is_file($outJson)) {
            throw new RuntimeException('Marker produced no output file.');
        }

        $data = json_decode(file_get_contents($outJson), true);
        @unlink($outJson);

        if (! is_array($data) || ! isset($data['markdown'])) {
            throw new RuntimeException('Marker output is invalid.');
        }

        return $data['markdown'];
    }
}
