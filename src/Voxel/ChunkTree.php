<?php

namespace App\Voxel;

/**
 * A simple octree that holds chunk handles aka strings (x:y:z)
 * Аt the end every node will have only one chunk
 */
class ChunkTree
{
    /**
     * The root node of the tree
     */
    private ?ChunkTreeNode $root = null;
}