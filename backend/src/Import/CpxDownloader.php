<?php

declare(strict_types=1);

namespace App\Import;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class CpxDownloader
{
    private const string URL = 'https://services.cuzk.gov.cz/gml/inspire/cpx/epsg-5514/%d.zip';

    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    public function download(int $cadastralAreaCode, string $directory): string
    {
        $zipPath = sprintf('%s/%d.zip', $directory, $cadastralAreaCode);
        $file = fopen($zipPath, 'wb');
        if ($file === false) {
            throw new \RuntimeException(sprintf('Cannot write %s.', $zipPath));
        }

        try {
            $response = $this->httpClient->request('GET', sprintf(self::URL, $cadastralAreaCode), ['timeout' => 120]);
            foreach ($this->httpClient->stream($response) as $chunk) {
                fwrite($file, $chunk->getContent());
            }
        } finally {
            fclose($file);
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException(sprintf('Downloaded file for %d is not a valid ZIP.', $cadastralAreaCode));
        }
        $zip->extractTo($directory);
        $zip->close();
        unlink($zipPath);

        $xml = glob($directory.'/*.xml') ?: [];
        if (\count($xml) !== 1) {
            throw new \RuntimeException(sprintf('Expected one XML file in the archive for %d, found %d.', $cadastralAreaCode, \count($xml)));
        }

        return $xml[0];
    }
}
