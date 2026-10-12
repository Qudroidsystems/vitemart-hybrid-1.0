<?php

namespace App\Services\Operations;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Spatie\DbDumper\Compressors\GzipCompressor;
use Spatie\DbDumper\Databases\MySql;

/**
 * Database backups and restores for one server.
 *
 *  dump()     a gzipped SQL dump in storage/app/vitemart-backups (used before upgrades and restores,
 *             and by "back up now"); scheduled nightly backups use the existing backup:run
 *  restore()  puts a dump back (.sql, .sql.gz, or a backup:run .zip) – after taking a safety dump first
 *
 * Every run is written to backup_runs, and the newest good one is reported to head office with the heartbeat.
 */
class BackupService
{
    public function dir(): string
    {
        $d = storage_path('app/vitemart-backups');
        File::ensureDirectoryExists($d);
        return $d;
    }

    protected function connection(?string $name = null): array
    {
        $name ??= config('database.default');
        $c = config("database.connections.{$name}");
        if (($c['driver'] ?? '') !== 'mysql' && ($c['driver'] ?? '') !== 'mariadb') {
            throw new \RuntimeException('Backups need a MySQL / MariaDB database.');
        }
        return $c;
    }

    protected function binary(string $name, array $c): string
    {
        $dir = $c['dump']['dump_binary_path'] ?? env('DB_DUMP_PATH');
        if ($dir) {
            foreach ([$name, $name . '.exe'] as $b) {
                if (is_file(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $b)) {
                    return rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $b;
                }
            }
        }
        return $name; // on the PATH
    }

