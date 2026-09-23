<?php

namespace App\Services;

use RuntimeException;

class FileUploadService
{
    private array $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
    ];

    private array $allowedExtensions = [
        'jpg',
        'jpeg',
        'png',
        'webp',
        'pdf',
    ];

    private int $maxBytes = 10 * 1024 * 1024; // 10 MB

    public function storeUploadedFile(array $file, string $targetDirectory): array
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new RuntimeException('Invalid file upload.');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('File upload failed.');
        }

        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Invalid uploaded file source.');
        }

        if (($file['size'] ?? 0) <= 0) {
            throw new RuntimeException('Uploaded file is empty.');
        }

        if (($file['size'] ?? 0) > $this->maxBytes) {
            throw new RuntimeException('Uploaded file exceeds the 10 MB limit.');
        }

        $originalName = (string) ($file['name'] ?? 'file');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, $this->allowedExtensions, true)) {
            throw new RuntimeException('File type is not allowed.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = (string) $finfo->file($file['tmp_name']);

        if (!in_array($mimeType, $this->allowedMimeTypes, true)) {
            throw new RuntimeException('Detected file MIME type is not allowed.');
        }

        if (!is_dir($targetDirectory)) {
            if (!mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
                throw new RuntimeException('Failed to create upload directory.');
            }
        }

        $storedFileName = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $fullPath = rtrim($targetDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $storedFileName;

        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            throw new RuntimeException('Failed to move uploaded file.');
        }

        return [
            'original_file_name' => $originalName,
            'stored_file_name' => $storedFileName,
            'file_path' => $fullPath,
            'mime_type' => $mimeType,
            'file_size' => (int) $file['size'],
        ];
    }
}