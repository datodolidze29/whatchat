<?php

namespace App;

class FileStorage
{
    private string $uploadDir;
    private int $maxBytes;

    public function __construct(string $uploadDir, int $maxBytes = 5_000_000)
    {
        $this->uploadDir = $uploadDir;
        $this->maxBytes = $maxBytes;
    }

    // takes one entry from $_FILES; returns the stored relative path, or throws on bad input
    public function storeImage(array $file): string
    {
        // 1. upload must have succeeded
        if ($file["error"] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException("upload failed");
        }
        // 2. size limit
        if ($file["size"] > $this->maxBytes) {
            throw new \RuntimeException("file too large");
        }
        // 3. verify it's a REAL image by reading the bytes, and map mime -> our extension (whitelist)
        $info = getimagesize($file["tmp_name"]);
        if ($info === false) {
            throw new \RuntimeException("not an image");
        }
        $allowed = [
            "image/jpeg" => "jpg",
            "image/png" => "png",
            "image/gif" => "gif",
        ];
        $mime = $info["mime"];
        if (!isset($allowed[$mime])) {
            throw new \RuntimeException("unsupported image type");
        }
        $ext = $allowed[$mime]; // WE decide the extension, from the real mime

        // 4. generate our own random filename (never trust the client's)
        $filename = bin2hex(random_bytes(16)) . "." . $ext;

        // 5. move it into the uploads dir
        if (!is_dir($this->uploadDir) && !mkdir($this->uploadDir, 0755, true)) {
            throw new \RuntimeException("upload directory unavailable");
        }
        if (!is_writable($this->uploadDir)) {
            throw new \RuntimeException("upload directory not writable");
        }

        $dest = $this->uploadDir . "/" . $filename;
        if (!move_uploaded_file($file["tmp_name"], $dest)) {
            throw new \RuntimeException("could not save file");
        }

        // 6. return the relative path we store in the DB
        return "uploads/" . $filename;
    }
}
