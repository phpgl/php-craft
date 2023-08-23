<?php

namespace App\Voxel\Noise;

// MIT License

// Copyright (c) 2022 Vitalij Mik

// Permission is hereby granted, free of charge, to any person obtaining a copy
// of this software and associated documentation files (the "Software"), to deal
// in the Software without restriction, including without limitation the rights
// to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
// copies of the Software, and to permit persons to whom the Software is
// furnished to do so, subject to the following conditions:

// The above copyright notice and this permission notice shall be included in all
// copies or substantial portions of the Software.

// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
// FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
// AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
// LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
// OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
// SOFTWARE.

final class NoiseData
{
    public const GRADIENT_MAP = [
        [1.0, 1.0, 0.0],
        [-1.0, 1.0, 0.0],
        [1.0, -1.0, 0.0],
        [-1.0, -1.0, 0.0],
        [1.0, 0.0, 1.0],
        [-1.0, 0.0, 1.0],
        [1.0, 0.0, -1.0],
        [-1.0, 0.0, -1.0],
        [0.0, 1.0, 1.0],
        [0.0, -1.0, 1.0],
        [0.0, 1.0, -1.0],
        [0.0, -1.0, -1.0]
    ];

    public const PSEUDO_RANDOM_POINTS = [
        151,
        160,
        137,
        91,
        90,
        15,
        131,
        13,
        201,
        95,
        96,
        53,
        194,
        233,
        7,
        225,
        140,
        36,
        103,
        30,
        69,
        142,
        8,
        99,
        37,
        240,
        21,
        10,
        23,
        190,
        6,
        148,
        247,
        120,
        234,
        75,
        0,
        26,
        197,
        62,
        94,
        252,
        219,
        203,
        117,
        35,
        11,
        32,
        57,
        177,
        33,
        88,
        237,
        149,
        56,
        87,
        174,
        20,
        125,
        136,
        171,
        168,
        68,
        175,
        74,
        165,
        71,
        134,
        139,
        48,
        27,
        166,
        77,
        146,
        158,
        231,
        83,
        111,
        229,
        122,
        60,
        211,
        133,
        230,
        220,
        105,
        92,
        41,
        55,
        46,
        245,
        40,
        244,
        102,
        143,
        54,
        65,
        25,
        63,
        161,
        1,
        216,
        80,
        73,
        209,
        76,
        132,
        187,
        208,
        89,
        18,
        169,
        200,
        196,
        135,
        130,
        116,
        188,
        159,
        86,
        164,
        100,
        109,
        198,
        173,
        186,
        3,
        64,
        52,
        217,
        226,
        250,
        124,
        123,
        5,
        202,
        38,
        147,
        118,
        126,
        255,
        82,
        85,
        212,
        207,
        206,
        59,
        227,
        47,
        16,
        58,
        17,
        182,
        189,
        28,
        42,
        223,
        183,
        170,
        213,
        119,
        248,
        152,
        2,
        44,
        154,
        163,
        70,
        221,
        153,
        101,
        155,
        167,
        43,
        172,
        9,
        129,
        22,
        39,
        253,
        19,
        98,
        108,
        110,
        79,
        113,
        224,
        232,
        178,
        185,
        112,
        104,
        218,
        246,
        97,
        228,
        251,
        34,
        242,
        193,
        238,
        210,
        144,
        12,
        191,
        179,
        162,
        241,
        81,
        51,
        145,
        235,
        249,
        14,
        239,
        107,
        49,
        192,
        214,
        31,
        181,
        199,
        106,
        157,
        184,
        84,
        204,
        176,
        115,
        121,
        50,
        45,
        127,
        4,
        150,
        254,
        138,
        236,
        205,
        93,
        222,
        114,
        67,
        29,
        24,
        72,
        243,
        141,
        128,
        195,
        78,
        66,
        215,
        61,
        156,
        180
    ];
}

class Noise2D
{
    private array $pointsMod12 = [];
    private array $points = [];
    private float $F2;
    private float $G2;
    private float $persistence;
    private int $octaves;
    private float $zoom;
    private float $elevation;

