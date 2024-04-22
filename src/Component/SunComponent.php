<?php

namespace App\Component;

use GL\Math\Vec3;

class SunComponent
{
    /**
     * Direction of the sun
     */
    public Vec3 $direction;

    /**
     * Diffuse color of the sun
     */
    public Vec3 $diffuse;

    /**
     * Ambient color of the sun
     */
    public Vec3 $ambient;

    /**
     * The current hour of the game
     */
    public float $hour = 6.0;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->direction = new Vec3(0.0, 1.0, 1.0);
        $this->diffuse = new Vec3(1.0, 1.0, 1.0);
        $this->ambient = new Vec3(0.2, 0.2, 0.2);

        $this->direction->normalize();
    }
}
