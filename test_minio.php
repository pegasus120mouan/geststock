<?php
require 'vendor/autoload.php';
use Aws\S3\S3Client;

$s3 = new S3Client([
    'version' => 'latest',
    'region' => 'us-east-1',
    'endpoint' => 'http://51.178.49.141:9000',
    'use_path_style_endpoint' => true,
    'credentials' => [
        'key' => 'minioadmin',
        'secret' => 'Azerty@@2020',
    ],
]);

try {
    echo "Downloading image 'exploitant_temp_1770929377_0e44e9fa.jpg':\n";
    $result = $s3->getObject([
        'Bucket' => 'planteurs',
        'Key' => 'exploitant_temp_1770929377_0e44e9fa.jpg',
    ]);
    $body = $result['Body']->getContents();
    echo "Content-Type: " . $result['ContentType'] . "\n";
    echo "Size: " . strlen($body) . " bytes\n";
    
    // Sauvegarder l'image pour vérifier
    file_put_contents('public/test_image.jpg', $body);
    echo "Image saved to public/test_image.jpg\n";
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
