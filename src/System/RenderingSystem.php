<?php

namespace App\System;

use App\Component\SunComponent;
use App\Renderer\DistanceFogRenderer;
use App\Renderer\SkyboxRenderer;
use App\Voxel\ChunkRenderer;
use GL\Math\{GLM, Quat, Vec2, Vec3};
use VISU\ECS\EntitiesInterface;
use VISU\ECS\SystemInterface;
use VISU\Geo\Transform;
use VISU\Graphics\GLState;
use VISU\Graphics\Rendering\Pass\BackbufferData;
use VISU\Graphics\Rendering\Pass\CameraData;
use VISU\Graphics\Rendering\Pass\ClearPass;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\Rendering\Renderer\FullscreenTextureRenderer;
use VISU\Graphics\ShaderCollection;
use VISU\Graphics\TextureOptions;

class RenderingSystem implements SystemInterface
{
    /**
     * Fullscreen Texture Debug Renderer
     */
    private FullscreenTextureRenderer $fullscreenRenderer;

    /**
     * Voxel Rendere
     */
    private ChunkRenderer $voxelRenderer;

    /**
     * Skybox Renderer
     */
    private SkyboxRenderer $skyboxRenderer;

    /**
     * Fog Renderer
     */
    private DistanceFogRenderer $fogRenderer;

    /**
     * Constructor
     */
    public function __construct(
        private GLState $gl,
        private ShaderCollection $shaders
    )
    {
        $this->fullscreenRenderer = new FullscreenTextureRenderer($this->gl);
        $this->voxelRenderer = new ChunkRenderer($this->gl, $this->shaders);
        $this->skyboxRenderer = new SkyboxRenderer($this->gl, $this->shaders);
        $this->fogRenderer = new DistanceFogRenderer($this->gl, $this->shaders);
    }
    
    /**
     * Registers the system, this is where you should register all required components.
     * 
     * @return void 
     */
    public function register(EntitiesInterface $entities) : void
    {
        $entities->registerComponent(Transform::class);
        $entities->registerComponent(SunComponent::class);

        // create a global sun entity
        $entities->setSingleton(new SunComponent);
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
    }

    /**
     * Handles rendering of the scene, here you can attach additional render passes,
     * modify the render pipeline or customize rendering related data.
     * 
     * @param RenderContext $context
     */
    public function render(EntitiesInterface $entities, RenderContext $context) : void
    {
        // retrieve the backbuffer and clear it
        $backbuffer = $context->data->get(BackbufferData::class)->target;
        $context->pipeline->addPass(new ClearPass($backbuffer));

        // fetch the camera data
        $cameraData = $context->data->get(CameraData::class);

        // attach scene related data
        $context->data->set($entities->getSingleton(SunComponent::class));

        // create an intermediate 
        $sceneRenderTarget = $context->pipeline->createRenderTarget('scene', $cameraData->resolutionX, $cameraData->resolutionY);
        
        // depth
        $sceneDepth = $context->pipeline->createDepthAttachment($sceneRenderTarget);

        // scene color
        $sceneColorOptions = new TextureOptions;
        $sceneColorOptions->internalFormat = GL_RGB;
        $sceneColor = $context->pipeline->createColorAttachment($sceneRenderTarget, 'sceneColor', $sceneColorOptions);

        // scene position
        $spaceTextureOptions = new TextureOptions;
        $spaceTextureOptions->internalFormat = GL_RGB32F;
        $spaceTextureOptions->generateMipmaps = false;
        $scenePosition = $context->pipeline->createColorAttachment($sceneRenderTarget, 'scenePosition', $spaceTextureOptions);

        // clear the scene render target
        $context->pipeline->addPass(new ClearPass($sceneRenderTarget));

        // render the chunks
        $this->voxelRenderer->attachPass($context->pipeline, $sceneRenderTarget, $entities);

        // render the skybox
        $this->skyboxRenderer->attachPass($context->pipeline, $sceneRenderTarget);

        // create an intermediate for post processing
        $postProcessRenderTarget = $context->pipeline->createRenderTarget('postProcess', $cameraData->resolutionX, $cameraData->resolutionY);
        $postProcessColor = $context->pipeline->createColorAttachment($postProcessRenderTarget, 'postProcessColor', $sceneColorOptions);

        // render the fog
        $this->fogRenderer->attachPass($context->pipeline, $postProcessRenderTarget, $sceneColor, $scenePosition);

        // add a pass that renders the scene render target to the backbuffer
        $this->fullscreenRenderer->attachPass($context->pipeline, $backbuffer, $postProcessColor);
    }
}