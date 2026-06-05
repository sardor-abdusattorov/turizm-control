<?php

namespace App\Services\Documents;

use DOMDocument;
use DOMElement;
use RuntimeException;
use ZipArchive;

class TemplateFiller
{
    private const WORD_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

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

        $entries = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[] = $zip->getNameIndex($i);
        }

        foreach ($entries as $name) {
            if (! $this->shouldProcess($name)) {
                continue;
            }

            $xml = $zip->getFromName($name);

            if ($xml === false) {
                continue;
            }

            $newXml = $this->processXml($xml, $values);

            if ($newXml !== null && $newXml !== $xml) {
                $zip->deleteName($name);
                $zip->addFromString($name, $newXml);
            }
        }

        $zip->close();
    }

    private function shouldProcess(string $entry): bool
    {
        return (bool) preg_match('#^word/(document|header\d*|footer\d*)\.xml$#', $entry);
    }

    private function processXml(string $xml, array $values): ?string
    {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;

        if (! @$dom->loadXML($xml, LIBXML_NONET)) {
            return null;
        }

        $paragraphs = [];

        foreach ($dom->getElementsByTagNameNS(self::WORD_NS, 'p') as $paragraph) {
            $paragraphs[] = $paragraph;
        }

        $changed = false;

        foreach ($paragraphs as $paragraph) {
            if ($this->fillParagraph($paragraph, $values)) {
                $changed = true;
            }
        }

        if (! $changed) {
            return null;
        }

        return $dom->saveXML();
    }

    private function fillParagraph(DOMElement $paragraph, array $values): bool
    {
        $textNodes = [];

        foreach ($paragraph->getElementsByTagNameNS(self::WORD_NS, 't') as $textNode) {
            $textNodes[] = $textNode;
        }

        if (empty($textNodes)) {
            return false;
        }

        $full = '';

        foreach ($textNodes as $textNode) {
            $full .= $textNode->nodeValue;
        }

        if (! str_contains($full, '{{')) {
            return false;
        }

        $replaced = preg_replace_callback(
            '/\{\{\s*([a-z][a-z0-9_.]*)\s*\}\}/i',
            function (array $match) use ($values): string {
                $key = strtolower($match[1]);

                return array_key_exists($key, $values)
                    ? (string) $values[$key]
                    : $match[0];
            },
            $full,
        );

        if ($replaced === $full) {
            return false;
        }

        $textNodes[0]->nodeValue = $replaced;
        $textNodes[0]->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');

        $count = count($textNodes);

        for ($i = 1; $i < $count; $i++) {
            $textNodes[$i]->nodeValue = '';
        }

        return true;
    }
}
