<?php

namespace App\Voxel;

use App\Voxel\Noise\Noise2D;
use App\Voxel\Noise\PerlinNoise;
use GL\Buffer\ByteBuffer;
use GL\Buffer\FloatBuffer;
use VISU\Graphics\BasicVertexArray;

class Chunk
{
    public const CHUNK_SIZE = 16;

    private ByteBuffer $blockTypes;
    private ByteBuffer $blockVisibility;

    const BLOCK_TYPE_AIR = 0;
    const BLOCK_TYPE_DIRT = 1;
    const BLOCK_TYPE_GRASS = 2;

    /**
     * Block textures foreach face of each block type
     * @var array
     */
    private array $blockTextures = [
        self::BLOCK_TYPE_AIR => [0, 0, 0, 0, 0, 0],
        self::BLOCK_TYPE_DIRT => [2, 2, 2, 2, 2, 2],
        self::BLOCK_TYPE_GRASS => [1, 1, 1, 1, 0, 2],
    ];

    public function __construct(
        public readonly int $x,
        public readonly int $y,
        public readonly int $z,
    )
    {
        $this->blockTypes = new ByteBuffer();
        $this->blockVisibility = new ByteBuffer();
        
        $this->blockTypes->fill(self::CHUNK_SIZE ** 3, 0);
        $this->blockVisibility->fill(self::CHUNK_SIZE ** 3, 0);

        // convert all the block textures to floats
        // because we will store them in a float buffer 
        // and we don't want to convert them every time we render
        foreach ($this->blockTextures as $blockType => $textures) {
            foreach ($textures as $face => $texture) {
                $this->blockTextures[$blockType][$face] = (float) $texture;
            }
        }

        // randomly enable/disable blocks
        for ($i = 0; $i < self::CHUNK_SIZE ** 3; $i++) {
            $this->blockVisibility[$i] = (int) rand(0, 1);
        }

        $noise = new Noise2D();

        // use a simple noise function to generate some terrain
        for ($x = 0; $x < self::CHUNK_SIZE; $x++) {
            for ($y = 0; $y < self::CHUNK_SIZE; $y++) {
                for ($z = 0; $z < self::CHUNK_SIZE; $z++) {

                    $this->blockTypes[$x + $y * self::CHUNK_SIZE + $z * self::CHUNK_SIZE ** 2] = (int) mt_rand(1, 2);

                    $absoluteX = $this->x * self::CHUNK_SIZE + $x;
                    $absoluteY = $this->z * self::CHUNK_SIZE + $z;
                    $absoluteZ = $this->y * self::CHUNK_SIZE + $y;

                    $height = $noise->fbm($absoluteX * 0.01, $absoluteY * 0.01);
                    $heightAbsolute = $height * 16 * 2;

                    $this->blockVisibility[$x + $y * self::CHUNK_SIZE + $z * self::CHUNK_SIZE ** 2] = $absoluteZ < $heightAbsolute ? 1 : 0;
                }
            }
        }
    }

