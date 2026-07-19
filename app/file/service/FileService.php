<?php declare(strict_types=1);

namespace app\file\service;

use app\common\exception\BusinessException;
use app\file\storage\MinioStorage;

/**
 * 文件上传与删除服务：按 file.type 走 MinIO 或本地磁盘，统一做扩展名与大小校验
 */
final class FileService
{
    public function uploadFile(mixed $file, mixed $request = null): array
    {
        $originalName = $this->originalName($file);
        $ext = $originalName !== '' ? strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) : 'bin';
        if ($ext === '') {
            $ext = 'bin';
        }

        $cfg = config('file.');

        // 扩展名白名单校验（置空表示不限制）
        $allowed = $cfg['upload']['allowed-extensions'] ?? [];
        if (!empty($allowed) && !in_array($ext, $allowed, true)) {
            throw new BusinessException('不支持的文件类型: .' . $ext);
        }

        // 单文件大小校验（0 表示不限制）
        $maxBytes = $this->parseSize((string) ($cfg['upload']['max-file-size'] ?? '0'));
        $size = is_object($file) && method_exists($file, 'getSize') ? (int) $file->getSize() : 0;
        if ($maxBytes > 0 && $size > $maxBytes) {
            throw new BusinessException('文件大小超过限制: ' . $this->formatSize($maxBytes));
        }

        $folder = date('Ymd');
        $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
        $key = $folder . '/' . $storedName;

        if (($cfg['type'] ?? 'local') === 'minio') {
            $minio = new MinioStorage($cfg['minio']);
            $pathname = is_object($file) && method_exists($file, 'getPathname')
                ? $file->getPathname() : null;
            if ($pathname === null || $pathname === '' || !is_file($pathname)) {
                throw new BusinessException('上传文件读取失败');
            }
            $stream = fopen($pathname, 'r');
            if ($stream === false) {
                throw new BusinessException('上传文件读取失败');
            }
            $contentType = is_object($file) && method_exists($file, 'getMime') ? $file->getMime() : null;
            $url = $minio->upload($key, $stream, $contentType);
        } else {
            $local = $cfg['local'] ?? [];
            $storageRoot = rtrim((string) ($local['path'] ?? app()->getRootPath() . 'public/storage'), "/\\");
            $targetDir = $storageRoot . DIRECTORY_SEPARATOR . $folder;

            if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
                throw new BusinessException('创建上传目录失败');
            }

            $saved = false;
            if (is_object($file) && method_exists($file, 'move')) {
                try {
                    $file->move($targetDir, $storedName);
                    $saved = true;
                } catch (\Throwable) {
                    $saved = false;
                }
            }

            if (!$saved) {
                $tmpPath = is_object($file) && method_exists($file, 'getPathname')
                    ? (string) $file->getPathname() : null;
                if ($tmpPath === null || $tmpPath === '' || !is_file($tmpPath)) {
                    throw new BusinessException('上传文件不存在');
                }
                if (!@copy($tmpPath, $targetDir . DIRECTORY_SEPARATOR . $storedName)) {
                    throw new BusinessException('上传文件失败');
                }
            }

            $baseUrl = (string) ($local['base-url'] ?? '/storage');
            $url = rtrim($baseUrl, '/') . '/' . $folder . '/' . $storedName;
        }

        return [
            'name' => $originalName,
            'url'  => $url,
        ];
    }

    public function deleteFile(string $filePath): bool
    {
        $filePath = trim($filePath);
        if ($filePath === '') {
            throw new BusinessException('文件路径不能为空');
        }

        $cfg = config('file.');

        if (($cfg['type'] ?? 'local') === 'minio') {
            (new MinioStorage($cfg['minio']))->delete((new MinioStorage($cfg['minio']))->extractKey($filePath));
            return true;
        }

        if (str_starts_with($filePath, 'http://') || str_starts_with($filePath, 'https://')) {
            $parsed = parse_url($filePath);
            if (is_array($parsed) && isset($parsed['path'])) {
                $filePath = (string) $parsed['path'];
            }
        }

        $path = $filePath;
        $storagePrefix = '/storage/';
        if (str_starts_with($path, $storagePrefix)) {
            $path = substr($path, strlen($storagePrefix));
        }

        $path = ltrim($path, '/\\');
        if ($path === '' || str_contains($path, '..')) {
            return false;
        }

        $local = $cfg['local'] ?? [];
        $storageRoot = rtrim((string) ($local['path'] ?? app()->getRootPath() . 'public/storage'), "/\\");
        $fullPath = $storageRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        if (!is_file($fullPath)) {
            return false;
        }

        return @unlink($fullPath);
    }

    // 解析文件名（兼容 think 与 symfony 风格的上传对象）
    private function originalName(mixed $file): string
    {
        if (!is_object($file)) {
            return '';
        }
        if (method_exists($file, 'getOriginalName')) {
            return (string) $file->getOriginalName();
        }
        if (method_exists($file, 'getClientOriginalName')) {
            return (string) $file->getClientOriginalName();
        }
        return '';
    }

    // 将 DataSize 字符串（如 50MB）解析为字节数
    private function parseSize(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        $multiplier = 1;
        $upper = strtoupper($value);
        if (str_ends_with($upper, 'GB')) {
            $multiplier = 1024 * 1024 * 1024;
            $value = substr($value, 0, -2);
        } elseif (str_ends_with($upper, 'MB')) {
            $multiplier = 1024 * 1024;
            $value = substr($value, 0, -2);
        } elseif (str_ends_with($upper, 'KB')) {
            $multiplier = 1024;
            $value = substr($value, 0, -2);
        } elseif (str_ends_with($upper, 'B')) {
            $value = substr($value, 0, -1);
        }
        $n = (int) trim($value);
        return $n * $multiplier;
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return number_format($bytes / (1024 * 1024 * 1024), 2) . ' GB';
        }
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}
