<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use InvalidArgumentException;

class DevRepoTools
{
    /** @var array<int, string> */
    private const ALLOWED_BASH_PREFIXES = [
        'grep',
        'find',
        'ls',
        'cat',
        'head',
        'tail',
        'wc',
        'php',
        'npm',
        'composer',
        './vendor/bin/pest',
        './vendor/bin/pint',
    ];

    public function __construct(
        private readonly string $repoPath,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            [
                'name' => 'read_file',
                'description' => 'Read a UTF-8 text file from the repository (relative path from repo root).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'path' => ['type' => 'string', 'description' => 'Relative file path, e.g. resources/css/app.css'],
                    ],
                    'required' => ['path'],
                ],
            ],
            [
                'name' => 'write_file',
                'description' => 'Create or overwrite a text file in the repository.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'path' => ['type' => 'string', 'description' => 'Relative file path'],
                        'content' => ['type' => 'string', 'description' => 'Full file contents'],
                    ],
                    'required' => ['path', 'content'],
                ],
            ],
            [
                'name' => 'list_dir',
                'description' => 'List files and directories at a path (relative to repo root).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'path' => ['type' => 'string', 'description' => 'Relative directory path, default "."'],
                    ],
                ],
            ],
            [
                'name' => 'bash',
                'description' => 'Run a read-only or build command in the repo (grep, ls, npm run build, pest, etc.).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'command' => ['type' => 'string', 'description' => 'Shell command to execute'],
                    ],
                    'required' => ['command'],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function execute(string $name, array $input): string
    {
        return match ($name) {
            'read_file' => $this->readFile((string) ($input['path'] ?? '')),
            'write_file' => $this->writeFile((string) ($input['path'] ?? ''), (string) ($input['content'] ?? '')),
            'list_dir' => $this->listDir((string) ($input['path'] ?? '.')),
            'bash' => $this->bash((string) ($input['command'] ?? '')),
            default => throw new InvalidArgumentException("Tool inconnu : {$name}"),
        };
    }

    private function readFile(string $path): string
    {
        $absolute = $this->resolvePath($path);

        if (! is_file($absolute)) {
            throw new InvalidArgumentException("Fichier introuvable : {$path}");
        }

        $contents = file_get_contents($absolute);

        if ($contents === false) {
            throw new InvalidArgumentException("Impossible de lire : {$path}");
        }

        return $contents;
    }

    private function writeFile(string $path, string $content): string
    {
        $absolute = $this->resolvePath($path, allowMissing: true);
        $directory = dirname($absolute);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new InvalidArgumentException("Impossible de créer le dossier pour : {$path}");
        }

        if (file_put_contents($absolute, $content) === false) {
            throw new InvalidArgumentException("Impossible d'écrire : {$path}");
        }

        return "Fichier écrit : {$path} (".strlen($content).' octets)';
    }

    private function listDir(string $path): string
    {
        $absolute = $this->resolvePath($path);

        if (! is_dir($absolute)) {
            throw new InvalidArgumentException("Dossier introuvable : {$path}");
        }

        $entries = scandir($absolute) ?: [];

        $lines = collect($entries)
            ->reject(fn (string $entry) => in_array($entry, ['.', '..'], true))
            ->map(function (string $entry) use ($absolute) {
                return is_dir($absolute.'/'.$entry) ? "{$entry}/" : $entry;
            })
            ->values()
            ->all();

        return implode("\n", $lines) ?: '(vide)';
    }

    private function bash(string $command): string
    {
        $command = trim($command);

        if ($command === '') {
            throw new InvalidArgumentException('Commande bash vide.');
        }

        if (! $this->isAllowedBashCommand($command)) {
            throw new InvalidArgumentException('Commande non autorisée.');
        }

        $result = Process::path($this->repoPath)
            ->timeout(120)
            ->run(['bash', '-c', $command]);

        $output = trim($result->output()."\n".$result->errorOutput());

        if ($result->failed()) {
            return "Exit {$result->exitCode()}:\n".$output;
        }

        return $output !== '' ? $output : 'OK (exit 0, pas de sortie)';
    }

    private function isAllowedBashCommand(string $command): bool
    {
        foreach (self::ALLOWED_BASH_PREFIXES as $prefix) {
            if ($command === $prefix || str_starts_with($command, $prefix.' ')) {
                return true;
            }
        }

        return false;
    }

    private function resolvePath(string $path, bool $allowMissing = false): string
    {
        $path = trim($path);

        if ($path === '' || str_starts_with($path, '/')) {
            throw new InvalidArgumentException('Chemin invalide.');
        }

        if (str_contains($path, '..')) {
            throw new InvalidArgumentException('Chemin hors dépôt interdit.');
        }

        $repoRoot = realpath($this->repoPath);

        if ($repoRoot === false) {
            throw new InvalidArgumentException('Dépôt local introuvable.');
        }

        $candidate = $repoRoot.'/'.ltrim($path, '/');

        if ($allowMissing) {
            $parent = realpath(dirname($candidate));

            if ($parent === false || ! str_starts_with($parent, $repoRoot)) {
                throw new InvalidArgumentException('Chemin hors dépôt interdit.');
            }

            return $candidate;
        }

        $resolved = realpath($candidate);

        if ($resolved === false || ! str_starts_with($resolved, $repoRoot)) {
            throw new InvalidArgumentException('Chemin hors dépôt interdit.');
        }

        return $resolved;
    }
}
