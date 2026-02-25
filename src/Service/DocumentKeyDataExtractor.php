<?php

namespace App\Service;

final class DocumentKeyDataExtractor
{
    /**
     * @return array{
     *   document_type: string,
     *   fields: array<string, mixed>
     * }
     */
    public function extract(string $text, string $documentType): array
    {
        $normalizedText = $this->normalizeText($text);
        $normalizedType = $this->normalizeType($documentType);

        if ($normalizedType === 'facture') {
            return [
                'document_type' => 'facture',
                'fields' => $this->extractInvoiceFields($normalizedText),
            ];
        }

        if ($normalizedType === 'contrat') {
            return [
                'document_type' => 'contrat',
                'fields' => $this->extractContractFields($normalizedText),
            ];
        }

        return [
            'document_type' => 'autre',
            'fields' => $this->extractGenericFields($normalizedText),
        ];
    }

    public function guessTypeFromText(string $text): string
    {
        $haystack = $this->normalizeForSearch($this->normalizeText($text));

        $invoiceScore = 0;
        $contractScore = 0;

        foreach (['facture', 'tva', 'montant ttc', 'echeance', 'total ht'] as $needle) {
            if (str_contains($haystack, $needle)) {
                ++$invoiceScore;
            }
        }

        foreach (['contrat', 'clause', 'preavis', 'renouvellement', 'resiliation'] as $needle) {
            if (str_contains($haystack, $needle)) {
                ++$contractScore;
            }
        }

        if ($invoiceScore >= $contractScore && $invoiceScore >= 2) {
            return 'facture';
        }

        if ($contractScore > $invoiceScore && $contractScore >= 2) {
            return 'contrat';
        }

        return 'autre';
    }

