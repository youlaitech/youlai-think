<?php

declare(strict_types=1);

namespace app\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;

final class FileService
{
    public function uploadFile(mixed $file, mixed $request = null): array
    {
        $originalName = null;
        if (is_object($file)) {
            if (method_exists($file, 'getOriginalName')) {
                $originalName = (string) $file->getOriginalName();
            } elseif (method_exists($file, 'getClientOriginalName')) {
                $originalName = (string) $file->getClientOriginalName();
            }
        }

        if ($originalName === null || $originalName === '') {
            $originalName = 'file';
        }

        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $ext = $ext !== '' ? strtolower($ext) : 'bin';

        $folder = date('Ymd');
        $storageRoot = rtrim(app()->getRootPath() . 'public/storage', "/\\");
        $targetDir = $storageRoot . DIRECTORY_SEPARATOR . $folder;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            throw new BusinessException(ResultCode::UPLOAD_FILE_EXCEPTION, '创建上传目录失败');
        }

        $fileName = bin2hex(random_bytes(16)) . '.' . $ext;
        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $fileName;

        $saved = false;
        if (is_object($file) && method_exists($file, 'move')) {
            try {
                $file->move($targetDir, $fileName);
                $saved = true;
            } catch (\Throwable) {
                $saved = false;
            }
        }

        if (!$saved) {
            $tmpPath = null;
            if (is_object($file) && method_exists($file, 'getPathname')) {
                $tmpPath = (string) $file->getPathname();
            }

            if ($tmpPath === null || $tmpPath === '' || !is_file($tmpPath)) {
                throw new BusinessException(ResultCode::UPLOAD_FILE_EXCEPTION);
            }

            if (!@copy($tmpPath, $targetPath)) {
                throw new BusinessException(ResultCode::UPLOAD_FILE_EXCEPTION);
            }
        }

        $url = '/storage/' . $folder . '/' . $fileName;

        return [
            'name' => $originalName,
            'url' => $url,
        ];
    }

    public function deleteFile(string $filePath): bool
    {
        $filePath = trim($filePath);
        if ($filePath === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
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

        $storageRoot = rtrim(app()->getRootPath() . 'public/storage', "/\\");
        $fullPath = $storageRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        if (!is_file($fullPath)) {
            return false;
        }

        return @unlink($fullPath);
    }
}
