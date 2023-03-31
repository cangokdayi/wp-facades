<?php

namespace Cangokdayi\WPFacades\Traits;

trait HandlesFiles
{
    /**
     * Returns a list of files from the given path
     */
    public function getFilesFromDir(string $directoryPath): array
    {
        return array_map(
            fn ($file) => "$directoryPath/$file",
            array_diff(scandir($directoryPath), ["..", "."])
        );
    }

    /**
     * Sorts the given files based on their last modified time attr
     * 
     * @param string[] $files
     */
    public function sortFilesByDate(array $files): array
    {
        usort($files, function ($x, $y) {
            $xtime = filemtime($x);
            $ytime = filemtime($y);

            $lowerOrEqual = $ytime > $xtime ? -1 : 0;

            return $xtime > $ytime ? 1 : $lowerOrEqual;
        });

        return $files;
    }

    /**
     * Returns the filename from the given file path
     * 
     * @param boolean $trimExtension Whether to remove the file extension or not
     */
    public function getFileName(string $path, bool $trimExt = true): string
    {
        $fileExt = pathinfo($path)['extension'];

        return basename(
            $path,
            $trimExt ? ".{$fileExt}" : ''
        );
    }
}
