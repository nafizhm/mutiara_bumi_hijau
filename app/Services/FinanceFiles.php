<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FinanceFiles
{
    public static function withUpload(Request $request, string $field, array $folders, callable $callback, ?string $old = null)
    {
        $name = $old ?: '';
        $new = $request->hasFile($field);
        try {
            if ($new) {
                $file = $request->file($field);
                $name = Str::random(32) . '.' . $file->extension();
                foreach ($folders as $folder) {
                    $directory = public_path('assets/keuangan/' . $folder);
                    if (!is_dir($directory) && !mkdir($directory, 0755, true)) throw new \RuntimeException('Folder lampiran gagal dibuat.');
                    if (!copy($file->getRealPath(), $directory . '/' . $name)) throw new \RuntimeException('Lampiran gagal disimpan.');
                }
            }
            $result = $callback($name);
        } catch (\Throwable $exception) {
            if ($new) self::remove($folders, $name);
            throw $exception;
        }
        if ($new && $old) self::remove($folders, $old);
        return $result;
    }

    public static function remove(array $folders, ?string $name): void
    {
        if (!$name) return;
        foreach ($folders as $folder) {
            $path = public_path('assets/keuangan/' . $folder . '/' . basename($name));
            if (is_file($path)) unlink($path);
        }
    }
}