    public function dump(string $kind = 'manual', ?string $connection = null): object
    {
        $c = $this->connection($connection);
        $id = DB::table('backup_runs')->insertGetId([
            'kind' => $kind, 'status' => 'running', 'app_version' => config('vitemart.version'),
            'started_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $file = $this->dir() . '/' . now()->format('Y-m-d_His') . "_{$kind}_" . $c['database'] . '.sql.gz';
        try {
            $dumper = MySql::create()
                ->setDbName($c['database'])->setUserName($c['username'])->setPassword((string) $c['password'])
                ->setHost($c['host'] ?? '127.0.0.1')->setPort((int) ($c['port'] ?? 3306))
                ->useSingleTransaction()->skipLockTables()->addExtraOption('--routines --triggers --default-character-set=utf8mb4')
                ->useCompressor(new GzipCompressor());
            if (!empty($c['unix_socket'])) {
                $dumper->setSocket($c['unix_socket']);
            }
            $dir = $c['dump']['dump_binary_path'] ?? env('DB_DUMP_PATH');
            if ($dir) {
                $dumper->setDumpBinaryPath($dir);
            }
            $dumper->dumpToFile($file);
            DB::table('backup_runs')->where('id', $id)->update(['status' => 'ok', 'path' => $file, 'size_bytes' => filesize($file),
                'finished_at' => now(), 'updated_at' => now()]);
        } catch (\Throwable $e) {
            DB::table('backup_runs')->where('id', $id)->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 2000),
                'finished_at' => now(), 'updated_at' => now()]);
            throw $e;
        }
        return DB::table('backup_runs')->find($id);
    }

    /** Newest good backups (ours and backup:run zips), newest first. */
    public function list(int $limit = 20): array
    {
        $files = collect(File::files($this->dir()))->map(fn ($f) => $f->getPathname());
        $disk = config('backup.backup.destination.disks.0', 'local');
        $root = config("filesystems.disks.{$disk}.root");
        $name = config('backup.backup.name', config('app.name'));
        if ($root && is_dir($root . '/' . $name)) {
            $files = $files->merge(collect(File::files($root . '/' . $name))->filter(fn ($f) => str_ends_with($f->getFilename(), '.zip'))->map(fn ($f) => $f->getPathname()));
        }
        return $files->filter(fn ($p) => preg_match('/\.(sql|sql\.gz|zip)$/', $p))
            ->sortByDesc(fn ($p) => filemtime($p))->take($limit)
            ->map(fn ($p) => ['path' => $p, 'name' => basename($p), 'size' => filesize($p), 'time' => filemtime($p)])->values()->all();
    }

    /** Is this a usable backup?  Returns the SQL inside (as a temp file path when unpacked). */
    public function verify(string $path): array
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Backup not found: {$path}");
        }
        $sql = $this->extract($path);
        $head = $this->head($sql);
        $ok = str_contains($head, 'CREATE TABLE') || str_contains($head, 'MySQL dump') || str_contains($head, 'MariaDB dump');
        $tables = $this->countTables($sql);
        return ['ok' => $ok && $tables > 0, 'tables' => $tables, 'sql' => $sql];
    }

    protected function extract(string $path): string
    {
        if (str_ends_with($path, '.sql')) {
            return $path;
        }
        $tmp = storage_path('app/vitemart-backups/tmp');
        File::ensureDirectoryExists($tmp);
        if (str_ends_with($path, '.sql.gz')) {
            $out = $tmp . '/' . basename($path, '.gz');
            $in = gzopen($path, 'rb');
            $fh = fopen($out, 'wb');
            while (!gzeof($in)) {
                fwrite($fh, gzread($in, 1 << 20));
            }
            gzclose($in);
            fclose($fh);
            return $out;
        }
        if (str_ends_with($path, '.zip')) {
            $zip = new \ZipArchive();
            if ($zip->open($path) !== true) {
                throw new \RuntimeException('Cannot open the backup zip.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $n = $zip->getNameIndex($i);
                if (preg_match('#db-dumps/.+\.sql(\.gz)?$#', $n)) {
                    $target = $tmp . '/' . basename($n);
                    file_put_contents($target, $zip->getFromIndex($i));
                    $zip->close();
                    return $this->extract($target);
                }
            }
            $zip->close();
            throw new \RuntimeException('No database dump inside this zip.');
        }
        throw new \RuntimeException('Unknown backup type.');
    }

    protected function head(string $sql): string
    {
        $fh = fopen($sql, 'rb');
        $h = (string) fread($fh, 1 << 16);
        fclose($fh);
        return $h;
    }

    protected function countTables(string $sql): int
    {
        $n = 0;
        $fh = fopen($sql, 'rb');
        while (($line = fgets($fh)) !== false) {
            if (str_starts_with($line, 'CREATE TABLE')) {
                $n++;
            }
        }
        fclose($fh);
        return $n;
    }

    /**
     * Load a backup into the database (replacing it).  The caller takes a safety dump first.
     */
    public function load(string $path, ?string $connection = null): void
    {
        $c = $this->connection($connection);
        $v = $this->verify($path);
        if (!$v['ok']) {
            throw new \RuntimeException('This file does not look like a complete database backup.');
        }
        $args = [$this->binary('mysql', $c), '--default-character-set=utf8mb4', '-u' . $c['username']];
        if (!empty($c['unix_socket'])) {
            $args[] = '--socket=' . $c['unix_socket'];
        } else {
            $args[] = '-h' . ($c['host'] ?? '127.0.0.1');
            $args[] = '-P' . ($c['port'] ?? 3306);
        }
        // start from an empty database so tables created after the backup don't linger
        DB::connection($connection)->statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (DB::connection($connection)->select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"') as $row) {
            $t = array_values((array) $row)[0];
            DB::connection($connection)->statement("DROP TABLE IF EXISTS `{$t}`");
        }
        foreach (DB::connection($connection)->select('SHOW FULL TABLES WHERE Table_type = "VIEW"') as $row) {
            $t = array_values((array) $row)[0];
            DB::connection($connection)->statement("DROP VIEW IF EXISTS `{$t}`");
        }
        DB::connection($connection)->statement('SET FOREIGN_KEY_CHECKS=1');

        $args[] = $c['database'];
        $result = Process::timeout(3600)->env(['MYSQL_PWD' => (string) $c['password']])->input(fopen($v['sql'], 'rb'))->run($args);
        if (!$result->successful()) {
            throw new \RuntimeException('Restore failed: ' . mb_substr($result->errorOutput(), 0, 1000));
        }
        DB::connection($connection)->reconnect();
    }

    public function latestOk(): ?object
    {
        return DB::table('backup_runs')->where('status', 'ok')->orderByDesc('finished_at')->first();
    }
}
