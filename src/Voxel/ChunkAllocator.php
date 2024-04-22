<?php

namespace App\Voxel;

use GL\Buffer\ByteBuffer;
use GL\Buffer\FloatBuffer;
use VISU\Geo\AABB;
use VISU\Graphics\BasicVertexArray;
use VISU\Graphics\GLState;

class ChunkAllocator
{
    /**
     * Chunk loader instance.
     */
    private ChunkLoader $chunkLoader;

    /**
     * An array of chunks.
     * 
     * @var array<string, Chunk>
     */
    private array $chunks = [];

    /**
     * An array of chunk render data.
     * 
     * @var array<string, ChunkRenderData>
     */
    private array $chunkRenderData = [];

    /**
     * An array of chunk keys currently in render distance.
     * 
     * @var array<string>
     */
    private array $chunksInRenderDistance = [];
    
    /**
     * Chunk render distance.
     */
    public int $renderDistance = 4;

    /**
     * Max height of the world.
     */
    private int $maxHeight = 128;

    /**
     * Min height of the world.
     */
    private int $minHeight = -128;

    /**
     * Empty chunk fallback.
     */
    private Chunk $emptyChunk;

    /**
     * Block textures foreach face of each block type
     * @var array
     */
    public array $blockTextures = [
        Chunk::BLOCK_TYPE_AIR => [0, 0, 0, 0, 0, 0],
        Chunk::BLOCK_TYPE_DIRT => [2, 2, 2, 2, 2, 2],
        Chunk::BLOCK_TYPE_GRASS => [1, 1, 1, 1, 0, 2],
        Chunk::BLOCK_TYPE_TREE => [4, 4, 4, 4, 4, 4],
    ];

    /**
     * Constructor
     */
    public function __construct(
        private GLState $gl,
    )
    {
        // create the chunk loader
        $this->chunkLoader = new ChunkLoader();

        // allocate empty chunk
        $this->emptyChunk = new Chunk(0, 0, 0);
        $this->emptyChunk->setVisibility(false);

        // convert all the block textures to floats
        // because we will store them in a float buffer 
        // and we don't want to convert them every time we render
        foreach ($this->blockTextures as $blockType => $textures) {
            foreach ($textures as $face => $texture) {
                $this->blockTextures[$blockType][$face] = (float) $texture;
            }
        }
    }

    /**
     * Returns the count of loaded chunks.
     */
    public function getChunkCount(): int
    {
        return count($this->chunks);
    }

    /**
     * Returns all loaded chunks.
     * 
     * @return array<Chunk>
     */
    public function getChunks(): array
    {
        return $this->chunks;
    }

    /**
     * Returns the chunk with the given key.
     */
    public function getChunk(string $key): ?Chunk
    {
        return $this->chunks[$key] ?? null;
    }

    /** 
     * Retuns all loaded chunk vertex arrays.
     * 
     * @return array<string, ChunkRenderData>
     */
    public function getToBeRenderedChunks(): array
    {
        $renderDataList = [];

        foreach ($this->chunksInRenderDistance as $key) {
            if (isset($this->chunkRenderData[$key])) {
                $renderDataList[$key] = $this->chunkRenderData[$key];
            }
        }

        return $renderDataList;
    }

    /**
     * Returns the string key of the chunk at the given position in world space.
     * 
     * @param float $x
     * @param float $y
     * @param float $z
     * @return string 
     */
    public function getChunkKeyAt(float $x, float $y, float $z): string
    {
        $chunkX = (int) floor($x / Chunk::CHUNK_SIZE);
        $chunkY = (int) floor($y / Chunk::CHUNK_SIZE);
        $chunkZ = (int) floor($z / Chunk::CHUNK_SIZE);

        return "{$chunkX}:{$chunkY}:{$chunkZ}";
    }

