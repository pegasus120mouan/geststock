<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Aws\S3\S3Client;

class MinioProxyController extends Controller
{
    /**
     * Proxy pour récupérer les images depuis MinIO (bucket planteurs)
     */
    public function planteurImage(string $filename)
    {
        // Vérifier si l'image est en cache local
        $cachePath = storage_path('app/planteurs/' . $filename);
        
        if (file_exists($cachePath)) {
            return response(file_get_contents($cachePath))
                ->header('Content-Type', 'image/jpeg')
                ->header('Cache-Control', 'public, max-age=86400');
        }

        try {
            // Créer un client S3 pour MinIO
            $s3Client = new S3Client([
                'version' => 'latest',
                'region' => 'us-east-1',
                'endpoint' => 'http://51.178.49.141:9000',
                'use_path_style_endpoint' => true,
                'credentials' => [
                    'key' => 'minioadmin',
                    'secret' => 'Azerty@@2020',
                ],
            ]);

            // Récupérer l'objet depuis MinIO
            $result = $s3Client->getObject([
                'Bucket' => 'planteurs',
                'Key' => $filename,
            ]);

            $body = $result['Body']->getContents();
            $contentType = $result['ContentType'] ?? 'image/jpeg';

            // Sauvegarder en cache local
            $cacheDir = storage_path('app/planteurs');
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }
            file_put_contents($cachePath, $body);

            return response($body)
                ->header('Content-Type', $contentType)
                ->header('Cache-Control', 'public, max-age=86400');

        } catch (\Aws\S3\Exception\S3Exception $e) {
            \Log::warning("MinIO S3 error for {$filename}: " . $e->getMessage());
            return $this->defaultImage();
        } catch (\Throwable $e) {
            \Log::error("MinIO proxy error for {$filename}: " . $e->getMessage());
            return $this->defaultImage();
        }
    }

    /**
     * Retourne l'image par défaut
     */
    private function defaultImage()
    {
        $defaultPath = public_path('img/avatars/default.png');
        
        if (file_exists($defaultPath)) {
            return response(file_get_contents($defaultPath))
                ->header('Content-Type', 'image/png')
                ->header('Cache-Control', 'public, max-age=86400');
        }

        // Image placeholder 1x1 transparent
        $placeholder = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        return response($placeholder)
            ->header('Content-Type', 'image/png');
    }
}
