<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Safe read / write of the .env file for the System Configuration page.
 *
 *  - Only keys in EDITABLE can be changed (APP_KEY never: it unlocks saved secrets).
 *  - A timestamped copy is saved to storage/app/env-backups before every write (last 30 kept).
 *  - Lines are replaced in place, so comments and order are kept; new keys are appended.
 */
class EnvEditor
{
    public const EDITABLE = [
        // app
        'APP_NAME', 'APP_ENV', 'APP_DEBUG', 'APP_URL', 'APP_TIMEZONE', 'APP_LOCALE', 'ASSET_URL',
        // database
        'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_SOCKET',
        // mail
        'MAIL_MAILER', 'MAIL_SCHEME', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_ENCRYPTION',
        'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME',
        // performance / runtime
        'QUEUE_CONNECTION', 'CACHE_STORE', 'SESSION_DRIVER', 'SESSION_LIFETIME', 'SESSION_SECURE_COOKIE',
        'LOG_LEVEL', 'LOG_STACK', 'REDIS_HOST', 'REDIS_PORT', 'REDIS_PASSWORD', 'FILESYSTEM_DISK',
        // ViteMart
        'VITEMART_MFA_ENFORCE', 'VITEMART_FEED_SETTLE_SECONDS', 'DB_DUMP_PATH',
    ];

    public const SECRET = ['DB_PASSWORD', 'MAIL_PASSWORD', 'REDIS_PASSWORD'];

    public static function path(): string
    {
        return app()->environmentFilePath();
    }

    public static function writable(): bool
    {
        $p = static::path();
        return is_file($p) ? is_writable($p) : is_writable(dirname($p));
    }

    /** @return array<string,string> raw values from the file (not the cached config) */
    public static function all(): array
    {
        $out = [];
        $p = static::path();
        if (!is_file($p)) {
            return $out;
        }
        foreach (preg_split('/\r\n|\n|\r/', (string) file_get_contents($p)) as $line) {
            if (!preg_match('/^\s*(?:export\s+)?([A-Z0-9_]+)\s*=\s*(.*)$/', $line, $m)) {
                continue;
            }
            $out[$m[1]] = static::unquote($m[2]);
        }
        return $out;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::all()[$key] ?? $default;
    }

