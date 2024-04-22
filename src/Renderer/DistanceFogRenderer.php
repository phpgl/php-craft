<?php

namespace App\Renderer;

use App\Component\SunComponent;
use VISU\Graphics\GLState;
use VISU\Graphics\QuadVertexArray;
use VISU\Graphics\Rendering\Pass\CallbackPass;
use VISU\Graphics\Rendering\Pass\CameraData;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\Rendering\Resource\TextureResource;
use VISU\Graphics\ShaderCollection;

class DistanceFogRenderer
{
    public function __construct(
        private GLState $gl,
        private ShaderCollection $shaders,
    )
    {
    }

    /**
     * Attaches a render pass to the pipeline
     * 
     * @param RenderPipeline $pipeline 
     * @param RenderTargetResource $renderTarget
     */
    public function attachPass(
        RenderPipeline $pipeline, 
        RenderTargetResource $renderTarget,
        TextureResource $colorTexture,
        TextureResource $positionTexture,
    ) : void
    {
        // you do not always have to create a new class for a render pass
        // often its more convenient to just create a closure as showcased here
        // to render the background
        $pipeline->addPass(new CallbackPass(
            'FogPass',
            // setup (we need to declare who is reading and writing what)
            function(RenderPass $pass, RenderPipeline $pipeline, PipelineContainer $data) use($renderTarget) {
                $pipeline->writes($pass, $renderTarget);
            },
            // execute
            function(PipelineContainer $data, PipelineResources $resources) use($renderTarget, $colorTexture, $positionTexture)
            {
                $resources->activateRenderTarget($renderTarget);
                $rt = $resources->getRenderTarget($renderTarget);

                glDisable(GL_CULL_FACE);
                glDisable(GL_DEPTH_TEST);

                // activate the shader
                $shader = $this->shaders->get('fog');
                $shader->use();
                $shader->setUniform1i('u_color_texture', 0);
                $shader->setUniform1i('u_position_texture', 1);
                $shader->setUniformVec3('u_camera_pos', $data->get(CameraData::class)->renderCamera->transform->position);

                $color = $resources->getTexture($colorTexture);
                $depth = $resources->getTexture($positionTexture);
                $color->bind(GL_TEXTURE0);
                $depth->bind(GL_TEXTURE1);

                $quadVA = $resources->cacheStaticResource('quadva', function(GLState $gl) {
                    return new QuadVertexArray($gl);
                });
        
                $quadVA->bind();
                $quadVA->draw();
            }
        ));
    }
}
