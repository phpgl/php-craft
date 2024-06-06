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

    /**
     * Returns the visibility of a block at the given position
     */
    public function getVisibility(int $x, int $y, int $z): int
    {
        return $this->blockVisibility[$x + $y * self::CHUNK_SIZE + $z * self::CHUNK_SIZE ** 2];
    }

    /**
     * Sets the visibility of a block at the given position
     */
    public function setVisibilityAt(int $x, int $y, int $z, int $visibility): void
    {
        $this->blockVisibility[$x + $y * self::CHUNK_SIZE + $z * self::CHUNK_SIZE ** 2] = $visibility;
    }

    /**
     * Returns the block type at the given position
     */
    public function getBlockType(int $x, int $y, int $z): int
    {
        return $this->blockTypes[$x + $y * self::CHUNK_SIZE + $z * self::CHUNK_SIZE ** 2];
    }

    /**
     * Sets the block type at the given position
     */
    public function setBlockType(int $x, int $y, int $z, int $type): void
    {
        $this->blockTypes[$x + $y * self::CHUNK_SIZE + $z * self::CHUNK_SIZE ** 2] = $type;
    }

    /**
     * Checks if the given position collides with a visible block 
     * and find the closest non-colliding position
     */
    public function collide(Vec3 $position, Vec3 $size, Vec3 $velocity): Vec3
    {
        // Calculate the bounds of the object based on its current position and size
        $minX = floor($position->x);
        $minY = floor($position->y);
        $minZ = floor($position->z);
        $maxX = ceil($position->x + $size->x);
        $maxY = ceil($position->y + $size->y);
        $maxZ = ceil($position->z + $size->z);
    
        // Collision flags for each direction
        $collisionX = $collisionY = $collisionZ = false;
    
        // Adjust for chunk boundaries
        $minX = max($minX, 0);
        $minY = max($minY, 0);
        $minZ = max($minZ, 0);
        $maxX = min($maxX, self::CHUNK_SIZE);
        $maxY = min($maxY, self::CHUNK_SIZE);
        $maxZ = min($maxZ, self::CHUNK_SIZE);
    
        // Check each block in the bounding box for collisions
        for ($x = $minX; $x < $maxX; $x++) {
            for ($y = $minY; $y < $maxY; $y++) {
                for ($z = $minZ; $z < $maxZ; $z++) {
                    if ($this->getVisibility($x, $y, $z)) {
                        // Determine the collision direction and set flags
                        if ($velocity->x != 0) $collisionX = true;
                        if ($velocity->y != 0) $collisionY = true;
                        if ($velocity->z != 0) $collisionZ = true;
                    }
                }
            }
        }
    
        // Calculate the new position considering the detected collisions
        $newX = $collisionX ? $position->x : $position->x + $velocity->x;
        $newY = $collisionY ? $position->y : $position->y + $velocity->y;
        $newZ = $collisionZ ? $position->z : $position->z + $velocity->z;
    
        // Return the nearest non-colliding position by adjusting to the nearest block face if there's a collision
        if ($collisionX) $newX = $velocity->x > 0 ? floor($position->x) : ceil($position->x);
        if ($collisionY) $newY = $velocity->y > 0 ? floor($position->y) : ceil($position->y);
        if ($collisionZ) $newZ = $velocity->z > 0 ? floor($position->z) : ceil($position->z);
    
        return new Vec3($newX, $newY, $newZ);
    }
}
