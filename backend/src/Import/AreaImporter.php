<?php

declare(strict_types=1);

namespace App\Import;

use App\Repository\CadastralAreaRepository;
use App\Repository\StagingRepository;
use Symfony\Component\Filesystem\Filesystem;

final readonly class AreaImporter
{
    public function __construct(
        private CpxDownloader $downloader,
        private Ogr2OgrLoader $loader,
        private CadastralAreaRepository $areas,
        private StagingRepository $staging,
        private Filesystem $filesystem,
    ) {
    }

    public function import(int $code): int
    {
        $this->areas->markImporting($code);

        $directory = sprintf('%s/replot-import/%d', sys_get_temp_dir(), $code);
        $this->filesystem->remove($directory);
        $this->filesystem->mkdir($directory);

        try {
            $gml = $this->downloader->download($code, $directory);
            $this->loader->load($gml, 'CadastralParcel', StagingRepository::PARCEL_TABLE);
            $this->loader->load($gml, 'CadastralZoning', StagingRepository::ZONING_TABLE);

            return $this->staging->replaceArea($code);
        } finally {
            $this->filesystem->remove($directory);
        }
    }
}
