<?php

namespace App\Renderer;

use App\Component\SunComponent;
use GL\Math\Vec3;
use GL\Math\Vec4;
use VISU\Graphics\GLState;
use VISU\Graphics\QuadVertexArray;
use VISU\Graphics\Rendering\Pass\CallbackPass;
use VISU\Graphics\Rendering\Pass\CameraData;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\ShaderCollection;

class SkyboxRenderer
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
        RenderTargetResource $renderTarget
    ) : void
    {
        // you do not always have to create a new class for a render pass
        // often its more convenient to just create a closure as showcased here
        // to render the background
        $pipeline->addPass(new CallbackPass(
            'SkyBoxPass',
            // setup (we need to declare who is reading and writing what)
            function(RenderPass $pass, RenderPipeline $pipeline, PipelineContainer $data) use($renderTarget) {
                $pipeline->writes($pass, $renderTarget);
            },
            // execute
            function(PipelineContainer $data, PipelineResources $resources) use($renderTarget)
            {
                $resources->activateRenderTarget($renderTarget);
                $rt = $resources->getRenderTarget($renderTarget);

                $cameraData = $data->get(CameraData::class);
                $sun = $data->get(SunComponent::class);

                glDisable(GL_CULL_FACE);

                // depth settings for skybox
                glEnable(GL_DEPTH_TEST);
                glDepthFunc(GL_LEQUAL);
                glDepthMask(GL_FALSE);

                // activate the shader
                $shader = $this->shaders->get('skybox');
                $shader->use();
                $shader->setUniformMat4('projection', false, $cameraData->projection);
                $shader->setUniformMat4('view', false, $cameraData->view);
                $shader->setUniformVec3('u_sun.diffuse', $sun->diffuse);
                $shader->setUniformVec3('u_sun.direction', $sun->direction);

                $quadVA = $resources->cacheStaticResource('quadva', function(GLState $gl) {
                    return new QuadVertexArray($gl);
                });
        
                $quadVA->bind();
                $quadVA->draw();

                glDepthFunc(GL_LESS);
                glDepthMask(GL_TRUE);
            }
        ));
    }
}
