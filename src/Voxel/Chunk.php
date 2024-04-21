<?php

namespace App\Voxel;

use GL\Buffer\ByteBuffer;
use GL\Math\Vec3;
use VISU\Geo\AABB;
use VISU\OS\Logger;

class Chunk
{
    public const CHUNK_SIZE = 16;

    public ByteBuffer $blockTypes;
    public ByteBuffer $blockVisibility;

    const BLOCK_TYPE_AIR = 0;
    const BLOCK_TYPE_DIRT = 1;
    const BLOCK_TYPE_GRASS = 2;
    const BLOCK_TYPE_TREE = 3;

    /**
     * The bounding box of the chunk
     */
    public readonly AABB $aabb;

    /**
     * Block textures foreach face of each block type
     * @var array
     */
    public array $blockTextures = [
        self::BLOCK_TYPE_AIR => [0, 0, 0, 0, 0, 0],
        self::BLOCK_TYPE_DIRT => [2, 2, 2, 2, 2, 2],
        self::BLOCK_TYPE_GRASS => [1, 1, 1, 1, 0, 2],
        self::BLOCK_TYPE_TREE => [4, 4, 4, 4, 4, 4],
    ];

    public function __construct(
        public readonly int $x,
        public readonly int $y,
        public readonly int $z,
    )
    {
        // build the bounding box of the chunk
        $this->aabb = new AABB(
            new Vec3(
                $this->x * self::CHUNK_SIZE, 
                $this->y * self::CHUNK_SIZE, 
                $this->z * self::CHUNK_SIZE
            ),
            new Vec3(
                $this->x * self::CHUNK_SIZE + self::CHUNK_SIZE, 
                $this->y * self::CHUNK_SIZE + self::CHUNK_SIZE, 
                $this->z * self::CHUNK_SIZE + self::CHUNK_SIZE
            )
        );
        // convert all the block textures to floats
        // because we will store them in a float buffer 
        // and we don't want to convert them every time we render
        foreach ($this->blockTextures as $blockType => $textures) {
            foreach ($textures as $face => $texture) {
                $this->blockTextures[$blockType][$face] = (float) $texture;
            }
        }

        $this->blockTypes = new ByteBuffer();
        $this->blockVisibility = new ByteBuffer();
        
        $this->blockTypes->fill(self::CHUNK_SIZE ** 3, 0);
        $this->blockVisibility->fill(self::CHUNK_SIZE ** 3, 0);

        $levelPath = PHPCRAFT_LEVELS_PATH;
        $levelKey = "{$this->x}_{$this->y}_{$this->z}";
        if (file_exists("{$levelPath}/{$levelKey}.chunk")) {
            $chunkData = unserialize(file_get_contents("{$levelPath}/{$levelKey}.chunk"));

            $this->blockTypes = $chunkData[0];
            $this->blockVisibility = $chunkData[1];

            Logger::info("Loaded chunk at {$this->x}, {$this->y}, {$this->z}");
            return;
        }

        // use a simple noise function to generate some terrain
        for ($x = 0; $x < self::CHUNK_SIZE; $x++) {
            $absoluteX = $this->x * self::CHUNK_SIZE + $x;

            for ($y = 0; $y < self::CHUNK_SIZE; $y++) {
                $absoluteY = $this->y * self::CHUNK_SIZE + $y;

                for ($z = 0; $z < self::CHUNK_SIZE; $z++) {
                    $absoluteZ = $this->z * self::CHUNK_SIZE + $z;

                    $this->blockTypes[$x + $y * self::CHUNK_SIZE + $z * self::CHUNK_SIZE ** 2] = (int) mt_rand(1, 3);

                    $height = \GL\Noise::fbm($absoluteX * 0.01 + 0.5, $absoluteZ * 0.01 + 0.5, 0.0);
                    $heightAbsolute = $height * 16 * 2;

                    $this->blockVisibility[$x + $y * self::CHUNK_SIZE + $z * self::CHUNK_SIZE ** 2] = $absoluteY < $heightAbsolute ? 1 : 0;
                }
            }
        }

        file_put_contents("{$levelPath}/{$levelKey}.chunk", serialize([$this->blockTypes, $this->blockVisibility]));
        Logger::info("Generated chunk at {$this->x}, {$this->y}, {$this->z}");
    }

    /**
     * Makes all blocks in the chunk visible or invisible
     */
    public function setVisibility(bool $visible): void
    {
        $this->blockVisibility->fill(self::CHUNK_SIZE ** 3, $visible ? 1 : 0);
    }
}