    /**
     * @return array<string, mixed>
     */
    private function extractInvoiceFields(string $text): array
    {
        return [
            'fournisseur_nom' => $this->findSupplierName($text),
            'numero_facture' => $this->findInvoiceNumber($text),
            'date_facture' => $this->findDateByLabel($text, ['date facture', 'date emission', 'date d emission', 'issued on']),
            'date_echeance' => $this->findDateByLabel($text, ['date echeance', 'date limite de paiement', 'limite de paiement', 'due date']),
            'devise' => $this->findCurrency($text),
            'montant_ht' => $this->findAmountByLabel($text, ['montant ht', 'total ht', 'sous total']),
            'tva_taux' => $this->findTaxRate($text),
            'tva_montant' => $this->findTaxAmount($text),
            'montant_ttc' => $this->findAmountByLabel($text, ['montant ttc', 'total ttc', 'total a payer']),
            'net_a_payer' => $this->findAmountByLabel($text, ['net a payer', 'reste a payer']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractContractFields(string $text): array
    {
        return [
            'partie_1' => $this->findParty($text, ['entre', 'partie 1', 'societe 1']),
            'partie_2' => $this->findParty($text, ['et', 'partie 2', 'societe 2']),
            'reference_contrat' => $this->findContractReference($text),
            'date_debut' => $this->findDateByLabel($text, ['date debut', 'effet', 'prise d effet']),
            'date_fin' => $this->findDateByLabel($text, ['date fin', 'expiration', 'terme']),
            'renouvellement_auto' => $this->findAutoRenewal($text),
            'preavis_jours' => $this->findNoticeDays($text),
            'clause_resiliation' => $this->findClauseSnippet($text, ['resiliation']),
            'clause_penalite' => $this->findClauseSnippet($text, ['penalite', 'penalites']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractGenericFields(string $text): array
    {
        return [
            'date_principale' => $this->findFirstDate($text),
            'montant_principal' => $this->findFirstAmount($text),
            'mots_cles' => $this->extractKeywords($text),
        ];
    }

    private function normalizeType(string $value): string
    {
        $normalized = trim(mb_strtolower($value));
        if (in_array($normalized, ['facture', 'invoice'], true)) {
            return 'facture';
        }
        if (in_array($normalized, ['contrat', 'contract'], true)) {
            return 'contrat';
        }

        return 'autre';
    }

    private function normalizeText(string $value): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $value);
        $normalized = preg_replace('/[ \t]+/', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function findSupplierName(string $text): ?string
    {
        $fromLabel = $this->findLineValueAfterLabels($text, ['fournisseur', 'vendeur', 'emetteur', 'supplier', 'seller']);
        if ($fromLabel !== null && $this->isLikelySupplierName($fromLabel)) {
            return $fromLabel;
        }

        $lines = preg_split('/\n+/', $text) ?: [];

        $supplierFromHeader = $this->findSupplierFromHeader($lines);
        if ($supplierFromHeader !== null) {
            return $supplierFromHeader;
        }

        $scanLimit = min(14, \count($lines));
        for ($i = 0; $i < $scanLimit; ++$i) {
            $line = trim((string) ($lines[$i] ?? ''));
            if ($line === '') {
                continue;
            }

            if ($this->isLikelySupplierName($line)) {
                return mb_substr($line, 0, 120);
            }
        }

        return null;
    }

    /**
     * @param list<string> $lines
     */
    private function findSupplierFromHeader(array $lines): ?string
    {
        $invoiceLineIndex = null;
        foreach ($lines as $index => $line) {
            if (!is_string($line)) {
                continue;
            }

            $normalized = $this->normalizeForSearch($line);
            if (
                str_contains($normalized, 'facture')
                && (
                    str_contains($normalized, ' n ')
                    || str_contains($normalized, ' numero')
                    || preg_match('/\bfacture\s*\d/u', $normalized) === 1
                )
            ) {
                $invoiceLineIndex = $index;
                break;
            }
        }

        if ($invoiceLineIndex === null) {
            return null;
        }

        $start = max(0, $invoiceLineIndex - 5);
        $candidates = [];
        for ($i = $start; $i < $invoiceLineIndex; ++$i) {
            $line = trim((string) ($lines[$i] ?? ''));
            if ($line === '') {
                continue;
            }
            if ($this->isLikelySupplierName($line)) {
                $candidates[] = $line;
            }
        }

        if ($candidates === []) {
            return null;
        }

        $picked = array_slice($candidates, -2);
        $joined = trim(implode(' ', $picked));

        return $joined !== '' ? mb_substr($joined, 0, 120) : null;
    }

    private function findInvoiceNumber(string $text): ?string
    {
        $lines = preg_split('/\n+/', $text) ?: [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            $normalized = $this->normalizeForSearch($trimmed);
            if (!str_contains($normalized, 'facture')) {
                continue;
            }

            if (preg_match('/(?:n(?:o|°|º)?|num(?:ero)?|#)\s*[:\-]?\s*([A-Z0-9][A-Z0-9\-\/_.]{2,})/iu', $trimmed, $matches) === 1) {
                $candidate = trim($matches[1]);
                if (preg_match('/\d{2,}/', $candidate) === 1) {
                    return $candidate;
                }
            }

            if (preg_match('/\bn\D{0,3}([A-Z0-9][A-Z0-9\-\/_.]{2,})/iu', $trimmed, $matches) === 1) {
                $candidate = trim($matches[1]);
                if (preg_match('/\d{2,}/', $candidate) === 1) {
                    return $candidate;
                }
            }

            if (preg_match_all('/[A-Z0-9][A-Z0-9\-\/_.]{2,}/iu', $trimmed, $matches) >= 1) {
                $tokens = $matches[0] ?? [];
                if (is_array($tokens)) {
                    foreach ($tokens as $token) {
                        if (!is_string($token)) {
                            continue;
                        }
                        $candidate = trim($token);
                        if (preg_match('/\d{2,}/', $candidate) === 1) {
                            return $candidate;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param list<string> $labels
     */
    private function findDateByLabel(string $text, array $labels): ?string
    {
        $lines = preg_split('/\n+/', $text) ?: [];

        foreach ($lines as $index => $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            $normalizedLine = $this->normalizeForSearch($trimmed);
            foreach ($labels as $label) {
                $normalizedLabel = $this->normalizeForSearch($label);
                if (!str_contains($normalizedLine, $normalizedLabel)) {
                    continue;
                }

                $direct = $this->parseDate($trimmed);
                if ($direct !== null) {
                    return $direct;
                }

                $valueAfterLabel = $this->findLineValueAfterLabels($trimmed, [$label]);
                if ($valueAfterLabel !== null) {
                    $parsed = $this->parseDate($valueAfterLabel);
                    if ($parsed !== null) {
                        return $parsed;
                    }
                }

                $nextLine = trim((string) ($lines[$index + 1] ?? ''));
                if ($nextLine !== '') {
                    $next = $this->parseDate($nextLine);
                    if ($next !== null) {
                        return $next;
                    }
                }
            }
        }

        return null;
    }

    private function findFirstDate(string $text): ?string
    {
        if (preg_match('/\b(\d{2}[\/\-.]\d{2}[\/\-.]\d{4}|\d{4}[\/\-.]\d{2}[\/\-.]\d{2})\b/u', $text, $matches) === 1) {
            return $this->parseDate($matches[1]);
        }

        return null;
    }

    private function parseDate(string $raw): ?string
    {
        $candidate = trim($raw);
        if ($candidate === '') {
            return null;
        }

        if (preg_match('/\b(\d{2}[\/\-.]\d{2}[\/\-.]\d{4}|\d{4}[\/\-.]\d{2}[\/\-.]\d{2})\b/u', $candidate, $matches) === 1) {
            $candidate = $matches[1];
        } else {
            return null;
        }

        $formats = ['d/m/Y', 'd-m-Y', 'd.m.Y', 'Y-m-d', 'Y/m/d', 'Y.m.d'];
        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $candidate);
            if ($date instanceof \DateTimeImmutable) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function findCurrency(string $text): string
    {
        if (preg_match('/\b(EUR|USD|GBP|CHF)\b/i', $text, $matches) === 1) {
            return strtoupper($matches[1]);
        }

        if (str_contains($text, '€') || str_contains($text, 'â‚¬')) {
            return 'EUR';
        }

        return 'EUR';
    }

    /**
     * @param list<string> $labels
     */
    private function findAmountByLabel(string $text, array $labels): ?float
    {
        $lines = preg_split('/\n+/', $text) ?: [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            $normalizedLine = $this->normalizeForSearch($trimmed);
            foreach ($labels as $label) {
                $normalizedLabel = $this->normalizeForSearch($label);
                if (!str_contains($normalizedLine, $normalizedLabel)) {
                    continue;
                }

                $amount = $this->parseLastAmount($trimmed);
                if ($amount !== null) {
                    return $amount;
                }
            }
        }

        return null;
    }

    private function findTaxAmount(string $text): ?float
    {
        $lines = preg_split('/\n+/', $text) ?: [];

        // 1) Most specific lines first.
        foreach ($lines as $line) {
            $trimmed = trim((string) $line);
            if ($trimmed === '') {
                continue;
            }

            if (!$this->lineContainsLabel($trimmed, ['montant tva', 'tax amount', 'vat amount'])) {
                continue;
            }

            $amount = $this->parseLastAmountIgnoringPercent($trimmed);
            if ($amount !== null) {
                return $amount;
            }
        }

        // 2) Generic TVA lines, but ignore percentage values like "20%".
        foreach ($lines as $index => $line) {
            $trimmed = trim((string) $line);
            if ($trimmed === '') {
                continue;
            }

            if (!$this->lineContainsLabel($trimmed, ['tva', 'vat'])) {
                continue;
            }

            $amount = $this->parseLastAmountIgnoringPercent($trimmed);
            if ($amount !== null) {
                return $amount;
            }

            // Some OCR outputs split the value on the next line.
            $nextLine = trim((string) ($lines[$index + 1] ?? ''));
            if ($nextLine !== '' && !$this->lineContainsLabel($nextLine, ['tva', 'vat'])) {
                $nextAmount = $this->parseLastAmountIgnoringPercent($nextLine);
                if ($nextAmount !== null) {
                    return $nextAmount;
                }
            }
        }

        return null;
    }

    private function findFirstAmount(string $text): ?float
    {
        if (preg_match('/(\d{1,3}(?:[ .]\d{3})+(?:[.,]\d{2})?|\d+(?:[.,]\d{2})?)\s*(?:€|â‚¬|EUR|USD|GBP|CHF)?/u', $text, $matches) === 1) {
            return $this->parseAmount($matches[1]);
        }

        return null;
    }

    private function parseAmount(string $raw): ?float
    {
        $candidate = trim($raw);
        if ($candidate === '') {
            return null;
        }

        if (preg_match('/(\d{1,3}(?:[ .]\d{3})+(?:[.,]\d{2})?|\d+(?:[.,]\d{2})?)/u', $candidate, $matches) !== 1) {
            return null;
        }

        return $this->parseAmountToken((string) $matches[1]);
    }

    private function parseLastAmount(string $raw): ?float
    {
        if (preg_match_all('/\d{1,3}(?:[ .]\d{3})+(?:[.,]\d{2})?|\d+(?:[.,]\d{2})?/u', $raw, $matches) < 1) {
            return null;
        }

        $tokens = $matches[0] ?? [];
        if (!is_array($tokens) || $tokens === []) {
            return null;
        }

        $values = [];
        foreach ($tokens as $token) {
            if (!is_string($token)) {
                continue;
            }
            $parsed = $this->parseAmountToken($token);
            if ($parsed !== null) {
                $values[] = $parsed;
            }
        }

        if ($values === []) {
            return null;
        }

        return (float) $values[array_key_last($values)];
    }

    private function parseLastAmountIgnoringPercent(string $raw): ?float
    {
        if (preg_match_all('/(\d{1,3}(?:[ .]\d{3})+(?:[.,]\d{2})?|\d+(?:[.,]\d{2})?)\s*(%?)/u', $raw, $matches, \PREG_SET_ORDER) < 1) {
            return null;
        }

        $values = [];
        foreach ($matches as $match) {
            $token = isset($match[1]) && is_string($match[1]) ? $match[1] : null;
            $suffix = isset($match[2]) && is_string($match[2]) ? $match[2] : '';
            if ($token === null || $suffix === '%') {
                continue;
            }

            $parsed = $this->parseAmountToken($token);
            if ($parsed !== null) {
                $values[] = $parsed;
            }
        }

        if ($values === []) {
            return null;
        }

        return (float) $values[array_key_last($values)];
    }

    private function parseAmountToken(string $token): ?float
    {
        $number = trim($token);
        if ($number === '') {
            return null;
        }

        $number = preg_replace('/\s+/', '', $number) ?? $number;
        $hasComma = str_contains($number, ',');
        $hasDot = str_contains($number, '.');

        if ($hasComma && $hasDot) {
            $lastComma = strrpos($number, ',');
            $lastDot = strrpos($number, '.');
            if ($lastComma !== false && $lastDot !== false && $lastComma > $lastDot) {
                // Example: 1.234,56
                $number = str_replace('.', '', $number);
                $number = str_replace(',', '.', $number);
            } else {
                // Example: 1,234.56
                $number = str_replace(',', '', $number);
            }
        } elseif ($hasComma) {
            $number = str_replace(',', '.', $number);
        } elseif ($hasDot) {
            // If the dot is not decimal separator, treat it as thousand separator.
            if (preg_match('/\.\d{1,2}$/', $number) !== 1) {
                $number = str_replace('.', '', $number);
            }
        }

        return is_numeric($number) ? (float) $number : null;
    }

    private function findTaxRate(string $text): ?float
    {
        if (preg_match('/(?:tva|vat)\s*(?:[:\-])?\s*(\d{1,2}(?:[.,]\d{1,2})?)\s*%/iu', $text, $matches) === 1) {
            $value = str_replace(',', '.', $matches[1]);
            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    private function findContractReference(string $text): ?string
    {
        if (preg_match('/(?:ref(?:erence)?|reference)\s*(?:contrat)?\s*[:#-]?\s*([A-Z0-9][A-Z0-9\-\/_.]{2,})/iu', $text, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * @param list<string> $labels
     */
    private function findParty(string $text, array $labels): ?string
    {
        $lineValue = $this->findLineValueAfterLabels($text, $labels);
        if ($lineValue !== null) {
            return mb_substr($lineValue, 0, 150);
        }

        return null;
    }

    private function findAutoRenewal(string $text): ?bool
    {
        $haystack = $this->normalizeForSearch($text);
        if (str_contains($haystack, 'renouvellement automatique') || str_contains($haystack, 'reconduction tacite')) {
            return true;
        }
        if (str_contains($haystack, 'sans renouvellement') || str_contains($haystack, 'non reconductible')) {
            return false;
        }

        return null;
    }

    private function findNoticeDays(string $text): ?int
    {
        if (preg_match('/preavis\s*(?:de)?\s*(\d{1,3})\s*(jours?|j)\b/iu', $text, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * @param list<string> $labels
     */
    private function findClauseSnippet(string $text, array $labels): ?string
    {
        $lines = preg_split('/\n+/', $text) ?: [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            $normalizedLine = $this->normalizeForSearch($trimmed);
            foreach ($labels as $label) {
                if (str_contains($normalizedLine, $this->normalizeForSearch($label))) {
                    return mb_substr($trimmed, 0, 180);
                }
            }
        }

        return null;
    }

    /**
     * @param list<string> $labels
     */
    private function findLineValueAfterLabels(string $text, array $labels): ?string
    {
        $lines = preg_split('/\n+/', $text) ?: [];
        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            if ($trimmedLine === '') {
                continue;
            }

            $normalizedLine = $this->normalizeForSearch($trimmedLine);
            foreach ($labels as $label) {
                $normalizedLabel = $this->normalizeForSearch($label);
                if (!str_contains($normalizedLine, $normalizedLabel)) {
                    continue;
                }

                $parts = preg_split('/[:\-]/', $trimmedLine, 2);
                if (is_array($parts) && isset($parts[1])) {
                    $value = trim($parts[1]);
                    if ($value !== '') {
                        return mb_substr($value, 0, 180);
                    }
                }

                $cleaned = trim((string) preg_replace('/' . preg_quote($label, '/') . '/iu', '', $trimmedLine, 1));
                if ($cleaned !== '' && $cleaned !== $trimmedLine) {
                    return mb_substr($cleaned, 0, 180);
                }
            }
        }

        return null;
    }

    private function isLikelySupplierName(string $candidate): bool
    {
        $value = trim($candidate);
        if ($value === '') {
            return false;
        }

        $normalized = $this->normalizeForSearch($value);
        $banned = [
            'total',
            'montant',
            'ttc',
            'ht',
            'tva',
            'facture',
            'invoice',
            'date',
            'echeance',
            'net a payer',
            'a payer',
            'numero',
            'description',
            'prix unitaire',
            'quantite',
            'facture a',
            'client',
        ];
        if (\in_array($normalized, $banned, true)) {
            return false;
        }

        if (preg_match('/\d{2,}/', $value) === 1) {
            return false;
        }

        if (preg_match('/\b(total|montant|ttc|ht|tva|facture|date)\b/i', $value) === 1) {
            return false;
        }

        if (mb_strlen($value) < 3) {
            return false;
        }

        return preg_match('/[A-Za-z]/', $value) === 1;
    }

    /**
     * @return list<string>
     */
    private function extractKeywords(string $text): array
    {
        $keywords = [];
        $normalized = $this->normalizeForSearch($text);

        foreach (['facture', 'contrat', 'tva', 'echeance', 'resiliation', 'renouvellement'] as $needle) {
            if (str_contains($normalized, $needle)) {
                $keywords[] = $needle;
            }
        }

        return $keywords;
    }

    private function normalizeForSearch(string $value): string
    {
        $normalized = mb_strtolower($value);
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if (is_string($transliterated) && $transliterated !== '') {
            $normalized = $transliterated;
        }

        $normalized = str_replace(["'", '’'], ' ', $normalized);
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    /**
     * @param list<string> $labels
     */
    private function lineContainsLabel(string $line, array $labels): bool
    {
        $normalizedLine = $this->normalizeForSearch($line);
        foreach ($labels as $label) {
            if (str_contains($normalizedLine, $this->normalizeForSearch($label))) {
                return true;
            }
        }

        return false;
    }
}