    public function __construct(
        float $zoom = 0.0001,
        int $octaves = 4,
        float $persistence = 0.5,
        float $elevation = 0.0
    ) {
        $this->persistence = $persistence;
        $this->octaves = $octaves;
        $this->zoom = $zoom;
        $this->elevation = $elevation;
        for ($i = 0; $i < 512; $i++) {
            $value = NoiseData::PSEUDO_RANDOM_POINTS[$i & 255];
            $this->points[$i] = $value;
            $this->pointsMod12[$i] = $value % 12;
        }

        $this->F2 = 0.5 * (sqrt(3.0) - 1.0);
        $this->G2 = (3.0 - sqrt(3.0)) / 6.0;
    }

    private function fastFloor(float $value): int
    {
        $intValue = (int)$value;
        return $value < $intValue ? $intValue - 1 : $intValue;
    }

    public function getGreyValue(int $x, int $y): int
    {
        $x = $x * $this->zoom;
        $y = $y * $this->zoom;
        $noise = $this->fbm($x, $y);

        return $this->fastFloor($this->interpolate(0, 255, $noise));
    }

    public function interpolate(float $x, float $y, float $alpha)
    {
        if ($alpha < 0.0) {
            return $x;
        }
        if ($alpha > 1.0) {
            return $y;
        }
        return (1 - $alpha) * $x + $alpha * $y;
    }

    public function noise(float $x, float $y): float
    {
        $s = ($x + $y) * $this->F2;

        $i = $this->fastFloor($x + $s);
        $j = $this->fastFloor($y + $s);

        $t = ($i + $j) * $this->G2;

        $x00 = $i - $t;
        $y00 = $j - $t;

        $x0 = $x - $x00;
        $y0 = $y - $y00;

        $i1 = 0;
        $j1 = 1;
        if ($x0 > $y0) {
            $i1 = 1;
            $j1 = 0;
        }

        $x1 = $x0 - $i1 + $this->G2;
        $y1 = $y0 - $j1 + $this->G2;

        $x2 = $x0 - 1.0 + 2.0 * $this->G2;
        $y2 = $y0 - 1.0 + 2.0 * $this->G2;

        $ii = $i & 255;
        $jj = $j & 255;

        $index0 = $ii + $this->points[$jj];
        $index1 = $ii + $i1 + $this->points[$jj + $j1];
        $index2 = $ii + 1 + $this->points[$jj + 1];

        $gi0 = (int)$this->pointsMod12[$index0];
        $gi1 = (int)$this->pointsMod12[$index1];
        $gi2 = (int)$this->pointsMod12[$index2];

        $n0 = $this->getN($x0, $y0, NoiseData::GRADIENT_MAP[$gi0]);
        $n1 = $this->getN($x1, $y1, NoiseData::GRADIENT_MAP[$gi1]);
        $n2 = $this->getN($x2, $y2, NoiseData::GRADIENT_MAP[$gi2]);

        return 70.0 * ($n0 + $n1 + $n2);
    }

    public function fbm(float $x, float $y): float
    {
        $value = $this->elevation;

        $maxValue = $this->elevation;
        $frequency = 1.0;
        $amplitude = 1.0;
        $persistence = $this->persistence;

        for ($octave = 0; $octave < $this->octaves; $octave++) {
            $value += $this->noise($x * $frequency, $y * $frequency) * $amplitude;
            $maxValue += $amplitude;
            $amplitude *= $persistence;
            $frequency *= 2.0;
        }
        return $value / $maxValue;
    }


    private function dot(array $points, float $x, float $y): float
    {
        return $points[0] * $x + $points[1] * $y;
    }

    private function getN(float $x, float $y, array $gradient): float
    {
        $t = (0.5 - $x * $x - $y * $y);
        if ($t < 0) {
            return 0.0;
        }
        $t *= $t;
        return $t * $t * $this->dot($gradient, $x, $y);
    }

}