    /**
     * Load the chunk with the given key
     */
    public function loadChunk(string $key): Chunk
    {
        if (isset($this->chunks[$key])) {
            return $this->chunks[$key];
        }

        // create a new chunk
        $this->chunks[$key] = new Chunk(...explode(':', $key));
        $chunk = $this->chunks[$key];

        // create render data for the chunk
        $renderData = new ChunkRenderData();
        $this->chunkRenderData[$key] = $renderData;

        // load the chunk data
        $this->chunkLoader->loadChunkData($chunk);

        return $chunk;
    }

    /**
     * Unload the chunk with the given key
     */
    public function unloadChunk(string $key): void
    {
        unset($this->chunks[$key]);
        unset($this->chunkRenderData[$key]);
    }


    /**
     * Ensure chunks around the given position are loaded.
     */
    public function ensureChunksLoaded(float $x, float $y, float $z, int $chunkLoadingBudget = 2): void
    {
        $chunkX = (int) floor($x / Chunk::CHUNK_SIZE);
        $chunkY = (int) floor($y / Chunk::CHUNK_SIZE);
        $chunkZ = (int) floor($z / Chunk::CHUNK_SIZE);

        $this->chunksInRenderDistance = [];

        for ($x = $chunkX - $this->renderDistance; $x <= $chunkX + $this->renderDistance; $x++) {
            for ($y = $chunkY - $this->renderDistance; $y <= $chunkY + $this->renderDistance; $y++) {
                for ($z = $chunkZ - $this->renderDistance; $z <= $chunkZ + $this->renderDistance; $z++) {
                    if ($y < $this->minHeight / Chunk::CHUNK_SIZE || $y > $this->maxHeight / Chunk::CHUNK_SIZE) {
                        continue;
                    }

                    $chunkKey = "{$x}:{$y}:{$z}";

                    if (!isset($this->chunks[$chunkKey])) {
                        if ($chunkLoadingBudget-- <= 0) {
                            continue;
                        }
                    }

                    $this->loadChunk($chunkKey);
                    $this->chunksInRenderDistance[] = $chunkKey;
                }
            }
        }

        // ensure VAO geometry is up to date for all chunks in render distance
        foreach($this->chunksInRenderDistance as $key) {
            $rd = $this->chunkRenderData[$key];

            if ($rd->vao === null) {
                $this->fillVAOWithGeometry($this->chunks[$key], $rd);
            }
        }

        // // unload all chunks outside of the render distance
        // foreach ($this->chunks as $key => $chunk) {
        //     if (!isset($shouldBeRendered[$key])) {
        //         $this->unloadChunk($key);
        //     }
        // }
  
        // // for the optimizer to work properly 
        // // we need to remove all neighboring chunks of chunks that are being 
        // // loaded aka not having a vertex array yet
        // foreach($this->chunks as $key => $chunk) {
        //     if ($this->chunkRenderData[$key]->vao === null) {
        //         // deloc the neighboring chunks by setting them to null
        //         if (isset($this->chunkRenderData[($chunk->x - 1) . ':' . $chunk->y . ':' . $chunk->z]->vao)) {
        //             $this->chunkRenderData[($chunk->x - 1) . ':' . $chunk->y . ':' . $chunk->z]->vao = null;
        //         }
        //         if (isset($this->chunkRenderData[($chunk->x + 1) . ':' . $chunk->y . ':' . $chunk->z]->vao)) {
        //             $this->chunkRenderData[($chunk->x + 1) . ':' . $chunk->y . ':' . $chunk->z]->vao = null;
        //         }
        //         if (isset($this->chunkRenderData[$chunk->x . ':' . ($chunk->y + 1) . ':' . $chunk->z]->vao)) {
        //             $this->chunkRenderData[$chunk->x . ':' . ($chunk->y + 1) . ':' . $chunk->z]->vao = null;
        //         }
        //         if (isset($this->chunkRenderData[$chunk->x . ':' . ($chunk->y - 1) . ':' . $chunk->z]->vao)) {
        //             $this->chunkRenderData[$chunk->x . ':' . ($chunk->y - 1) . ':' . $chunk->z]->vao = null;
        //         }
        //         if (isset($this->chunkRenderData[$chunk->x . ':' . $chunk->y . ':' . ($chunk->z + 1)]->vao)) {
        //             $this->chunkRenderData[$chunk->x . ':' . $chunk->y . ':' . ($chunk->z + 1)]->vao = null;
        //         }
        //         if (isset($this->chunkRenderData[$chunk->x . ':' . $chunk->y . ':' . ($chunk->z - 1)]->vao)) {
        //             $this->chunkRenderData[$chunk->x . ':' . $chunk->y . ':' . ($chunk->z - 1)]->vao = null;
        //         }
        //     }
        // }
    }

