#version 330 core

layout(location = 0) out vec4 color;

in vec3 v_position;
in vec3 v_normal;
in vec2 v_uv;

uniform sampler2DArray textureArray;

void main()
{
    color = vec4(v_uv, 0.0, 1.0);
}