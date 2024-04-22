<?php

namespace App\Scene;

use App\Signals\SwitchToSceneSignal;
use GameContainer;
use App\System\CameraSystem;
use App\System\RenderingSystem;
use App\System\TimeOfDaySystem;
use App\System\VisuPhpantSystem;
use App\System\VoxelSystem;
use VISU\Graphics\Rendering\Pass\BackbufferData;
use VISU\Graphics\Rendering\Pass\ClearPass;
use VISU\Graphics\Rendering\RenderContext;
use VISU\OS\Input;
use VISU\OS\Key;
use VISU\OS\Logger;
use VISU\Runtime\DebugConsole;
use VISU\Signals\Input\KeySignal;
use VISU\Signals\Runtime\ConsoleCommandSignal;

class GameViewScene extends BaseScene
{
    /**
     * Returns the scenes name
     */
    public function getName() : string
    {
        return sprintf('Game Scene');
    }

    /**
     * Systems: Camera
     */
    private CameraSystem $cameraSystem;

    /**
     * system: Rendering
     */
    private RenderingSystem $renderingSystem;

    /**
     * system: Voxel
     */
    private VoxelSystem $voxelSystem;

    /**
     * system: Time of day
     */
    private TimeOfDaySystem $timeOfDaySystem;

    /**
     * Function ID for keyboard handler
     */
    private int $keyboardHandlerId = 0;

    /**
     * Function ID for console handler
     */
    private int $consoleHandlerId = 0;

    /**
     * Constructor
     *  
     * Dont load resources or bind event listeners here, use the `load` method instead.
     * We want to be able to load scenes without loading their resources. For example 
     * when preparing a scene to be switched or a loading screen.
     */
    public function __construct(
        protected GameContainer $container,
    )
    {
        parent::__construct($container);

        // voxel system
        $this->voxelSystem = new VoxelSystem(
            $this->container->resolveGL(),
        );

        // basic camera system
        $this->cameraSystem = new CameraSystem(
            $this->container->resolveInput(),
            $this->container->resolveVisuDispatcher(),
            $this->container->resolveInputContext(),
        );

        // basic rendering system
        $this->renderingSystem = new RenderingSystem(
            $this->container->resolveGL(),
            $this->container->resolveShaders()
        );

        $this->timeOfDaySystem = new TimeOfDaySystem();

        // bind all systems to the scene itself
        $this->bindSystems([
            $this->voxelSystem,
            $this->cameraSystem,
            $this->renderingSystem,
            $this->timeOfDaySystem,
        ]);
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->unload();
    }

    /**
     * Registers the console commmand handler, for level scene specific commands
     */
    private function registerConsoleCommands()
    {
        $this->consoleHandlerId = $this->container->resolveVisuDispatcher()->register(DebugConsole::EVENT_CONSOLE_COMMAND, function(ConsoleCommandSignal $signal) 
        {
            // do something with the console command (if you want to)
            var_dump($signal->commandParts);
        });
    }

    /**
     * Loads resources required for the scene, prepere base entiteis 
     * basically prepare anything that is required to be ready before the scene is rendered.
     * 
     * @return void 
     */
    public function load(): void
    {
        // register console command
        $this->registerConsoleCommands();

        // register key handler for debugging 
        // usally a system should handle this but this is temporary
        $this->keyboardHandlerId = $this->container->resolveVisuDispatcher()->register('input.key', function(KeySignal $signal) {
            $this->handleKeyboardEvent($signal);
        });
        
        // register the systems
        $this->registerSystems();
    }

    /**
     * Unloads resources required for the scene, cleanup base entities
     * 
     * @return void 
     */
    public function unload(): void
    {
        parent::unload();
        $this->container->resolveVisuDispatcher()->unregister('input.key', $this->keyboardHandlerId);
        $this->container->resolveVisuDispatcher()->unregister(DebugConsole::EVENT_CONSOLE_COMMAND, $this->consoleHandlerId);
    }

    /**
     * Updates the scene state
     * This is the place where you should update your game state.
     * 
     * @return void 
     */
    public function update(): void
    {
        $this->cameraSystem->update($this->entities);
        $this->timeOfDaySystem->update($this->entities);

        $this->voxelSystem->update($this->entities);
    }

    /**
     * Renders the scene
     * This is the place where you should render your game state.
     * 
     * @param RenderContext $context
     */
    public function render(RenderContext $context): void
    {
        // update the camera
        $this->cameraSystem->render($this->entities, $context);

        // let the rendering system render the scene
        $this->renderingSystem->render($this->entities, $context);
    }

    /**
     * Keyboard event handler
     */
    public function handleKeyboardEvent(KeySignal $signal): void
    {
        // reload the scene by switching to the same scene
        if ($signal->key === Key::F5 && $signal->action === Input::PRESS) {
            Logger::info('Reloading scene');
            $this->container->resolveVisuDispatcher()->dispatch('scene.switch', new SwitchToSceneSignal(new GameViewScene($this->container)));
        }
    }
}
