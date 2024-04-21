<?php

namespace App\Voxel;

use VISU\Geo\AABB;

/**
 * A simple octree that holds chunk handles aka strings (x:y:z)
 */
class ChunkTreeNode
{
    public AABB $aabb;

    public string $handle;
}