    public function fillVAOWithGeometry(BasicVertexArray $vao): void
    {
        $floatBuffer = new FloatBuffer();

        // Unfolded cube
        //         *---*       F = Front
        //         | U |       B = Back
        // *---*---*---*---*   L = Left
        // | F | L | B | R |   R = Right
        // *---*---*---*---*   U = Up
        //         | D |       D = Down
        //         *---*        

        // our coordinate system is 
        // +x = right     -x = left
        // +y = up        -y = down
        // +z = front     -z = back

        // vertex layout 
        // ->  (x, y, z, nx, ny, nz, u, v)
        // aka (vec3<position>, vec3<normal>, vec2<uv>)
        // 
        // This could be optimized a lot, for once there is no real need to store the full float normals
        // we could just store some kind of index and then calculate the normals in the shader.
        // also we don't have to store the per chunk positions as floats as technically the values 
        // can never be larger than 16, so we could just store them in 3 bytes.
        // but for the sake of clarity we will just store the full normals here.

        $blockType = 0;
        $blockVisibility = 0;
        $blockVisibilityFront = 0;
        $blockVisibilityBack = 0;
        $blockVisibilityLeft = 0;
        $blockVisibilityRight = 0;
        $blockVisibilityUp = 0;
        $blockVisibilityDown = 0;
        $frontFaceTexture = 0.0;
        $backFaceTexture = 0.0;
        $leftFaceTexture = 0.0;
        $rightFaceTexture = 0.0;
        $upFaceTexture = 0.0;
        $downFaceTexture = 0.0;

        for ($x = 0; $x < self::CHUNK_SIZE; $x++) {
            for ($y = 0; $y < self::CHUNK_SIZE; $y++) {
                for ($z = 0; $z < self::CHUNK_SIZE; $z++) {
                    $blockType = (float) $this->blockTypes[$x + $y * self::CHUNK_SIZE + $z * self::CHUNK_SIZE ** 2];
                    $blockVisibility = $this->blockVisibility[$x + $y * self::CHUNK_SIZE + $z * self::CHUNK_SIZE ** 2];

                    $blockVisibilityFront = $this->blockVisibility[$x + $y * self::CHUNK_SIZE + ($z + 1) * self::CHUNK_SIZE ** 2];
                    $blockVisibilityBack = $this->blockVisibility[$x + $y * self::CHUNK_SIZE + ($z - 1) * self::CHUNK_SIZE ** 2];
                    $blockVisibilityLeft = $this->blockVisibility[($x - 1) + $y * self::CHUNK_SIZE + $z * self::CHUNK_SIZE ** 2];
                    $blockVisibilityRight = $this->blockVisibility[($x + 1) + $y * self::CHUNK_SIZE + $z * self::CHUNK_SIZE ** 2];
                    $blockVisibilityUp = $this->blockVisibility[$x + ($y + 1) * self::CHUNK_SIZE + $z * self::CHUNK_SIZE ** 2];
                    $blockVisibilityDown = $this->blockVisibility[$x + ($y - 1) * self::CHUNK_SIZE + $z * self::CHUNK_SIZE ** 2];

                    $frontFaceTexture = $this->blockTextures[$blockType][0];
                    $backFaceTexture = $this->blockTextures[$blockType][1];
                    $leftFaceTexture = $this->blockTextures[$blockType][2];
                    $rightFaceTexture = $this->blockTextures[$blockType][3];
                    $upFaceTexture = $this->blockTextures[$blockType][4];
                    $downFaceTexture = $this->blockTextures[$blockType][5];

                    // special cases for edges
                    if ($x === 0) {
                        $blockVisibilityLeft = 0;
                    } else if ($x === self::CHUNK_SIZE - 1) {
                        $blockVisibilityRight = 0;
                    }

                    if ($y === 0) {
                        $blockVisibilityDown = 0;
                    } else if ($y === self::CHUNK_SIZE - 1) {
                        $blockVisibilityUp = 0;
                    }

                    if ($z === 0) {
                        $blockVisibilityBack = 0;
                    } else if ($z === self::CHUNK_SIZE - 1) {
                        $blockVisibilityFront = 0;
                    }

                    if ($blockVisibility === 0) {
                        continue;
                    }

                    $blockX = $x;
                    $blockY = $y;
                    $blockZ = $z;

                    // front face (z+) 2 triangles
                    if ($blockVisibilityFront !== 1) {
                        $floatBuffer->pushArray([
                            $blockX + 1.0, $blockY + 0.0, $blockZ + 1.0, 0.0, 0.0, 1.0, 1.0, 1.0, $frontFaceTexture,
                            $blockX + 1.0, $blockY + 1.0, $blockZ + 1.0, 0.0, 0.0, 1.0, 1.0, 0.0, $frontFaceTexture,
                            $blockX + 0.0, $blockY + 0.0, $blockZ + 1.0, 0.0, 0.0, 1.0, 0.0, 1.0, $frontFaceTexture,
                            $blockX + 0.0, $blockY + 0.0, $blockZ + 1.0, 0.0, 0.0, 1.0, 0.0, 1.0, $frontFaceTexture,
                            $blockX + 1.0, $blockY + 1.0, $blockZ + 1.0, 0.0, 0.0, 1.0, 1.0, 0.0, $frontFaceTexture,
                            $blockX + 0.0, $blockY + 1.0, $blockZ + 1.0, 0.0, 0.0, 1.0, 0.0, 0.0, $frontFaceTexture,
                        ]);
                    } 

                    // back face (z-) 2 triangles
                    if ($blockVisibilityBack !== 1) {
                        $floatBuffer->pushArray([
                            $blockX + 0.0, $blockY + 0.0, $blockZ + 0.0, 0.0, 0.0, -1.0, 0.0, 1.0, $backFaceTexture,
                            $blockX + 0.0, $blockY + 1.0, $blockZ + 0.0, 0.0, 0.0, -1.0, 0.0, 0.0, $backFaceTexture,
                            $blockX + 1.0, $blockY + 0.0, $blockZ + 0.0, 0.0, 0.0, -1.0, 1.0, 1.0, $backFaceTexture,
                            $blockX + 1.0, $blockY + 0.0, $blockZ + 0.0, 0.0, 0.0, -1.0, 1.0, 1.0, $backFaceTexture,
                            $blockX + 0.0, $blockY + 1.0, $blockZ + 0.0, 0.0, 0.0, -1.0, 0.0, 0.0, $backFaceTexture,
                            $blockX + 1.0, $blockY + 1.0, $blockZ + 0.0, 0.0, 0.0, -1.0, 1.0, 0.0, $backFaceTexture,
                        ]);   
                    }

                    // left face (x-) 2 triangles
                    if ($blockVisibilityLeft !== 1) {
                        $floatBuffer->pushArray([
                            $blockX + 0.0, $blockY + 0.0, $blockZ + 0.0, -1.0, 0.0, 0.0, 0.0, 1.0, $leftFaceTexture,
                            $blockX + 0.0, $blockY + 0.0, $blockZ + 1.0, -1.0, 0.0, 0.0, 1.0, 1.0, $leftFaceTexture,
                            $blockX + 0.0, $blockY + 1.0, $blockZ + 1.0, -1.0, 0.0, 0.0, 1.0, 0.0, $leftFaceTexture,
                            $blockX + 0.0, $blockY + 0.0, $blockZ + 0.0, -1.0, 0.0, 0.0, 0.0, 1.0, $leftFaceTexture,
                            $blockX + 0.0, $blockY + 1.0, $blockZ + 1.0, -1.0, 0.0, 0.0, 1.0, 0.0, $leftFaceTexture,
                            $blockX + 0.0, $blockY + 1.0, $blockZ + 0.0, -1.0, 0.0, 0.0, 0.0, 0.0, $leftFaceTexture,
                        ]);
                    }

                    // right face (x+) 2 triangles
                    if ($blockVisibilityRight !== 1) {
                        $floatBuffer->pushArray([
                            $blockX + 1.0, $blockY + 0.0, $blockZ + 0.0, 1.0, 0.0, 0.0, 1.0, 1.0, $rightFaceTexture,
                            $blockX + 1.0, $blockY + 1.0, $blockZ + 0.0, 1.0, 0.0, 0.0, 1.0, 0.0, $rightFaceTexture,
                            $blockX + 1.0, $blockY + 0.0, $blockZ + 1.0, 1.0, 0.0, 0.0, 0.0, 1.0, $rightFaceTexture,
                            $blockX + 1.0, $blockY + 0.0, $blockZ + 1.0, 1.0, 0.0, 0.0, 0.0, 1.0, $rightFaceTexture,
                            $blockX + 1.0, $blockY + 1.0, $blockZ + 0.0, 1.0, 0.0, 0.0, 1.0, 0.0, $rightFaceTexture,
                            $blockX + 1.0, $blockY + 1.0, $blockZ + 1.0, 1.0, 0.0, 0.0, 0.0, 0.0, $rightFaceTexture,
                        ]);
                    }                    

                    // up face (y+) 2 triangles
                    if ($blockVisibilityUp !== 1) {
                        $floatBuffer->pushArray([
                            $blockX + 0.0, $blockY + 1.0, $blockZ + 0.0, 0.0, 1.0, 0.0, 0.0, 0.0, $upFaceTexture,
                            $blockX + 0.0, $blockY + 1.0, $blockZ + 1.0, 0.0, 1.0, 0.0, 0.0, 1.0, $upFaceTexture,
                            $blockX + 1.0, $blockY + 1.0, $blockZ + 0.0, 0.0, 1.0, 0.0, 1.0, 0.0, $upFaceTexture,
                            $blockX + 1.0, $blockY + 1.0, $blockZ + 0.0, 0.0, 1.0, 0.0, 1.0, 0.0, $upFaceTexture,
                            $blockX + 0.0, $blockY + 1.0, $blockZ + 1.0, 0.0, 1.0, 0.0, 0.0, 1.0, $upFaceTexture,
                            $blockX + 1.0, $blockY + 1.0, $blockZ + 1.0, 0.0, 1.0, 0.0, 1.0, 1.0, $upFaceTexture,
                        ]);
                    }

                    // down face (y-) 2 triangles
                    if ($blockVisibilityDown !== 1) {
                        $floatBuffer->pushArray([
                            $blockX + 0.0, $blockY + 0.0, $blockZ + 0.0, 0.0, -1.0, 0.0, 0.0, 0.0, $downFaceTexture,
                            $blockX + 1.0, $blockY + 0.0, $blockZ + 0.0, 0.0, -1.0, 0.0, 1.0, 0.0, $downFaceTexture,
                            $blockX + 0.0, $blockY + 0.0, $blockZ + 1.0, 0.0, -1.0, 0.0, 0.0, 1.0, $downFaceTexture,
                            $blockX + 0.0, $blockY + 0.0, $blockZ + 1.0, 0.0, -1.0, 0.0, 0.0, 1.0, $downFaceTexture,
                            $blockX + 1.0, $blockY + 0.0, $blockZ + 0.0, 0.0, -1.0, 0.0, 1.0, 0.0, $downFaceTexture,
                            $blockX + 1.0, $blockY + 0.0, $blockZ + 1.0, 0.0, -1.0, 0.0, 1.0, 1.0, $downFaceTexture,
                        ]);
                    }
                }
            }
        }

        $vao->bind();
        $vao->upload($floatBuffer);
    }
}
