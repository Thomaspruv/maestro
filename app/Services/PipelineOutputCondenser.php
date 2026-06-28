<?php

namespace App\Services;

use Illuminate\Support\Str;

class PipelineOutputCondenser
{
    /** ~2000 tokens (approx. 4 chars/token). */
    public const MAX_CHARS_PER_STEP = 8000;

    private const HIGH_PRIORITY_KEYWORDS = [
        'critères', 'acceptation', 'fichiers', 'plan', 'risques', 'sécurité',
        'architecture', 'migrations', 'étapes', 'implémentation', 'checklist',
        'contraintes', 'dépendances', 'verdict', 'points pour',
    ];

    private const MEDIUM_PRIORITY_KEYWORDS = [
        'résumé', 'objectif', 'décisions', 'hypothèses', 'configuration', 'seeds',
        'compatibilité', 'permissions', 'hors périmètre', 'out of scope',
    ];

    public function condense(string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        $maxChars = (int) config('maestro.pipeline_output_max_chars', self::MAX_CHARS_PER_STEP);

        if (Str::length($text) <= $maxChars) {
            return $text;
        }

        $sections = $this->splitIntoSections($text);

        if (count($sections) <= 1) {
            return $this->truncateHeadTail($text, $maxChars);
        }

        return $this->selectSectionsByPriority($sections, $maxChars);
    }

    /**
     * @return array<int, array{heading: string, body: string, score: int}>
     */
    private function splitIntoSections(string $text): array
    {
        $lines = explode("\n", $text);
        $sections = [];
        $currentHeading = '';
        $currentBody = [];

        foreach ($lines as $line) {
            if (preg_match('/^#{1,3} .+/', $line)) {
                if ($currentHeading !== '' || $currentBody !== []) {
                    $body = trim(implode("\n", $currentBody));
                    $sections[] = [
                        'heading' => $currentHeading,
                        'body' => $body,
                        'score' => $this->scoreSection($currentHeading, $body),
                    ];
                }
                $currentHeading = $line;
                $currentBody = [];
            } else {
                $currentBody[] = $line;
            }
        }

        if ($currentHeading !== '' || $currentBody !== []) {
            $body = trim(implode("\n", $currentBody));
            $sections[] = [
                'heading' => $currentHeading,
                'body' => $body,
                'score' => $this->scoreSection($currentHeading, $body),
            ];
        }

        return $sections;
    }

    private function scoreSection(string $heading, string $body): int
    {
        $haystack = mb_strtolower($heading.' '.$body);

        foreach (self::HIGH_PRIORITY_KEYWORDS as $kw) {
            if (str_contains($haystack, $kw)) {
                return 3;
            }
        }

        foreach (self::MEDIUM_PRIORITY_KEYWORDS as $kw) {
            if (str_contains($haystack, $kw)) {
                return 2;
            }
        }

        return 1;
    }

    /**
     * @param  array<int, array{heading: string, body: string, score: int}>  $sections
     */
    private function selectSectionsByPriority(array $sections, int $maxChars): string
    {
        $omittedHeadings = [];
        $selected = [];
        $usedChars = 0;
        $reserved = 120;

        $sortedByScore = collect($sections)
            ->sortByDesc('score')
            ->values();

        $includedIndexes = [];

        foreach ($sortedByScore as $section) {
            $content = $this->renderSection($section);
            $len = Str::length($content) + 2;

            if ($usedChars + $len + $reserved <= $maxChars) {
                $includedIndexes[] = array_search($section, $sections, true);
                $usedChars += $len;
            }
        }

        foreach ($sections as $originalIndex => $section) {
            if (in_array($originalIndex, $includedIndexes, true)) {
                $selected[] = $this->renderSection($section);
            } elseif ($section['heading'] !== '') {
                $omittedHeadings[] = $section['heading'];
            }
        }

        $result = implode("\n\n", $selected);

        if ($omittedHeadings !== []) {
            $omittedList = implode(', ', array_map(fn ($h) => trim(ltrim($h, '#')), $omittedHeadings));
            $result .= "\n\n[Sections omises pour réduire les tokens : ".$omittedList.']';
        }

        return $result;
    }

    /**
     * @param  array{heading: string, body: string, score: int}  $section
     */
    private function renderSection(array $section): string
    {
        if ($section['heading'] === '') {
            return $section['body'];
        }

        if ($section['body'] === '') {
            return $section['heading'];
        }

        return $section['heading']."\n".$section['body'];
    }

    private function truncateHeadTail(string $text, int $maxChars): string
    {
        $half = (int) ($maxChars / 2) - 80;

        return Str::substr($text, 0, $half)
            ."\n\n[… contenu tronqué — consultez la sortie complète dans Maestro …]\n\n"
            .Str::substr($text, -$half);
    }
}
