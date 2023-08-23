<?php

namespace App\System;

use App\Component\GameCamera2DComponent;
use App\Debug\DebugTextOverlay;
use GL\Math\Vec2;
use GL\Math\Vec3;
use VISU\ECS\EntitiesInterface;
use VISU\Graphics\Camera;
use VISU\Graphics\CameraProjectionMode;
use VISU\Graphics\Rendering\RenderContext;
use VISU\OS\Input;
use VISU\OS\InputContextMap;
use VISU\Signal\Dispatcher;
use VISU\Signals\Input\CursorPosSignal;
use VISU\Signals\Input\ScrollSignal;
use VISU\System\VISUCameraSystem;

class CameraSystem extends VISUCameraSystem
{
    /**
     * Default camera mode is game in the game... 
     */
    protected int $visuCameraMode = self::CAMERA_MODE_FLYING;

    /**
     * Constructor
     */
    public function __construct(
        Input $input,
        Dispatcher $dispatcher,
        protected InputContextMap $inputContext,
    )
    {
        parent::__construct($input, $dispatcher);
    }

    /**
     * Registers the system, this is where you should register all required components.
     * 
     * @return void 
     */
    public function register(EntitiesInterface $entities) : void
    {
        parent::register($entities);

        // create an inital camera entity
        $cameraEntity = $entities->create();
        $camera = $entities->attach($cameraEntity, new Camera(CameraProjectionMode::perspective));
        $camera->nearPlane = 0.1;
        $camera->farPlane = 16000;

        // make the camera the active camera
        $this->setActiveCameraEntity($cameraEntity);
    }

    /**
     * Unregisters the system, this is where you can handle any cleanup.
     * 
     * @return void 
     */
    public function unregister(EntitiesInterface $entities) : void
    {
        parent::unregister($entities);

        $entities->removeSingleton(GameCamera2DComponent::class);
    }

    /**
     * Override this method to handle the cursor position in game mode
     * 
     * @param CursorPosSignal $signal 
     * @return void 
     */
    protected function handleCursorPosVISUGame(EntitiesInterface $entities, CursorPosSignal $signal) : void
    {
        // handle mouse movement
    }

    /**
     * Override this method to handle the scroll wheel in game mode
     * 
     * @param ScrollSignal $signal
     * @return void 
     */
    protected function handleScrollVISUGame(EntitiesInterface $entities, ScrollSignal $signal) : void
    {
        // handle mouse scroll
    }

    /**
     * Override this method to update the camera in game mode
     * 
     * @param EntitiesInterface $entities
     */
    public function updateGameCamera(EntitiesInterface $entities, Camera $camera) : void
    {
    }

    /**
     * Handles rendering of the scene, here you can attach additional render passes,
     * modify the render pipeline or customize rendering related data.
     * 
     * @param RenderContext $context
     */
    public function render(EntitiesInterface $entities, RenderContext $context) : void
    {
        parent::render($entities, $context);
    }
}
