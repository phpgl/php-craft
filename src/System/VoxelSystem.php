<?php

namespace App\System;

use App\Voxel\ChunkAllocator;
use GL\Math\{GLM, Quat, Vec2, Vec3};
use VISU\ECS\EntitiesInterface;
use VISU\ECS\SystemInterface;
use VISU\Graphics\Camera;
use VISU\Graphics\GLState;
use VISU\Graphics\Rendering\RenderContext;

class VoxelSystem implements SystemInterface
{   
    private ChunkAllocator $chunkAllocator;

    public function __construct(
        private GLState $gl,
    )
    {
        $this->chunkAllocator = new ChunkAllocator($gl);
    }

    /**
     * Registers the system, this is where you should register all required components.
     * 
     * @return void 
     */
    public function register(EntitiesInterface $entities) : void
    {
        $entities->setSingleton($this->chunkAllocator);
    }

    /**
     * Unregisters the system, this is where you can handle any cleanup.
     * 
     * @return void 
     */
    public function unregister(EntitiesInterface $entities) : void
    {
    }

    /**
     * Updates handler, this is where the game state should be updated.
     * 
     * @return void 
     */
    public function update(EntitiesInterface $entities) : void
    {
        $cameraEntity = $entities->first(Camera::class);

        $this->chunkAllocator->ensureChunksLoaded(
            $cameraEntity->transform->position->x, 
            $cameraEntity->transform->position->y, 
            $cameraEntity->transform->position->z
        );
    }

    /**
     * Handles rendering of the scene, here you can attach additional render passes,
     * modify the render pipeline or customize rendering related data.
     * 
     * @param RenderContext $context
     */
    public function render(EntitiesInterface $entities, RenderContext $context) : void
    {
    }
}