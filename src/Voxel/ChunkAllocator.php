<?php

namespace App\Voxel;

use GL\Buffer\ByteBuffer;
use GL\Buffer\FloatBuffer;
use VISU\Graphics\BasicVertexArray;
use VISU\Graphics\GLState;

class ChunkAllocator
{
    /**
     * An array of chunks.
     * 
     * @var array<Chunk>
     */
    private array $chunks = [];

    /**
     * An array of vertex array for each chunk.
     * 
     * @var array<BasicVertexArray|null>
     */
    private array $chunkVAOs = [];

    /**
     * Chunk render distance.
     */
    private int $renderDistance = 4;

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
     * Constructor
     */
    public function __construct(
        private GLState $gl,
    )
    {
        // allocate empty chunk
        $this->emptyChunk = new Chunk(0, 0, 0);
        $this->emptyChunk->setVisibility(false);
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
     * Retuns all loaded chunk vertex arrays.
     * 
     * @return array<BasicVertexArray>
     */
    public function getChunkVAOs(): array
    {
        return $this->chunkVAOs;
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

        $this->chunks[$key] = new Chunk(...explode(':', $key));
        $this->chunkVAOs[$key] = null;

        return $this->chunks[$key];
    }

    /**
     * Unload the chunk with the given key
     */
    public function unloadChunk(string $key): void
    {
        unset($this->chunks[$key]);
        unset($this->chunkVAOs[$key]);
    }

    /**
     * Build chunk vertex array
     */
    public function buildChunkVAO(string $key) : void
    {
        if (!isset($this->chunks[$key])) {
            return;
        }
    }

    /**
     * Ensure chunks around the given position are loaded.
     */
    public function ensureChunksLoaded(float $x, float $y, float $z): void
    {
        $chunkX = (int) floor($x / Chunk::CHUNK_SIZE);
        $chunkY = (int) floor($y / Chunk::CHUNK_SIZE);
        $chunkZ = (int) floor($z / Chunk::CHUNK_SIZE);

        $shouldBeRendered = [];

        // we limit the number of chunks we can load per game tick 
        // to avoid ugly frame drops
        $chunkLoadingBudget = 2;

        for ($x = $chunkX - $this->renderDistance; $x <= $chunkX + $this->renderDistance; $x++) {
            for ($y = $chunkY - $this->renderDistance; $y <= $chunkY + $this->renderDistance; $y++) {
                for ($z = $chunkZ - $this->renderDistance; $z <= $chunkZ + $this->renderDistance; $z++) {
                    if ($y < $this->minHeight / Chunk::CHUNK_SIZE || $y > $this->maxHeight / Chunk::CHUNK_SIZE) {
                        continue;
                    }

                    $chunkKey = "{$x}:{$y}:{$z}";

                    $shouldBeRendered[$chunkKey] = true;

                    if (!isset($this->chunks[$chunkKey])) {
                        if ($chunkLoadingBudget-- <= 0) {
                            continue;
                        }
                    }

                    $this->loadChunk($chunkKey);
                }
            }
        }

        // unload all chunks outside of the render distance
        foreach ($this->chunks as $key => $chunk) {
            if (!isset($shouldBeRendered[$key])) {
                $this->unloadChunk($key);
            }
        }

        // for the optimizer to work properly 
        // we need to remove all neighboring chunks of chunks that are being 
        // loaded aka not having a vertex array yet
        foreach($this->chunks as $key => $chunk) {
            if ($this->chunkVAOs[$key] === null) {
                // deloc the neighboring chunks by setting them to null
                if (isset($this->chunkVAOs[($chunk->x - 1) . ':' . $chunk->y . ':' . $chunk->z])) {
                    $this->chunkVAOs[($chunk->x - 1) . ':' . $chunk->y . ':' . $chunk->z] = null;
                }
                if (isset($this->chunkVAOs[($chunk->x + 1) . ':' . $chunk->y . ':' . $chunk->z])) {
                    $this->chunkVAOs[($chunk->x + 1) . ':' . $chunk->y . ':' . $chunk->z] = null;
                }
                if (isset($this->chunkVAOs[$chunk->x . ':' . ($chunk->y + 1) . ':' . $chunk->z])) {
                    $this->chunkVAOs[$chunk->x . ':' . ($chunk->y + 1) . ':' . $chunk->z] = null;
                }
                if (isset($this->chunkVAOs[$chunk->x . ':' . ($chunk->y - 1) . ':' . $chunk->z])) {
                    $this->chunkVAOs[$chunk->x . ':' . ($chunk->y - 1) . ':' . $chunk->z] = null;
                }
                if (isset($this->chunkVAOs[$chunk->x . ':' . $chunk->y . ':' . ($chunk->z + 1)])) {
                    $this->chunkVAOs[$chunk->x . ':' . $chunk->y . ':' . ($chunk->z + 1)] = null;
                }
                if (isset($this->chunkVAOs[$chunk->x . ':' . $chunk->y . ':' . ($chunk->z - 1)])) {
                    $this->chunkVAOs[$chunk->x . ':' . $chunk->y . ':' . ($chunk->z - 1)] = null;
                }
            }
        }

        foreach($this->chunks as $key => $chunk) {
            if ($this->chunkVAOs[$key] === null) {
                $this->chunkVAOs[$key] = new BasicVertexArray($this->gl, [3, 3, 2, 1]);
                $this->fillVAOWithGeometry($chunk, $this->chunkVAOs[$key]);
            }
        }
    }


    public function fillVAOWithGeometry(Chunk $chunk, BasicVertexArray $vao): void
    {
        $floatBuffer = new FloatBuffer();

        // load the neighboring chunks 
        $leftChunk = $this->chunks[($chunk->x - 1) . ':' . $chunk->y . ':' . $chunk->z] ?? null;
        $rightChunk = $this->chunks[($chunk->x + 1) . ':' . $chunk->y . ':' . $chunk->z] ?? null;
        $upChunk = $this->chunks[$chunk->x . ':' . ($chunk->y + 1) . ':' . $chunk->z] ?? null;
        $downChunk = $this->chunks[$chunk->x . ':' . ($chunk->y - 1) . ':' . $chunk->z] ?? null;
        $frontChunk = $this->chunks[$chunk->x . ':' . $chunk->y . ':' . ($chunk->z + 1)] ?? null;
        $backChunk = $this->chunks[$chunk->x . ':' . $chunk->y . ':' . ($chunk->z - 1)] ?? null;

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

                    $frontFaceTexture = $chunk->blockTextures[$blockType][0];
                    $backFaceTexture = $chunk->blockTextures[$blockType][1];
                    $leftFaceTexture = $chunk->blockTextures[$blockType][2];
                    $rightFaceTexture = $chunk->blockTextures[$blockType][3];
                    $upFaceTexture = $chunk->blockTextures[$blockType][4];
                    $downFaceTexture = $chunk->blockTextures[$blockType][5];


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

        $vao->bind();
        $vao->upload($floatBuffer);
    }
}
