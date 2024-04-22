#version 330 core

layout(location = 0) in vec3 position;
layout(location = 1) in vec3 normal;
layout(location = 2) in vec2 uv;
layout(location = 3) in float blocktype;

uniform mat4 model;
uniform mat4 view;
uniform mat4 projection;

out vec3 v_position;
out vec3 v_normal;
out vec2 v_uv;
out float v_blocktype;

void main()
{
    v_position = (model * vec4(position, 1.0)).xyz;
    v_normal = normal;
    v_uv = uv;
    v_blocktype = blocktype;
    gl_Position = projection * view * model * vec4(position, 1.0);
}