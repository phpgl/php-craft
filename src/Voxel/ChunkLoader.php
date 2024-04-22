<?php

namespace App\Voxel;

use VISU\OS\Logger;

class ChunkLoader
{  
    /**
     * Constructor
     */
    public function __construct()
    {
        // ensure the level directory exists
        if (!is_dir(PHPCRAFT_LEVELS_PATH)) {
            mkdir(PHPCRAFT_LEVELS_PATH);
        }
    }

   /**
    * Generates procedural chunk data for a given chunk.
    */
    private function generateProcerduralChunkData(Chunk $chunk) 
    {
        // use a simple noise function to generate some terrain
        for ($x = 0; $x < Chunk::CHUNK_SIZE; $x++) {
            $absoluteX = $chunk->x * Chunk::CHUNK_SIZE + $x;

            for ($y = 0; $y < Chunk::CHUNK_SIZE; $y++) {
                $absoluteY = $chunk->y * Chunk::CHUNK_SIZE + $y;

                for ($z = 0; $z < Chunk::CHUNK_SIZE; $z++) {
                    $absoluteZ = $chunk->z * Chunk::CHUNK_SIZE + $z;

                    $chunk->blockTypes[$x + $y * Chunk::CHUNK_SIZE + $z * Chunk::CHUNK_SIZE ** 2] = (int) mt_rand(1, 3);

                    $height = \GL\Noise::fbm($absoluteX * 0.01 + 0.5, $absoluteZ * 0.01 + 0.5, 0.0);
                    $heightAbsolute = $height * 16 * 2;

                    $chunk->blockVisibility[$x + $y * Chunk::CHUNK_SIZE + $z * Chunk::CHUNK_SIZE ** 2] = $absoluteY < $heightAbsolute ? 1 : 0;

                    // add some caves by making some blocks invisible underground
                    $v = \GL\Noise::perlin($absoluteX * 0.05, $absoluteY * 0.05, $absoluteZ * 0.05);
                    if ($v > 0.3) {
                        $chunk->blockVisibility[$x + $y * Chunk::CHUNK_SIZE + $z * Chunk::CHUNK_SIZE ** 2] = 0;
                    }
                }
            }
        }

        Logger::info("Generated procedural chunk at {$chunk->x}, {$chunk->y}, {$chunk->z}");
        $this->persistChunkData($chunk);
    }

    private function getChunkSavePath(Chunk $chunk) : string
    {
        return sprintf('%s/%d_%d_%d.chunk', PHPCRAFT_LEVELS_PATH, $chunk->x, $chunk->y, $chunk->z);
    }
    /**
     * Loads chunk data from a file if it exists, otherwise generates procedural chunk data.
     */
    public function loadChunkData(Chunk $chunk) 
    {
        $path = $this->getChunkSavePath($chunk);

        // if a chunk file exists, load it
        if (file_exists($path)) {
            $chunkData = unserialize(file_get_contents($path));

            $chunk->blockTypes = $chunkData[0];
            $chunk->blockVisibility = $chunkData[1];

            Logger::info("Loaded chunk at {$chunk->x}, {$chunk->y}, {$chunk->z}");
        }
        // otherwise generate some procedural chunk
        else {
            $this->generateProcerduralChunkData($chunk);
        }
    }

    /**
     * Persists chunk data to a file.
     */
    public function persistChunkData(Chunk $chunk) 
    {
        file_put_contents($this->getChunkSavePath($chunk), serialize([$chunk->blockTypes, $chunk->blockVisibility]));

        Logger::info("Persisted chunk at {$chunk->x}, {$chunk->y}, {$chunk->z}");
    }
}