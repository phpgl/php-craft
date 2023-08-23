<?php

namespace App\System;

use App\Component\ExampleImage;
use GL\Math\{GLM, Quat, Vec2, Vec3};
use VISU\ECS\EntitiesInterface;
use VISU\ECS\SystemInterface;
use VISU\Geo\Transform;
use VISU\Graphics\Rendering\RenderContext;

class VisuPhpantSystem implements SystemInterface
{   
    /**
     * Registers the system, this is where you should register all required components.
     * 
     * @return void 
     */
    public function register(EntitiesInterface $entities) : void
    {
        $entities->registerComponent(ExampleImage::class);

        // create a bunch of phpants
        for ($i = 0; $i < 50; $i++) {
            $entity = $entities->create();
            $spite = $entities->attach($entity, new ExampleImage);
            $spite->spriteFrame = (int) rand(0, 2);
            $spite->spriteFrameTick = (int) rand(0, 100);
            $spite->speed->x = (float) rand(2, 40) / 10;
            $spite->speed->y = (float) (rand(-20, 20)) / 10;
            $transform = $entities->attach($entity, new Transform);
            $transform->position = new Vec3(rand(0, 4096), rand(0, 2048), 0);
            $transform->scale = new Vec3(32, 32, 1);
        }
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
        // update the sprite frame
        foreach ($entities->view(ExampleImage::class) as $entity => $exampleImage) {
            $exampleImage->spriteFrameTick++;
            if ($exampleImage->spriteFrameTick >= $exampleImage->spriteFrameRate) {
                $exampleImage->spriteFrameTick = 0;
                $exampleImage->spriteFrame++;
                if ($exampleImage->spriteFrame >= 3) {
                    $exampleImage->spriteFrame = 0;
                }
            }
        }
        
        // update the transform
        foreach ($entities->view(ExampleImage::class) as $entity => $exampleImage) {
            $transform = $entities->get($entity, Transform::class);
            $transform->position->x = $transform->position->x - $exampleImage->speed->x;
            $transform->position->y = $transform->position->y + $exampleImage->speed->y;
            if ($transform->position->x < -32) {
                $transform->position->x = 4096;
            }

            if ($transform->position->y < -32) {
                $transform->position->y = 2048;
            }

            if ($transform->position->x > 4096) {
                $transform->position->x = -32;
            }

            if ($transform->position->y > 2048) {
                $transform->position->y = -32;
            }
        }
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