    /**
     * Write keys. Values of null are skipped; '' writes an empty value.
     * Returns the backup file name.
     */
    public static function set(array $values): string
    {
        $values = array_filter($values, fn ($v) => $v !== null);
        foreach (array_keys($values) as $k) {
            if (!in_array($k, static::EDITABLE, true)) {
                throw new \InvalidArgumentException("{$k} cannot be changed here.");
            }
        }
        if (!static::writable()) {
            throw new \RuntimeException('The web server cannot write to ' . static::path() . '. Give it write permission and try again.');
        }

        $p = static::path();
        $content = is_file($p) ? (string) file_get_contents($p) : '';
        $backup = static::backup($content);

        $eol = str_contains($content, "\r\n") ? "\r\n" : "\n";
        $lines = $content === '' ? [] : preg_split('/\r\n|\n|\r/', $content);
        $done = [];
        foreach ($lines as $i => $line) {
            if (preg_match('/^\s*(?:export\s+)?([A-Z0-9_]+)\s*=/', $line, $m) && array_key_exists($m[1], $values)) {
                if (isset($done[$m[1]])) {
                    $lines[$i] = null; // duplicate key: keep only the first
                    continue;
                }
                $lines[$i] = $m[1] . '=' . static::quote((string) $values[$m[1]]);
                $done[$m[1]] = true;
            }
        }
        $lines = array_values(array_filter($lines, fn ($l) => $l !== null));
        while ($lines && end($lines) === '') {
            array_pop($lines);
        }
        $new = array_diff_key($values, $done);
        if ($new) {
            $lines[] = '';
            foreach ($new as $k => $v) {
                $lines[] = $k . '=' . static::quote((string) $v);
            }
        }
        $out = implode($eol, $lines) . $eol;

        $tmp = $p . '.tmp-' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, $out, LOCK_EX) === false || !@rename($tmp, $p)) {
            @unlink($tmp);
            if (@file_put_contents($p, $out, LOCK_EX) === false) {
                throw new \RuntimeException('Could not write ' . $p . '.');
            }
        }
        @chmod($p, 0640);
        return $backup;
    }

    public static function backupDir(): string
    {
        return storage_path('app/env-backups');
    }

    protected static function backup(string $content): string
    {
        $dir = static::backupDir();
        File::ensureDirectoryExists($dir, 0750);
        if (!is_file($dir . '/.gitignore')) {
            @file_put_contents($dir . '/.gitignore', "*\n!.gitignore\n");
        }
        $name = '.env.' . now()->format('Y-m-d_His') . '.bak';
        file_put_contents($dir . '/' . $name, $content);
        @chmod($dir . '/' . $name, 0600);
        $files = collect(File::files($dir))->filter(fn ($f) => str_starts_with($f->getFilename(), '.env.'))
            ->sortByDesc(fn ($f) => $f->getMTime())->values();
        foreach ($files->slice(30) as $old) {
            @unlink($old->getPathname());
        }
        return $name;
    }

    /** @return array<int,array{name:string,size:int,time:int}> */
    public static function backups(): array
    {
        $dir = static::backupDir();
        if (!is_dir($dir)) {
            return [];
        }
        return collect(File::files($dir))->filter(fn ($f) => str_starts_with($f->getFilename(), '.env.'))
            ->sortByDesc(fn ($f) => $f->getMTime())->values()
            ->map(fn ($f) => ['name' => $f->getFilename(), 'size' => $f->getSize(), 'time' => $f->getMTime()])->all();
    }

    /** Put a backup back (the current file is itself backed up first). */
    public static function restore(string $name): void
    {
        if (!preg_match('/^\.env\.[0-9_\-]+\.bak$/', $name) || !is_file(static::backupDir() . '/' . $name)) {
            throw new \InvalidArgumentException('Backup not found.');
        }
        if (!static::writable()) {
            throw new \RuntimeException('The web server cannot write to ' . static::path() . '.');
        }
        $content = (string) file_get_contents(static::backupDir() . '/' . $name);
        $current = static::all();
        // never let a restore change the encryption key
        if (isset($current['APP_KEY']) && preg_match('/^APP_KEY=.*$/m', $content)) {
            $content = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $current['APP_KEY'], $content);
        }
        static::backup((string) file_get_contents(static::path()));
        file_put_contents(static::path(), $content, LOCK_EX);
    }

    public static function quote(string $v): string
    {
        if ($v === '') {
            return '';
        }
        if (in_array(strtolower($v), ['true', 'false', 'null'], true) || preg_match('/^[A-Za-z0-9_.:\/@\-+,]+$/', $v)) {
            return $v;
        }
        // "${APP_NAME}" style references stay live
        if (preg_match('/^\$\{[A-Z0-9_]+\}$/', $v)) {
            return '"' . $v . '"';
        }
        return '"' . str_replace(['\\', '"', "\n", "\r", '$'], ['\\\\', '\\"', '\\n', '', '\\$'], $v) . '"';
    }

    public static function unquote(string $v): string
    {
        $v = trim($v);
        if ($v === '') {
            return '';
        }
        if ($v[0] === '"') {
            if (preg_match('/^"((?:[^"\\\\]|\\\\.)*)"/', $v, $m)) {
                return str_replace(['\\n', '\\"', '\\$', '\\\\'], ["\n", '"', '$', '\\'], $m[1]);
            }
            return trim($v, '"');
        }
        if ($v[0] === "'") {
            return preg_match("/^'([^']*)'/", $v, $m) ? $m[1] : trim($v, "'");
        }
        return trim(preg_replace('/\s+#.*$/', '', $v));
    }

    public static function mask(?string $v): string
    {
        return $v ? str_repeat('•', 8) : '';
    }
}