    /**
     * Use an octree to find all chunks intersecting with the given AABB
     * 
     * @param AABB $aabb 
     * @return array 
     */
    public function findIntersectingChunks(AABB $aabb) : array 
    {
        $intersectingChunks = [];

        foreach($this->chunks as $key => $chunk) {
            if ($chunk->aabb->intersects($aabb)) {
                $intersectingChunks[] = $chunk;
            }
        }

        return $intersectingChunks; 
    }

    public function fillVAOWithGeometry(Chunk $chunk, ChunkRenderData $rd): void
    {
        $floatBuffer = new FloatBuffer();

        // load the neighboring chunks 
        $leftChunk = $this->chunks[($chunk->x - 1) . ':' . $chunk->y . ':' . $chunk->z] ?? null;
        $rightChunk = $this->chunks[($chunk->x + 1) . ':' . $chunk->y . ':' . $chunk->z] ?? null;
        $upChunk = $this->chunks[$chunk->x . ':' . ($chunk->y + 1) . ':' . $chunk->z] ?? null;
        $downChunk = $this->chunks[$chunk->x . ':' . ($chunk->y - 1) . ':' . $chunk->z] ?? null;
        $frontChunk = $this->chunks[$chunk->x . ':' . $chunk->y . ':' . ($chunk->z + 1)] ?? null;
        $backChunk = $this->chunks[$chunk->x . ':' . $chunk->y . ':' . ($chunk->z - 1)] ?? null;

        // only fill chunks that have all neighbors loaded
        if ($leftChunk === null || $rightChunk === null || $upChunk === null || $downChunk === null || $frontChunk === null || $backChunk === null) {
            return;
        }

        // create the VAO if it doesn't exist yet
        if ($rd->vao === null) {
            // initalize a VAO for the chunk
            $rd->vao = new BasicVertexArray($this->gl, [3, 3, 2, 1]);
        }

        // replace null chunks with our empty chunk fallback
        $leftChunk ??= $this->emptyChunk;
        $rightChunk ??= $this->emptyChunk;
        $upChunk ??= $this->emptyChunk;
        $downChunk ??= $this->emptyChunk;
        $frontChunk ??= $this->emptyChunk;
        $backChunk ??= $this->emptyChunk;


        // Unfolded cube
        //         *---*       F = Front
        //         | U |       B = Back
        // *---*---*---*---*   L = Left
        // | F | L | B | R |   R = Right
        // *---*---*---*---*   U = Up
        //         | D |       D = Down
        //         *---*        

        // our coordinate system is 
        // +x = right     -x = left
        // +y = up        -y = down
        // +z = front     -z = back

        // vertex layout 
        // ->  (x, y, z, nx, ny, nz, u, v)
        // aka (vec3<position>, vec3<normal>, vec2<uv>)
        // 
        // This could be optimized a lot, for once there is no real need to store the full float normals
        // we could just store some kind of index and then calculate the normals in the shader.
        // also we don't have to store the per chunk positions as floats as technically the values 
        // can never be larger than 16, so we could just store them in 3 bytes.
        // but for the sake of clarity we will just store the full normals here.

        $blockType = 0;
        $blockVisibility = 0;
        $blockVisibilityFront = 0;
        $blockVisibilityBack = 0;
        $blockVisibilityLeft = 0;
        $blockVisibilityRight = 0;
        $blockVisibilityUp = 0;
        $blockVisibilityDown = 0;
        $frontFaceTexture = 0.0;
        $backFaceTexture = 0.0;
        $leftFaceTexture = 0.0;
        $rightFaceTexture = 0.0;
        $upFaceTexture = 0.0;
        $downFaceTexture = 0.0;

        for ($x = 0; $x < Chunk::CHUNK_SIZE; $x++) {
            for ($y = 0; $y < Chunk::CHUNK_SIZE; $y++) {
                for ($z = 0; $z < Chunk::CHUNK_SIZE; $z++) {
                    $blockType = (float) $chunk->blockTypes[$x + $y * Chunk::CHUNK_SIZE + $z * Chunk::CHUNK_SIZE ** 2];
                    $blockVisibility = $chunk->blockVisibility[$x + $y * Chunk::CHUNK_SIZE + $z * Chunk::CHUNK_SIZE ** 2];

                    $blockVisibilityFront = $chunk->blockVisibility[$x + $y * Chunk::CHUNK_SIZE + ($z + 1) * Chunk::CHUNK_SIZE ** 2];
                    $blockVisibilityBack = $chunk->blockVisibility[$x + $y * Chunk::CHUNK_SIZE + ($z - 1) * Chunk::CHUNK_SIZE ** 2];
                    $blockVisibilityLeft = $chunk->blockVisibility[($x - 1) + $y * Chunk::CHUNK_SIZE + $z * Chunk::CHUNK_SIZE ** 2];
                    $blockVisibilityRight = $chunk->blockVisibility[($x + 1) + $y * Chunk::CHUNK_SIZE + $z * Chunk::CHUNK_SIZE ** 2];
                    $blockVisibilityUp = $chunk->blockVisibility[$x + ($y + 1) * Chunk::CHUNK_SIZE + $z * Chunk::CHUNK_SIZE ** 2];
                    $blockVisibilityDown = $chunk->blockVisibility[$x + ($y - 1) * Chunk::CHUNK_SIZE + $z * Chunk::CHUNK_SIZE ** 2];

                    $frontFaceTexture = $this->blockTextures[$blockType][0];
                    $backFaceTexture = $this->blockTextures[$blockType][1];
                    $leftFaceTexture = $this->blockTextures[$blockType][2];
                    $rightFaceTexture = $this->blockTextures[$blockType][3];
                    $upFaceTexture = $this->blockTextures[$blockType][4];
                    $downFaceTexture = $this->blockTextures[$blockType][5];


                    // special cases for edges
                    // here we check the neighboring chunks to determine if we need to render the edge
                    if ($x === 0) {
                        $blockVisibilityLeft = $leftChunk->blockVisibility[Chunk::CHUNK_SIZE - 1 + $y * Chunk::CHUNK_SIZE + $z * Chunk::CHUNK_SIZE ** 2];
                    } else if ($x === Chunk::CHUNK_SIZE - 1) {
                        $blockVisibilityRight = $rightChunk->blockVisibility[0 + $y * Chunk::CHUNK_SIZE + $z * Chunk::CHUNK_SIZE ** 2];
                    }

                    if ($y === 0) {
                        $blockVisibilityDown = $downChunk->blockVisibility[$x + (Chunk::CHUNK_SIZE - 1) * Chunk::CHUNK_SIZE + $z * Chunk::CHUNK_SIZE ** 2];
                    } else if ($y === Chunk::CHUNK_SIZE - 1) {
                        $blockVisibilityUp = $upChunk->blockVisibility[$x + 0 * Chunk::CHUNK_SIZE + $z * Chunk::CHUNK_SIZE ** 2];
                    }

                    if ($z === 0) {
                        $blockVisibilityBack = $backChunk->blockVisibility[$x + $y * Chunk::CHUNK_SIZE + (Chunk::CHUNK_SIZE - 1) * Chunk::CHUNK_SIZE ** 2];
                    } else if ($z === Chunk::CHUNK_SIZE - 1) {
                        $blockVisibilityFront = $frontChunk->blockVisibility[$x + $y * Chunk::CHUNK_SIZE + 0 * Chunk::CHUNK_SIZE ** 2];
                    }

                    if ($blockVisibility === 0) {
                        continue;
                    }

                    $blockX = $x;
                    $blockY = $y;
                    $blockZ = $z;

                    // front face (z+) 2 triangles
                    if ($blockVisibilityFront !== 1) {
                        $floatBuffer->pushArray([
                            $blockX + 1.0, $blockY + 0.0, $blockZ + 1.0, 0.0, 0.0, 1.0, 1.0, 1.0, $frontFaceTexture,
                            $blockX + 1.0, $blockY + 1.0, $blockZ + 1.0, 0.0, 0.0, 1.0, 1.0, 0.0, $frontFaceTexture,
                            $blockX + 0.0, $blockY + 0.0, $blockZ + 1.0, 0.0, 0.0, 1.0, 0.0, 1.0, $frontFaceTexture,
                            $blockX + 0.0, $blockY + 0.0, $blockZ + 1.0, 0.0, 0.0, 1.0, 0.0, 1.0, $frontFaceTexture,
                            $blockX + 1.0, $blockY + 1.0, $blockZ + 1.0, 0.0, 0.0, 1.0, 1.0, 0.0, $frontFaceTexture,
                            $blockX + 0.0, $blockY + 1.0, $blockZ + 1.0, 0.0, 0.0, 1.0, 0.0, 0.0, $frontFaceTexture,
                        ]);
                    } 

                    // back face (z-) 2 triangles
                    if ($blockVisibilityBack !== 1) {
                        $floatBuffer->pushArray([
                            $blockX + 0.0, $blockY + 0.0, $blockZ + 0.0, 0.0, 0.0, -1.0, 0.0, 1.0, $backFaceTexture,
                            $blockX + 0.0, $blockY + 1.0, $blockZ + 0.0, 0.0, 0.0, -1.0, 0.0, 0.0, $backFaceTexture,
                            $blockX + 1.0, $blockY + 0.0, $blockZ + 0.0, 0.0, 0.0, -1.0, 1.0, 1.0, $backFaceTexture,
                            $blockX + 1.0, $blockY + 0.0, $blockZ + 0.0, 0.0, 0.0, -1.0, 1.0, 1.0, $backFaceTexture,
                            $blockX + 0.0, $blockY + 1.0, $blockZ + 0.0, 0.0, 0.0, -1.0, 0.0, 0.0, $backFaceTexture,
                            $blockX + 1.0, $blockY + 1.0, $blockZ + 0.0, 0.0, 0.0, -1.0, 1.0, 0.0, $backFaceTexture,
                        ]);   
                    }

                    // left face (x-) 2 triangles
                    if ($blockVisibilityLeft !== 1) {
                        $floatBuffer->pushArray([
                            $blockX + 0.0, $blockY + 0.0, $blockZ + 0.0, -1.0, 0.0, 0.0, 0.0, 1.0, $leftFaceTexture,
                            $blockX + 0.0, $blockY + 0.0, $blockZ + 1.0, -1.0, 0.0, 0.0, 1.0, 1.0, $leftFaceTexture,
                            $blockX + 0.0, $blockY + 1.0, $blockZ + 1.0, -1.0, 0.0, 0.0, 1.0, 0.0, $leftFaceTexture,
                            $blockX + 0.0, $blockY + 0.0, $blockZ + 0.0, -1.0, 0.0, 0.0, 0.0, 1.0, $leftFaceTexture,
                            $blockX + 0.0, $blockY + 1.0, $blockZ + 1.0, -1.0, 0.0, 0.0, 1.0, 0.0, $leftFaceTexture,
                            $blockX + 0.0, $blockY + 1.0, $blockZ + 0.0, -1.0, 0.0, 0.0, 0.0, 0.0, $leftFaceTexture,
                        ]);
                    }

                    // right face (x+) 2 triangles
                    if ($blockVisibilityRight !== 1) {
                        $floatBuffer->pushArray([
                            $blockX + 1.0, $blockY + 0.0, $blockZ + 0.0, 1.0, 0.0, 0.0, 1.0, 1.0, $rightFaceTexture,
                            $blockX + 1.0, $blockY + 1.0, $blockZ + 0.0, 1.0, 0.0, 0.0, 1.0, 0.0, $rightFaceTexture,
                            $blockX + 1.0, $blockY + 0.0, $blockZ + 1.0, 1.0, 0.0, 0.0, 0.0, 1.0, $rightFaceTexture,
                            $blockX + 1.0, $blockY + 0.0, $blockZ + 1.0, 1.0, 0.0, 0.0, 0.0, 1.0, $rightFaceTexture,
                            $blockX + 1.0, $blockY + 1.0, $blockZ + 0.0, 1.0, 0.0, 0.0, 1.0, 0.0, $rightFaceTexture,
                            $blockX + 1.0, $blockY + 1.0, $blockZ + 1.0, 1.0, 0.0, 0.0, 0.0, 0.0, $rightFaceTexture,
                        ]);
                    }                    

                    // up face (y+) 2 triangles
                    if ($blockVisibilityUp !== 1) {
                        $floatBuffer->pushArray([
                            $blockX + 0.0, $blockY + 1.0, $blockZ + 0.0, 0.0, 1.0, 0.0, 0.0, 0.0, $upFaceTexture,
                            $blockX + 0.0, $blockY + 1.0, $blockZ + 1.0, 0.0, 1.0, 0.0, 0.0, 1.0, $upFaceTexture,
                            $blockX + 1.0, $blockY + 1.0, $blockZ + 0.0, 0.0, 1.0, 0.0, 1.0, 0.0, $upFaceTexture,
                            $blockX + 1.0, $blockY + 1.0, $blockZ + 0.0, 0.0, 1.0, 0.0, 1.0, 0.0, $upFaceTexture,
                            $blockX + 0.0, $blockY + 1.0, $blockZ + 1.0, 0.0, 1.0, 0.0, 0.0, 1.0, $upFaceTexture,
                            $blockX + 1.0, $blockY + 1.0, $blockZ + 1.0, 0.0, 1.0, 0.0, 1.0, 1.0, $upFaceTexture,
                        ]);
                    }

                    // down face (y-) 2 triangles
                    if ($blockVisibilityDown !== 1) {
                        $floatBuffer->pushArray([
                            $blockX + 0.0, $blockY + 0.0, $blockZ + 0.0, 0.0, -1.0, 0.0, 0.0, 0.0, $downFaceTexture,
                            $blockX + 1.0, $blockY + 0.0, $blockZ + 0.0, 0.0, -1.0, 0.0, 1.0, 0.0, $downFaceTexture,
                            $blockX + 0.0, $blockY + 0.0, $blockZ + 1.0, 0.0, -1.0, 0.0, 0.0, 1.0, $downFaceTexture,
                            $blockX + 0.0, $blockY + 0.0, $blockZ + 1.0, 0.0, -1.0, 0.0, 0.0, 1.0, $downFaceTexture,
                            $blockX + 1.0, $blockY + 0.0, $blockZ + 0.0, 0.0, -1.0, 0.0, 1.0, 0.0, $downFaceTexture,
                            $blockX + 1.0, $blockY + 0.0, $blockZ + 1.0, 0.0, -1.0, 0.0, 1.0, 1.0, $downFaceTexture,
                        ]);
                    }
                }
            }
        }

        $rd->vao->bind();
        $rd->vao->upload($floatBuffer);

        // if no geometry was uploaded we can skip rendering this chunk
        $rd->hasNoVisibleBlocks = $floatBuffer->size() === 0;
    }
}
