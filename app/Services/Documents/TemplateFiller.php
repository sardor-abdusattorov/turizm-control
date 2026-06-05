<?php

namespace App\Services\Documents;

use RuntimeException;
use ZipArchive;

class TemplateFiller
{
    public function fill(string $sourcePath, string $destinationPath, array $values): void
    {
        if (! is_file($sourcePath)) {
            throw new RuntimeException("Template not found: {$sourcePath}");
        }

        $destinationDir = dirname($destinationPath);

        if (! is_dir($destinationDir)) {
            mkdir($destinationDir, 0775, true);
        }

        if (! copy($sourcePath, $destinationPath)) {
            throw new RuntimeException("Failed to copy template to: {$destinationPath}");
        }

        $zip = new ZipArchive();

        if ($zip->open($destinationPath) !== true) {
            throw new RuntimeException("Failed to open docx as zip: {$destinationPath}");
        }

        $patterns = [];
        $count = $zip->numFiles;

        for ($i = 0; $i < $count; $i++) {
            $patterns[] = $zip->getNameIndex($i);
        }

        foreach ($patterns as $name) {
            if (! $this->shouldProcess($name)) {
                continue;
            }

            $xml = $zip->getFromName($name);

            if ($xml === false) {
                continue;
            }

            $replaced = $this->replaceInXml($xml, $values);

            if ($replaced !== $xml) {
                $zip->deleteName($name);
                $zip->addFromString($name, $replaced);
            }
        }

        $zip->close();
    }

    private function shouldProcess(string $entry): bool
    {
        return (bool) preg_match('#^word/(document|header\d*|footer\d*)\.xml$#', $entry);
    }

    private function replaceInXml(string $xml, array $values): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-z][a-z0-9_.]*)\s*\}\}/i',
            function (array $match) use ($values): string {
                $key = strtolower($match[1]);
                $value = $values[$key] ?? null;

                if ($value === null) {
                    return $match[0];
                }

                return $this->escapeForXml((string) $value);
            },
            $xml,
        );
    }

    private function escapeForXml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
