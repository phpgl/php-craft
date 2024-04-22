<?php

namespace App\System;

use App\Component\SunComponent;
use App\Debug\DebugTextOverlay;
use GL\Math\GLM;
use VISU\ECS\EntitiesInterface;
use VISU\ECS\SystemInterface;
use VISU\Graphics\Rendering\RenderContext;

class TimeOfDaySystem implements SystemInterface
{   
    /**
     * Registers the system, this is where you should register all required components.
     * 
     * @return void 
     */
    public function register(EntitiesInterface $entities) : void
    {
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
        // increase the hour of the day
        $sun = $entities->getSingleton(SunComponent::class);

        $sun->hour += 0.001;

        // set sun direction based on the hour
        // a day has 24 hours, the hour value is increased continously
        // and wont wrap around.
        $progress = $sun->hour / 24.0;
        $progress = fmod($progress, 1.0);

        DebugTextOverlay::debugString(sprintf("Sun Hour: %d Progress: %d", (int)$sun->hour, $progress * 100.0));

        // the sun should be at the horizon at 0.25 and 0.75
        // and at the top 0.5, meanig < 0.25 and > 0.75 the sun is below the horizon
        $angle = $progress * 360.0;
        $angle = fmod($angle, 360.0);
        $angle -= 90.0;

        $sun->direction->x = cos(GLM::radians($angle));
        $sun->direction->y = sin(GLM::radians($angle));
        $sun->direction->z = 0.0;
        
        // the sun doesnt shine at night, so no diffuse light below the horizon
        // but so that we can see something we add a little ambient light
        $skyRiseProgress = max(0.0, min(($progress - 0.20) / 0.05, 1.0));
        $skySetProgress = max(0.0, min(($progress - 0.70) / 0.05, 1.0));
        $skyDiffuse = $progress > 0.5 ? 1 - $skySetProgress : $skyRiseProgress;
        $sun->diffuse->x = $skyDiffuse;
        $sun->diffuse->y = $skyDiffuse;
        $sun->diffuse->z = $skyDiffuse;


        DebugTextOverlay::debugString(sprintf("Sky Diffuse: %d", $skyDiffuse * 100.0));
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