<?php

namespace App\Voxel;

use App\Debug\DebugTextOverlay;
use GL\Math\Vec4;
use VISU\ECS\EntitiesInterface;
use VISU\Geo\Transform;
use VISU\Graphics\BasicVertexArray;
use VISU\Graphics\GLState;
use VISU\Graphics\Rendering\Pass\CallbackPass;
use VISU\Graphics\Rendering\Pass\CameraData;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\ShaderCollection;
use VISU\Graphics\Texture;
use VISU\Graphics\TextureOptions;

class ChunkRenderer
{
    private Texture $texture;

    public function __construct(
        private GLState $gl,
        private ShaderCollection $shaders,
    )
    {
        $this->texture = new Texture($this->gl, 'blocks');
        $textureOptions = new TextureOptions;
        $textureOptions->minFilter = GL_NEAREST;
        $textureOptions->magFilter = GL_NEAREST;
        $this->texture->loadFromFile(VISU_PATH_RESOURCES . '/sprites/textures.png', $textureOptions);
    }

    /**
     * Attaches a render pass to the pipeline
     * 
     * @param RenderPipeline $pipeline 
     * @param RenderTargetResource $renderTarget
     * @param array<SpriteComponent> $exampleImages
     */
    public function attachPass(
        RenderPipeline $pipeline, 
        RenderTargetResource $renderTarget,
        EntitiesInterface $entities,
    ) : void
    {
        // you do not always have to create a new class for a render pass
        // often its more convenient to just create a closure as showcased here
        // to render the background
        $pipeline->addPass(new CallbackPass(
            'VoxelPass',
            // setup (we need to declare who is reading and writing what)
            function(RenderPass $pass, RenderPipeline $pipeline, PipelineContainer $data) use($renderTarget) {
                $pipeline->writes($pass, $renderTarget);
            },
            // execute
            function(PipelineContainer $data, PipelineResources $resources) use($renderTarget, $entities)
            {
                $resources->activateRenderTarget($renderTarget);
                $rt = $resources->getRenderTarget($renderTarget);

                // simply sky blue
                $rt->framebuffer()->clearColor = new Vec4(0.5, 0.7, 1.0, 1.0);

                $cameraData = $data->get(CameraData::class);

                glEnable(GL_DEPTH_TEST);
                glEnable(GL_CULL_FACE);

                // activate the shader
                $shader = $this->shaders->get('voxelchunk');
                $shader->use();
                $shader->setUniformMat4('projection', false, $cameraData->projection);
                $shader->setUniformMat4('view', false, $cameraData->view);

                $this->texture->bind(GL_TEXTURE0);
                $shader->setUniform1i('u_texture', 0);

                $chunkAllocator = $entities->getSingleton(ChunkAllocator::class);
                
                $chunkVAOs = $chunkAllocator->getChunkVAOs();

                DebugTextOverlay::debugString('Chunks: ' . count($chunkVAOs));
                
                foreach ($chunkAllocator->getChunks() as $chunkKey => $chunk) 
                {
                    $transform = new Transform;
                    // $transform->position->x = $chunk->x * Chunk::CHUNK_SIZE * 1.1;
                    // $transform->position->y = $chunk->y * Chunk::CHUNK_SIZE * 1.1;
                    // $transform->position->z = $chunk->z * Chunk::CHUNK_SIZE * 1.1;
                    $transform->position->x = $chunk->x * Chunk::CHUNK_SIZE;
                    $transform->position->y = $chunk->y * Chunk::CHUNK_SIZE;
                    $transform->position->z = $chunk->z * Chunk::CHUNK_SIZE;

                    $shader->setUniformMat4('model', false, $transform->getLocalMatrix());
                    $chunkVAO = $chunkVAOs[$chunkKey];
                    $chunkVAO->bind();
                    $chunkVAO->drawAll();
                }
            }
        ));
    }
}
