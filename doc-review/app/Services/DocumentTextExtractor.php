<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\Element\PageBreak;
use PhpOffice\PhpWord\IOFactory as PhpWordIOFactory;
use Smalot\PdfParser\Parser as PdfParser;

class DocumentTextExtractor
{
    /**
     * Extract plain text from a stored document file.
     *
     * @throws \RuntimeException
     */
    public function extract(string $disk, string $filePath, string $mimeType, string $originalName): string
    {
        $pages = $this->extractPerPage($disk, $filePath, $mimeType, $originalName);

        return implode("\n\n", $pages);
    }

    /**
     * Extract text per page from a stored document file.
     *
     * Returns an array of strings, one per page.
     * - PDF: each page from PdfParser
     * - Word: split by explicit page breaks (PageBreak elements); if none, single page
     * - TXT: single page
     *
     * @return array<int, string> Array of text per page (1-indexed conceptually, 0-indexed array)
     *
     * @throws \RuntimeException
     */
    public function extractPerPage(string $disk, string $filePath, string $mimeType, string $originalName): array
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $type = $this->resolveType($extension, $mimeType);
        $absolutePath = Storage::disk($disk)->path($filePath);

        switch ($type) {
            case 'txt':
                $contents = file_get_contents($absolutePath);
                if ($contents === false) {
                    throw new \RuntimeException('Unable to read text file.');
                }

                return [str_replace("\0", '', $contents)];

            case 'pdf':
                return $this->extractPdfPerPage($absolutePath);

            case 'docx':
            case 'doc':
                return $this->extractWordPerPage($absolutePath, $type);

            default:
                throw new \RuntimeException('Unsupported document type.');
        }
    }

    protected function resolveType(string $extension, string $mimeType): ?string
    {
        $mime = strtolower($mimeType);

        $map = [
            'txt' => ['txt', 'text', 'log'],
            'pdf' => ['pdf'],
            'docx' => ['docx'],
            'doc' => ['doc'],
        ];

        foreach ($map as $type => $exts) {
            if (in_array($extension, $exts, true)) {
                return $type;
            }
        }

        if (str_contains($mime, 'pdf')) {
            return 'pdf';
        }
        if (str_contains($mime, 'wordprocessingml')) {
            return 'docx';
        }
        if (str_contains($mime, 'msword')) {
            return 'doc';
        }
        if (str_starts_with($mime, 'text/')) {
            return 'txt';
        }

        return null;
    }

    /**
     * Extract per-page text from a PDF file stored on the given disk.
     *
     * @return array<int, string>
     */
    public function extractPdfPerPageFromDisk(string $disk, string $filePath): array
    {
        $absolutePath = Storage::disk($disk)->path($filePath);

        return $this->extractPdfPerPage($absolutePath);
    }

    protected function extractPdfPerPage(string $absolutePath): array
    {
        $parser = new PdfParser;
        $pdf = $parser->parseFile($absolutePath);
        $pages = $pdf->getPages();

        if (empty($pages)) {
            // Fallback: treat as single page
            return [$this->cleanText($pdf->getText())];
        }

        $result = [];
        foreach ($pages as $page) {
            $result[] = $this->cleanText($page->getText());
        }

        return $result;
    }

    protected function extractWordPerPage(string $absolutePath, string $type): array
    {
        if ($type === 'doc') {
            throw new \RuntimeException('Legacy .doc files are not supported. Please convert to .docx.');
        }

        $phpWord = PhpWordIOFactory::load($absolutePath);
        $pages = [];
        $currentPageText = '';

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                // PageBreak element marks a page boundary
                if ($element instanceof PageBreak) {
                    $pages[] = $this->cleanText($currentPageText);
                    $currentPageText = '';

                    continue;
                }

                $text = $this->extractWordElementText($element);
                if ($text !== '') {
                    $currentPageText .= $text."\n";
                }
            }
        }

        // Add the last page
        $pages[] = $this->cleanText($currentPageText);

        // Filter out completely empty pages at the end
        while (count($pages) > 1 && trim(end($pages)) === '') {
            array_pop($pages);
        }

        return $pages;
    }

    protected function extractWordElementText($element): string
    {
        $text = '';

        if (is_object($element) && method_exists($element, 'getText')) {
            try {
                $result = $element->getText();
                if (is_array($result)) {
                    $text .= implode('', $result);
                } else {
                    $text .= (string) $result;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if (is_object($element) && method_exists($element, 'getElements')) {
            try {
                foreach ($element->getElements() as $child) {
                    // Don't recurse into PageBreak children
                    if ($child instanceof PageBreak) {
                        continue;
                    }
                    $text .= $this->extractWordElementText($child);
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return $text;
    }

    protected function cleanText(string $text): string
    {
        $text = str_replace("\0", '', $text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        return $text;
    }
}
