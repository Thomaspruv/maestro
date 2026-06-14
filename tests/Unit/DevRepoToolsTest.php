<?php

namespace Tests\Unit;

use App\Services\DevRepoTools;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DevRepoToolsTest extends TestCase
{
    private string $repoPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoPath = sys_get_temp_dir().'/maestro_dev_tools_'.uniqid();
        mkdir($this->repoPath, 0755, true);
        file_put_contents($this->repoPath.'/hello.txt', 'bonjour');
        mkdir($this->repoPath.'/src', 0755, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->repoPath.'/hello.txt');
        @unlink($this->repoPath.'/src/new.txt');
        @rmdir($this->repoPath.'/src');
        @rmdir($this->repoPath);

        parent::tearDown();
    }

    #[Test]
    public function it_reads_and_writes_files_inside_repo(): void
    {
        $tools = new DevRepoTools($this->repoPath);

        $this->assertSame('bonjour', $tools->execute('read_file', ['path' => 'hello.txt']));
        $this->assertStringContainsString('Fichier écrit', $tools->execute('write_file', [
            'path' => 'src/new.txt',
            'content' => 'test',
        ]));
        $this->assertSame('test', $tools->execute('read_file', ['path' => 'src/new.txt']));
    }

    #[Test]
    public function it_rejects_paths_outside_repo(): void
    {
        $tools = new DevRepoTools($this->repoPath);

        $this->expectException(\InvalidArgumentException::class);
        $tools->execute('read_file', ['path' => '../etc/passwd']);
    }

    #[Test]
    public function it_lists_directory_entries(): void
    {
        $tools = new DevRepoTools($this->repoPath);

        $listing = $tools->execute('list_dir', ['path' => '.']);

        $this->assertStringContainsString('hello.txt', $listing);
        $this->assertStringContainsString('src/', $listing);
    }
}
