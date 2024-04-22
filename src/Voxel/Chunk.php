<?php

namespace App\Voxel;

use GL\Buffer\ByteBuffer;
use GL\Math\Vec3;
use VISU\Geo\AABB;

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

        $this->blockTypes = new ByteBuffer();
        $this->blockVisibility = new ByteBuffer();
        
        $this->blockTypes->fill(self::CHUNK_SIZE ** 3, 0);
        $this->blockVisibility->fill(self::CHUNK_SIZE ** 3, 0);
    }

    /**
     * Makes all blocks in the chunk visible or invisible
     */
    public function setVisibility(bool $visible): void
    {
        $this->blockVisibility->fill(self::CHUNK_SIZE ** 3, $visible ? 1 : 0);
    }
}
