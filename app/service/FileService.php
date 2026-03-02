<?php declare(strict_types=1);

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

        // 淇濈暀鍘熷鎵╁睍鍚嶏紝缂虹渷鐢?bin
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $ext = $ext !== '' ? strtolower($ext) : 'bin';

        // 鎸夋棩鏈熷垎鐩綍锛岄伩鍏嶅崟鐩綍杩囧鏂囦欢
        $folder = date('Ymd');
        $storageRoot = rtrim(app()->getRootPath() . 'public/storage', "/\\");
        $targetDir = $storageRoot . DIRECTORY_SEPARATOR . $folder;

        // 鎸夋棩鏈熷垎鐩綍锛屼究浜庣鐞?
        if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            throw new BusinessException(ResultCode::UPLOAD_FILE_EXCEPTION, '鍒涘缓涓婁紶鐩綍澶辫触');
        }

        // 闅忔満鏂囦欢鍚嶉伩鍏嶅啿绐?
        $fileName = bin2hex(random_bytes(16)) . '.' . $ext;
        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $fileName;

        $saved = false;
        if (is_object($file) && method_exists($file, 'move')) {
            try {
                // 浼樺厛璧版鏋剁殑 move
                $file->move($targetDir, $fileName);
                $saved = true;
            } catch (\Throwable) {
                $saved = false;
            }
        }

        if (!$saved) {
            // fallback 鍒扮郴缁熶复鏃舵枃浠惰矾寰?
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

        // 鍏煎浼犲叆瀹屾暣 URL
        if (str_starts_with($filePath, 'http://') || str_starts_with($filePath, 'https://')) {
            $parsed = parse_url($filePath);
            if (is_array($parsed) && isset($parsed['path'])) {
                $filePath = (string) $parsed['path'];
            }
        }

        // 缁熶竴瑁佸壀鎴?storage 涓嬬殑鐩稿璺緞
        $path = $filePath;
        $storagePrefix = '/storage/';
        if (str_starts_with($path, $storagePrefix)) {
            $path = substr($path, strlen($storagePrefix));
        }

        $path = ltrim($path, '/\\');
        // 閬垮厤璺緞绌胯秺
        if ($path === '' || str_contains($path, '..')) {
            return false;
        }

        // 缁熶竴瀹氫綅鍒?storage 鐩綍
        $storageRoot = rtrim(app()->getRootPath() . 'public/storage', "/\\");
        $fullPath = $storageRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        if (!is_file($fullPath)) {
            return false;
        }

        return @unlink($fullPath);
    }
}
