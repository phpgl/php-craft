<?php

namespace App\Voxel;

use GL\Buffer\ByteBuffer;
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
     * @var array<BasicVertexArray>
     */
    private array $chunkVAOs = [];

    /**
     * Chunk render distance.
     */
    private int $renderDistance = 2;

    /**
     * Constructor
     */
    public function __construct(
        private GLState $gl,
    )
    {
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
        $this->chunkVAOs[$key] = new BasicVertexArray($this->gl, [3, 3, 2]);

        $this->chunks[$key]->fillVAOWithGeometry($this->chunkVAOs[$key]);

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

        $loaded = [];

        for ($x = $chunkX - $this->renderDistance; $x <= $chunkX + $this->renderDistance; $x++) {
            for ($y = $chunkY - $this->renderDistance; $y <= $chunkY + $this->renderDistance; $y++) {
                for ($z = $chunkZ - $this->renderDistance; $z <= $chunkZ + $this->renderDistance; $z++) {
                    $this->loadChunk("{$x}:{$y}:{$z}");
                    $loaded[] = "{$x}:{$y}:{$z}";
                }
            }
        }

        foreach ($this->chunks as $key => $chunk) {
            if (!in_array($key, $loaded)) {
                $this->unloadChunk($key);
            }
        }
    }
}
