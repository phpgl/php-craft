<?php

namespace App\Voxel;

use VISU\Graphics\BasicVertexArray;

class ChunkRenderData
{
    public bool $hasNoVisibleBlocks = true;

    public ?BasicVertexArray $vao = null;
}