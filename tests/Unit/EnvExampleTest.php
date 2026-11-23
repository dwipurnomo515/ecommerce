<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * .env.example is committed to a public repository. A real API key pasted
 * into it by mistake is leaked the moment it is pushed (this happened once
 * with the RajaOngkir key), so every secret-looking variable must be empty.
 */
class EnvExampleTest extends TestCase
{
    /**
     * Per committed example file: values allowed although the name looks
     * secret. Only local Docker defaults; production allows none.
     */
    private const ALLOWED = [
        '.env.example' => ['DB_PASSWORD' => 'secret'],
        '.env.production.example' => [],
    ];

    /**
     * @return array<string, array{string}>
     */
    public static function exampleFiles(): array
    {
        $files = array_keys(self::ALLOWED);

        return array_combine($files, array_map(fn (string $f) => [$f], $files));
    }

    #[Test]
    #[DataProvider('exampleFiles')]
    public function env_example_contains_no_secrets(string $file): void
    {
        $leaks = [];

        foreach ($this->variables(dirname(__DIR__, 2).'/'.$file) as $name => $value) {
            if (! preg_match('/(KEY|SECRET|PASSWORD|TOKEN)(_ID)?$/', $name)) {
                continue;
            }

            if (in_array($value, ['', 'null'], true) || (self::ALLOWED[$file][$name] ?? null) === $value) {
                continue;
            }

            $leaks[] = $name;
        }

        $this->assertSame([], $leaks, "Kosongkan nilai rahasia di {$file}: ".implode(', ', $leaks));
    }

    #[Test]
    public function the_check_recognises_a_pasted_key(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($file, "APP_KEY=\nRAJAONGKIR_API_KEY= abc123\n# FOO_KEY=commented\n");

        $this->assertSame(['APP_KEY' => '', 'RAJAONGKIR_API_KEY' => 'abc123'], $this->variables($file));

        unlink($file);
    }

    /**
     * @return array<string, string>
     */
    private function variables(string $path): array
    {
        $variables = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (preg_match('/^\s*([A-Z0-9_]+)\s*=\s*(.*?)\s*$/', $line, $match)) {
                $variables[$match[1]] = trim($match[2], '"\'');
            }
        }

        return $variables;
    }
}
